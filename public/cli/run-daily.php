<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·6 — Daily pipeline CLI entrypoint (for cron).
 *
 * Runs the full autonomous chain end-to-end:
 *   ingest → cluster → synthesize → fact-check gate → publish + assemble
 *
 * Hostinger cron example (once per day, 07:00):
 *   0 7 * * *  /usr/bin/php /home/uXXXX/domains/.../moneytidecn/cli/run-daily.php
 *
 * Respects the autopilot kill-switch (does nothing while it's OFF).
 * Web-triggering is blocked; this only runs under the CLI SAPI.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: CLI only.\n";
    exit;
}

// Elevate to system context so headless publishing passes role checks.
// (current_user() returns a system admin only when this constant is set.)
define('PIPELINE_SYSTEM', true);

$base = dirname(__DIR__);
if (!is_dir($base . '/src')) {
    $base = dirname($base);
}
define('APP_BASE_PATH', rtrim($base, DIRECTORY_SEPARATOR));

require_once APP_BASE_PATH . '/src/bootstrap.php';

$started = microtime(true);
fwrite(STDOUT, "[" . gmdate('c') . "] daily pipeline start\n");

if (!autopilot_enabled()) {
    fwrite(STDOUT, "autopilot is OFF — skipping. Enable it at /admin/autopilot.\n");
    exit(0);
}

$result = run_daily_pipeline('cron');

fwrite(STDOUT, "status: " . ($result['status'] ?? '?') . "\n");
fwrite(STDOUT, "summary: " . ($result['message'] ?? '') . "\n");
fwrite(STDOUT, sprintf("[%s] done · %ss\n", gmdate('c'), round(microtime(true) - $started, 1)));

exit(($result['status'] ?? '') === 'ok' || ($result['status'] ?? '') === 'paused' ? 0 : 1);
