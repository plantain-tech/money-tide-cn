<?php

declare(strict_types=1);

function canonical_url(string $path = ''): string
{
    return app_url($path);
}

function default_og_image(): string
{
    return app_url('assets/img/og-money-tide.svg');
}

function seo_title(string $title, string $suffix = '钱潮 Money Tide'): string
{
    $title = trim($title);
    if ($title === '') {
        return $suffix;
    }
    return str_contains($title, $suffix) ? $title : $title . ' - ' . $suffix;
}

function seo_description(string $primary, string $fallback = ''): string
{
    $value = trim($primary) !== '' ? trim($primary) : trim($fallback);
    if ($value === '') {
        $value = '面向中文读者的全球财经、科技与商业简报。';
    }
    return mb_strlen($value, 'UTF-8') > 180 ? mb_substr($value, 0, 177, 'UTF-8') . '...' : $value;
}

function emit_sitemap(array $categories, array $articles, array $tags = []): void
{
    header('Content-Type: application/xml; charset=utf-8');

    $urls = [
        ['loc' => canonical_url(), 'priority' => '1.0'],
        ['loc' => canonical_url('latest'), 'priority' => '0.8'],
        ['loc' => canonical_url('search'), 'priority' => '0.6'],
        ['loc' => canonical_url('subscribe'), 'priority' => '0.8'],
        ['loc' => canonical_url('newsletter'), 'priority' => '0.7'],
        ['loc' => canonical_url('feed/all.xml'), 'priority' => '0.4'],
        ['loc' => canonical_url('topics'), 'priority' => '0.7'],
        ['loc' => canonical_url('about'), 'priority' => '0.4'],
        ['loc' => canonical_url('editorial-standards'), 'priority' => '0.4'],
        ['loc' => canonical_url('disclaimer'), 'priority' => '0.3'],
    ];

    foreach ($categories as $category) {
        $urls[] = ['loc' => canonical_url('category/' . $category['slug']), 'priority' => '0.7'];
        $urls[] = ['loc' => canonical_url('feed/category/' . $category['slug'] . '.xml'), 'priority' => '0.3'];
    }
    foreach ($tags as $tag) {
        $urls[] = ['loc' => canonical_url('tag/' . $tag['slug']), 'priority' => '0.6'];
    }
    foreach ($articles as $article) {
        $urls[] = [
            'loc' => canonical_url('article/' . $article['slug']),
            'priority' => '0.9',
            'lastmod' => sitemap_date((string) ($article['published_at'] ?? '')),
        ];
    }
    if (function_exists('public_newsletter_archive')) {
        foreach (public_newsletter_archive(100) as $issue) {
            if (!empty($issue['slug'])) {
                $urls[] = [
                    'loc' => canonical_url('newsletter/' . $issue['slug']),
                    'priority' => '0.5',
                    'lastmod' => sitemap_date((string) ($issue['published_at'] ?? $issue['updated_at'] ?? '')),
                ];
            }
        }
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($urls as $url) {
        echo "  <url>\n";
        echo '    <loc>' . e($url['loc']) . "</loc>\n";
        if (!empty($url['lastmod'])) {
            echo '    <lastmod>' . e($url['lastmod']) . "</lastmod>\n";
        }
        echo '    <priority>' . e($url['priority']) . "</priority>\n";
        echo "  </url>\n";
    }
    echo "</urlset>\n";
}

function sitemap_date(string $date): string
{
    if ($date === '') {
        return date('Y-m-d');
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
}

function emit_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    echo "Allow: /\n\n";
    echo 'Sitemap: ' . canonical_url('sitemap.xml') . "\n";
    echo 'RSS: ' . canonical_url('feed/all.xml') . "\n";
}

function qa_checks(): array
{
    ensure_editorial_schema();
    ensure_analytics_table();
    ensure_ai_prompt_templates_table();
    ensure_ai_draft_versions_table();
    ensure_ai_draft_checks_table();

    return [
        ['name' => 'Database connection', 'ok' => db_is_ready(), 'detail' => db_is_ready() ? 'connected' : 'unavailable'],
        ['name' => 'Categories available', 'ok' => count(get_categories()) > 0, 'detail' => (string) count(get_categories())],
        ['name' => 'Published articles available', 'ok' => count(get_articles()) > 0, 'detail' => (string) count(get_articles())],
        ['name' => 'AI provider configured', 'ok' => ai_provider_status()['ready'], 'detail' => ai_provider_status()['label'] . ' - ' . ai_provider_status()['model']],
        ['name' => 'Subscriber table reachable', 'ok' => db_count('subscribers') >= 0, 'detail' => (string) db_count('subscribers')],
        ['name' => 'Analytics table reachable', 'ok' => db_count('analytics_events') >= 0, 'detail' => (string) db_count('analytics_events')],
        ['name' => 'AI versions table reachable', 'ok' => db_count('ai_draft_versions') >= 0, 'detail' => (string) db_count('ai_draft_versions')],
        ['name' => 'AI checks table reachable', 'ok' => db_count('ai_draft_checks') >= 0, 'detail' => (string) db_count('ai_draft_checks')],
    ];
}
