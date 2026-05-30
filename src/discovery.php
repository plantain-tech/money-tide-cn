<?php

declare(strict_types=1);

function search_query(string $value): string
{
    return trim(mb_substr(preg_replace('/\s+/u', ' ', $value) ?? $value, 0, 120, 'UTF-8'));
}

function search_articles(string $query, int $limit = 30): array
{
    $query = search_query($query);
    if ($query === '') {
        return [];
    }

    ensure_editorial_schema();
    if (function_exists('ensure_tags_schema')) {
        ensure_tags_schema();
    }
    if (function_exists('ensure_monetization_schema')) {
        ensure_monetization_schema();
    }

    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $like = '%' . $query . '%';
    $sql = "SELECT a.id, a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
                a.read_time_minutes, a.published_at, a.updated_at, a.seo_title, a.seo_description,
                a.hero_image_path, a.hero_image_alt, a.is_premium, a.premium_excerpt,
                c.slug AS category, c.name AS category_name,
                au.name AS author_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            LEFT JOIN authors au ON au.id = a.author_id
            WHERE a.status = 'published'
              AND (
                a.title LIKE :title OR a.dek LIKE :dek OR a.brief LIKE :brief
                OR a.why_it_matters LIKE :why OR a.body LIKE :body
                OR c.name LIKE :category_name OR c.slug LIKE :category_slug
                OR EXISTS (
                    SELECT 1 FROM article_tags at
                    INNER JOIN tags t ON t.id = at.tag_id
                    WHERE at.article_id = a.id AND (t.name LIKE :tag_name OR t.slug LIKE :tag_slug)
                )
              )
            ORDER BY
                CASE
                    WHEN a.title LIKE :exact_title THEN 0
                    WHEN a.dek LIKE :exact_dek THEN 1
                    ELSE 2
                END,
                a.published_at DESC,
                a.id DESC
            LIMIT " . max(1, min(60, $limit));

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute([
            'title' => $like,
            'dek' => $like,
            'brief' => $like,
            'why' => $like,
            'body' => $like,
            'category_name' => $like,
            'category_slug' => $like,
            'tag_name' => $like,
            'tag_slug' => $like,
            'exact_title' => $like,
            'exact_dek' => $like,
        ]);
        return array_map('map_article_row', $statement->fetchAll() ?: []);
    } catch (Throwable $exception) {
        return [];
    }
}

function record_search_query(string $query, int $resultCount): void
{
    $query = search_query($query);
    if ($query === '') {
        return;
    }
    record_event('search', [
        'path' => 'search',
        'slug' => mb_substr($query, 0, 180, 'UTF-8'),
        'source' => 'results:' . $resultCount,
    ]);
}

function search_highlight(string $text, string $query): string
{
    $query = search_query($query);
    if ($query === '') {
        return e($text);
    }
    $parts = preg_split('/(' . preg_quote($query, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return e($text);
    }
    $out = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (mb_strtolower($part, 'UTF-8') === mb_strtolower($query, 'UTF-8')) {
            $out .= '<mark class="search-mark">' . e($part) . '</mark>';
        } else {
            $out .= e($part);
        }
    }
    return $out;
}

function rss_articles(?string $categorySlug = null, int $limit = 40): array
{
    $articles = $categorySlug ? filter_articles_by_category($categorySlug) : get_articles();
    return array_slice($articles, 0, max(1, min(100, $limit)));
}

function emit_rss_feed(array $articles, string $title, string $description, string $path): void
{
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('Vary: Accept-Encoding');
    // Last-Modified: use the latest article's publish date if available
    if (!empty($articles)) {
        $latestTs = 0;
        foreach ($articles as $a) {
            $ts = strtotime((string) ($a['published_at'] ?? '')) ?: 0;
            if ($ts > $latestTs) {
                $latestTs = $ts;
            }
        }
        if ($latestTs > 0) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $latestTs) . ' GMT');
        }
    }
    $self = canonical_url($path);
    $home = canonical_url();
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
    echo "  <channel>\n";
    echo '    <title>' . rss_escape($title) . "</title>\n";
    echo '    <link>' . rss_escape($home) . "</link>\n";
    echo '    <description>' . rss_escape($description) . "</description>\n";
    echo "    <language>zh-CN</language>\n";
    echo '    <lastBuildDate>' . date(DATE_RSS) . "</lastBuildDate>\n";
    echo '    <atom:link href="' . rss_escape($self) . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
    foreach ($articles as $article) {
        $url = canonical_url('article/' . $article['slug']);
        $summary = (string) (($article['dek'] ?? '') ?: ($article['brief'] ?? ''));
        $date = strtotime((string) ($article['published_at'] ?? '')) ?: time();
        echo "    <item>\n";
        echo '      <title>' . rss_escape((string) $article['title']) . "</title>\n";
        echo '      <link>' . rss_escape($url) . "</link>\n";
        echo '      <guid isPermaLink="true">' . rss_escape($url) . "</guid>\n";
        echo '      <description>' . rss_escape($summary) . "</description>\n";
        echo '      <category>' . rss_escape((string) ($article['category_name'] ?? '')) . "</category>\n";
        echo '      <pubDate>' . date(DATE_RSS, $date) . "</pubDate>\n";
        echo "    </item>\n";
    }
    echo "  </channel>\n";
    echo "</rss>\n";
}

function rss_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
