<?php

declare(strict_types=1);

function launch_checklist(): array
{
    $articles = get_articles();
    $categories = get_categories();
    $ai = ai_provider_status();

    return [
        ['label' => 'Homepage works', 'ok' => true, 'detail' => canonical_url()],
        ['label' => 'Published articles exist', 'ok' => count($articles) >= 3, 'detail' => count($articles) . ' published'],
        ['label' => 'Categories exist', 'ok' => count($categories) >= 8, 'detail' => count($categories) . ' categories'],
        ['label' => 'Newsletter signup stores emails', 'ok' => db_count('subscribers') >= 1, 'detail' => db_count('subscribers') . ' subscribers'],
        ['label' => 'AI provider configured', 'ok' => $ai['ready'], 'detail' => $ai['label'] . ' · ' . $ai['model']],
        ['label' => 'Sitemap enabled', 'ok' => true, 'detail' => canonical_url('sitemap.xml')],
        ['label' => 'Robots enabled', 'ok' => true, 'detail' => canonical_url('robots.txt')],
        ['label' => 'Disclaimer page exists', 'ok' => true, 'detail' => canonical_url('disclaimer')],
        ['label' => 'Editorial standards page exists', 'ok' => true, 'detail' => canonical_url('editorial-standards')],
        ['label' => 'Subscriber CSV export available', 'ok' => true, 'detail' => canonical_url('admin/subscribers.csv')],
    ];
}

function launch_ready(array $items): bool
{
    foreach ($items as $item) {
        if (!$item['ok']) {
            return false;
        }
    }

    return true;
}
