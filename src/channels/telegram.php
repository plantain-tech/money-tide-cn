<?php

declare(strict_types=1);

/**
 * Day 10·2 — Telegram channel integration (one concrete channel behind the
 * social_publish.php dispatcher).
 *
 * Config is read from pipeline_settings first (set live in /admin/channels, no
 * redeploy needed) and falls back to deploy secrets (telegram.* in config.php).
 * Sends via the Telegram Bot API sendMessage; returns the message_id so the
 * dispatcher can log it.
 */

function telegram_config(): array
{
    $token = function_exists('pipeline_setting') ? trim(pipeline_setting('telegram_bot_token', '')) : '';
    if ($token === '') {
        $token = trim((string) app_config('telegram.bot_token', ''));
    }
    $chat = function_exists('pipeline_setting') ? trim(pipeline_setting('telegram_channel_id', '')) : '';
    if ($chat === '') {
        $chat = trim((string) app_config('telegram.channel_id', ''));
    }
    $enabled = function_exists('pipeline_setting') ? (pipeline_setting('telegram_enabled', '0') === '1') : false;
    return ['bot_token' => $token, 'channel_id' => $chat, 'enabled' => $enabled];
}

function telegram_configured(): bool
{
    $c = telegram_config();
    return $c['bot_token'] !== '' && $c['channel_id'] !== '';
}

/** Ready to auto-dispatch (configured AND enabled). */
function telegram_ready(): bool
{
    $c = telegram_config();
    return $c['enabled'] && $c['bot_token'] !== '' && $c['channel_id'] !== '';
}

/** Escape text for Telegram HTML parse mode (only & < > matter). */
function telegram_escape(string $s): string
{
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
}

/**
 * Send an HTML message to the configured channel.
 * Returns ['ok'=>bool, 'message_id'=>int, 'error'=>string].
 */
function telegram_send_message(string $html, array $opts = []): array
{
    $c = telegram_config();
    if ($c['bot_token'] === '' || $c['channel_id'] === '') {
        return ['ok' => false, 'error' => '未配置 Telegram Bot Token 或频道 ID。'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => '服务器未启用 cURL。'];
    }
    $payload = [
        'chat_id' => $c['channel_id'],
        'text' => $html,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => !empty($opts['no_preview']),
        'disable_notification' => !empty($opts['silent']),
    ];
    $ch = curl_init('https://api.telegram.org/bot' . $c['bot_token'] . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);
    if (is_array($decoded) && !empty($decoded['ok'])) {
        return ['ok' => true, 'message_id' => (int) ($decoded['result']['message_id'] ?? 0)];
    }
    $reason = is_array($decoded) ? (string) ($decoded['description'] ?? '') : '';
    return ['ok' => false, 'error' => $reason !== '' ? $reason : ($err !== '' ? $err : 'HTTP ' . $status)];
}
