<?php

declare(strict_types=1);

/**
 * AI-powered, trend-aware article tagging.
 *
 * Tags (标签) are generated at draft time by the section bot (added to the draft
 * payload) and attached to the article on publish/convert. They feed the public
 * 热门话题 page via all_tags().
 *
 * "Trend" signal: we can't call X / Reddit / Fox / CNN / Yahoo Finance live (no
 * API keys), but the platform already INGESTS those mainstream outlets via RSS
 * into news_items. recent_trend_terms() mines the last ~2 weeks of that corpus
 * for the entities/topics currently dominating coverage, and we feed those to
 * the writing bot so its tags lean into what's actually trending right now.
 *
 * Dedup is enforced at three levels: within the generated set (slug-unique),
 * against the prompt's "avoid duplicates" instruction, and at the DB layer where
 * find_or_create_tag() reuses an existing tag row by slug.
 */

/**
 * Top trending entity/topic terms from the last $days of ingested mainstream
 * news (titles). English proper nouns + notable bigrams, stop-word filtered,
 * ranked by how many distinct headlines mention them.
 */
function recent_trend_terms(int $days = 14, int $limit = 30): array
{
    static $cache = [];
    $key = $days . ':' . $limit;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $rows = $pdo->query('SELECT title FROM news_items
            WHERE fetched_at >= (NOW() - INTERVAL ' . max(1, min(60, $days)) . ' DAY)
            ORDER BY id DESC LIMIT 1200')->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }

    // Sentence-starters / generic words that are capitalised but not topics.
    $stop = array_fill_keys([
        'the', 'a', 'an', 'this', 'that', 'these', 'those', 'how', 'why', 'what', 'when', 'where', 'who',
        'new', 'here', 'there', 'more', 'most', 'best', 'top', 'first', 'last', 'next', 'after', 'before',
        'is', 'are', 'was', 'were', 'will', 'would', 'could', 'should', 'can', 'may', 'might', 'has', 'have',
        'and', 'or', 'but', 'for', 'with', 'from', 'into', 'over', 'under', 'about', 'as', 'at', 'by', 'on', 'in', 'to', 'of',
        'it', 'its', 'they', 'their', 'you', 'your', 'we', 'our', 'he', 'she', 'his', 'her',
        'report', 'reports', 'says', 'said', 'update', 'updates', 'breaking', 'live', 'watch', 'opinion',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'today', 'week', 'year',
    ], true);

    $counts = [];
    foreach ($rows as $r) {
        $title = (string) ($r['title'] ?? '');
        if ($title === '') {
            continue;
        }
        // Capitalised word or two-word proper phrase (Apple, Wall Street, Federal Reserve).
        if (preg_match_all('/\b([A-Z][a-zA-Z0-9&.\']+(?:\s+[A-Z][a-zA-Z0-9&.\']+)?)\b/', $title, $m)) {
            $seenInTitle = [];
            foreach ($m[1] as $term) {
                $term = trim((string) $term, " .'");
                $lc = strtolower($term);
                if ($term === '' || mb_strlen($term) < 2 || isset($stop[$lc]) || isset($seenInTitle[$lc])) {
                    continue;
                }
                // Skip ALL-CAPS noise longer than a ticker (e.g. headline shouting).
                if (mb_strlen($term) > 28) {
                    continue;
                }
                $seenInTitle[$lc] = true;
                $counts[$term] = ($counts[$term] ?? 0) + 1;
            }
        }
    }
    arsort($counts);
    $out = [];
    foreach ($counts as $term => $n) {
        if ($n < 2) { // require at least 2 distinct headlines = a real trend
            continue;
        }
        $out[] = $term;
        if (count($out) >= $limit) {
            break;
        }
    }
    $cache[$key] = $out;
    return $out;
}

/**
 * Clean + deduplicate a tag list: trim, strip leading #/punctuation, drop
 * empties / overlong, and remove slug-duplicates (case-insensitive). Caps count.
 */
function normalize_tag_list(array $tags, int $max = 6): array
{
    $seen = [];
    $out = [];
    foreach ($tags as $tag) {
        $tag = trim((string) $tag);
        $tag = trim($tag, " \t\n\r\0\x0B#＃，,、;；·-");
        if ($tag === '' || mb_strlen($tag, 'UTF-8') > 24) {
            continue;
        }
        $slug = function_exists('slugify') ? slugify($tag) : strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $tag), '-'));
        if ($slug === '' || isset($seen[$slug])) {
            continue;
        }
        $seen[$slug] = true;
        $out[] = $tag;
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

/**
 * Decide an article's tags from its AI draft payload, with a trend-aware
 * fallback. Returns a comma-joined string ready for save_article(['tags' => …]).
 *
 * 1) Use the bot's generated tags (payload['tags']), normalized + deduped.
 * 2) If fewer than 3, top up with trending terms actually present in the
 *    article's title/dek/body (so we never invent an off-topic tag).
 */
function derive_article_tags(array $payload, string $sectionSlug = ''): string
{
    $tags = normalize_tag_list((array) ($payload['tags'] ?? []));

    if (count($tags) < 3) {
        $haystack = mb_strtolower(
            (string) ($payload['title'] ?? '') . ' '
            . (string) ($payload['dek'] ?? '') . ' '
            . (string) ($payload['brief'] ?? '') . ' '
            . implode(' ', array_map('strval', (array) ($payload['body'] ?? []))),
            'UTF-8'
        );
        foreach (recent_trend_terms() as $term) {
            if (count($tags) >= 5) {
                break;
            }
            if (mb_strpos($haystack, mb_strtolower($term, 'UTF-8')) !== false) {
                $tags = normalize_tag_list(array_merge($tags, [$term]));
            }
        }
    }

    return implode(', ', $tags);
}

/**
 * A compact, comma-joined trend hint for injecting into the writing prompt.
 */
function trend_hint_for_prompt(int $max = 18): string
{
    $terms = recent_trend_terms(14, $max);
    return $terms ? implode(', ', $terms) : '';
}
