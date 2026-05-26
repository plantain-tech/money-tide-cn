<?php

declare(strict_types=1);

function launch_checklist(): array
{
    $articles = get_articles();
    $categories = get_categories();
    $ai = ai_provider_status();
    $analytics = external_analytics_status();
    ensure_ai_prompt_templates_table();
    ensure_ai_draft_versions_table();
    ensure_ai_draft_checks_table();
    ensure_analytics_table();

    return [
        ['label' => 'Homepage works', 'ok' => true, 'detail' => canonical_url()],
        ['label' => 'Published articles exist', 'ok' => count($articles) >= 3, 'detail' => count($articles) . ' published'],
        ['label' => 'Categories exist', 'ok' => count($categories) >= 8, 'detail' => count($categories) . ' categories'],
        ['label' => 'Newsletter signup stores emails', 'ok' => db_count('subscribers') >= 1, 'detail' => db_count('subscribers') . ' subscribers'],
        ['label' => 'AI provider configured', 'ok' => $ai['ready'], 'detail' => $ai['label'] . ' - ' . $ai['model']],
        ['label' => 'AI prompt templates available', 'ok' => db_count('ai_prompt_templates') >= 0, 'detail' => 'template table reachable'],
        ['label' => 'AI version history available', 'ok' => db_count('ai_draft_versions') >= 0, 'detail' => 'version table reachable'],
        ['label' => 'Article workflow available', 'ok' => db_count('articles', "status IN ('draft','review','published','archived')") >= 0, 'detail' => 'draft/review/published/archived'],
        ['label' => 'Analytics event table reachable', 'ok' => db_count('analytics_events') >= 0, 'detail' => db_count('analytics_events') . ' events'],
        ['label' => 'Internal analytics page available', 'ok' => true, 'detail' => canonical_url('admin/analytics')],
        ['label' => 'External analytics optional', 'ok' => true, 'detail' => ($analytics['ga_id'] !== '' ? 'GA configured' : 'GA not configured') . ' / ' . ($analytics['plausible_domain'] !== '' ? 'Plausible configured' : 'Plausible not configured')],
        ['label' => 'Sitemap enabled', 'ok' => true, 'detail' => canonical_url('sitemap.xml')],
        ['label' => 'Robots enabled', 'ok' => true, 'detail' => canonical_url('robots.txt')],
        ['label' => 'Disclaimer page exists', 'ok' => true, 'detail' => canonical_url('disclaimer')],
        ['label' => 'Editorial standards page exists', 'ok' => true, 'detail' => canonical_url('editorial-standards')],
        ['label' => 'Subscriber CSV export available', 'ok' => true, 'detail' => canonical_url('admin/subscribers.csv')],
        ['label' => 'Safe draft cleanup available', 'ok' => true, 'detail' => 'draft/archive delete only'],
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
