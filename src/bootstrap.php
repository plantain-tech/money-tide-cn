<?php

declare(strict_types=1);

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', dirname(__DIR__));
}

require_once __DIR__ . '/app-config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repositories.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/subscribers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/ai_bots.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/launch.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/newsletter_issues.php';
require_once __DIR__ . '/email_delivery.php';
require_once __DIR__ . '/ai_sources.php';
require_once __DIR__ . '/ai_quality.php';
require_once __DIR__ . '/newsletter_ai.php';
require_once __DIR__ . '/reader_accounts.php';
require_once __DIR__ . '/retention.php';
require_once __DIR__ . '/monetization.php';
require_once __DIR__ . '/monetize.php';
require_once __DIR__ . '/tags.php';
require_once __DIR__ . '/ai_tags.php';
require_once __DIR__ . '/social.php';
require_once __DIR__ . '/share_cards.php';
require_once __DIR__ . '/short_format.php';
require_once __DIR__ . '/social_analytics.php';
require_once __DIR__ . '/discovery.php';
require_once __DIR__ . '/editorial_calendar.php';
require_once __DIR__ . '/reactions.php';
require_once __DIR__ . '/news_ingest.php';
require_once __DIR__ . '/news_select.php';
require_once __DIR__ . '/news_synthesize.php';
require_once __DIR__ . '/auto_review.php';
require_once __DIR__ . '/channels/telegram.php';
require_once __DIR__ . '/channels/x.php';
require_once __DIR__ . '/channels/wechat.php';
require_once __DIR__ . '/assisted_export.php';
require_once __DIR__ . '/social_publish.php';
require_once __DIR__ . '/news_publish.php';
require_once __DIR__ . '/pipeline.php';
require_once __DIR__ . '/pipeline_analytics.php';
require_once __DIR__ . '/pipeline_alerts.php';
require_once __DIR__ . '/autonomy.php';
require_once __DIR__ . '/backup.php';
require_once __DIR__ . '/content_ops.php';
require_once __DIR__ . '/milestone.php';
require_once __DIR__ . '/diagnostics.php';
require_once __DIR__ . '/launch_cleanup.php';
require_once __DIR__ . '/content.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = trim($path, '/');
    return $path === '' ? '/' : '/' . $path;
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

/** Public Telegram channel join link (for reader CTAs). Override via config. */
function telegram_channel_url(): string
{
    $u = function_exists('app_config') ? trim((string) app_config('social.telegram_channel_url', '')) : '';
    return $u !== '' ? $u : 'https://t.me/moneytide_cn';
}

/**
 * Human, reader-friendly relative time ("刚刚 / 12 分钟前 / 3 小时前 / 2 天前"),
 * falling back to an absolute date for anything older than a week. Reinforces
 * "always fresh" without changing the underlying data.
 */
function relative_time($datetime): string
{
    $dt = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    if (!$dt) {
        return (string) $datetime;
    }
    $diff = time() - $dt;
    if ($diff < 0) {
        $diff = 0;
    }
    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' 分钟前';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' 小时前';
    }
    if ($diff < 86400 * 7) {
        return (int) floor($diff / 86400) . ' 天前';
    }
    return date('Y-m-d', $dt);
}

/** Relative time wrapped in a <time> tag with the absolute timestamp on hover. */
function time_ago_html($datetime): string
{
    $dt = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    $abs = $dt ? date('Y-m-d H:i', $dt) : (string) $datetime;
    $iso = $dt ? date('c', $dt) : '';
    return '<time datetime="' . e($iso) . '" title="' . e($abs) . '">' . e(relative_time($datetime)) . '</time>';
}

/**
 * Freshness state for the newest published item — drives the homepage "fresh"
 * light. fresh = within 24h, warn = 24–48h, stale = older (pipeline likely
 * stalled). Returns state, a label, and the age in hours.
 */
function freshness_state($datetime): array
{
    $dt = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    if (!$dt) {
        return ['state' => 'stale', 'label' => '暂无更新', 'hours' => null];
    }
    $hours = (int) floor((time() - $dt) / 3600);
    if ($hours <= 24) {
        return ['state' => 'fresh', 'label' => '内容新鲜', 'hours' => $hours];
    }
    if ($hours <= 48) {
        return ['state' => 'warn', 'label' => '近日更新', 'hours' => $hours];
    }
    return ['state' => 'stale', 'label' => '可能不是最新', 'hours' => $hours];
}

function render_page(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = APP_BASE_PATH . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('View not found: ' . $view);
    }

    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require APP_BASE_PATH . '/views/layout.php';
}
