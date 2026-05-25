<?php

declare(strict_types=1);

$basePath = getenv('APP_BASE_PATH') ?: dirname(__DIR__);
define('APP_BASE_PATH', rtrim((string) $basePath, DIRECTORY_SEPARATOR));

require_once APP_BASE_PATH . '/src/bootstrap.php';

$route = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$route = $route === '' ? 'home' : $route;

$site = site_meta();
$categories = get_categories();
$articles = get_articles();
$featured = $articles[0] ?? null;

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
