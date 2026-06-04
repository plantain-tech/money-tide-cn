<?php

declare(strict_types=1);

/**
 * Live-ish market snapshot for the homepage strip.
 * Source: Yahoo Finance public chart endpoint (free, no key). Cached ~60s in
 * pipeline_settings so page loads never block on the API, with graceful
 * fallback (last-good cache → static) so the strip never breaks the page.
 *
 *   market_snapshot(false) → cache-only (used at page render; never fetches)
 *   market_snapshot(true)  → fetch if stale (used by the /api endpoint the JS polls)
 */

function market_symbols(): array
{
    return [
        ['key' => 'nasdaq', 'label' => 'NASDAQ',  'symbol' => '^IXIC',   'type' => 'pct'],
        ['key' => 'hsi',    'label' => 'HSI',     'symbol' => '^HSI',    'type' => 'pct'],
        ['key' => 'btc',    'label' => 'BTC',     'symbol' => 'BTC-USD', 'type' => 'pct'],
        ['key' => 'usdcnh', 'label' => 'USD/CNH', 'symbol' => 'CNH=X',   'type' => 'price'],
        ['key' => 'gold',   'label' => 'GOLD',    'symbol' => 'GC=F',    'type' => 'pct'],
    ];
}

/** Never-empty placeholder so the strip always renders. */
function market_snapshot_fallback(): array
{
    $out = [];
    foreach (market_symbols() as $s) {
        $out[$s['key']] = ['label' => $s['label'], 'display' => '—', 'dir' => 0, 'pct' => 0.0];
    }
    return $out;
}

function market_snapshot(bool $allowFetch = false): array
{
    $raw = function_exists('pipeline_setting') ? (string) pipeline_setting('market_snapshot', '') : '';
    $cache = $raw !== '' ? json_decode($raw, true) : null;
    $fresh = is_array($cache) && isset($cache['ts']) && (time() - (int) $cache['ts'] < 60);

    if (($fresh || !$allowFetch)) {
        if (is_array($cache) && !empty($cache['data'])) {
            return $cache['data'];
        }
        if (!$allowFetch) {
            return market_snapshot_fallback();
        }
    }

    $data = market_fetch_yahoo();
    if (!$data) {
        return (is_array($cache) && !empty($cache['data'])) ? $cache['data'] : market_snapshot_fallback();
    }
    // Keep any symbols that failed this round by merging over the last cache.
    if (is_array($cache) && !empty($cache['data'])) {
        $data = array_merge($cache['data'], $data);
    }
    if (function_exists('set_pipeline_setting')) {
        set_pipeline_setting('market_snapshot', json_encode(['ts' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE));
    }
    return $data;
}

function market_fetch_yahoo(): array
{
    if (!function_exists('curl_init')) {
        return [];
    }
    $out = [];
    foreach (market_symbols() as $s) {
        $q = market_fetch_one($s['symbol']);
        if ($q === null) {
            continue;
        }
        [$price, $pct] = $q;
        $dir = $pct > 0.01 ? 1 : ($pct < -0.01 ? -1 : 0);
        if ($s['type'] === 'price') {
            $display = number_format($price, $price >= 100 ? 2 : 4);
        } else {
            $display = ($pct >= 0 ? '+' : '') . number_format($pct, 2) . '%';
        }
        $out[$s['key']] = [
            'label' => $s['label'],
            'display' => $display,
            'dir' => $dir,
            'pct' => round($pct, 2),
            'price' => round($price, 4),
        ];
    }
    return $out;
}

function market_fetch_one(string $symbol): ?array
{
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?interval=1d&range=1d';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MoneyTideBot/1.0)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        return null;
    }
    $j = json_decode((string) $body, true);
    $meta = $j['chart']['result'][0]['meta'] ?? null;
    if (!is_array($meta)) {
        return null;
    }
    $price = (float) ($meta['regularMarketPrice'] ?? 0);
    $prev = (float) ($meta['chartPreviousClose'] ?? ($meta['previousClose'] ?? 0));
    if ($price <= 0) {
        return null;
    }
    $pct = $prev > 0 ? (($price - $prev) / $prev * 100) : 0.0;
    return [$price, $pct];
}
