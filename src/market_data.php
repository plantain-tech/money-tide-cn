<?php

declare(strict_types=1);

/**
 * Live market ticker for the homepage (Yahoo-Finance style): price + absolute
 * change + percent + intraday sparkline. Source: Yahoo Finance public chart
 * endpoint (free, no key). Cached ~60s in pipeline_settings so page loads never
 * block on the API, with graceful fallback so the strip never breaks the page.
 *
 *   market_snapshot(false) → cache-only (page render; never fetches)
 *   market_snapshot(true)  → fetch if stale (the /api endpoint the JS polls)
 */

function market_symbols(): array
{
    return [
        ['key' => 'sp500',  'label' => '标普500',    'symbol' => '^GSPC',   'dec' => 2],
        ['key' => 'nasdaq', 'label' => '纳斯达克',   'symbol' => '^IXIC',   'dec' => 2],
        ['key' => 'dow',    'label' => '道指',       'symbol' => '^DJI',    'dec' => 2],
        ['key' => 'hsi',    'label' => '恒生',       'symbol' => '^HSI',    'dec' => 2],
        ['key' => 'gold',   'label' => '黄金',       'symbol' => 'GC=F',    'dec' => 2],
        ['key' => 'btc',    'label' => '比特币',     'symbol' => 'BTC-USD', 'dec' => 2],
        ['key' => 'brent',  'label' => '布伦特原油', 'symbol' => 'BZ=F',    'dec' => 2],
        ['key' => 'vix',    'label' => 'VIX',        'symbol' => '^VIX',    'dec' => 2],
    ];
}

/** Never-empty placeholder so the ticker always renders. */
function market_snapshot_fallback(): array
{
    $out = [];
    foreach (market_symbols() as $s) {
        $out[$s['key']] = ['label' => $s['label'], 'price' => '—', 'change' => '', 'pct' => '', 'dir' => 0, 'spark' => []];
    }
    return $out;
}

function market_snapshot(bool $allowFetch = false): array
{
    $raw = function_exists('pipeline_setting') ? (string) pipeline_setting('market_snapshot', '') : '';
    $cache = $raw !== '' ? json_decode($raw, true) : null;
    $fresh = is_array($cache) && isset($cache['ts']) && (time() - (int) $cache['ts'] < 60);

    if ($fresh || !$allowFetch) {
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
    if (is_array($cache) && !empty($cache['data'])) {
        $data = array_merge($cache['data'], $data); // keep any symbols that failed this round
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
        [$price, $prev, $spark] = $q;
        $change = $price - $prev;
        $pct = $prev > 0 ? ($change / $prev * 100) : 0.0;
        $dir = $pct > 0.01 ? 1 : ($pct < -0.01 ? -1 : 0);
        $dec = (int) $s['dec'];
        $out[$s['key']] = [
            'label' => $s['label'],
            'price' => number_format($price, $dec),
            'change' => ($change >= 0 ? '+' : '') . number_format($change, $dec),
            'pct' => ($pct >= 0 ? '+' : '') . number_format($pct, 2) . '%',
            'dir' => $dir,
            'spark' => $spark,
        ];
    }
    return $out;
}

function market_fetch_one(string $symbol): ?array
{
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?interval=5m&range=1d';
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
    $res = $j['chart']['result'][0] ?? null;
    if (!is_array($res)) {
        return null;
    }
    $meta = $res['meta'] ?? [];
    $price = (float) ($meta['regularMarketPrice'] ?? 0);
    $prev = (float) ($meta['chartPreviousClose'] ?? ($meta['previousClose'] ?? 0));
    if ($price <= 0) {
        return null;
    }
    if ($prev <= 0) {
        $prev = $price;
    }
    $closes = $res['indicators']['quote'][0]['close'] ?? [];
    $pts = [];
    foreach ((array) $closes as $v) {
        if ($v !== null && is_numeric($v)) {
            $pts[] = (float) $v;
        }
    }
    $spark = market_downsample($pts, 32);
    return [$price, $prev, $spark];
}

/** Evenly sample an intraday series down to ~$n points for a compact sparkline. */
function market_downsample(array $pts, int $n): array
{
    $c = count($pts);
    if ($c === 0) {
        return [];
    }
    if ($c <= $n) {
        return array_map(static fn ($v) => round((float) $v, 4), $pts);
    }
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $idx = (int) floor($i * ($c - 1) / ($n - 1));
        $out[] = round((float) $pts[$idx], 4);
    }
    return $out;
}

/** Build the inner <polyline> for a sparkline SVG (viewBox 0 0 $w $h). */
function market_spark_svg(array $spark, int $dir): string
{
    if (count($spark) < 2) {
        return '';
    }
    $w = 64;
    $h = 22;
    $pad = 2;
    $min = min($spark);
    $max = max($spark);
    $range = ($max - $min) ?: 1;
    $n = count($spark);
    $pts = [];
    foreach ($spark as $i => $v) {
        $x = $pad + $i * ($w - 2 * $pad) / ($n - 1);
        $y = $h - $pad - (($v - $min) / $range) * ($h - 2 * $pad);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $color = $dir > 0 ? '#3fb950' : ($dir < 0 ? '#ff5c5c' : '#9aa0a6');
    return '<polyline fill="none" stroke="' . $color . '" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $pts) . '"/>';
}
