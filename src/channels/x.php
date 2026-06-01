<?php

declare(strict_types=1);

/**
 * Day 10·3 — X (Twitter) channel adapter for the social_publish.php dispatcher.
 *
 * Posts via X API v2 (POST /2/tweets) authenticated with OAuth 1.0a User Context
 * (the 4 app credentials: API key/secret + Access token/secret), which the free
 * tier supports for writes (~500 posts/month). Caption reuses the AI-written
 * social_headline from synthesis (no extra AI call) + a UTM-tagged link.
 *
 * Budget-aware: it counts this month's successful X posts and stops before the
 * free-tier ceiling so we never burn the quota or get rate-limited.
 *
 * Config: pipeline_settings (set in /admin/channels, no redeploy) with deploy-
 * secret fallback (x.*).
 */

function x_config(): array
{
    $get = static function (string $dbKey, string $cfgKey): string {
        $v = function_exists('pipeline_setting') ? trim(pipeline_setting($dbKey, '')) : '';
        if ($v === '') {
            $v = trim((string) app_config($cfgKey, ''));
        }
        return $v;
    };
    return [
        'api_key' => $get('x_api_key', 'x.api_key'),
        'api_secret' => $get('x_api_secret', 'x.api_secret'),
        'access_token' => $get('x_access_token', 'x.access_token'),
        'access_token_secret' => $get('x_access_token_secret', 'x.access_token_secret'),
        'enabled' => function_exists('pipeline_setting') ? (pipeline_setting('x_enabled', '0') === '1') : false,
        'monthly_budget' => function_exists('pipeline_setting') ? max(1, min(10000, (int) pipeline_setting('x_monthly_budget', '450'))) : 450,
    ];
}

function x_configured(): bool
{
    $c = x_config();
    return $c['api_key'] !== '' && $c['api_secret'] !== '' && $c['access_token'] !== '' && $c['access_token_secret'] !== '';
}

/** Enabled AND fully configured. */
function x_ready(): bool
{
    return x_config()['enabled'] && x_configured();
}

/** Successful X posts so far this calendar month. */
function x_month_usage(): int
{
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM social_dispatches
            WHERE channel = 'x' AND status = 'ok' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function x_budget_remaining(): int
{
    return max(0, x_config()['monthly_budget'] - x_month_usage());
}

/** Build the OAuth 1.0a Authorization header for a JSON (no body-param) request. */
function x_oauth1_header(array $cfg, string $method, string $url): string
{
    $oauth = [
        'oauth_consumer_key' => $cfg['api_key'],
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => (string) time(),
        'oauth_token' => $cfg['access_token'],
        'oauth_version' => '1.0',
    ];
    // JSON body + no query string ⇒ only oauth_* params are signed.
    $params = $oauth;
    ksort($params);
    $paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $base = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
    $signingKey = rawurlencode($cfg['api_secret']) . '&' . rawurlencode($cfg['access_token_secret']);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $signingKey, true));

    $parts = [];
    foreach ($oauth as $k => $v) {
        $parts[] = rawurlencode($k) . '="' . rawurlencode($v) . '"';
    }
    return 'OAuth ' . implode(', ', $parts);
}

/**
 * Post a tweet. Returns ['ok'=>bool, 'id'=>string, 'error'=>string].
 */
function x_post_tweet(string $text): array
{
    if (!x_configured()) {
        return ['ok' => false, 'error' => '未配置 X API 凭证（需 4 项）。'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => '服务器未启用 cURL。'];
    }
    $cfg = x_config();
    $url = 'https://api.twitter.com/2/tweets';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . x_oauth1_header($cfg, 'POST', $url),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);
    if ($status >= 200 && $status < 300 && isset($decoded['data']['id'])) {
        return ['ok' => true, 'id' => (string) $decoded['data']['id']];
    }
    $reason = '';
    if (is_array($decoded)) {
        $reason = (string) ($decoded['detail'] ?? $decoded['title'] ?? ($decoded['errors'][0]['message'] ?? ''));
    }
    if ($status === 429) {
        $reason = 'X 速率/月度配额已达上限（429）' . ($reason !== '' ? '：' . $reason : '');
    } elseif ($status === 401 || $status === 403) {
        $reason = 'X 认证失败（' . $status . '）—— 检查 4 项凭证与 App 的 Read+Write 权限' . ($reason !== '' ? '：' . $reason : '');
    }
    return ['ok' => false, 'error' => $reason !== '' ? $reason : ($err !== '' ? $err : 'HTTP ' . $status)];
}

/** Post a one-off connectivity test tweet (uses 1 of the monthly write budget). */
function x_send_test(): array
{
    if (!x_configured()) {
        return ['ok' => false, 'error' => '请先填写并保存 4 项 X API 凭证。'];
    }
    return x_post_tweet('钱潮 Money Tide ✅ 自动分发已接通（连接测试 · ' . date('H:i') . '）');
}
