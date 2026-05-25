<?php

declare(strict_types=1);

require_once __DIR__ . '/content.php';

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', dirname(__DIR__));
}

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
