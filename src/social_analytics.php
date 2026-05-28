<?php

declare(strict_types=1);

/**
 * Week 6 Day 6: Social tracking + referral analytics.
 * Reads from the existing analytics_events table (event types
 * share_copy / article_share / newsletter_cta) plus subscribers.source.
 */

function share_utm_url(string $articleUrl, string $channel): string
{
    $sep = strpos($articleUrl, '?') === false ? '?' : '&';
    $params = http_build_query([
        'utm_source' => $channel,
        'utm_medium' => 'social',
        'utm_campaign' => 'article_share',
    ]);
    return $articleUrl . $sep . $params;
}

function social_share_analytics(): array
{
    $pdo = db();
    $out = [
        'share_events_7d' => 0,
        'share_events_30d' => 0,
        'copy_events_30d' => 0,
        'top_shared_7d' => [],
        'channels_30d' => [],
        'social_referred_views_7d' => 0,
        'referral_signups_30d' => 0,
        'top_referral_sources_30d' => [],
    ];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['share_events_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM analytics_events
            WHERE event_type IN ('share_copy','article_share') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $out['share_events_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM analytics_events
            WHERE event_type IN ('share_copy','article_share') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $out['copy_events_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM analytics_events
            WHERE event_type = 'share_copy' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

        $topShared = $pdo->query("SELECT e.slug, COUNT(*) AS shares, a.title
            FROM analytics_events e
            LEFT JOIN articles a ON a.slug = e.slug
            WHERE e.event_type IN ('share_copy','article_share') AND e.slug IS NOT NULL AND e.slug <> ''
              AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY e.slug, a.title
            ORDER BY shares DESC, e.slug ASC
            LIMIT 10")->fetchAll();
        $out['top_shared_7d'] = $topShared ?: [];

        $channels = $pdo->query("SELECT COALESCE(NULLIF(source, ''), 'unknown') AS channel, COUNT(*) AS total
            FROM analytics_events
            WHERE event_type IN ('share_copy','article_share') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY channel
            ORDER BY total DESC
            LIMIT 10")->fetchAll();
        $out['channels_30d'] = $channels ?: [];

        // Inbound traffic that came from a social/referral source (UTM lands in referrer or source).
        $out['social_referred_views_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM analytics_events
            WHERE event_type = 'article_view'
              AND (referrer LIKE '%utm_medium=social%' OR referrer LIKE '%t.co%' OR referrer LIKE '%linkedin%' OR referrer LIKE '%xiaohongshu%' OR referrer LIKE '%weixin%')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

        $out['referral_signups_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM subscribers
            WHERE (referred_by_code IS NOT NULL AND referred_by_code <> '')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

        $refSources = $pdo->query("SELECT COALESCE(NULLIF(source, ''), 'unknown') AS source, COUNT(*) AS total
            FROM subscribers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY source ORDER BY total DESC LIMIT 10")->fetchAll();
        $out['top_referral_sources_30d'] = $refSources ?: [];
    } catch (Throwable $exception) {
    }
    return $out;
}
