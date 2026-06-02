<?php

declare(strict_types=1);

function ensure_tags_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    // tags + article_tags already in base schema; idempotent guard
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS article_tags (
            article_id INT UNSIGNED NOT NULL,
            tag_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (article_id, tag_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function find_or_create_tag(string $name): ?int
{
    ensure_tags_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $slug = function_exists('slugify') ? slugify($name) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?: '');
    if ($slug === '') {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT id FROM tags WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $id = (int) ($statement->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $pdo->prepare('INSERT INTO tags (slug, name) VALUES (:slug, :name)')
            ->execute(['slug' => $slug, 'name' => $name]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        return null;
    }
}

function set_article_tags(int $articleId, array $tagNames): void
{
    ensure_tags_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM article_tags WHERE article_id = :id')->execute(['id' => $articleId]);
        $insert = $pdo->prepare('INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (:article_id, :tag_id)');
        foreach ($tagNames as $name) {
            $tagId = find_or_create_tag((string) $name);
            if ($tagId) {
                $insert->execute(['article_id' => $articleId, 'tag_id' => $tagId]);
            }
        }
    } catch (Throwable $exception) {
    }
}

function article_tags(int $articleId): array
{
    ensure_tags_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare('SELECT t.slug, t.name FROM article_tags at
            INNER JOIN tags t ON t.id = at.tag_id WHERE at.article_id = :id ORDER BY t.name ASC');
        $statement->execute(['id' => $articleId]);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function article_tags_by_slug(string $slug): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare('SELECT t.slug, t.name FROM articles a
            INNER JOIN article_tags at ON at.article_id = a.id
            INNER JOIN tags t ON t.id = at.tag_id
            WHERE a.slug = :slug ORDER BY t.name ASC');
        $statement->execute(['slug' => $slug]);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function all_tags(): array
{
    ensure_tags_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        // Merge duplicate tag rows that share the same name (e.g. several
        // "人工智能" rows with different slugs) into ONE topic, counting distinct
        // published articles across all of them. A representative slug is picked
        // so the link works; the detail page also merges by name.
        return $pdo->query("SELECT MIN(t.slug) AS slug, t.name, COUNT(DISTINCT a.id) AS article_count
            FROM tags t
            INNER JOIN article_tags at ON at.tag_id = t.id
            INNER JOIN articles a ON a.id = at.article_id AND a.status = 'published'
            GROUP BY t.name
            HAVING article_count > 0
            ORDER BY article_count DESC, t.name ASC
            LIMIT 50")->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function find_tag(string $slug): ?array
{
    ensure_tags_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT slug, name FROM tags WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        return $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function articles_by_tag(string $slug): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        // Match by NAME, not just this slug, so a merged topic (duplicate tag
        // rows sharing a name) shows every article tagged under any of them.
        $statement = $pdo->prepare("SELECT a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
                a.hero_image_path, a.hero_image_alt, a.read_time_minutes, a.published_at,
                c.slug AS category, c.name AS category_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'published' AND a.id IN (
                SELECT at.article_id FROM article_tags at
                INNER JOIN tags t ON t.id = at.tag_id
                WHERE t.name = (SELECT name FROM tags WHERE slug = :slug LIMIT 1)
            )
            ORDER BY a.published_at DESC, a.id DESC
            LIMIT 50");
        $statement->execute(['slug' => $slug]);
        $rows = $statement->fetchAll();
        return array_map('map_article_row', $rows ?: []);
    } catch (Throwable $exception) {
        return [];
    }
}

function most_read_articles(int $limit = 5, int $days = 7): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare("SELECT a.slug, a.title, a.dek, a.brief, a.hero_image_path,
                a.published_at, c.slug AS category, c.name AS category_name,
                COUNT(e.id) AS views
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            LEFT JOIN analytics_events e ON e.slug = a.slug
                AND e.event_type = 'article_view'
                AND e.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            WHERE a.status = 'published'
            GROUP BY a.id, a.slug, a.title, a.dek, a.brief, a.hero_image_path, a.published_at, c.slug, c.name
            HAVING views > 0
            ORDER BY views DESC
            LIMIT :limit");
        $statement->bindValue(':days', $days, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'dek' => (string) $row['dek'],
                'brief' => (string) $row['brief'],
                'category' => (string) $row['category'],
                'category_name' => (string) $row['category_name'],
                'views' => (int) $row['views'],
                'hero_image' => function_exists('article_media_url')
                    ? article_media_url(['hero_image_path' => $row['hero_image_path'], 'category' => $row['category']])
                    : (string) $row['hero_image_path'],
                'published_at' => $row['published_at'] ? date('Y-m-d', strtotime((string) $row['published_at'])) : '',
            ];
        }
        return $out;
    } catch (Throwable $exception) {
        return [];
    }
}
