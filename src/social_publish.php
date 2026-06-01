<?php

declare(strict_types=1);

/**
 * Day 10·2 — Channel dispatcher abstraction.
 *
 * A thin, channel-agnostic layer the pipeline calls when an article is published
 * (and once per run for the daily digest). Today it drives Telegram; new channels
 * (e.g. a Discord/Slack webhook) just register here and implement send().
 *
 * Every dispatch is logged to social_dispatches with the external message id, and
 * a UNIQUE (channel, kind, ref_id) key makes dispatch idempotent — re-running the
 * pipeline never double-posts the same article or digest.
 *
 * This only posts the platform's OWN links to its OWN channel — it does not
 * publish article bodies anywhere, so the copyright/attribution rules are intact.
 */

function ensure_social_dispatch_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS social_dispatches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(20) NOT NULL,
            kind VARCHAR(20) NOT NULL,
            ref_id INT UNSIGNED NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'ok',
            external_id VARCHAR(80) NULL,
            message VARCHAR(600) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_dispatch (channel, kind, ref_id),
            INDEX idx_dispatch_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

/** Registered auto-dispatch channels + readiness. Extend here for new channels. */
function social_dispatch_channels(): array
{
    return [
        'telegram' => [
            'label' => 'Telegram 频道',
            'icon' => '✈️',
            'configured' => function_exists('telegram_configured') && telegram_configured(),
            'ready' => function_exists('telegram_ready') && telegram_ready(),
        ],
    ];
}

