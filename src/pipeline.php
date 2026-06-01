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
    $ensured = true; // set early so the raw diag writer below cannot recurse
    // Each table gets its OWN try/catch — a failure creating one must not block
    // the other, and any DDL error is captured (not silently swallowed) so the
    // autopilot page can show why run logging might be failing.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pipeline_settings (
            setting_key VARCHAR(60) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
        pipeline_write_setting_raw($pdo, 'last_schema_error', 'settings: ' . $exception->getMessage());
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pipeline_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_type VARCHAR(20) NOT NULL DEFAULT 'cron',
            status VARCHAR(20) NOT NULL DEFAULT 'ok',
            stages LONGTEXT NULL,
            summary VARCHAR(600) NULL,
            duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            INDEX idx_runs_time (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
        pipeline_write_setting_raw($pdo, 'last_schema_error', 'runs: ' . $exception->getMessage());
    }
}

/**
 * Write a pipeline_settings row directly (no static-cache round-trip, no
 * recursion into ensure_pipeline_schema) — used by the diagnostics path.
 */
function pipeline_write_setting_raw(PDO $pdo, string $key, string $value): void
{
    try {
        $pdo->prepare('INSERT INTO pipeline_settings (setting_key, setting_value) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
            ->execute(['k' => $key, 'v' => mb_substr($value, 0, 250, 'UTF-8')]);
    } catch (Throwable $exception) {
    }
}

/**
 * Live, uncached health read for the run-logging path: does the runs table
 * exist, how many rows, and the last captured schema/insert error. Powers the
 * diagnostic banner on /admin/autopilot.
 */
function pipeline_logging_diag(): array
{
    $pdo = db_live();
    $out = ['runs_exists' => false, 'runs_count' => 0, 'schema_error' => '', 'log_error' => '', 'log_ok_at' => ''];
    if (!$pdo instanceof PDO) {
        $out['log_error'] = 'no database connection';
        return $out;
    }
    try {
        $out['runs_count'] = (int) $pdo->query('SELECT COUNT(*) FROM pipeline_runs')->fetchColumn();
        $out['runs_exists'] = true;
    } catch (Throwable $exception) {
        $out['log_error'] = 'read pipeline_runs failed: ' . $exception->getMessage();
    }
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM pipeline_settings
            WHERE setting_key IN ('last_schema_error','last_log_error','last_log_ok_at')")->fetchAll() ?: [];
        foreach ($rows as $r) {
            if ($r['setting_key'] === 'last_schema_error') {
                $out['schema_error'] = (string) $r['setting_value'];
            } elseif ($r['setting_key'] === 'last_log_error') {
                $out['log_error'] = $out['log_error'] ?: (string) $r['setting_value'];
            } elseif ($r['setting_key'] === 'last_log_ok_at') {
                $out['log_ok_at'] = (string) $r['setting_value'];
            }
        }
    } catch (Throwable $exception) {
    }
    return $out;
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

    // Day 10·2 resilience: each stage is wrapped so one failure can't abort the
    // whole run; failures are recorded and surfaced as alerts afterwards.
    $errors = [];
    $retry = null;

    // 1) Ingest (no AI)
    if (function_exists('ingest_all_news_sources')) {
        try {
            $r = ingest_all_news_sources();
            $stages['ingest'] = ['new_items' => (int) ($r['new_items'] ?? 0), 'sources' => (int) ($r['sources'] ?? 0)];
        } catch (Throwable $ex) {
            $stages['ingest'] = ['error' => $ex->getMessage()];
            $errors[] = 'ingest';
        }
    }
    // 2) Cluster (AI)
    if (function_exists('cluster_all_categories')) {
        try {
            $r = cluster_all_categories();
            $stages['cluster'] = ['clusters' => (int) ($r['clusters'] ?? 0), 'ok' => (int) ($r['ok'] ?? 0)];
            if ($stagePause > 0 && ($stages['cluster']['clusters'] ?? 0) > 0) {
                sleep($stagePause); // let the provider cool down before synthesis
            }
        } catch (Throwable $ex) {
            $stages['cluster'] = ['error' => $ex->getMessage()];
            $errors[] = 'cluster';
        }
    }
    // 3) Synthesize selected clusters -> drafts (AI), with ONE self-heal retry:
    //    if it produced 0 drafts but had failures (the throttle signature), wait
    //    a longer backoff and try once more before giving up.
    if (function_exists('synthesize_selected_clusters')) {
        try {
            $r = synthesize_selected_clusters(null, $synthLimit);
            $drafts = (int) ($r['ok'] ?? 0);
            $failed = (int) ($r['failed'] ?? 0);
            if ($drafts === 0 && $failed > 0) {
                $backoff = max(15, $stagePause * 2);
                sleep($backoff);
                $r2 = synthesize_selected_clusters(null, $synthLimit);
                $drafts = (int) ($r2['ok'] ?? 0);
                $failed = (int) ($r2['failed'] ?? 0);
                $retry = ['stage' => 'synthesize', 'backoff' => $backoff, 'recovered' => $drafts > 0];
            }
            $stages['synthesize'] = ['drafts' => $drafts, 'failed' => $failed];
            if ($retry !== null) {
                $stages['synthesize']['retried'] = true;
                $stages['synthesize']['recovered'] = $retry['recovered'];
            }
            if ($stagePause > 0 && $drafts > 0) {
                sleep($stagePause); // cool down before the fact-check gate
            }
        } catch (Throwable $ex) {
            $stages['synthesize'] = ['error' => $ex->getMessage()];
            $errors[] = 'synthesize';
        }
    }
    // 4) Fact-check gate (AI)
    if (function_exists('assess_pending_drafts')) {
        try {
            $r = assess_pending_drafts($assessLimit);
            $stages['assess'] = ['auto' => (int) ($r['auto'] ?? 0), 'review' => (int) ($r['review'] ?? 0)];
        } catch (Throwable $ex) {
            $stages['assess'] = ['error' => $ex->getMessage()];
            $errors[] = 'assess';
        }
    }
    // 5) Publish approved + assemble newsletters (no AI)
    if (function_exists('run_auto_publish_and_assemble')) {
        try {
            $r = run_auto_publish_and_assemble($publishLimit);
            $stages['publish'] = ['articles' => (int) ($r['publish']['ok'] ?? 0)];
            $stages['assemble'] = ['issues' => (int) ($r['assemble']['issues'] ?? 0)];
        } catch (Throwable $ex) {
            $stages['publish'] = ['error' => $ex->getMessage()];
            $errors[] = 'publish';
        }
    }

    $status = empty($errors) ? 'ok' : 'error';
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
    if ($retry !== null) {
        $summary .= ' · 写稿重试' . ($retry['recovered'] ? '成功' : '未恢复');
    }
    if (!empty($errors)) {
        $summary .= ' · 失败阶段：' . implode('/', $errors);
    }

    // The AI stages above can idle the DB connection past MySQL's wait_timeout,
    // so the connection may be dead by now. Force a fresh one before the tail
    // writes so the run record + settings actually persist.
    db(true);
    log_pipeline_run($trigger, $status, $stages, $summary, $duration);
    set_pipeline_setting('last_run_at', date('Y-m-d H:i:s'));
    set_pipeline_setting('last_run_status', $status);

    // Day 10·2: self-monitor — record health alerts; email only on cron so
    // manual/dry runs don't spam the inbox.
    if (function_exists('evaluate_pipeline_health')) {
        try {
            evaluate_pipeline_health(['context' => ['trigger' => $trigger, 'errors' => $errors]]);
            if ($trigger === 'cron' && function_exists('dispatch_pipeline_alerts')) {
                dispatch_pipeline_alerts();
            }
        } catch (Throwable $ex) {
        }
    }

    return ['status' => $status, 'message' => $summary, 'stages' => $stages, 'duration' => $duration];
}

