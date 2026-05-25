<?php

declare(strict_types=1);

$basePath = getenv('APP_BASE_PATH') ?: '';
if ($basePath === '' || !is_dir(rtrim((string) $basePath, DIRECTORY_SEPARATOR) . '/src')) {
    $basePath = is_dir(dirname(__DIR__) . '/src') ? dirname(__DIR__) : __DIR__;
}
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

if ($route === 'admin/articles') {
    require_admin();
    render_page('admin/articles', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'articles' => admin_articles([
            'status' => $_GET['status'] ?? '',
            'category' => $_GET['category'] ?? '',
            'q' => $_GET['q'] ?? '',
        ]),
        'filters' => [
            'status' => (string) ($_GET['status'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'q' => (string) ($_GET['q'] ?? ''),
        ],
        'dbReady' => db_is_ready(),
    ]);
    exit;
}

if ($route === 'admin/articles/new') {
    require_admin();
    $errors = [];
    $form = article_form_defaults();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_article($_POST);
        if ($result['ok']) {
            header('Location: ' . url('admin/articles'));
            exit;
        }
        $errors = $result['errors'];
        $form = array_replace($form, $_POST);
    }

    render_page('admin/article-form', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'form' => $form,
        'errors' => $errors,
        'mode' => 'create',
        'action' => url('admin/articles/new'),
    ]);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/edit$#', $route, $matches)) {
    require_admin();
    $articleId = (int) $matches[1];
    $article = admin_article_by_id($articleId);
    if (!$article) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }

    $errors = [];
    $form = article_to_form($article);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_article($_POST, $articleId);
        if ($result['ok']) {
            header('Location: ' . url('admin/articles'));
            exit;
        }
        $errors = $result['errors'];
        $form = array_replace($form, $_POST);
    }

    render_page('admin/article-form', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'form' => $form,
        'errors' => $errors,
        'mode' => 'edit',
        'action' => url('admin/articles/' . $articleId . '/edit'),
    ]);
    exit;
}

if ($route === 'admin/categories') {
    require_admin();
    render_page('admin/categories', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'dbReady' => db_is_ready(),
    ]);
    exit;
}

if ($route === 'admin/seed-launch-articles' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $seedResult = seed_launch_articles();
    render_page('admin/seed-result', [
        'site' => $site,
        'categories' => $categories,
        'seedResult' => $seedResult,
    ]);
    exit;
}

if ($route === 'admin/ai-drafts') {
    require_admin();
    render_page('admin/ai-drafts', [
        'site' => $site,
        'categories' => $categories,
        'drafts' => ai_drafts([
            'status' => $_GET['status'] ?? '',
            'section_slug' => $_GET['section_slug'] ?? '',
        ]),
        'templates' => editorial_bot_templates(),
        'filters' => [
            'status' => (string) ($_GET['status'] ?? ''),
            'section_slug' => (string) ($_GET['section_slug'] ?? ''),
        ],
        'aiReady' => (string) app_config('ai.api_key', '') !== '',
    ]);
    exit;
}

if ($route === 'admin/ai-drafts/new') {
    require_admin();
    $errors = [];
    $form = ai_draft_form_defaults();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = generate_ai_draft($_POST);
        if ($result['ok']) {
            header('Location: ' . url('admin/ai-drafts/' . $result['id']));
            exit;
        }
        $errors = $result['errors'];
        $form = array_replace($form, $_POST);
    }

    render_page('admin/ai-draft-form', [
        'site' => $site,
        'categories' => $categories,
        'templates' => editorial_bot_templates(),
        'form' => $form,
        'errors' => $errors,
        'aiReady' => (string) app_config('ai.api_key', '') !== '',
    ]);
    exit;
}

if (preg_match('#^admin/ai-drafts/(\d+)$#', $route, $matches)) {
    require_admin();
    $draft = ai_draft_by_id((int) $matches[1]);
    if (!$draft) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }

    render_page('admin/ai-draft-detail', [
        'site' => $site,
        'categories' => $categories,
        'draft' => $draft,
        'templates' => editorial_bot_templates(),
        'message' => (string) ($_GET['message'] ?? ''),
    ]);
    exit;
}

if (preg_match('#^admin/ai-drafts/(\d+)/status$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    update_ai_draft_status((int) $matches[1], (string) ($_POST['status'] ?? 'reviewed'));
    header('Location: ' . url('admin/ai-drafts/' . (int) $matches[1]));
    exit;
}

if (preg_match('#^admin/ai-drafts/(\d+)/convert$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $result = convert_ai_draft_to_article((int) $matches[1]);
    if ($result['ok']) {
        header('Location: ' . url('admin/articles/' . $result['article_id'] . '/edit'));
        exit;
    }

    header('Location: ' . url('admin/ai-drafts/' . (int) $matches[1] . '?message=' . rawurlencode($result['message'] ?? '转换失败。')));
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

if (strpos($route, 'category/') === 0) {
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

if (strpos($route, 'article/') === 0) {
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
