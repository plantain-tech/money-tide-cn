<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·1 — News ingestion CLI entrypoint (for cron).
 *
 * Hostinger cron example (runs every 2 hours):
 *   0 *\/2 * * *  /usr/bin/php /home/uXXXX/domains/.../moneytidecn/cli/fetch-news.php
 *
 * Web-triggering is blocked; this only runs under the CLI SAPI.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: CLI only.\n";
    exit;
}

// Resolve the app base (parent of this cli/ directory on the server).
$base = dirname(__DIR__);
if (!is_dir($base . '/src')) {
    $base = dirname($base);
}
define('APP_BASE_PATH', rtrim($base, DIRECTORY_SEPARATOR));

require_once APP_BASE_PATH . '/src/bootstrap.php';

$started = microtime(true);
fwrite(STDOUT, "[" . gmdate('c') . "] news ingest start\n");

$category = $argv[1] ?? null; // optional: limit to one category slug
$summary = ingest_all_news_sources($category);

foreach ($summary['details'] as $d) {
    fwrite(STDOUT, sprintf(
        "  %-7s %-28s %s\n",
        '[' . $d['category'] . ']',
        $d['name'],
        $d['message']
    ));
}

$elapsed = round(microtime(true) - $started, 1);
fwrite(STDOUT, sprintf(
    "[%s] done · %d sources · %d ok · %d failed · %d new items · %ss\n",
    gmdate('c'),
    $summary['sources'],
    $summary['ok'],
    $summary['failed'],
    $summary['new_items'],
    $elapsed
));

exit($summary['failed'] > 0 && $summary['ok'] === 0 ? 1 : 0);
