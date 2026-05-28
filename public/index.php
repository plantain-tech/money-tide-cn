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

if (strpos($route, 'admin') === 0) {
    header('X-Robots-Tag: noindex, nofollow', false);
}

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

if ($route === 'api/analytics/event' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $result = record_public_event_from_request($_POST);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$site = site_meta();
$categories = get_categories();
$articles = get_articles();
$featured = $articles[0] ?? null;

if ($route === 'sitemap.xml') {
    emit_sitemap($categories, $articles, all_tags());
    exit;
}

if ($route === 'robots.txt') {
    emit_robots();
    exit;
}

if ($route === 'feed/all.xml') {
    emit_rss_feed(
        rss_articles(null, 50),
        '钱潮 Money Tide - 最新文章',
        '面向中文读者的全球财经、科技与商业简报。',
        'feed/all.xml'
    );
    exit;
}

if (preg_match('#^feed/category/([a-z0-9-]+)\.xml$#', $route, $matches)) {
    $feedCategory = find_category($matches[1]);
    if ($feedCategory === null) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    emit_rss_feed(
        rss_articles((string) $feedCategory['slug'], 50),
        '钱潮 Money Tide - ' . (string) $feedCategory['name'],
        '钱潮 Money Tide ' . (string) $feedCategory['name'] . ' 栏目的最新文章。',
        'feed/category/' . (string) $feedCategory['slug'] . '.xml'
    );
    exit;
}

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
        'aiProvider' => ai_provider_status(),
        'aiUsage' => ai_usage_summary(),
        'analytics' => analytics_summary(),
        'externalAnalytics' => external_analytics_status(),
    ]);
    exit;
}

if ($route === 'admin/analytics') {
    require_admin();
    render_page('admin/analytics', [
        'site' => $site,
        'categories' => $categories,
        'analytics' => analytics_summary(),
        'externalAnalytics' => external_analytics_status(),
    ]);
    exit;
}

if ($route === 'admin/calendar') {
    require_admin();
    $filters = calendar_filters_from_request($_GET);
    $range = calendar_range($filters['view'], $filters['date']);
    $events = editorial_calendar_events($filters, $range);
    render_page('admin/calendar', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'editors' => admin_editors(),
        'filters' => $filters,
        'range' => $range,
        'events' => $events,
        'monthDays' => calendar_month_days($range),
        'weekDays' => calendar_week_days($range),
        'stats' => calendar_stats($events),
        'statusOptions' => calendar_status_options(),
        'viewModes' => calendar_view_modes(),
    ]);
    exit;
}

if ($route === 'admin/monetization') {
    require_admin();
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_monetization_settings($_POST);
        $flash = $result['message'] ?? '';
    }
    render_page('admin/monetization', [
        'site' => $site,
        'categories' => $categories,
        'summary' => monetization_summary(),
        'flash' => $flash,
    ]);
    exit;
}

if ($route === 'admin/week4-checklist') {
    require_admin();
    render_page('admin/week4-checklist', [
        'site' => $site,
        'categories' => $categories,
        'items' => week_four_qa_checklist(),
        'backlog' => week_five_backlog(),
    ]);
    exit;
}

if ($route === 'admin/week5-checklist') {
    require_admin();
    render_page('admin/week5-checklist', [
        'site' => $site,
        'categories' => $categories,
        'items' => week_five_qa_checklist(),
        'backlog' => week_six_backlog(),
        'smokeChecks' => admin_smoke_checks(),
    ]);
    exit;
}

