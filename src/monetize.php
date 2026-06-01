<?php

declare(strict_types=1);

/**
 * Day 10·5 — Monetization rails: disclosed affiliate-link injection + native
 * sponsor slots. Config lives in the existing monetization_settings key/value
 * table (so no new schema), managed at /admin/monetization.
 *
 * Compliance first: affiliate links are auto-disclosed (a notice + a visible ↗
 * mark + rel="sponsored nofollow"), and sponsor slots carry a clear「赞助 /
 * Sponsored」label. Nothing is hidden — this is the compliant version of the
 * owner's "passive income" goal.
 */

function monetize_config(): array
{
    $s = function_exists('monetization_settings') ? monetization_settings() : [];
    $rules = [];
    $raw = (string) ($s['affiliate_rules'] ?? '');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $r) {
                $kw = trim((string) ($r['keyword'] ?? ''));
                $url = trim((string) ($r['url'] ?? ''));
                if ($kw !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                    $rules[] = ['keyword' => $kw, 'url' => $url, 'label' => trim((string) ($r['label'] ?? ''))];
                }
            }
        }
    }
    return [
        'affiliate_rules' => $rules,
        'affiliate_enabled' => ($s['affiliate_enabled'] ?? '1') === '1',
        'affiliate_max' => max(1, min(10, (int) ($s['affiliate_max'] ?? 3))),
        'affiliate_disclosure' => trim((string) ($s['affiliate_disclosure'] ?? '本文含合作推广链接，通过链接购买我们可能获得佣金，不影响内容独立性。')),
        'sponsor_enabled' => ($s['sponsor_enabled'] ?? '0') === '1',
        'sponsor_label' => trim((string) ($s['sponsor_label'] ?? '赞助 · Sponsored')),
        'sponsor_name' => trim((string) ($s['sponsor_name'] ?? '')),
        'sponsor_blurb' => trim((string) ($s['sponsor_blurb'] ?? '')),
        'sponsor_url' => trim((string) ($s['sponsor_url'] ?? '')),
        'sponsor_cta' => trim((string) ($s['sponsor_cta'] ?? '了解更多')),
        // Day 10·6 — compliant AdSense + RPM target for revenue estimates.
        'adsense_enabled' => ($s['adsense_enabled'] ?? '0') === '1',
        'adsense_client' => trim((string) ($s['adsense_client'] ?? '')),
        'adsense_slot_article' => trim((string) ($s['adsense_slot_article'] ?? '')),
        'target_rpm' => max(0.0, (float) ($s['target_rpm'] ?? 0)),
    ];
}

/** AdSense is "on" only when explicitly enabled AND a real publisher id is set. */
function adsense_enabled(): bool
{
    $c = monetize_config();
    return $c['adsense_enabled'] && strncmp($c['adsense_client'], 'ca-pub-', 7) === 0;
}

/** The loader script — injected in <head> on PUBLIC pages only (see layout). */
function adsense_head_script(): string
{
    if (!adsense_enabled()) {
        return '';
    }
    $client = monetize_config()['adsense_client'];
    return '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='
        . e($client) . '" crossorigin="anonymous"></script>';
}

/**
 * Render an AdSense unit — ONLY from approved in-content slots (article body).
 * Always wrapped + labeled "广告 / Advertisement" for policy compliance. Returns
 * '' unless enabled and a slot id is configured, so ads never appear elsewhere.
 */
function adsense_slot_html(string $variant = 'article'): string
{
    if (!adsense_enabled()) {
        return '';
    }
    $c = monetize_config();
    $slot = $c['adsense_slot_article'];
    if ($slot === '') {
        return '';
    }
    return '<div class="ad-zone" data-ad-zone="' . e($variant) . '">'
        . '<span class="ad-zone-label">广告 · Advertisement</span>'
        . '<ins class="adsbygoogle" style="display:block" data-ad-client="' . e($c['adsense_client']) . '"'
        . ' data-ad-slot="' . e($slot) . '" data-ad-format="auto" data-full-width-responsive="true"></ins>'
        . '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>'
        . '</div>';
}

function monetize_affiliate_rules(): array
{
    static $cache = null;
    if ($cache === null) {
        $cfg = monetize_config();
        $cache = $cfg['affiliate_enabled'] ? $cfg['affiliate_rules'] : [];
    }
    return $cache;
}

function monetize_affiliate_max(): int
{
    static $max = null;
    if ($max === null) {
        $max = monetize_config()['affiliate_max'];
    }
    return $max;
}

/** Fresh state for one article render. */
function monetize_new_state(): array
{
    return ['used' => [], 'count' => 0];
}

