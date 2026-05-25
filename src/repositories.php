<?php

declare(strict_types=1);

function db_categories(): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $rows = $pdo->query('SELECT slug, name, summary FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
        return $rows ?: null;
    } catch (Throwable) {
        return null;
    }
}

function db_articles(?string $categorySlug = null): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    $sql = "SELECT a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
                a.read_time_minutes, a.published_at, c.slug AS category, c.name AS category_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'published'";
    $params = [];

    if ($categorySlug !== null) {
        $sql .= ' AND c.slug = :category';
        $params['category'] = $categorySlug;
    }

    $sql .= ' ORDER BY a.published_at DESC, a.id DESC LIMIT 50';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        if (!$rows) {
            return null;
        }

        return array_map('map_article_row', $rows);
    } catch (Throwable) {
        return null;
    }
}

function db_article(string $slug): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $statement = $pdo->prepare("SELECT a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
                a.read_time_minutes, a.published_at, c.slug AS category, c.name AS category_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'published' AND a.slug = :slug
            LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row ? map_article_row($row) : null;
    } catch (Throwable) {
        return null;
    }
}

function map_article_row(array $row): array
{
    $body = json_decode((string) $row['body'], true);
    if (!is_array($body)) {
        $body = preg_split('/\R{2,}/', trim((string) $row['body'])) ?: [];
    }

    return [
        'slug' => (string) $row['slug'],
        'category' => (string) $row['category'],
        'category_name' => (string) $row['category_name'],
        'title' => (string) $row['title'],
        'dek' => (string) $row['dek'],
        'brief' => (string) $row['brief'],
        'why' => (string) $row['why_it_matters'],
        'numbers' => [],
        'body' => array_values(array_filter(array_map('strval', $body))),
        'read_time' => (int) $row['read_time_minutes'] . ' min read',
        'published_at' => $row['published_at'] ? date('Y-m-d', strtotime((string) $row['published_at'])) : '',
    ];
}
