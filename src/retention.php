<?php

declare(strict_types=1);

function ensure_retention_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reader_saved_articles (
            user_id INT UNSIGNED NOT NULL,
            article_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, article_id),
            INDEX idx_saved_article (article_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS reader_recent_reads (
            user_id INT UNSIGNED NOT NULL,
            article_id INT UNSIGNED NOT NULL,
            read_count INT UNSIGNED NOT NULL DEFAULT 1,
            last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, article_id),
            INDEX idx_recent_user_time (user_id, last_read_at),
            INDEX idx_recent_article_time (article_id, last_read_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function article_id_by_slug(string $slug): int
{
    $pdo = db();
    if (!$pdo instanceof PDO || $slug === '') {
        return 0;
    }
    try {
        $statement = $pdo->prepare("SELECT id FROM articles WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        return (int) ($statement->fetchColumn() ?: 0);
    } catch (Throwable $exception) {
        return 0;
    }
}

function reader_has_saved_article(int $userId, int $articleId): bool
{
    ensure_retention_schema();
    $pdo = db();
    if (!$pdo instanceof PDO || $userId <= 0 || $articleId <= 0) {
        return false;
    }
    try {
        $statement = $pdo->prepare('SELECT 1 FROM reader_saved_articles WHERE user_id = :user_id AND article_id = :article_id LIMIT 1');
        $statement->execute(['user_id' => $userId, 'article_id' => $articleId]);
        return (bool) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return false;
    }
}

function toggle_saved_article(int $userId, string $slug): array
{
    ensure_retention_schema();
    $pdo = db();
    $articleId = article_id_by_slug($slug);
    if (!$pdo instanceof PDO || $userId <= 0 || $articleId <= 0) {
        return ['ok' => false, 'saved' => false, 'message' => 'Article unavailable.'];
    }

    try {
        if (reader_has_saved_article($userId, $articleId)) {
            $pdo->prepare('DELETE FROM reader_saved_articles WHERE user_id = :user_id AND article_id = :article_id')
                ->execute(['user_id' => $userId, 'article_id' => $articleId]);
            record_event('article_unsave', ['slug' => $slug, 'source' => 'reader']);
            return ['ok' => true, 'saved' => false, 'message' => 'Removed from saved articles.'];
        }

        $pdo->prepare('INSERT IGNORE INTO reader_saved_articles (user_id, article_id) VALUES (:user_id, :article_id)')
            ->execute(['user_id' => $userId, 'article_id' => $articleId]);
        record_event('article_save', ['slug' => $slug, 'source' => 'reader']);
        return ['ok' => true, 'saved' => true, 'message' => 'Saved for later.'];
    } catch (Throwable $exception) {
        return ['ok' => false, 'saved' => false, 'message' => 'Save failed.'];
    }
}

function record_recent_read(int $userId, string $slug): void
{
    ensure_retention_schema();
    $pdo = db();
    $articleId = article_id_by_slug($slug);
    if (!$pdo instanceof PDO || $userId <= 0 || $articleId <= 0) {
        return;
    }

    try {
        $pdo->prepare('INSERT INTO reader_recent_reads (user_id, article_id, read_count)
            VALUES (:user_id, :article_id, 1)
            ON DUPLICATE KEY UPDATE read_count = read_count + 1, last_read_at = CURRENT_TIMESTAMP')
            ->execute(['user_id' => $userId, 'article_id' => $articleId]);
    } catch (Throwable $exception) {
    }
}

function retention_article_select(): string
{
    return "SELECT a.id, a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
            a.read_time_minutes, a.published_at, a.updated_at, a.seo_title, a.seo_description,
            a.hero_image_path, a.hero_image_alt, a.is_premium, a.premium_excerpt,
            c.slug AS category, c.name AS category_name, au.name AS author_name
        FROM articles a
        INNER JOIN categories c ON c.id = a.category_id
        LEFT JOIN authors au ON au.id = a.author_id";
}

function reader_saved_articles(int $userId): array
{
    ensure_retention_schema();
    if (function_exists('ensure_monetization_schema')) {
        ensure_monetization_schema();
    }
    $pdo = db();
    if (!$pdo instanceof PDO || $userId <= 0) {
        return [];
    }

    try {
        $statement = $pdo->prepare(retention_article_select() . "
            INNER JOIN reader_saved_articles s ON s.article_id = a.id
            WHERE s.user_id = :user_id AND a.status = 'published'
            ORDER BY s.created_at DESC
            LIMIT 50");
        $statement->execute(['user_id' => $userId]);
        return array_map('map_article_row', $statement->fetchAll() ?: []);
    } catch (Throwable $exception) {
        return [];
    }
}

function reader_recent_articles(int $userId, int $limit = 8): array
{
    ensure_retention_schema();
    if (function_exists('ensure_monetization_schema')) {
        ensure_monetization_schema();
    }
    $pdo = db();
    if (!$pdo instanceof PDO || $userId <= 0) {
        return [];
    }

    try {
        $statement = $pdo->prepare(retention_article_select() . "
            INNER JOIN reader_recent_reads r ON r.article_id = a.id
            WHERE r.user_id = :user_id AND a.status = 'published'
            ORDER BY r.last_read_at DESC
            LIMIT " . max(1, min(30, $limit)));
        $statement->execute(['user_id' => $userId]);
        return array_map('map_article_row', $statement->fetchAll() ?: []);
    } catch (Throwable $exception) {
        return [];
    }
}

function personalized_related_articles(array $article, array $fallback, ?int $userId = null): array
{
    $seen = [(string) ($article['slug'] ?? '') => true];
    $out = [];
    $add = static function (array $items) use (&$out, &$seen): void {
        foreach ($items as $item) {
            $slug = (string) ($item['slug'] ?? '');
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $out[] = $item;
        }
    };

    if ($userId !== null && $userId > 0) {
        $account = reader_account_data($userId);
        foreach ((array) ($account['topics'] ?? []) as $topic) {
            $add(filter_articles_by_category((string) $topic));
        }
        $add(reader_recent_articles($userId, 6));
        $add(reader_saved_articles($userId));
    }

    $add($fallback);
    return array_slice($out, 0, 6);
}

function newsletter_cta_for_category(string $categorySlug): array
{
    $map = [
        'markets' => ['label' => '市场早报', 'title' => '每天开盘前，收到全球市场主线。', 'copy' => '利率、股市、汇率和大宗商品，用 5 分钟整理成中文信号。'],
        'business' => ['label' => '商业简报', 'title' => '把公司新闻变成可理解的商业脉络。', 'copy' => '财报、并购、消费品牌和商业模式，一封邮件快速跟上。'],
        'tech' => ['label' => '科技雷达', 'title' => 'AI、芯片和平台公司的变化，不错过。', 'copy' => '从产品发布到资本开支，追踪科技如何影响市场。'],
        'crypto' => ['label' => '加密观察', 'title' => '用更冷静的方式跟踪加密资产。', 'copy' => 'ETF、监管、链上资金和风险提示，一起看清。'],
        'wealth' => ['label' => '理财笔记', 'title' => '把长期投资和风险教育放进邮箱。', 'copy' => '少一点噪音，多一点可执行的个人财务判断。'],
    ];

    return $map[$categorySlug] ?? ['label' => '钱潮早报', 'title' => '把下一组市场信号发到你的邮箱。', 'copy' => '每天 5 分钟，读懂全球市场、科技、加密与中国公司出海。'];
}

function retention_analytics(): array
{
    ensure_retention_schema();
    $pdo = db();
    $out = [
        'saved_total' => 0,
        'recent_readers_30d' => 0,
        'returning_readers_30d' => 0,
        'top_saved' => [],
        'completion_events_30d' => 0,
        'share_events_30d' => 0,
    ];
    if (!$pdo instanceof PDO) {
        return $out;
    }

    try {
        $out['saved_total'] = (int) $pdo->query('SELECT COUNT(*) FROM reader_saved_articles')->fetchColumn();
        $out['recent_readers_30d'] = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM reader_recent_reads WHERE last_read_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $out['returning_readers_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM (
            SELECT ua_hash FROM analytics_events
            WHERE event_type = 'article_view' AND ua_hash IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY ua_hash HAVING COUNT(*) > 1
        ) x")->fetchColumn();
        $out['completion_events_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type = 'article_complete' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $out['share_events_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM analytics_events WHERE event_type IN ('share_copy','article_share') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $out['top_saved'] = $pdo->query("SELECT a.slug, a.title, COUNT(*) AS saves
            FROM reader_saved_articles s
            INNER JOIN articles a ON a.id = s.article_id
            GROUP BY a.slug, a.title
            ORDER BY saves DESC, a.title ASC
            LIMIT 10")->fetchAll() ?: [];
    } catch (Throwable $exception) {
    }

    return $out;
}
