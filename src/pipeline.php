<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·6 — Orchestration + autopilot.
 *
 * run_daily_pipeline() chains every stage end-to-end:
 *   ingest → cluster → synthesize → assess (gate) → publish + assemble
 * Idempotent at every stage; gated by a master kill-switch (default OFF, so
 * nothing auto-runs until the owner explicitly enables it). Each run is logged.
 *
 * Drafts the AI flags for review do NOT auto-publish — they wait in the human
 * queue — so the 5% checkpoint holds even in full-auto mode.
 */

function ensure_pipeline_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pipeline_settings (
            setting_key VARCHAR(60) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pipeline_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_type VARCHAR(20) NOT NULL DEFAULT 'cron',
            status VARCHAR(20) NOT NULL DEFAULT 'ok',
            stages LONGTEXT NULL,
            summary VARCHAR(600) NULL,
            duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            finished_at TIMESTAMP NULL,
            INDEX idx_runs_time (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

function pipeline_setting(string $key, string $default = ''): string
{
    ensure_pipeline_schema();
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $pdo = db();
        if ($pdo instanceof PDO) {
            try {
                foreach ($pdo->query('SELECT setting_key, setting_value FROM pipeline_settings')->fetchAll() as $row) {
                    $cache[(string) $row['setting_key']] = (string) $row['setting_value'];
                }
            } catch (Throwable $exception) {
            }
        }
    }
    return $cache[$key] ?? $default;
}

function set_pipeline_setting(string $key, string $value): bool
{
    ensure_pipeline_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $pdo->prepare('INSERT INTO pipeline_settings (setting_key, setting_value) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
            ->execute(['k' => $key, 'v' => $value]);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function autopilot_enabled(): bool
{
    return pipeline_setting('autopilot_enabled', '0') === '1';
}

function pipeline_config(): array
{
    return [
        'enabled' => autopilot_enabled(),
        'synthesize_limit' => max(1, min(24, (int) pipeline_setting('synthesize_limit', '8'))),
        'assess_limit' => max(1, min(24, (int) pipeline_setting('assess_limit', '8'))),
        'publish_limit' => max(1, min(50, (int) pipeline_setting('publish_limit', '12'))),
        'stage_pause' => max(0, min(60, (int) pipeline_setting('stage_pause', '8'))),
        'last_run_at' => pipeline_setting('last_run_at', ''),
        'last_run_status' => pipeline_setting('last_run_status', ''),
    ];
}

/**
 * Run the full daily pipeline. $opts can override stage limits (web uses smaller
 * caps to fit the request window; cron uses the configured limits).
 */
function run_daily_pipeline(string $trigger = 'cron', array $opts = []): array
{
    ensure_pipeline_schema();
    $cfg = pipeline_config();
    $started = microtime(true);
    $stages = [];

    // Kill-switch (manual trigger can force a one-off run via opts['force']).
    if (!$cfg['enabled'] && empty($opts['force'])) {
        $result = ['status' => 'paused', 'message' => '自动驾驶已关闭，本次跳过。', 'stages' => []];
        log_pipeline_run($trigger, 'paused', [], '自动驾驶关闭', 0);
        return $result;
    }

    $synthLimit = (int) ($opts['synthesize_limit'] ?? $cfg['synthesize_limit']);
    $assessLimit = (int) ($opts['assess_limit'] ?? $cfg['assess_limit']);
    $publishLimit = (int) ($opts['publish_limit'] ?? $cfg['publish_limit']);
    // Pause between AI-heavy stages so a free-tier provider recovers from the
    // previous stage's burst (clustering ~8 calls) before the next begins.
    $stagePause = max(0, min(60, (int) ($opts['stage_pause'] ?? (int) pipeline_setting('stage_pause', '8'))));

    // 1) Ingest (no AI)
    if (function_exists('ingest_all_news_sources')) {
        $r = ingest_all_news_sources();
        $stages['ingest'] = ['new_items' => (int) ($r['new_items'] ?? 0), 'sources' => (int) ($r['sources'] ?? 0)];
    }
    // 2) Cluster (AI)
    if (function_exists('cluster_all_categories')) {
        $r = cluster_all_categories();
        $stages['cluster'] = ['clusters' => (int) ($r['clusters'] ?? 0), 'ok' => (int) ($r['ok'] ?? 0)];
        if ($stagePause > 0 && ($stages['cluster']['clusters'] ?? 0) > 0) {
            sleep($stagePause); // let the provider cool down before synthesis
        }
    }
    // 3) Synthesize selected clusters -> drafts (AI)
    if (function_exists('synthesize_selected_clusters')) {
        $r = synthesize_selected_clusters(null, $synthLimit);
        $stages['synthesize'] = ['drafts' => (int) ($r['ok'] ?? 0), 'failed' => (int) ($r['failed'] ?? 0)];
        if ($stagePause > 0 && (($stages['synthesize']['drafts'] ?? 0) > 0)) {
            sleep($stagePause); // cool down before the fact-check gate
        }
    }
    // 4) Fact-check gate (AI)
    if (function_exists('assess_pending_drafts')) {
        $r = assess_pending_drafts($assessLimit);
        $stages['assess'] = ['auto' => (int) ($r['auto'] ?? 0), 'review' => (int) ($r['review'] ?? 0)];
    }
    // 5) Publish approved + assemble newsletters (no AI)
    if (function_exists('run_auto_publish_and_assemble')) {
        $r = run_auto_publish_and_assemble($publishLimit);
        $stages['publish'] = ['articles' => (int) ($r['publish']['ok'] ?? 0)];
        $stages['assemble'] = ['issues' => (int) ($r['assemble']['issues'] ?? 0)];
    }

    $duration = (int) round(microtime(true) - $started);
    $summary = sprintf(
        '抓取 %d · 聚类 %d · 草稿 %d · 自动通过 %d/转人工 %d · 发布 %d · 早报 %d',
        $stages['ingest']['new_items'] ?? 0,
        $stages['cluster']['clusters'] ?? 0,
        $stages['synthesize']['drafts'] ?? 0,
        $stages['assess']['auto'] ?? 0,
        $stages['assess']['review'] ?? 0,
        $stages['publish']['articles'] ?? 0,
        $stages['assemble']['issues'] ?? 0
    );

    log_pipeline_run($trigger, 'ok', $stages, $summary, $duration);
    set_pipeline_setting('last_run_at', date('Y-m-d H:i:s'));
    set_pipeline_setting('last_run_status', 'ok');

    return ['status' => 'ok', 'message' => $summary, 'stages' => $stages, 'duration' => $duration];
}

function log_pipeline_run(string $trigger, string $status, array $stages, string $summary, int $duration): void
{
    ensure_pipeline_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->prepare('INSERT INTO pipeline_runs (trigger_type, status, stages, summary, duration_sec, finished_at)
            VALUES (:t, :s, :st, :sum, :d, NOW())')
            ->execute([
                't' => $trigger,
                's' => $status,
                'st' => json_encode($stages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sum' => mb_substr($summary, 0, 590, 'UTF-8'),
                'd' => $duration,
            ]);
    } catch (Throwable $exception) {
    }
    if (function_exists('record_event')) {
        record_event('pipeline_run', ['source' => $trigger . ':' . $status]);
    }
}

function pipeline_runs(int $limit = 20): array
{
    ensure_pipeline_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $rows = $pdo->query('SELECT * FROM pipeline_runs ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)))->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['stages'] = json_decode((string) ($row['stages'] ?? '{}'), true) ?: [];
    }
    return $rows;
}

/**
 * Live counts at each pipeline stage, for the autopilot flow visual.
 */
function pipeline_live_stats(): array
{
    $news = function_exists('news_ingest_summary') ? news_ingest_summary() : [];
    $clusters = function_exists('clustering_summary') ? clustering_summary() : [];
    $synth = function_exists('synthesis_summary') ? synthesis_summary() : [];
    $review = function_exists('auto_review_summary') ? auto_review_summary() : [];
    $publish = function_exists('auto_publish_summary') ? auto_publish_summary() : [];
    return [
        'ingest_new' => (int) ($news['items_new'] ?? 0),
        'clusters_selected' => (int) ($synth['pending_selected'] ?? 0),
        'review_pending' => (int) ($review['pending_review'] ?? 0),
        'publish_pending' => (int) ($publish['approved_pending'] ?? 0),
        'published_today' => (int) ($publish['published_today'] ?? 0),
        'issues_today' => (int) ($publish['issues_today'] ?? 0),
    ];
}
