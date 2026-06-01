<?php

declare(strict_types=1);

/**
 * Day 10·4 — WeChat Official Account "semi-auto" channel.
 *
 * Creates a DRAFT in the official account's 草稿箱 via the draft/add API. It never
 * publishes or mass-sends — the owner reviews and sends from the WeChat backend
 * (the human gate). So this is "AI writes the draft, you approve & send".
 *
 * Requirements (WeChat side): a verified Official Account, AppID + AppSecret,
 * the server IP added to the API 白名单, and one permanent cover image whose
 * thumb_media_id is configured here (draft/add requires a cover).
 *
 * Config: pipeline_settings (set in /admin/channels) with deploy-secret fallback.
 */

function wechat_config(): array
{
    $get = static function (string $dbKey, string $cfgKey): string {
        $v = function_exists('pipeline_setting') ? trim(pipeline_setting($dbKey, '')) : '';
        if ($v === '') {
            $v = trim((string) app_config($cfgKey, ''));
        }
        return $v;
    };
    return [
        'app_id' => $get('wechat_app_id', 'wechat.app_id'),
        'app_secret' => $get('wechat_app_secret', 'wechat.app_secret'),
        'thumb_media_id' => $get('wechat_thumb_media_id', 'wechat.thumb_media_id'),
        'author' => $get('wechat_author', 'wechat.author') ?: '钱潮 Money Tide',
        'enabled' => function_exists('pipeline_setting') ? (pipeline_setting('wechat_enabled', '0') === '1') : false,
    ];
}

function wechat_configured(): bool
{
    $c = wechat_config();
    return $c['app_id'] !== '' && $c['app_secret'] !== '';
}

/** Ready to auto-create drafts (enabled + creds + a cover media id). */
function wechat_ready(): bool
{
    $c = wechat_config();
    return $c['enabled'] && $c['app_id'] !== '' && $c['app_secret'] !== '' && $c['thumb_media_id'] !== '';
}

/** Fetch + cache the access token (valid ~7200s) in pipeline_settings. */
function wechat_access_token(bool $force = false): array
{
    $c = wechat_config();
    if ($c['app_id'] === '' || $c['app_secret'] === '') {
        return ['ok' => false, 'error' => '未配置 AppID / AppSecret。'];
    }
    if (!$force && function_exists('pipeline_setting')) {
        $tok = pipeline_setting('wechat_access_token', '');
        $exp = (int) pipeline_setting('wechat_token_expires', '0');
        if ($tok !== '' && $exp > time() + 60) {
            return ['ok' => true, 'token' => $tok];
        }
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => '服务器未启用 cURL。'];
    }
    $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='
        . rawurlencode($c['app_id']) . '&secret=' . rawurlencode($c['app_secret']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $d = json_decode((string) $raw, true);
    if (is_array($d) && !empty($d['access_token'])) {
        if (function_exists('set_pipeline_setting')) {
            set_pipeline_setting('wechat_access_token', (string) $d['access_token']);
            set_pipeline_setting('wechat_token_expires', (string) (time() + (int) ($d['expires_in'] ?? 7200)));
        }
        return ['ok' => true, 'token' => (string) $d['access_token']];
    }
    $reason = is_array($d) ? trim(($d['errcode'] ?? '') . ' ' . ($d['errmsg'] ?? '')) : '';
    return ['ok' => false, 'error' => $reason !== '' ? $reason : ($err !== '' ? $err : '获取 access_token 失败')];
}

/** Wrap plain body text into WeChat-friendly article HTML. */
function wechat_build_content_html(string $bodyText, string $sourceUrl = ''): string
{
    $paras = preg_split('/\n\s*\n|\n/u', trim($bodyText)) ?: [];
    $html = '';
    foreach ($paras as $p) {
        $p = trim($p);
        if ($p !== '') {
            $html .= '<p style="margin:0 0 16px;line-height:1.8;font-size:16px;">'
                . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
    if ($sourceUrl !== '') {
        $html .= '<p style="margin:20px 0 0;color:#888;font-size:13px;">本文来自钱潮 Money Tide。</p>';
    }
    return $html !== '' ? $html : '<p>（无正文）</p>';
}

/**
 * Create a draft article in 草稿箱. Returns ['ok','media_id','error'].
 */
function wechat_create_draft(string $title, string $digest, string $contentHtml, string $sourceUrl = ''): array
{
    if (!wechat_configured()) {
        return ['ok' => false, 'error' => '未配置 AppID / AppSecret。'];
    }
    $c = wechat_config();
    if ($c['thumb_media_id'] === '') {
        return ['ok' => false, 'error' => '缺少封面 thumb_media_id（草稿必须有封面，请先在公众号上传一张永久图片素材并填入其 media_id）。'];
    }
    $tok = wechat_access_token();
    if (empty($tok['ok'])) {
        return ['ok' => false, 'error' => (string) $tok['error']];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => '服务器未启用 cURL。'];
    }
    $payload = ['articles' => [[
        'title' => mb_substr($title, 0, 64, 'UTF-8'),
        'author' => mb_substr($c['author'], 0, 8, 'UTF-8'),
        'digest' => mb_substr($digest, 0, 120, 'UTF-8'),
        'content' => $contentHtml,
        'content_source_url' => $sourceUrl,
        'thumb_media_id' => $c['thumb_media_id'],
        'need_open_comment' => 0,
        'only_fans_can_comment' => 0,
    ]]];
    $url = 'https://api.weixin.qq.com/cgi-bin/draft/add?access_token=' . rawurlencode((string) $tok['token']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $d = json_decode((string) $raw, true);
    if (is_array($d) && !empty($d['media_id'])) {
        return ['ok' => true, 'media_id' => (string) $d['media_id']];
    }
    $reason = is_array($d) ? trim(($d['errcode'] ?? '') . ' ' . ($d['errmsg'] ?? '')) : '';
    return ['ok' => false, 'error' => $reason !== '' ? $reason : ($err !== '' ? $err : '创建草稿失败')];
}

/** Non-destructive test: verify credentials by fetching a token. */
function wechat_send_test(): array
{
    if (!wechat_configured()) {
        return ['ok' => false, 'error' => '请先填写并保存 AppID 和 AppSecret。'];
    }
    $tok = wechat_access_token(true);
    if (!empty($tok['ok'])) {
        return ['ok' => true, 'message' => '凭证有效，已成功获取 access_token。' . (wechat_config()['thumb_media_id'] === '' ? '（提示：还需填写封面 thumb_media_id 才能创建草稿）' : '')];
    }
    return ['ok' => false, 'error' => (string) $tok['error']];
}
