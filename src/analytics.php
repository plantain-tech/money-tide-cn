<?php

declare(strict_types=1);

function ensure_analytics_table(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(40) NOT NULL,
            path VARCHAR(255) NOT NULL DEFAULT '',
            slug VARCHAR(180) NULL,
            source VARCHAR(120) NULL,
            referrer VARCHAR(500) NULL,
            ua_hash CHAR(16) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_events_type_time (event_type, created_at),
            INDEX idx_events_slug (slug, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function record_event(string $type, array $context = []): void
{
    static $ensured = false;
    if (!$ensured) {
        ensure_analytics_table();
        $ensured = true;
    }

    if (analytics_should_skip()) {
        return;
    }

    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    $path = (string) ($context['path'] ?? trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/'));
    $slug = isset($context['slug']) ? substr((string) $context['slug'], 0, 180) : null;
    $source = isset($context['source']) ? substr((string) $context['source'], 0, 120) : null;
    $referrer = isset($context['referrer'])
        ? substr((string) $context['referrer'], 0, 500)
        : substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    $uaHash = substr(md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Y-m-d')), 0, 16);

    try {
        $statement = $pdo->prepare('INSERT INTO analytics_events (event_type, path, slug, source, referrer, ua_hash)
            VALUES (:type, :path, :slug, :source, :referrer, :ua_hash)');
        $statement->execute([
            'type' => substr($type, 0, 40),
            'path' => substr($path, 0, 255),
            'slug' => $slug,
            'source' => $source,
            'referrer' => $referrer ?: null,
            'ua_hash' => $uaHash,
        ]);
    } catch (Throwable $exception) {
    }
}

function analytics_should_skip(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return true;
    }
    if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|uptime|pingdom|gtmetrix|lighthouse|headless/i', $ua)) {
        return true;
    }
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    if (strpos($path, '/admin') === 0) {
        return true;
    }
    return false;
}

function analytics_summary(): array
{
    ensure_analytics_table();
    $pdo = db();
    $defaults = [
        'views' => ['today' => 0, 'last_7d' => 0, 'last_30d' => 0, 'total' => 0],
        'subscribes' => ['today' => 0, 'last_7d' => 0, 'last_30d' => 0, 'total' => 0],
        'top_articles_7d' => [],
        'top_sources_30d' => [],
        'top_referrers_7d' => [],
        'views_by_day' => [],
        'retention' => function_exists('retention_analytics') ? retention_analytics() : [],
    ];
    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    try {
        $defaults['views'] = [
            'today' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'article_view' AND created_at >= CURDATE()")->fetchColumn(),
            'last_7d' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'article_view' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'last_30d' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'article_view' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            'total' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'article_view'")->fetchColumn(),
        ];
        $defaults['subscribes'] = [
            'today' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'subscribe' AND created_at >= CURDATE()")->fetchColumn(),
            'last_7d' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'subscribe' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'last_30d' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'subscribe' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            'total' => (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'subscribe'")->fetchColumn(),
        ];

        $topArticles = $pdo->query("SELECT e.slug, COUNT(*) AS views, a.title
            FROM analytics_events e
            LEFT JOIN articles a ON a.slug = e.slug
            WHERE e.event_type = 'article_view' AND e.slug IS NOT NULL
              AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY e.slug, a.title
            ORDER BY views DESC, e.slug ASC
            LIMIT 10")->fetchAll();
        $defaults['top_articles_7d'] = $topArticles ?: [];

        $topSources = $pdo->query("SELECT COALESCE(NULLIF(source, ''), 'unknown') AS source, COUNT(*) AS total
            FROM analytics_events
            WHERE event_type = 'subscribe' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY source
            ORDER BY total DESC
            LIMIT 8")->fetchAll();
        $defaults['top_sources_30d'] = $topSources ?: [];

        $topReferrers = $pdo->query("SELECT COALESCE(NULLIF(referrer, ''), 'direct') AS referrer, COUNT(*) AS total
            FROM analytics_events
            WHERE event_type = 'article_view' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY referrer
            ORDER BY total DESC
            LIMIT 8")->fetchAll();
        $defaults['top_referrers_7d'] = $topReferrers ?: [];

        $byDay = $pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM analytics_events
            WHERE event_type = 'article_view' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
            GROUP BY day
            ORDER BY day ASC")->fetchAll();
        $defaults['views_by_day'] = $byDay ?: [];
    } catch (Throwable $exception) {
    }

    return $defaults;
}

function record_public_event_from_request(array $input): array
{
    $type = (string) ($input['event_type'] ?? '');
    $allowed = ['share_copy', 'article_complete', 'article_share', 'newsletter_cta'];
    if (!in_array($type, $allowed, true)) {
        return ['ok' => false, 'message' => 'Unsupported event.'];
    }

    record_event($type, [
        'slug' => (string) ($input['slug'] ?? ''),
        'source' => (string) ($input['source'] ?? 'public'),
        'path' => (string) ($input['path'] ?? ''),
    ]);
    return ['ok' => true];
}

function external_analytics_status(): array
{
    return [
        'ga_id' => (string) app_config('analytics.ga_measurement_id', ''),
        'plausible_domain' => (string) app_config('analytics.plausible_domain', ''),
    ];
}
