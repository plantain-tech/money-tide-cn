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
        'x' => [
            'label' => 'X (Twitter)',
            'icon' => '𝕏',
            'configured' => function_exists('x_configured') && x_configured(),
            'ready' => function_exists('x_ready') && x_ready(),
        ],
        'wechat' => [
            'label' => 'WeChat 公众号',
            'icon' => '💬',
            'configured' => function_exists('wechat_configured') && wechat_configured(),
            'ready' => function_exists('wechat_ready') && wechat_ready(),
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

/**
 * CTA footer for Telegram channel posts. Channel readers are already on Telegram,
 * so this pushes (a) the email subscribe (move them to the owned list) and (b) a
 * channel link, so when a post is FORWARDED into other groups the recipient can
 * tap through to join. Links are UTM-tagged for attribution.
 */
function telegram_cta_footer(): string
{
    $sub = canonical_url('subscribe') . '?utm_source=telegram&utm_medium=channel&utm_campaign=footer';
    $footer = "\n\n———\n📬 <a href=\"" . telegram_escape($sub) . "\">订阅邮件版早报（免费）</a>";
    $tg = function_exists('telegram_channel_url') ? trim(telegram_channel_url()) : '';
    if ($tg !== '') {
        $footer .= '　·　✈️ <a href="' . telegram_escape($tg) . '">钱潮频道</a>';
    }
    return $footer;
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
    $text .= telegram_cta_footer();
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
    if (function_exists('x_ready') && x_ready()) {
        if (!social_dispatch_already('x', 'article', $articleId)) {
            if (function_exists('x_budget_remaining') && x_budget_remaining() > 0) {
                $r = x_post_tweet(build_x_caption($article));
                record_social_dispatch('x', 'article', $articleId, !empty($r['ok']), (string) ($r['id'] ?? ''), !empty($r['ok']) ? 'tweet' : (string) ($r['error'] ?? ''));
                $out['x'] = $r;
            } else {
                // Monthly free-tier budget spent — skip WITHOUT recording, so the
                // article stays eligible to post once the quota resets.
                $out['x'] = ['ok' => false, 'skipped' => true, 'error' => '本月 X 配额已用完'];
            }
        }
    }
    if (function_exists('wechat_ready') && wechat_ready()) {
        if (!social_dispatch_already('wechat', 'article', $articleId)) {
            $url = canonical_url('article/' . (string) ($article['slug'] ?? ''));
            $html = wechat_build_content_html((string) ($article['body'] ?? ''), $url);
            $r = wechat_create_draft((string) ($article['title'] ?? ''), (string) ($article['brief'] ?? ''), $html, $url);
            record_social_dispatch('wechat', 'article', $articleId, !empty($r['ok']), (string) ($r['media_id'] ?? ''), !empty($r['ok']) ? 'draft' : (string) ($r['error'] ?? ''));
            $out['wechat'] = $r;
        }
    }
    return $out;
}

/**
 * Build a tweet: AI social headline (from synthesis) + up to 2 English hashtags
 * + a UTM-tagged article link. Trimmed to stay well under the 280-char limit.
 */
function build_x_caption(array $a): string
{
    $base = trim((string) ($a['social_headline'] ?? '')) ?: trim((string) ($a['title'] ?? ''));
    $base = mb_substr($base, 0, 150, 'UTF-8');
    $hashtags = '';
    $count = 0;
    foreach ((array) ($a['tags'] ?? []) as $t) {
        $t = trim((string) $t);
        if ($t !== '' && preg_match('/^[A-Za-z0-9]{2,20}$/', $t)) {
            $hashtags .= ' #' . $t;
            if (++$count >= 2) {
                break;
            }
        }
    }
    $url = canonical_url('article/' . (string) ($a['slug'] ?? '')) . '?utm_source=x&utm_medium=social&utm_campaign=auto_dispatch';
    return $base . $hashtags . "\n" . $url;
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
    $text .= telegram_cta_footer();
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

function save_x_settings(array $input): void
{
    if (!function_exists('set_pipeline_setting')) {
        return;
    }
    foreach (['x_api_key', 'x_api_secret', 'x_access_token', 'x_access_token_secret'] as $field) {
        $v = trim((string) ($input[$field] ?? ''));
        if ($v !== '' && strpos($v, '•') === false) { // ignore masked placeholder
            set_pipeline_setting($field, $v);
        }
    }
    set_pipeline_setting('x_enabled', !empty($input['x_enabled']) ? '1' : '0');
    if (isset($input['x_monthly_budget'])) {
        set_pipeline_setting('x_monthly_budget', (string) max(1, min(10000, (int) $input['x_monthly_budget'])));
    }
}

function save_wechat_settings(array $input): void
{
    if (!function_exists('set_pipeline_setting')) {
        return;
    }
    foreach (['wechat_app_id', 'wechat_thumb_media_id', 'wechat_author'] as $field) {
        if (isset($input[$field])) {
            set_pipeline_setting($field, trim((string) $input[$field]));
        }
    }
    $secret = trim((string) ($input['wechat_app_secret'] ?? ''));
    if ($secret !== '' && strpos($secret, '•') === false) {
        set_pipeline_setting('wechat_app_secret', $secret);
    }
    set_pipeline_setting('wechat_enabled', !empty($input['wechat_enabled']) ? '1' : '0');
}

function send_channel_test(): array
{
    if (!(function_exists('telegram_configured') && telegram_configured())) {
        return ['ok' => false, 'error' => '请先填写并保存 Bot Token 和频道 ID。'];
    }
    // Include the live CTA footer so the test message doubles as a footer preview.
    $r = telegram_send_message('<b>✅ 钱潮 Money Tide</b>' . "\nTelegram 频道连接测试成功，自动分发已就绪。\n（下方为每条推送将自动附带的订阅页脚示例）" . telegram_cta_footer());
    return $r;
}
