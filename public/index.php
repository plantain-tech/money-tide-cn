<?php

declare(strict_types=1);

$basePath = getenv('APP_BASE_PATH') ?: dirname(__DIR__);
define('APP_BASE_PATH', rtrim((string) $basePath, DIRECTORY_SEPARATOR));

require_once APP_BASE_PATH . '/src/bootstrap.php';

$route = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$route = $route === '' ? 'home' : $route;

if ($route === 'api/newsletter/subscribe' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $result = subscribe_email(
        (string) ($_POST['email'] ?? ''),
        (array) ($_POST['topics'] ?? []),
        (string) ($_POST['source'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))
    );
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$site = site_meta();
$categories = get_categories();
$articles = get_articles();
$featured = $articles[0] ?? null;

if ($route === 'admin/login') {
    $error = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (login_admin((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: ' . url('admin'));
            exit;
        }
        $error = '邮箱或密码不正确。';
    }

    render_page('admin/login', [
        'site' => $site,
        'categories' => $categories,
        'error' => $error,
    ]);
    exit;
}

if ($route === 'admin/logout') {
    logout_admin();
    header('Location: ' . url('admin/login'));
    exit;
}

if ($route === 'admin') {
    require_admin();
    render_page('admin/dashboard', [
        'site' => $site,
        'categories' => $categories,
        'user' => current_user(),
        'stats' => admin_stats(),
        'dbReady' => db_is_ready(),
    ]);
    exit;
}

if ($route === 'admin/db-health') {
    require_admin();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'database' => db_is_ready() ? 'connected' : 'not_configured_or_unavailable',
        'checked_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($route === 'home') {
    render_page('home', [
        'site' => $site,
        'categories' => $categories,
        'articles' => $articles,
        'featured' => $featured,
    ]);
    exit;
}

if ($route === 'latest') {
    render_page('latest', [
        'site' => $site,
        'categories' => $categories,
        'articles' => $articles,
    ]);
    exit;
}

if ($route === 'subscribe') {
    render_page('subscribe', [
        'site' => $site,
        'categories' => $categories,
    ]);
    exit;
}

if ($route === 'about') {
    render_page('about', [
        'site' => $site,
        'categories' => $categories,
    ]);
    exit;
}

if ($route === 'editorial-standards') {
    render_page('editorial-standards', [
        'site' => $site,
        'categories' => $categories,
    ]);
    exit;
}

if ($route === 'disclaimer') {
    render_page('disclaimer', [
        'site' => $site,
        'categories' => $categories,
    ]);
    exit;
}

if (str_starts_with($route, 'category/')) {
    $slug = basename($route);
    $category = find_category($slug);
    if ($category === null) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }

    render_page('category', [
        'site' => $site,
        'categories' => $categories,
        'category' => $category,
        'articles' => filter_articles_by_category($slug),
    ]);
    exit;
}

if (str_starts_with($route, 'article/')) {
    $slug = basename($route);
    $article = find_article($slug);
    if ($article === null) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }

    render_page('article', [
        'site' => $site,
        'categories' => $categories,
        'article' => $article,
        'related' => related_articles($article),
    ]);
    exit;
}

http_response_code(404);
render_page('404', compact('site', 'categories'));