if ($route === 'admin/week6-checklist') {
    require_admin();
    render_page('admin/week6-checklist', [
        'site' => $site,
        'categories' => $categories,
        'items' => week_six_qa_checklist(),
        'backlog' => week_seven_backlog(),
        'smokeChecks' => admin_smoke_checks(),
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

if ($route === 'admin/qa') {
    require_admin();
    render_page('admin/qa', [
        'site' => $site,
        'categories' => $categories,
        'checks' => qa_checks(),
    ]);
    exit;
}

if ($route === 'admin/launch-checklist') {
    require_admin();
    $items = launch_checklist();
    render_page('admin/launch-checklist', [
        'site' => $site,
        'categories' => $categories,
        'items' => $items,
        'ready' => launch_ready($items),
    ]);
    exit;
}

if ($route === 'admin/articles') {
    require_admin();
    $filters = [
        'status' => (string) ($_GET['status'] ?? ''),
        'category' => (string) ($_GET['category'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
        'sort' => (string) ($_GET['sort'] ?? 'updated_desc'),
    ];
    render_page('admin/articles', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'articles' => admin_articles($filters),
        'filters' => $filters,
        'statusCounts' => admin_article_status_counts(),
        'flash' => (string) ($_GET['flash'] ?? ''),
        'dbReady' => db_is_ready(),
        'user' => current_user(),
    ]);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/status$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $result = transition_article_status($articleId, (string) ($_POST['status'] ?? ''));
    $target = (string) ($_POST['return'] ?? 'list') === 'edit'
        ? url('admin/articles/' . $articleId . '/edit')
        : url('admin/articles');
    $flash = $result['ok'] ? '状态已更新。' : ('无法发布：' . implode(' ', $result['errors']));
    $sep = strpos($target, '?') === false ? '?' : '&';
    header('Location: ' . $target . $sep . 'flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/articles/(\d+)/duplicate$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $result = duplicate_article((int) $matches[1]);
    if ($result['ok']) {
        header('Location: ' . url('admin/articles/' . $result['id'] . '/edit') . '?flash=' . rawurlencode('已创建副本。'));
        exit;
    }
    header('Location: ' . url('admin/articles') . '?flash=' . rawurlencode('复制失败：' . implode(' ', $result['errors'])));
    exit;
}

if (preg_match('#^admin/articles/(\d+)/delete$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $result = delete_article((int) $matches[1]);
    $flash = $result['ok'] ? 'Article deleted.' : ('Delete failed: ' . implode(' ', $result['errors']));
    header('Location: ' . url('admin/articles') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/articles/(\d+)/preview$#', $route, $matches)) {
    require_admin();
    $articleId = (int) $matches[1];
    $article = admin_article_by_id($articleId);
    if (!$article || !can_edit_article($article)) {
        http_response_code($article ? 403 : 404);
        render_page($article ? '403' : '404', compact('site', 'categories'));
        exit;
    }
    $preview = admin_article_preview($articleId);
    if (!$preview) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    render_page('admin/article-preview', [
        'site' => $site,
        'categories' => $categories,
        'article' => $preview,
        'related' => [],
    ]);
    exit;
}

if ($route === 'admin/articles/new') {
    require_admin();
    if (!can_create_article()) {
        http_response_code(403);
        render_page('403', compact('site', 'categories'));
        exit;
    }
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
        'authors' => admin_authors(),
        'editors' => admin_editors(),
        'form' => $form,
        'errors' => $errors,
        'mode' => 'create',
        'action' => url('admin/articles/new'),
        'articleId' => 0,
        'currentStatus' => 'draft',
        'checklist' => [],
        'seoChecklist' => [],
        'auditLogs' => [],
        'flash' => '',
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
    if (!can_edit_article($article)) {
        http_response_code(403);
        render_page('403', compact('site', 'categories'));
        exit;
    }

    $errors = [];
    $form = article_to_form($article);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_article($_POST, $articleId);
        if ($result['ok']) {
            header('Location: ' . url('admin/articles/' . $articleId . '/edit') . '?flash=' . rawurlencode('已保存。'));
            exit;
        }
        $errors = $result['errors'];
        $form = array_replace($form, $_POST);
    }

    render_page('admin/article-form', [
        'site' => $site,
        'categories' => $categories,
        'adminCategories' => admin_categories(),
        'authors' => admin_authors(),
        'editors' => admin_editors(),
        'form' => $form,
        'errors' => $errors,
        'mode' => 'edit',
        'action' => url('admin/articles/' . $articleId . '/edit'),
        'articleId' => $articleId,
        'currentStatus' => (string) $article['status'],
        'checklist' => publish_checklist(array_merge($article, [
            'category_id' => $article['category_id'],
        ])),
        'seoChecklist' => seo_article_checklist($article),
        'auditLogs' => article_audit_logs($articleId),
        'flash' => (string) ($_GET['flash'] ?? ''),
        'warnings' => article_warnings($article),
        'riskCategories' => ai_risk_categories(),
        'claims' => article_claims($articleId),
        'claimTypes' => ai_claim_types(),
        'suggestedClaims' => extract_article_claims_locally($article),
    ]);
    exit;
}

if ($route === 'admin/social') {
    require_admin();
    $filters = [
        'channel' => (string) ($_GET['channel'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
    ];
    render_page('admin/social', [
        'site' => $site,
        'categories' => $categories,
        'posts' => social_posts_index($filters),
        'channels' => social_channels(),
        'statusOptions' => social_post_status_options(),
        'statusCounts' => social_status_counts(),
        'filters' => $filters,
        'flash' => (string) ($_GET['flash'] ?? ''),
    ]);
    exit;
}

if ($route === 'admin/social/schedule') {
    require_admin();
    $filters = [
        'scheduled' => (string) ($_GET['scheduled'] ?? 'today'),
        'channel' => (string) ($_GET['channel'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
    ];
    if (!array_key_exists($filters['scheduled'], social_schedule_segments())) {
        $filters['scheduled'] = 'today';
    }
    render_page('admin/social-schedule', [
        'site' => $site,
        'categories' => $categories,
        'posts' => social_scheduled_posts($filters),
        'summary' => social_schedule_summary(),
        'channels' => social_channels(),
        'statusOptions' => social_post_status_options(),
        'filters' => $filters,
    ]);
    exit;
}

if ($route === 'admin/social/schedule.csv') {
    require_admin();
    $filters = [
        'scheduled' => (string) ($_GET['scheduled'] ?? 'today'),
        'channel' => (string) ($_GET['channel'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
    ];
    export_social_schedule_csv($filters);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/social$#', $route, $matches)) {
    require_admin();
    $articleId = (int) $matches[1];
    $article = admin_article_by_id($articleId);
    if (!$article) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    render_page('admin/article-social', [
        'site' => $site,
        'categories' => $categories,
        'article' => $article,
        'posts' => social_posts_for_article($articleId),
        'channels' => social_channels(),
        'statusOptions' => social_post_status_options(),
        'flash' => (string) ($_GET['flash'] ?? ''),
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/social/([a-z_]+)/generate$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $channel = (string) $matches[2];
    $result = generate_social_caption($articleId, $channel);
    $flash = $result['ok'] ? ('已为 ' . $channel . ' 生成新文案。') : ($result['message'] ?? 'AI 生成失败。');
    header('Location: ' . url('admin/articles/' . $articleId . '/social') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/articles/(\d+)/social/([a-z_]+)/save$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $channel = (string) $matches[2];
    $result = save_social_post($articleId, $channel, $_POST);
    $flash = $result['ok'] ? '已保存。' : ($result['message'] ?? '保存失败。');
    header('Location: ' . url('admin/articles/' . $articleId . '/social') . '?flash=' . rawurlencode($flash) . '#ch-' . $channel);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/social/([a-z_]+)/status$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $channel = (string) $matches[2];
    $next = (string) ($_POST['status'] ?? '');
    $result = update_social_post_status($articleId, $channel, $next);
    $flash = $result['ok'] ? ('状态已更新为 ' . $next) : ($result['message'] ?? '更新失败。');
    header('Location: ' . url('admin/articles/' . $articleId . '/social') . '?flash=' . rawurlencode($flash) . '#ch-' . $channel);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/social/([a-z_]+)/delete$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $channel = (string) $matches[2];
    delete_social_post($articleId, $channel);
    header('Location: ' . url('admin/articles/' . $articleId . '/social') . '?flash=' . rawurlencode('已删除 ' . $channel . ' 的文案。'));
    exit;
}

if (preg_match('#^admin/articles/(\d+)/wechat-export$#', $route, $matches)) {
    require_admin();
    $articleId = (int) $matches[1];
    $article = admin_article_by_id($articleId);
    if (!$article) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    // Enrich with author + category for the export template.
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare("SELECT a.name AS author_name, c.name AS category_name
                FROM articles ar
                LEFT JOIN authors a ON a.id = ar.author_id
                LEFT JOIN categories c ON c.id = ar.category_id
                WHERE ar.id = :id LIMIT 1");
            $statement->execute(['id' => $articleId]);
            $extra = $statement->fetch();
            if ($extra) {
                $article = array_merge($article, $extra);
            }
        } catch (Throwable $exception) {
        }
    }
    render_page('admin/article-wechat-export', [
        'site' => $site,
        'categories' => $categories,
        'article' => $article,
        'wechatHtml' => render_wechat_article_html($article),
    ]);
    exit;
}

// ===== W6D4 share cards: admin preview + public SVG =====
if (preg_match('#^admin/articles/(\d+)/share-cards$#', $route, $matches)) {
    require_admin();
    $articleId = (int) $matches[1];
    $article = admin_article_by_id($articleId);
    if (!$article) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    ensure_share_image_column();
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $override = trim((string) ($_POST['social_image_path'] ?? ''));
        $pdo = db();
        if ($pdo instanceof PDO) {
            try {
                $pdo->prepare('UPDATE articles SET social_image_path = :p WHERE id = :id')
                    ->execute(['p' => $override !== '' ? $override : null, 'id' => $articleId]);
                $flash = '已保存社交图覆盖。';
                $article['social_image_path'] = $override;
            } catch (Throwable $exception) {
                $flash = '保存失败：' . $exception->getMessage();
            }
        }
    }
    // Provide category slug for palette
    $pdo = db();
    if ($pdo instanceof PDO && empty($article['category'])) {
        try {
            $cat = $pdo->prepare('SELECT slug, name FROM categories WHERE id = :id LIMIT 1');
            $cat->execute(['id' => (int) $article['category_id']]);
            $row = $cat->fetch();
            if ($row) {
                $article['category'] = $row['slug'];
                $article['category_name'] = $row['name'];
            }
        } catch (Throwable $exception) {
        }
    }
    render_page('admin/article-share-cards', [
        'site' => $site,
        'categories' => $categories,
        'article' => $article,
        'cardTypes' => share_card_types(),
        'shortFormat' => short_format_for_article($articleId) ?? [],
        'currentOgImage' => article_social_image_url($article),
        'flash' => $flash,
    ]);
    exit;
}

if (preg_match('#^share-card/([a-z0-9-]+)/([a-z]+)\.svg$#', $route, $matches)) {
    $slug = (string) $matches[1];
    $type = (string) $matches[2];
    if (!array_key_exists($type, share_card_types())) {
        $type = 'headline';
    }
    $article = find_article($slug);
    if ($article === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'not found';
        exit;
    }
    $sf = [];
    // find_article returns public shape; fetch id for short format
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $idStmt = $pdo->prepare('SELECT id FROM articles WHERE slug = :slug LIMIT 1');
            $idStmt->execute(['slug' => $slug]);
            $aid = (int) ($idStmt->fetchColumn() ?: 0);
            if ($aid > 0) {
                $sf = short_format_for_article($aid) ?? [];
            }
        } catch (Throwable $exception) {
        }
    }
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo render_share_card_svg($article, $type, $sf);
    exit;
}

// ===== W6D5 short format admin =====
if (preg_match('#^admin/articles/(\d+)/short-format$#', $route, $matches)) {
    require_admin();
    $articleId = (int) $matches[1];
    $article = admin_article_by_id($articleId);
    if (!$article) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_short_format($articleId, $_POST);
        $flash = $result['ok'] ? '已保存。' : ('保存失败：' . ($result['message'] ?? ''));
    }
    $sf = short_format_for_article($articleId);
    $form = $sf ? array_merge(short_format_form_defaults(), $sf) : short_format_form_defaults();
    if (empty($form['bullets'])) {
        $form['bullets'] = ['', '', ''];
    }
    render_page('admin/article-short-format', [
        'site' => $site,
        'categories' => $categories,
        'article' => $article,
        'form' => $form,
        'shortFormat' => $sf,
        'exportText' => $sf ? short_format_as_text($sf, $article) : '',
        'flash' => $flash,
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/short-format/generate$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $result = generate_short_format($articleId);
    $flash = $result['ok'] ? '已用 AI 生成 60 秒看懂。' : ($result['message'] ?? 'AI 生成失败。');
    header('Location: ' . url('admin/articles/' . $articleId . '/short-format') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/articles/(\d+)/short-format/delete$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    delete_short_format($articleId);
    header('Location: ' . url('admin/articles/' . $articleId . '/short-format') . '?flash=' . rawurlencode('已删除 60 秒看懂。'));
    exit;
}

// ===== W6D6 social analytics =====
if ($route === 'admin/social-analytics') {
    require_admin();
    render_page('admin/social-analytics', [
        'site' => $site,
        'categories' => $categories,
        'stats' => social_share_analytics(),
        'channels' => social_channels(),
    ]);
    exit;
}

if (preg_match('#^admin/articles/(\d+)/claims/add$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    save_article_claim($articleId, (string) ($_POST['claim_type'] ?? ''), (string) ($_POST['content'] ?? ''), (string) ($_POST['source_url'] ?? ''));
    header('Location: ' . url('admin/articles/' . $articleId . '/edit') . '#claims');
    exit;
}

if (preg_match('#^admin/articles/(\d+)/claims/(\d+)/(verify|dispute|delete)$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $articleId = (int) $matches[1];
    $claimId = (int) $matches[2];
    $action = $matches[3];
    if ($action === 'delete') {
        delete_article_claim($claimId);
    } else {
        update_article_claim_status($claimId, $action === 'verify' ? 'verified' : 'disputed');
    }
    header('Location: ' . url('admin/articles/' . $articleId . '/edit') . '#claims');
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
    ensure_ai_quality_columns();
    $statusFilter = (string) ($_GET['status'] ?? '');
    $sectionFilter = (string) ($_GET['section_slug'] ?? '');
    // The 'approved' tab also matches legacy 'accepted' rows.
    $statusForQuery = $statusFilter === 'approved' ? ['approved', 'accepted'] : $statusFilter;
    $drafts = ai_drafts([
        'status' => $statusForQuery,
        'section_slug' => $sectionFilter,
    ]);
    foreach ($drafts as &$d) {
        $d['_quality'] = ai_draft_quality_score($d);
        $d['_warnings'] = ai_draft_warnings($d);
    }
    unset($d);
    $statusCounts = ai_draft_status_counts(['section_slug' => $sectionFilter]);
    render_page('admin/ai-drafts', [
        'site' => $site,
        'categories' => $categories,
        'drafts' => $drafts,
        'templates' => editorial_bot_templates(),
        'statusOptions' => ai_draft_status_options(),
        'statusCounts' => $statusCounts,
        'totalCount' => array_sum($statusCounts),
        'filters' => [
            'status' => $statusFilter,
            'section_slug' => $sectionFilter,
        ],
        'aiProvider' => ai_provider_status(),
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if ($route === 'admin/ai-bots') {
    require_admin();
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['action'] ?? '') === 'reset') {
            $result = reset_ai_bot_profile((string) ($_POST['section_slug'] ?? ''));
        } else {
            $result = save_ai_bot_profile($_POST);
        }
        $flash = (string) ($result['message'] ?? ($result['ok'] ? 'Saved.' : 'Save failed.'));
    }
    render_page('admin/ai-bots', [
        'site' => $site,
        'categories' => $categories,
        'bots' => ai_bot_profiles(false),
        'defaults' => ai_bot_profile_defaults(),
        'flash' => $flash,
    ]);
    exit;
}

if ($route === 'admin/ai-intake') {
    require_admin();
    $form = ai_story_intake_defaults();
    $message = (string) ($_GET['message'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = generate_ai_story_brief($_POST);
        if ($result['ok']) {
            header('Location: ' . url('admin/ai-intake') . '?brief=' . (int) $result['id']);
            exit;
        }
        $message = (string) ($result['message'] ?? 'Brief generation failed.');
        $form = array_replace($form, $_POST);
    }
    $briefId = (int) ($_GET['brief'] ?? 0);
    render_page('admin/ai-intake', [
        'site' => $site,
        'categories' => $categories,
        'bots' => ai_bot_profiles(true),
        'form' => $form,
        'message' => $message,
        'selectedBrief' => $briefId > 0 ? ai_story_intake_by_id($briefId) : null,
        'briefs' => ai_story_intakes(20),
        'aiProvider' => ai_provider_status(),
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if ($route === 'admin/ai-drafts/new') {
    require_admin();
    $errors = [];
    $form = ai_draft_form_defaults();
    foreach (['section_slug', 'topic_angle', 'source_links', 'target_reader', 'urgency'] as $prefillKey) {
        if (isset($_GET[$prefillKey])) {
            $form[$prefillKey] = (string) $_GET[$prefillKey];
        }
    }
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
        'aiProvider' => ai_provider_status(),
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if ($route === 'admin/subscribers') {
    require_admin();
    $filters = [
        'q' => (string) ($_GET['q'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'source' => (string) ($_GET['source'] ?? ''),
    ];
    render_page('admin/subscribers', [
        'site' => $site,
        'categories' => $categories,
        'subscribers' => admin_subscribers($filters),
        'analytics' => subscriber_growth_analytics(),
        'filters' => $filters,
    ]);
    exit;
}

if ($route === 'admin/subscribers.csv') {
    require_admin();
    output_subscribers_csv([
        'q' => (string) ($_GET['q'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'source' => (string) ($_GET['source'] ?? ''),
    ]);
    exit;
}

if (preg_match('#^admin/ai-drafts/(\d+)/tone$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $draftId = (int) $matches[1];
    $preset = (string) ($_POST['preset'] ?? '');
    $presets = ai_tone_presets();
    if (!isset($presets[$preset])) {
        header('Location: ' . url('admin/ai-drafts/' . $draftId) . '?message=' . rawurlencode('未知语气预设。'));
        exit;
    }
    $result = ai_rewrite_section($draftId, 'body', $presets[$preset]['instruction']);
    $flash = $result['ok'] ? ('已套用「' . $presets[$preset]['label'] . '」语气。') : ($result['message'] ?? '改写失败。');
    header('Location: ' . url('admin/ai-drafts/' . $draftId) . '?message=' . rawurlencode($flash));
    exit;
}


if (preg_match('#^admin/ai-drafts/(\d+)$#', $route, $matches)) {
    require_admin();
    $draftId = (int) $matches[1];
    $draft = ai_draft_by_id($draftId);
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
        'rewriteTargets' => ai_rewrite_targets(),
        'tonePresets' => ai_tone_presets(),
        'factChecks' => ai_draft_checks($draftId),
        'versions' => ai_draft_versions($draftId),
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

if (preg_match('#^admin/ai-drafts/(\d+)/rewrite$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $draftId = (int) $matches[1];
    $result = ai_rewrite_section($draftId, (string) ($_POST['target'] ?? ''), (string) ($_POST['instruction'] ?? ''));
    $flash = $result['ok'] ? '已重写。' : ($result['message'] ?? '改写失败。');
    header('Location: ' . url('admin/ai-drafts/' . $draftId) . '?message=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/ai-drafts/(\d+)/check$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $draftId = (int) $matches[1];
    update_ai_draft_check($draftId, (string) ($_POST['key'] ?? ''), !empty($_POST['passed']));
    header('Location: ' . url('admin/ai-drafts/' . $draftId));
    exit;
}

if (preg_match('#^admin/ai-drafts/(\d+)/restore/(\d+)$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $draftId = (int) $matches[1];
    $versionId = (int) $matches[2];
    $result = restore_ai_draft_version($versionId);
    $flash = $result['ok'] ? '已恢复到该版本。' : ($result['message'] ?? '恢复失败。');
    header('Location: ' . url('admin/ai-drafts/' . $draftId) . '?message=' . rawurlencode($flash));
    exit;
}

if ($route === 'admin/ai-templates') {
    require_admin();
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['action'] ?? '') === 'reset') {
            reset_ai_task_template((string) ($_POST['task_key'] ?? ''));
            $flash = '已重置为默认提示词。';
        } else {
            $result = save_ai_task_template($_POST);
            $flash = $result['ok'] ? '已保存。' : implode(' ', $result['errors']);
        }
    }

    render_page('admin/ai-templates', [
        'site' => $site,
        'categories' => $categories,
        'templates' => ai_task_templates(),
        'defaults' => ai_task_template_defaults(),
        'flash' => $flash,
    ]);
    exit;
}

if ($route === 'admin/newsletter') {
    require_admin();
    render_page('admin/newsletter-issues', [
        'site' => $site,
        'categories' => $categories,
        'issues' => newsletter_issues([]),
        'providerStatus' => email_provider_status(),
        'flash' => (string) ($_GET['flash'] ?? ''),
    ]);
    exit;
}

if ($route === 'admin/newsletter/schedule') {
    require_admin();
    $filters = [
        'scheduled' => (string) ($_GET['scheduled'] ?? 'today'),
        'status' => (string) ($_GET['status'] ?? ''),
    ];
    if (!array_key_exists($filters['scheduled'], newsletter_schedule_segments())) {
        $filters['scheduled'] = 'today';
    }
    $issues = scheduled_newsletter_queue($filters);
    foreach ($issues as &$issue) {
        $issue['articles'] = newsletter_issue_articles((int) $issue['id']);
        $issue['checklist'] = newsletter_presend_checklist($issue);
    }
    unset($issue);
    render_page('admin/newsletter-schedule', [
        'site' => $site,
        'categories' => $categories,
        'issues' => $issues,
        'summary' => newsletter_schedule_summary(),
        'segments' => newsletter_schedule_segments(),
        'filters' => $filters,
        'providerStatus' => email_provider_status(),
    ]);
    exit;
}

if ($route === 'admin/newsletter/new') {
    require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_newsletter_issue($_POST);
        if ($result['ok']) {
            header('Location: ' . url('admin/newsletter/' . $result['id'] . '/edit') . '?flash=' . rawurlencode('新建期号已创建。'));
            exit;
        }
        render_page('admin/newsletter-issue-form', [
            'site' => $site,
            'categories' => $categories,
            'issue' => null,
            'form' => array_replace(newsletter_issue_form_defaults(), $_POST),
            'errors' => $result['errors'] ?? [],
            'mode' => 'create',
            'action' => url('admin/newsletter/new'),
            'availableArticles' => [],
            'providerStatus' => email_provider_status(),
            'sends' => [],
            'checklist' => [],
        ]);
        exit;
    }
    render_page('admin/newsletter-issue-form', [
        'site' => $site,
        'categories' => $categories,
        'issue' => null,
        'form' => newsletter_issue_form_defaults(),
        'errors' => [],
        'mode' => 'create',
        'action' => url('admin/newsletter/new'),
        'availableArticles' => [],
        'providerStatus' => email_provider_status(),
        'sends' => [],
        'checklist' => [],
    ]);
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/edit$#', $route, $matches)) {
    require_admin();
    $issueId = (int) $matches[1];
    $issue = newsletter_issue_by_id($issueId);
    if (!$issue) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    $errors = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_newsletter_issue($_POST, $issueId);
        if ($result['ok']) {
            header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode('已保存。'));
            exit;
        }
        $errors = $result['errors'] ?? [];
    }
    $form = [
        'subject' => (string) $issue['subject'],
        'intro' => (string) ($issue['intro'] ?? ''),
        'outro' => (string) ($issue['outro'] ?? ''),
        'scheduled_at' => !empty($issue['scheduled_at']) ? date('Y-m-d\TH:i', strtotime((string) $issue['scheduled_at'])) : '',
    ];
    render_page('admin/newsletter-issue-form', [
        'site' => $site,
        'categories' => $categories,
        'issue' => $issue,
        'form' => $form,
        'errors' => $errors,
        'mode' => 'edit',
        'action' => url('admin/newsletter/' . $issueId . '/edit'),
        'availableArticles' => publishable_articles_for_issue($issueId),
        'providerStatus' => email_provider_status(),
        'sends' => newsletter_issue_sends($issueId),
        'checklist' => newsletter_presend_checklist($issue),
        'flash' => (string) ($_GET['flash'] ?? ''),
    ]);
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/articles/add$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    add_article_to_issue($issueId, (int) ($_POST['article_id'] ?? 0), (string) ($_POST['blurb'] ?? ''));
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit'));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/articles/(\d+)/remove$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    remove_article_from_issue($issueId, (int) $matches[2]);
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit'));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/preview$#', $route, $matches)) {
    require_admin();
    $issue = newsletter_issue_by_id((int) $matches[1]);
    if (!$issue) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow', false);
    echo render_newsletter_issue_html($issue);
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/ai-intro$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    $result = generate_newsletter_intro($issueId);
    $flash = $result['ok'] ? '已生成开场白。' : ($result['message'] ?? 'AI 生成失败。');
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/ai-blurbs$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    $result = generate_newsletter_blurbs($issueId);
    $flash = $result['ok'] ? '已生成每篇文章的推荐语。' : ($result['message'] ?? 'AI 生成失败。');
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/ai-theme$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    $theme = (string) ($_POST['theme'] ?? '');
    $result = generate_themed_block($issueId, $theme);
    if ($result['ok']) {
        // Append the generated content to the issue's intro field (or you could store per-theme).
        $pdo = db();
        if ($pdo instanceof PDO) {
            try {
                $issue = newsletter_issue_by_id($issueId);
                $append = "\n\n【" . ($result['label'] ?? '主题') . "】\n" . $result['content'];
                $newIntro = trim((string) ($issue['intro'] ?? '')) . $append;
                $pdo->prepare('UPDATE newsletter_issues SET intro = :intro WHERE id = :id')
                    ->execute(['intro' => $newIntro, 'id' => $issueId]);
            } catch (Throwable $exception) {
            }
        }
    }
    $flash = $result['ok'] ? ('已追加「' . ($result['label'] ?? '') . '」段落到开场白。') : ($result['message'] ?? 'AI 生成失败。');
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/test$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    $result = send_newsletter_test($issueId, (string) ($_POST['test_email'] ?? ''));
    $flash = $result['ok'] ? '测试邮件已发送/记录。' : ('测试发送失败：' . ($result['message'] ?? ''));
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/send$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    if (!can_publish_article()) {
        http_response_code(403);
        render_page('403', compact('site', 'categories'));
        exit;
    }
    $issueId = (int) $matches[1];
    $result = send_newsletter_broadcast($issueId);
    if ($result['ok']) {
        $flash = sprintf('广播完成：收件人 %d，成功 %d，失败 %d。', $result['recipients'], $result['sent'], $result['failed']);
    } else {
        $flash = '发送失败：' . ($result['message'] ?? '');
    }
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode($flash));
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/status$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $issueId = (int) $matches[1];
    $next = (string) ($_POST['status'] ?? '');
    $result = transition_newsletter_status($issueId, $next);
    $flash = $result['ok'] ? '状态已更新为 ' . $next . '。' : ('切换失败：' . ($result['message'] ?? ''));
    header('Location: ' . url('admin/newsletter/' . $issueId . '/edit') . '?flash=' . rawurlencode($flash));
    exit;
}

if ($route === 'admin/oauth') {
    require_admin();
    render_page('admin/oauth', [
        'site' => $site,
        'categories' => $categories,
        'providers' => oauth_provider_status(),
    ]);
    exit;
}

if (preg_match('#^admin/newsletter/(\d+)/delete$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    if (!can_delete_article()) {
        http_response_code(403);
        render_page('403', compact('site', 'categories'));
        exit;
    }
    delete_newsletter_issue((int) $matches[1]);
    header('Location: ' . url('admin/newsletter') . '?flash=' . rawurlencode('已删除。'));
    exit;
}

if ($route === 'admin/sources') {
    require_admin();
    $filters = [
        'section_slug' => (string) ($_GET['section_slug'] ?? ''),
        'credibility' => (string) ($_GET['credibility'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
    ];
    $flash = '';
    $errors = [];
    $form = ['id' => '', 'name' => '', 'url' => '', 'section_slug' => '', 'credibility' => 'standard', 'notes' => ''];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['action'] ?? '') === 'delete') {
            delete_source_profile((int) ($_POST['id'] ?? 0));
            $flash = '已删除来源。';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $result = save_source_profile($_POST, $id > 0 ? $id : null);
            if ($result['ok']) {
                $flash = '已保存来源。';
            } else {
                $errors = $result['errors'] ?? [];
                $form = array_replace($form, $_POST);
            }
        }
    }
    render_page('admin/sources', [
        'site' => $site,
        'categories' => $categories,
        'sources' => source_profiles($filters),
        'filters' => $filters,
        'flash' => $flash,
        'errors' => $errors,
        'form' => $form,
        'credibilityOptions' => source_credibility_options(),
    ]);
    exit;
}

if ($route === 'admin/source-templates') {
    require_admin();
    $flash = '';
    $errors = [];
    $form = ['id' => '', 'section_slug' => '', 'name' => '', 'topic_angle' => '', 'source_links' => ''];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['action'] ?? '') === 'delete') {
            delete_source_template((int) ($_POST['id'] ?? 0));
            $flash = '已删除模板。';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $result = save_source_template($_POST, $id > 0 ? $id : null);
            if ($result['ok']) {
                $flash = '已保存模板。';
            } else {
                $errors = $result['errors'] ?? [];
                $form = array_replace($form, $_POST);
            }
        }
    }
    render_page('admin/source-templates', [
        'site' => $site,
        'categories' => $categories,
        'templates' => source_templates([]),
        'flash' => $flash,
        'errors' => $errors,
        'form' => $form,
        'botSections' => editorial_bot_templates(),
    ]);
    exit;
}

if ($route === 'admin/research-desk') {
    require_admin();
    $filters = [
        'section_slug' => (string) ($_GET['section_slug'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
    ];
    render_page('admin/research-desk', [
        'site' => $site,
        'categories' => $categories,
        'briefs' => research_briefs($filters),
        'filters' => $filters,
        'botSections' => editorial_bot_templates(),
        'flash' => (string) ($_GET['flash'] ?? ''),
    ]);
    exit;
}

if ($route === 'admin/research-desk/new') {
    require_admin();
    $errors = [];
    $form = [
        'section_slug' => (string) ($_GET['section_slug'] ?? 'markets'),
        'topic_angle' => '',
        'source_links' => '',
    ];
    if (!empty($_GET['template_id'])) {
        $template = source_template_by_id((int) $_GET['template_id']);
        if ($template) {
            $form['section_slug'] = (string) $template['section_slug'];
            $form['topic_angle'] = (string) ($template['topic_angle'] ?? '');
            $form['source_links'] = (string) ($template['source_links'] ?? '');
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = generate_research_brief($_POST);
        if ($result['ok']) {
            header('Location: ' . url('admin/research-desk/' . $result['id']));
            exit;
        }
        $errors = $result['errors'] ?? [];
        $form = array_replace($form, $_POST);
    }
    render_page('admin/research-desk-form', [
        'site' => $site,
        'categories' => $categories,
        'form' => $form,
        'errors' => $errors,
        'botSections' => editorial_bot_templates(),
        'templates' => source_templates([]),
        'aiProvider' => ai_provider_status(),
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if (preg_match('#^admin/research-desk/(\d+)$#', $route, $matches)) {
    require_admin();
    $brief = research_brief_by_id((int) $matches[1]);
    if (!$brief) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    render_page('admin/research-brief-detail', [
        'site' => $site,
        'categories' => $categories,
        'brief' => $brief,
        'botSections' => editorial_bot_templates(),
        'flash' => (string) ($_GET['flash'] ?? ''),
    ]);
    exit;
}

if (preg_match('#^admin/research-desk/(\d+)/use$#', $route, $matches) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $brief = research_brief_by_id((int) $matches[1]);
    if (!$brief) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    mark_research_brief_used((int) $matches[1]);
    $payload = $brief['brief_payload'];
    $angle = (string) $brief['topic_angle'];
    $extras = "\n\n[Research brief #" . (int) $brief['id'] . "]\n";
    if (!empty($payload['key_facts'])) {
        $extras .= "Key facts:\n- " . implode("\n- ", (array) $payload['key_facts']) . "\n";
    }
    if (!empty($payload['angles'])) {
        $extras .= "Angles:\n- " . implode("\n- ", (array) $payload['angles']) . "\n";
    }
    $angleWithExtras = trim($angle . $extras);
    $query = http_build_query([
        'topic_angle' => $angleWithExtras,
        'source_links' => implode("\n", (array) $brief['source_links']),
        'section_slug' => $brief['section_slug'],
        'brief_id' => (int) $brief['id'],
    ]);
    header('Location: ' . url('admin/ai-drafts/new') . '?' . $query);
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

// ===== Reader account routes =====
if ($route === 'account/signup') {
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = reader_signup(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['display_name'] ?? '')
        );
        if ($result['ok']) {
            header('Location: ' . url('account/referral'));
            exit;
        }
        $error = $result['message'];
    }
    render_page('account/signup', [
        'site' => $site,
        'categories' => $categories,
        'error' => $error,
        'oauth' => oauth_provider_status(),
    ]);
    exit;
}

if ($route === 'account/login') {
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = reader_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($result['ok']) {
            header('Location: ' . url('account'));
            exit;
        }
        $error = $result['message'];
    }
    render_page('account/login', [
        'site' => $site,
        'categories' => $categories,
        'error' => $error,
        'oauth' => oauth_provider_status(),
    ]);
    exit;
}

if ($route === 'account/logout') {
    reader_logout();
    header('Location: ' . url());
    exit;
}

if ($route === 'account/profile') {
    require_reader();
    $reader = reader_session();
    $flash = '';
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = update_reader_profile((int) $reader['id'], $_POST);
        if ($result['ok']) {
            $flash = '资料已更新。';
            $reader = reader_session();
        } else {
            $error = $result['message'];
        }
    }
    render_page('account/profile', [
        'site' => $site,
        'categories' => $categories,
        'reader' => $reader,
        'flash' => $flash,
        'error' => $error,
    ]);
    exit;
}

if (preg_match('#^account/oauth/([a-z]+)$#', $route, $matches)) {
    $result = oauth_initiate((string) $matches[1]);
    if ($result['ok'] && !empty($result['redirect_url'])) {
        header('Location: ' . $result['redirect_url']);
        exit;
    }
    $msg = $result['message'] ?? '该登录方式暂不可用。';
    header('Location: ' . url('account/login') . '?error=' . rawurlencode($msg));
    exit;
}

if (preg_match('#^account/oauth/([a-z]+)/callback$#', $route, $matches)) {
    $result = oauth_handle_callback((string) $matches[1], $_GET);
    if ($result['ok']) {
        header('Location: ' . url('account'));
        exit;
    }
    $msg = $result['message'] ?? '回调暂未实现。';
    header('Location: ' . url('account/login') . '?error=' . rawurlencode($msg));
    exit;
}

if ($route === 'account') {
    require_reader();
    $reader = reader_session();
    render_page('account/dashboard', [
        'site' => $site,
        'categories' => $categories,
        'reader' => $reader,
        'account' => reader_account_data((int) $reader['id']),
        'referral' => reader_referral_data((int) $reader['id']),
        'savedCount' => count(reader_saved_articles((int) $reader['id'])),
        'recentArticles' => reader_recent_articles((int) $reader['id'], 4),
    ]);
    exit;
}

if ($route === 'account/saved') {
    require_reader();
    $reader = reader_session();
    render_page('account/saved', [
        'site' => $site,
        'categories' => $categories,
        'reader' => $reader,
        'articles' => reader_saved_articles((int) $reader['id']),
        'recentArticles' => reader_recent_articles((int) $reader['id'], 8),
    ]);
    exit;
}

if ($route === 'account/bookmarks/toggle' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_reader();
    $reader = reader_session();
    $slug = (string) ($_POST['slug'] ?? '');
    $result = toggle_saved_article((int) $reader['id'], $slug);
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $return = (string) ($_POST['return'] ?? ('article/' . $slug));
    header('Location: ' . url($return));
    exit;
}

if ($route === 'account/preferences') {
    require_reader();
    $reader = reader_session();
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = save_reader_preferences(
            (int) $reader['id'],
            (array) ($_POST['topics'] ?? []),
            (string) ($_POST['digest_frequency'] ?? 'daily')
        );
        $flash = $result['ok'] ? '已保存偏好。' : ('保存失败：' . ($result['message'] ?? ''));
    }
    render_page('account/preferences', [
        'site' => $site,
        'categories' => $categories,
        'reader' => $reader,
        'account' => reader_account_data((int) $reader['id']),
        'flash' => $flash,
    ]);
    exit;
}

if ($route === 'account/unsubscribe') {
    require_reader();
    $reader = reader_session();
    $flash = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['action'] ?? '') === 'resubscribe') {
            resubscribe_reader((int) $reader['id']);
            $flash = '已重新订阅。';
        } else {
            unsubscribe_reader((int) $reader['id']);
            $flash = '已退订邮件订阅。可以随时回来重新订阅。';
        }
    }
    render_page('account/unsubscribe', [
        'site' => $site,
        'categories' => $categories,
        'reader' => $reader,
        'account' => reader_account_data((int) $reader['id']),
        'flash' => $flash,
    ]);
    exit;
}

if ($route === 'account/referral') {
    require_reader();
    $reader = reader_session();
    render_page('account/referral', [
        'site' => $site,
        'categories' => $categories,
        'reader' => $reader,
        'referral' => reader_referral_data((int) $reader['id']),
    ]);
    exit;
}

// ===== Public newsletter archive =====
if ($route === 'newsletter') {
    render_page('newsletter-archive', [
        'site' => $site,
        'categories' => $categories,
        'issues' => public_newsletter_archive(30),
    ]);
    exit;
}

if (strpos($route, 'newsletter/') === 0) {
    $slug = basename($route);
    $issue = public_newsletter_issue($slug);
    if (!$issue) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    render_page('newsletter-issue', [
        'site' => $site,
        'categories' => $categories,
        'issue' => $issue,
    ]);
    exit;
}

// ===== Signed unsubscribe link from emails =====
if ($route === 'unsubscribe') {
    $token = (string) ($_GET['token'] ?? '');
    $result = $token !== '' ? unsubscribe_email_by_token($token) : ['ok' => false, 'message' => '缺少退订令牌。'];
    render_page('unsubscribe-result', [
        'site' => $site,
        'categories' => $categories,
        'result' => $result,
    ]);
    exit;
}

// ===== Tags / topic pages =====
if (strpos($route, 'tag/') === 0) {
    $slug = basename($route);
    $tag = find_tag($slug);
    if ($tag === null) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    render_page('tag', [
        'site' => $site,
        'categories' => $categories,
        'tag' => $tag,
        'articles' => articles_by_tag($slug),
    ]);
    exit;
}

if ($route === 'topics') {
    render_page('topics', [
        'site' => $site,
        'categories' => $categories,
        'tags' => all_tags(),
    ]);
    exit;
}

if ($route === 'search') {
    $query = search_query((string) ($_GET['q'] ?? ''));
    $results = $query !== '' ? search_articles($query, 40) : [];
    if ($query !== '') {
        record_search_query($query, count($results));
    }
    render_page('search', [
        'site' => $site,
        'categories' => $categories,
        'query' => $query,
        'results' => $results,
        'fallbackArticles' => array_slice($articles, 0, 8),
        'popularTags' => array_slice(all_tags(), 0, 10),
    ]);
    exit;
}

// ===== W3D7 admin diagnostics, exports, smoke, audit, QA =====
if ($route === 'admin/diagnostics') {
    require_admin();
    render_page('admin/diagnostics', [
        'site' => $site,
        'categories' => $categories,
        'diagnostics' => database_diagnostics(),
        'errorLog' => diagnostics_error_log_tail(80),
        'aiUsage' => ai_usage_summary(),
    ]);
    exit;
}

if ($route === 'admin/smoke') {
    require_admin();
    if (($_GET['format'] ?? '') === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['checks' => admin_smoke_checks()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    render_page('admin/smoke', [
        'site' => $site,
        'categories' => $categories,
        'checks' => admin_smoke_checks(),
    ]);
    exit;
}

if ($route === 'admin/exports') {
    require_admin();
    render_page('admin/exports', [
        'site' => $site,
        'categories' => $categories,
    ]);
    exit;
}

if (preg_match('#^admin/exports/([a-z_]+)\.csv$#', $route, $matches)) {
    require_admin();
    if (!diagnostics_export_csv($matches[1])) {
        http_response_code(404);
        render_page('404', compact('site', 'categories'));
        exit;
    }
    exit;
}

if ($route === 'admin/audit') {
    require_admin();
    $pdo = db();
    $entries = [];
    if ($pdo instanceof PDO) {
        try {
            $statement = $pdo->query('SELECT l.id, l.article_id, l.action, l.from_status, l.to_status, l.note, l.created_at,
                    a.title, a.slug
                FROM article_audit_logs l
                LEFT JOIN articles a ON a.id = l.article_id
                ORDER BY l.created_at DESC LIMIT 200');
            $entries = $statement->fetchAll() ?: [];
        } catch (Throwable $exception) {
        }
    }
    render_page('admin/audit', [
        'site' => $site,
        'categories' => $categories,
        'entries' => $entries,
    ]);
    exit;
}

if ($route === 'admin/qa-checklist') {
    require_admin();
    render_page('admin/qa-checklist', [
        'site' => $site,
        'categories' => $categories,
        'items' => week_three_qa_checklist(),
    ]);
    exit;
}

if ($route === 'home') {
    render_page('home', [
        'site' => $site,
        'categories' => $categories,
        'articles' => $articles,
        'featured' => $featured,
        'mostRead' => most_read_articles(5, 7),
        'popularTags' => array_slice(all_tags(), 0, 12),
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

    record_event('article_view', ['slug' => $slug, 'path' => 'article/' . $slug]);
    $reader = reader_session();
    if (is_array($reader)) {
        record_recent_read((int) $reader['id'], $slug);
    }
    $fallbackRelated = related_articles($article);

    // Resolve article id for short-format + social image.
    $articleDbId = 0;
    $pdoArticle = db();
    if ($pdoArticle instanceof PDO) {
        try {
            $idStmt = $pdoArticle->prepare('SELECT id, social_image_path FROM articles WHERE slug = :slug LIMIT 1');
            $idStmt->execute(['slug' => $slug]);
            if ($idRow = $idStmt->fetch()) {
                $articleDbId = (int) $idRow['id'];
                $article['social_image_path'] = $idRow['social_image_path'] ?? '';
            }
        } catch (Throwable $exception) {
        }
    }
    $shortFormat = $articleDbId > 0 ? short_format_for_article($articleDbId) : null;

    render_page('article', [
        'site' => $site,
        'categories' => $categories,
        'article' => $article,
        'related' => personalized_related_articles($article, $fallbackRelated, is_array($reader) ? (int) $reader['id'] : null),
        'tags' => article_tags_by_slug($slug),
        'reader' => $reader,
        'isSaved' => is_array($reader) ? reader_has_saved_article((int) $reader['id'], (int) ($article['id'] ?? 0)) : false,
        'newsletterCta' => newsletter_cta_for_category((string) ($article['category'] ?? '')),
        'monetization' => monetization_settings(),
        'shortFormat' => $shortFormat,
        'socialImage' => function_exists('article_social_image_url') ? article_social_image_url($article) : ($article['hero_image'] ?? ''),
    ]);
    exit;
}

http_response_code(404);
render_page('404', compact('site', 'categories'));