function log_pipeline_run(string $trigger, string $status, array $stages, string $summary, int $duration): void
{
    ensure_pipeline_schema();
    // JSON_PARTIAL_OUTPUT_ON_ERROR: a stray bad byte in a stage error/title must
    // never make the encode return false and blank the column.
    $stagesJson = json_encode($stages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($stagesJson)) {
        $stagesJson = '{}';
    }
    $params = [
        't' => $trigger,
        's' => $status,
        'st' => $stagesJson,
        'sum' => mb_substr($summary, 0, 590, 'UTF-8'),
        'd' => $duration,
    ];
    $sql = 'INSERT INTO pipeline_runs (trigger_type, status, stages, summary, duration_sec, finished_at)
        VALUES (:t, :s, :st, :sum, :d, NOW())';

    // Try, and on a dropped connection ("server has gone away") reconnect once
    // and retry — this is the exact failure that left 运行记录 empty.
    $lastError = '';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $pdo = $attempt === 1 ? db_live() : db(true);
        if (!$pdo instanceof PDO) {
            $lastError = 'no database connection';
            continue;
        }
        try {
            $pdo->prepare($sql)->execute($params);
            pipeline_write_setting_raw($pdo, 'last_log_error', '');
            pipeline_write_setting_raw($pdo, 'last_log_ok_at', date('Y-m-d H:i:s'));
            $lastError = '';
            break;
        } catch (Throwable $exception) {
            $lastError = $exception->getMessage();
            // Retry only if it looks like a dropped connection.
            if ($attempt === 1 && stripos($lastError, 'gone away') === false && stripos($lastError, 'Lost connection') === false) {
                break; // a real error (schema/data) — don't bother retrying
            }
        }
    }
    if ($lastError !== '') {
        $pdoErr = db();
        if ($pdoErr instanceof PDO) {
            pipeline_write_setting_raw($pdoErr, 'last_log_error', 'insert failed: ' . $lastError);
        }
    }
    if (function_exists('record_event')) {
        try {
            record_event('pipeline_run', ['source' => $trigger . ':' . $status]);
        } catch (Throwable $exception) {
        }
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