/** True if any affiliate keyword appears in the body (to show top disclosure). */
function monetize_body_has_affiliate(array $paragraphs): bool
{
    $rules = monetize_affiliate_rules();
    if (!$rules) {
        return false;
    }
    $joined = implode("\n", array_map('strval', $paragraphs));
    foreach ($rules as $r) {
        if ($r['keyword'] !== '' && mb_strpos($joined, $r['keyword']) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Escape a paragraph and inject affiliate anchors on the FIRST occurrence of
 * each keyword (each keyword once per article, capped by affiliate_max).
 * $state is passed by reference across the body loop.
 */
function monetize_render_paragraph(string $paragraph, array &$state): string
{
    $html = e($paragraph);
    $rules = monetize_affiliate_rules();
    if (!$rules) {
        return $html;
    }
    $max = monetize_affiliate_max();
    foreach ($rules as $r) {
        if ($state['count'] >= $max) {
            break;
        }
        $kw = $r['keyword'];
        if ($kw === '' || isset($state['used'][$kw])) {
            continue;
        }
        $needle = e($kw);
        $pos = mb_strpos($html, $needle);
        if ($pos === false) {
            continue;
        }
        $anchor = '<a href="' . e($r['url']) . '" rel="sponsored nofollow noopener" target="_blank" class="affiliate-link"'
            . ' title="' . e($r['label'] !== '' ? $r['label'] : '合作推广链接') . '">'
            . $needle . '<span class="affiliate-mark" aria-hidden="true">↗</span></a>';
        $html = mb_substr($html, 0, $pos) . $anchor . mb_substr($html, $pos + mb_strlen($needle));
        $state['used'][$kw] = true;
        $state['count']++;
    }
    return $html;
}

function monetize_affiliate_disclosure(): string
{
    return monetize_config()['affiliate_disclosure'];
}

function monetize_sponsor_enabled(): bool
{
    $c = monetize_config();
    return $c['sponsor_enabled'] && $c['sponsor_name'] !== '' && $c['sponsor_blurb'] !== '';
}

function monetize_sponsor(): array
{
    return monetize_config();
}

/** Sponsor slot HTML (used by article view + newsletter). */
function monetize_sponsor_html(string $variant = 'article'): string
{
    if (!monetize_sponsor_enabled()) {
        return '';
    }
    $c = monetize_config();
    $hasUrl = filter_var($c['sponsor_url'], FILTER_VALIDATE_URL) !== false;
    if ($variant === 'email') {
        $cta = $hasUrl
            ? '<a href="' . e($c['sponsor_url']) . '" style="color:#2554ff;font-weight:700;text-decoration:none;">' . e($c['sponsor_cta']) . ' →</a>'
            : '';
        return '<table width="100%" style="margin:18px 0;border:1px solid #e2dccd;background:#faf7ef;border-radius:8px;"><tr><td style="padding:14px 16px;">'
            . '<div style="font-size:11px;letter-spacing:.05em;color:#a06a00;font-weight:700;text-transform:uppercase;">' . e($c['sponsor_label']) . '</div>'
            . '<div style="font-weight:700;margin:4px 0;">' . e($c['sponsor_name']) . '</div>'
            . '<div style="color:#555;font-size:14px;line-height:1.6;">' . e($c['sponsor_blurb']) . '</div>'
            . ($cta !== '' ? '<div style="margin-top:8px;">' . $cta . '</div>' : '')
            . '</td></tr></table>';
    }
    $cta = $hasUrl
        ? '<a class="sponsor-cta" href="' . e($c['sponsor_url']) . '" rel="sponsored nofollow noopener" target="_blank">' . e($c['sponsor_cta']) . ' <span aria-hidden="true">→</span></a>'
        : '';
    return '<aside class="sponsor-slot" aria-label="赞助内容">'
        . '<span class="sponsor-tag">' . e($c['sponsor_label']) . '</span>'
        . '<div class="sponsor-body"><strong>' . e($c['sponsor_name']) . '</strong><p>' . e($c['sponsor_blurb']) . '</p></div>'
        . $cta
        . '</aside>';
}

/**
 * Save affiliate + sponsor config into monetization_settings (separate from the
 * premium settings saver). Affiliate rules come in as one per line:
 *   keyword | https://aff.url | optional label
 */
function save_monetize_config(array $input): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    if (function_exists('ensure_monetization_schema')) {
        ensure_monetization_schema();
    }
    $rules = [];
    foreach (preg_split('/\R/', (string) ($input['affiliate_rules'] ?? '')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $kw = $parts[0] ?? '';
        $url = $parts[1] ?? '';
        if ($kw !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $rules[] = ['keyword' => $kw, 'url' => $url, 'label' => $parts[2] ?? ''];
        }
    }
    $values = [
        'affiliate_enabled' => !empty($input['affiliate_enabled']) ? '1' : '0',
        'affiliate_max' => (string) max(1, min(10, (int) ($input['affiliate_max'] ?? 3))),
        'affiliate_rules' => json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'affiliate_disclosure' => trim((string) ($input['affiliate_disclosure'] ?? '')),
        'sponsor_enabled' => !empty($input['sponsor_enabled']) ? '1' : '0',
        'sponsor_label' => trim((string) ($input['sponsor_label'] ?? '赞助 · Sponsored')),
        'sponsor_name' => trim((string) ($input['sponsor_name'] ?? '')),
        'sponsor_blurb' => trim((string) ($input['sponsor_blurb'] ?? '')),
        'sponsor_url' => trim((string) ($input['sponsor_url'] ?? '')),
        'sponsor_cta' => trim((string) ($input['sponsor_cta'] ?? '了解更多')) ?: '了解更多',
        'adsense_enabled' => !empty($input['adsense_enabled']) ? '1' : '0',
        'adsense_client' => trim((string) ($input['adsense_client'] ?? '')),
        'adsense_slot_article' => trim((string) ($input['adsense_slot_article'] ?? '')),
        'target_rpm' => (string) max(0.0, (float) ($input['target_rpm'] ?? 0)),
    ];
    try {
        $stmt = $pdo->prepare('INSERT INTO monetization_settings (setting_key, setting_value) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $k => $v) {
            $stmt->execute(['k' => $k, 'v' => $v]);
        }
    } catch (Throwable $exception) {
    }
}

/** Re-flatten stored rules into the textarea format for the admin form. */
function monetize_rules_textarea(): string
{
    $lines = [];
    foreach (monetize_config()['affiliate_rules'] as $r) {
        $lines[] = $r['keyword'] . ' | ' . $r['url'] . ($r['label'] !== '' ? ' | ' . $r['label'] : '');
    }
    return implode("\n", $lines);
}
