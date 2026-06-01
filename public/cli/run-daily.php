<?php

declare(strict_types=1);

/**
 * Sprint 1 — Daily pipeline CLI entrypoint (for cron).
 *
 * Runs the full autonomous chain end-to-end in BOUNDED, OBSERVABLE steps:
 *   ingest → cluster (per category) → synthesize (per cluster) → gate → publish
 *
 * Why step-by-step instead of one run_daily_pipeline() call:
 *   - prints progress per stage so hPanel "View Output" shows where it is,
 *   - paces AI calls to dodge free-tier throttling (the "草稿 0" cause),
 *   - honours a wall-clock budget so it logs a record before any host kill,
 *   - an overlap lock stops a fast test cron (every-5-min) from piling up runs.
 *
 * Respects the autopilot kill-switch. CLI-only.
 *
 * Hostinger cron (once daily, 07:00 — recommend NOT more often than every 15m):
 *   0 7 * * *  /usr/bin/php /home/uXXXX/domains/.../moneytidecn/cli/run-daily.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: CLI only.\n";
    exit;
}

define('PIPELINE_SYSTEM', true);

$base = dirname(__DIR__);
if (!is_dir($base . '/src')) {
    $base = dirname($base);
}
define('APP_BASE_PATH', rtrim($base, DIRECTORY_SEPARATOR));

require_once APP_BASE_PATH . '/src/bootstrap.php';

@set_time_limit(0);
@ini_set('max_execution_time', '0');

function cli_out(string $msg): void
{
    fwrite(STDOUT, '[' . gmdate('c') . '] ' . $msg . "\n");
    @flush();
}

$startedAt = microtime(true);
cli_out('daily pipeline start');

if (!autopilot_enabled()) {
    cli_out('autopilot is OFF — skipping. Enable it at /admin/autopilot.');
    exit(0);
}

// ── Overlap lock: a run takes minutes; a frequent test cron must not pile up.
$lockSince = pipeline_setting('cron_lock_since', '');
if ($lockSince !== '' && (time() - strtotime($lockSince)) < 1200) {
    cli_out('another run is still in progress (since ' . $lockSince . ') — skipping to avoid overlap.');
    exit(0);
}
set_pipeline_setting('cron_lock_since', date('Y-m-d H:i:s'));

// Wall-clock budget: stop launching new AI work past this so we always reach
// the logging tail before a host-imposed kill. Remaining work resumes next run.
$budgetSec = 280;
$cfg = pipeline_config();
$stages = [
    'ingest' => ['new_items' => 0],
    'cluster' => ['clusters' => 0],
    'synthesize' => ['drafts' => 0],
    'assess' => ['auto' => 0, 'review' => 0],
    'publish' => ['articles' => 0],
    'assemble' => ['issues' => 0],
];
$overBudget = false;
$elapsed = static fn (): float => microtime(true) - $startedAt;

try {
    // 1) Ingest
    if (function_exists('ingest_all_news_sources')) {
        $r = ingest_all_news_sources();
        $stages['ingest']['new_items'] = (int) ($r['new_items'] ?? 0);
        cli_out('ingest: +' . $stages['ingest']['new_items'] . ' items (' . (int) ($r['sources'] ?? 0) . ' sources)');
    }

    // 2) Cluster — one category per step, paced.
    $cats = function_exists('get_categories') ? get_categories() : [];
    foreach ($cats as $c) {
        if ($elapsed() > $budgetSec) {
            $overBudget = true;
            cli_out('budget reached before clustering finished — will resume next run.');
            break;
        }
        if (function_exists('category_paused') && category_paused((string) $c['slug'])) {
            cli_out('cluster ' . (string) $c['slug'] . ': paused — skipped');
            continue;
        }
        $cr = cluster_news_for_category((string) $c['slug']);
        $stages['cluster']['clusters'] += (int) ($cr['clusters'] ?? 0);
        cli_out('cluster ' . $c['slug'] . ': +' . (int) ($cr['clusters'] ?? 0));
        sleep(2); // pace AI
    }

    // 3) Synthesize — one selected cluster per step, paced.
    if (!$overBudget && function_exists('synthesize_cluster_to_draft')) {
        $limit = max(1, (int) $cfg['synthesize_limit']);
        $ids = [];
        foreach (story_clusters(['status' => 'selected']) as $cl) {
            if (count($ids) >= $limit) {
                break;
            }
            if (empty($cl['draft_id'])) {
                $ids[] = (int) $cl['id'];
            }
        }
        cli_out('synthesize: ' . count($ids) . ' clusters queued');
        foreach ($ids as $cid) {
            if ($elapsed() > $budgetSec) {
                $overBudget = true;
                cli_out('budget reached during synthesis — will resume next run.');
                break;
            }
            $sr = synthesize_cluster_to_draft($cid);
            if (!empty($sr['ok']) && ($sr['code'] ?? '') !== 'exists') {
                $stages['synthesize']['drafts']++;
            }
            cli_out('synthesize #' . $cid . ': ' . ($sr['message'] ?? ''));
            sleep(2); // pace AI
        }
    }

    // 4) Fact-check gate
    if (function_exists('assess_pending_drafts')) {
        $r = assess_pending_drafts((int) $cfg['assess_limit']);
        $stages['assess']['auto'] = (int) ($r['auto'] ?? 0);
        $stages['assess']['review'] = (int) ($r['review'] ?? 0);
        cli_out('assess: ' . $stages['assess']['auto'] . ' auto / ' . $stages['assess']['review'] . ' review');
    }

    // 5) Publish + assemble newsletters
    if (function_exists('run_auto_publish_and_assemble')) {
        $r = run_auto_publish_and_assemble((int) $cfg['publish_limit']);
        $stages['publish']['articles'] = (int) ($r['publish']['ok'] ?? 0);
        $stages['assemble']['issues'] = (int) ($r['assemble']['issues'] ?? 0);
        cli_out('publish: ' . $stages['publish']['articles'] . ' articles / ' . $stages['assemble']['issues'] . ' issues');
    }
} catch (Throwable $exception) {
    cli_out('ERROR: ' . $exception->getMessage());
}

// ── Logging tail (reconnect-safe) — always reached, so a record is written.
$duration = (int) round($elapsed());
$summary = sprintf(
    '抓取 %d · 聚类 %d · 草稿 %d · 自动通过 %d/转人工 %d · 发布 %d · 早报 %d%s',
    $stages['ingest']['new_items'],
    $stages['cluster']['clusters'],
    $stages['synthesize']['drafts'],
    $stages['assess']['auto'],
    $stages['assess']['review'],
    $stages['publish']['articles'],
    $stages['assemble']['issues'],
    $overBudget ? ' · 预算内未跑完(下次续跑)' : ''
);

db(true); // AI work idled the connection; reconnect before writing the record
log_pipeline_run('cron', 'ok', $stages, $summary, $duration);
set_pipeline_setting('last_run_at', date('Y-m-d H:i:s'));
set_pipeline_setting('last_run_status', 'ok');

// Day 10·2: record health alerts + email on cron.
if (function_exists('evaluate_pipeline_health')) {
    try {
        evaluate_pipeline_health(['context' => ['trigger' => 'cron']]);
        if (function_exists('dispatch_pipeline_alerts')) {
            dispatch_pipeline_alerts();
        }
    } catch (Throwable $exception) {
    }
}

set_pipeline_setting('cron_lock_since', ''); // release lock

cli_out('status: ok');
cli_out('summary: ' . $summary);
cli_out(sprintf('done · %ss', $duration));
exit(0);
