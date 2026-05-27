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
require_once __DIR__ . '/ai_sources.php';
require_once __DIR__ . '/ai_quality.php';
require_once __DIR__ . '/newsletter_ai.php';
require_once __DIR__ . '/reader_accounts.php';
require_once __DIR__ . '/retention.php';
require_once __DIR__ . '/monetization.php';
require_once __DIR__ . '/tags.php';
require_once __DIR__ . '/diagnostics.php';
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