function social_dispatch_already(string $channel, string $kind, int $refId): bool
{
    ensure_social_dispatch_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM social_dispatches WHERE channel = :c AND kind = :k AND ref_id = :r AND status = 'ok' LIMIT 1");
        $stmt->execute(['c' => $channel, 'k' => $kind, 'r' => $refId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        return false;
    }
}

function record_social_dispatch(string $channel, string $kind, int $refId, bool $ok, string $externalId, string $message): void
{
    ensure_social_dispatch_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->prepare('INSERT INTO social_dispatches (channel, kind, ref_id, status, external_id, message)
            VALUES (:c, :k, :r, :s, :e, :m)
            ON DUPLICATE KEY UPDATE status = VALUES(status), external_id = VALUES(external_id), message = VALUES(message), created_at = NOW()')
            ->execute([
                'c' => $channel,
                'k' => $kind,
                'r' => $refId,
                's' => $ok ? 'ok' : 'failed',
                'e' => mb_substr($externalId, 0, 78, 'UTF-8'),
                'm' => mb_substr($message, 0, 590, 'UTF-8'),
            ]);
    } catch (Throwable $exception) {
    }
}

function category_name_by_slug(string $slug): string
{
    foreach ((function_exists('get_categories') ? get_categories() : []) as $c) {
        if ((string) ($c['slug'] ?? '') === $slug) {
            return (string) ($c['name'] ?? $slug);
        }
    }
    return $slug;
}

function format_article_for_telegram(array $a): string
{
    $title = telegram_escape((string) ($a['title'] ?? ''));
    $brief = telegram_escape((string) ($a['brief'] ?? ''));
    $cat = telegram_escape((string) ($a['category_name'] ?? ''));
    $url = canonical_url('article/' . (string) ($a['slug'] ?? ''));
    $text = '<b>' . $title . '</b>' . "\n";
    if ($cat !== '') {
        $text .= '🏷 ' . $cat . "\n";
    }
    if ($brief !== '') {
        $text .= "\n" . $brief . "\n";
    }
    $text .= "\n🔗 <a href=\"" . telegram_escape($url) . "\">阅读全文 · 钱潮 Money Tide</a>";
    return $text;
}

/**
 * Dispatch one freshly-published article to every ready channel. Best-effort,
 * idempotent. $article needs: id, slug, title, brief, category_name.
 */
function dispatch_article_to_channels(array $article): array
{
    $out = [];
    $articleId = (int) ($article['id'] ?? 0);
    if ($articleId <= 0 || trim((string) ($article['slug'] ?? '')) === '') {
        return $out;
    }
    if (function_exists('telegram_ready') && telegram_ready()) {
        if (!social_dispatch_already('telegram', 'article', $articleId)) {
            $r = telegram_send_message(format_article_for_telegram($article));
            record_social_dispatch('telegram', 'article', $articleId, !empty($r['ok']), (string) ($r['message_id'] ?? ''), !empty($r['ok']) ? 'article #' . $articleId : (string) ($r['error'] ?? ''));
            $out['telegram'] = $r;
        }
    }
    return $out;
}

/**
 * Post a single daily digest (links to the day's published articles) to each
 * ready channel, once per day per channel.
 */
function dispatch_daily_digest_to_channels(?string $date = null): array
{
    $date = $date ?: date('Y-m-d');
    $refId = (int) date('Ymd', strtotime($date));
    $out = [];
    if (!(function_exists('telegram_ready') && telegram_ready())) {
        return $out;
    }
    if (social_dispatch_already('telegram', 'digest', $refId)) {
        return $out;
    }
    $articles = published_articles_on_date($date, 12);
    if (!$articles) {
        return $out;
    }
    $text = '<b>📰 钱潮 Money Tide · ' . telegram_escape($date) . ' 早报</b>' . "\n\n";
    $i = 1;
    foreach ($articles as $a) {
        $url = canonical_url('article/' . (string) $a['slug']);
        $text .= $i . '. <a href="' . telegram_escape($url) . '">' . telegram_escape((string) $a['title']) . '</a>' . "\n";
        $i++;
    }
    $text .= "\n— 每天 5 分钟，看懂全球市场。";
    $r = telegram_send_message($text, ['no_preview' => true]);
    record_social_dispatch('telegram', 'digest', $refId, !empty($r['ok']), (string) ($r['message_id'] ?? ''), !empty($r['ok']) ? count($articles) . ' 篇' : (string) ($r['error'] ?? ''));
    $out['telegram'] = $r;
    return $out;
}

function published_articles_on_date(string $date, int $limit = 12): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("SELECT slug, title FROM articles
            WHERE status = 'published' AND DATE(published_at) = :d
            ORDER BY published_at DESC LIMIT " . max(1, min(30, $limit)));
        $stmt->execute(['d' => $date]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function social_dispatches(int $limit = 40): array
{
    ensure_social_dispatch_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        return $pdo->query('SELECT * FROM social_dispatches ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)))->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function social_publish_summary(): array
{
    ensure_social_dispatch_schema();
    $pdo = db();
    $out = ['total' => 0, 'ok' => 0, 'failed' => 0, 'today' => 0];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['total'] = (int) $pdo->query('SELECT COUNT(*) FROM social_dispatches')->fetchColumn();
        $out['ok'] = (int) $pdo->query("SELECT COUNT(*) FROM social_dispatches WHERE status = 'ok'")->fetchColumn();
        $out['failed'] = (int) $pdo->query("SELECT COUNT(*) FROM social_dispatches WHERE status = 'failed'")->fetchColumn();
        $out['today'] = (int) $pdo->query("SELECT COUNT(*) FROM social_dispatches WHERE status = 'ok' AND DATE(created_at) = CURDATE()")->fetchColumn();
    } catch (Throwable $exception) {
    }
    return $out;
}

function save_telegram_settings(array $input): void
{
    if (!function_exists('set_pipeline_setting')) {
        return;
    }
    // Only overwrite the token when a new non-masked value is provided.
    $token = trim((string) ($input['telegram_bot_token'] ?? ''));
    if ($token !== '' && strpos($token, '•') === false) {
        set_pipeline_setting('telegram_bot_token', $token);
    }
    if (isset($input['telegram_channel_id'])) {
        set_pipeline_setting('telegram_channel_id', trim((string) $input['telegram_channel_id']));
    }
    set_pipeline_setting('telegram_enabled', !empty($input['telegram_enabled']) ? '1' : '0');
}

function send_channel_test(): array
{
    if (!(function_exists('telegram_configured') && telegram_configured())) {
        return ['ok' => false, 'error' => '请先填写并保存 Bot Token 和频道 ID。'];
    }
    $r = telegram_send_message('<b>✅ 钱潮 Money Tide</b>' . "\nTelegram 频道连接测试成功，自动分发已就绪。");
    return $r;
}
