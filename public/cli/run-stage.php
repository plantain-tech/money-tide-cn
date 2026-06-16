<?php

declare(strict_types=1);

/**
 * Sprint 2 — Relay-style staged pipeline runner (Day 10 follow-up).
 *
 * Instead of one long cron doing the whole pipeline (which runs out of wall-clock
 * time on the slow clustering stage), this runs ONE stage per invocation. Set
 * three staggered crons that hand off through the database like a relay:
 *
 *   run-stage.php ingest       -> ingest news + cluster topics
 *   run-stage.php synthesize   -> write drafts from selected clusters
 *   run-stage.php publish       -> AI gate + publish + assemble 早报 + dispatch
 *
 * Each stage is short, idempotent, resumable, has its own overlap lock, and logs
 * its own run row (trigger "cron:<stage>"). Respects the autopilot kill-switch
 * and per-category pause. CLI-only.
 *
 * Example Hostinger crons (every 30 min, staggered by 10 min):
 *   0,30  * * *  /usr/bin/php /home/uXXXX/.../moneytidecn-app/public/cli/run-stage.php ingest
 *   10,40 * * *  /usr/bin/php /home/uXXXX/.../moneytidecn-app/public/cli/run-stage.php synthesize
 *   20,50 * * *  /usr/bin/php /home/uXXXX/.../moneytidecn-app/public/cli/run-stage.php publish
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

function stage_out(string $msg): void
{
    fwrite(STDOUT, '[' . gmdate('c') . '] ' . $msg . "\n");
    @flush();
}

$stage = isset($argv[1]) ? strtolower(trim((string) $argv[1])) : '';
$valid = ['ingest', 'synthesize', 'publish'];
if (!in_array($stage, $valid, true)) {
    fwrite(STDERR, "Usage: run-stage.php <ingest|synthesize|publish>\n");
    exit(2);
}

$startedAt = microtime(true);
stage_out('stage start: ' . $stage);

if (!autopilot_enabled()) {
    stage_out('autopilot is OFF — skipping. Enable it at /admin/autopilot.');
    exit(0);
}

// Per-stage overlap lock (so a slow run can't be doubled up by the next tick).
$lockKey = 'stage_lock_' . $stage;
$lockSince = pipeline_setting($lockKey, '');
if ($lockSince !== '' && (time() - strtotime($lockSince)) < 1200) {
    stage_out('stage "' . $stage . '" already running (since ' . $lockSince . ') — skipping.');
    exit(0);
}
set_pipeline_setting($lockKey, date('Y-m-d H:i:s'));

$budgetSec = 280;
$cfg = pipeline_config();
$elapsed = static fn (): float => microtime(true) - $startedAt;
$stages = [
    'ingest' => ['new_items' => 0],
    'cluster' => ['clusters' => 0],
    'synthesize' => ['drafts' => 0],
    'assess' => ['auto' => 0, 'review' => 0],
    'publish' => ['articles' => 0],
    'assemble' => ['issues' => 0],
];

try {
    if ($stage === 'ingest') {
        if (function_exists('ingest_all_news_sources')) {
            $r = ingest_all_news_sources();
            $stages['ingest']['new_items'] = (int) ($r['new_items'] ?? 0);
            stage_out('ingest: +' . $stages['ingest']['new_items'] . ' items (' . (int) ($r['sources'] ?? 0) . ' sources)');
        }
        foreach ((function_exists('get_categories') ? get_categories() : []) as $c) {
            if ($elapsed() > $budgetSec) {
                stage_out('budget reached — remaining categories resume next ingest tick.');
                break;
            }
            $slug = (string) $c['slug'];
            if (function_exists('category_paused') && category_paused($slug)) {
                stage_out('cluster ' . $slug . ': paused — skipped');
                continue;
            }
            $cr = cluster_news_for_category($slug);
            $stages['cluster']['clusters'] += (int) ($cr['clusters'] ?? 0);
            stage_out('cluster ' . $slug . ': +' . (int) ($cr['clusters'] ?? 0));
            sleep(2);
        }
    } elseif ($stage === 'synthesize') {
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
        stage_out('synthesize: ' . count($ids) . ' clusters queued');
        foreach ($ids as $cid) {
            if ($elapsed() > $budgetSec) {
                stage_out('budget reached during synthesis — remaining resume next tick.');
                break;
            }
            $sr = synthesize_cluster_to_draft($cid);
            if (!empty($sr['ok']) && ($sr['code'] ?? '') !== 'exists') {
                $stages['synthesize']['drafts']++;
            }
            stage_out('synthesize #' . $cid . ': ' . ($sr['message'] ?? ''));
            sleep(2);
        }
    } elseif ($stage === 'publish') {
        if (function_exists('assess_pending_drafts')) {
            $r = assess_pending_drafts((int) $cfg['assess_limit']);
            $stages['assess']['auto'] = (int) ($r['auto'] ?? 0);
            $stages['assess']['review'] = (int) ($r['review'] ?? 0);
            stage_out('assess: ' . $stages['assess']['auto'] . ' auto / ' . $stages['assess']['review'] . ' review');
        }
        if (function_exists('run_auto_publish_and_assemble')) {
            $r = run_auto_publish_and_assemble((int) $cfg['publish_limit']);
            $stages['publish']['articles'] = (int) ($r['publish']['ok'] ?? 0);
            $stages['assemble']['issues'] = (int) ($r['assemble']['issues'] ?? 0);
            stage_out('publish: ' . $stages['publish']['articles'] . ' articles / ' . $stages['assemble']['issues'] . ' issues (+dispatch)');
            // Surface why any approved draft failed to publish (was hidden before,
            // so "发布 0" gave no clue). Failed drafts are auto-routed to 人工审核.
            foreach ((array) ($r['publish']['details'] ?? []) as $d) {
                if (empty($d['ok'])) {
                    stage_out('  ⚠ draft #' . ($d['draft_id'] ?? '?') . ' 未发布 → 转人工：' . ($d['message'] ?? ''));
                }
            }
            // Freshness sweep runs inside run_auto_publish_and_assemble; just report it.
            $cl = $r['cleanup'] ?? ['news' => 0, 'clusters' => 0, 'drafts' => 0];
            stage_out('cleanup: 素材 ' . $cl['news'] . ' · 选题 ' . $cl['clusters'] . ' · 草稿 ' . $cl['drafts']);
        }
    }
} catch (Throwable $exception) {
    stage_out('ERROR: ' . $exception->getMessage());
}

// Logging tail (reconnect-safe) — always reached so a row is written.
$duration = (int) round($elapsed());
$summary = sprintf(
    '[%s] 抓取 %d · 聚类 %d · 草稿 %d · 自动通过 %d/转人工 %d · 发布 %d · 早报 %d',
    $stage,
    $stages['ingest']['new_items'],
    $stages['cluster']['clusters'],
    $stages['synthesize']['drafts'],
    $stages['assess']['auto'],
    $stages['assess']['review'],
    $stages['publish']['articles'],
    $stages['assemble']['issues']
);

db(true);
log_pipeline_run('cron:' . $stage, 'ok', $stages, $summary, $duration);
set_pipeline_setting('last_run_at', date('Y-m-d H:i:s'));
set_pipeline_setting('last_run_status', 'ok');

if ($stage === 'publish' && function_exists('evaluate_pipeline_health')) {
    try {
        evaluate_pipeline_health(['context' => ['trigger' => 'cron:publish']]);
        if (function_exists('dispatch_pipeline_alerts')) {
            dispatch_pipeline_alerts();
        }
    } catch (Throwable $exception) {
    }
}

set_pipeline_setting($lockKey, ''); // release

stage_out('status: ok');
stage_out('summary: ' . $summary);
stage_out(sprintf('done · %ss', $duration));
exit(0);
