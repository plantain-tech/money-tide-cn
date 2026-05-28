<?php

declare(strict_types=1);

/**
 * Week 7 Day 6: Reader engagement basics — lightweight article reactions.
 * Three options guide content direction: 有帮助 / 想看更多 / 太复杂.
 * One reaction per (article, reaction, daily ua_hash) — dedup, no accounts needed.
 */

function reaction_types(): array
{
    return [
        'helpful' => '有帮助',
        'more' => '想看更多',
        'complex' => '太复杂',
    ];
}

function ensure_reactions_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS article_reactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id INT UNSIGNED NOT NULL,
            reaction VARCHAR(20) NOT NULL,
            ua_hash CHAR(16) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reaction_voter (article_id, reaction, ua_hash),
            INDEX idx_reaction_article (article_id, reaction),
            INDEX idx_reaction_time (reaction, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

function reaction_voter_hash(): string
{
    return substr(md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . date('Y-m-d')), 0, 16);
}

/**
 * Counts keyed by reaction type, always returns every type (zero-filled).
 */
function reaction_counts_for_article(int $articleId): array
{
    ensure_reactions_schema();
    $counts = [];
    foreach (array_keys(reaction_types()) as $key) {
        $counts[$key] = 0;
    }
    $pdo = db();
    if (!$pdo instanceof PDO || $articleId <= 0) {
        return $counts;
    }
    try {
        $statement = $pdo->prepare('SELECT reaction, COUNT(*) AS n FROM article_reactions WHERE article_id = :id GROUP BY reaction');
        $statement->execute(['id' => $articleId]);
        foreach ($statement->fetchAll() as $row) {
            $key = (string) $row['reaction'];
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row['n'];
            }
        }
    } catch (Throwable $exception) {
    }
    return $counts;
}

/**
 * Which reactions this visitor already cast today (so the UI can mark them active).
 */
function reactions_by_voter(int $articleId): array
{
    ensure_reactions_schema();
    $pdo = db();
    if (!$pdo instanceof PDO || $articleId <= 0) {
        return [];
    }
    try {
        $statement = $pdo->prepare('SELECT reaction FROM article_reactions WHERE article_id = :id AND ua_hash = :hash');
        $statement->execute(['id' => $articleId, 'hash' => reaction_voter_hash()]);
        return array_map('strval', array_column($statement->fetchAll(), 'reaction'));
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * Toggle a reaction for the current visitor. Returns refreshed counts + active set.
 */
function record_reaction(int $articleId, string $reaction): array
{
    ensure_reactions_schema();
    if (!array_key_exists($reaction, reaction_types())) {
        return ['ok' => false, 'message' => '未知的反馈类型。'];
    }
    if ($articleId <= 0) {
        return ['ok' => false, 'message' => '文章不存在。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    $hash = reaction_voter_hash();
    $active = true;
    try {
        // Toggle: if this visitor already cast this reaction today, remove it.
        $check = $pdo->prepare('SELECT id FROM article_reactions WHERE article_id = :id AND reaction = :r AND ua_hash = :h LIMIT 1');
        $check->execute(['id' => $articleId, 'r' => $reaction, 'h' => $hash]);
        if ($check->fetchColumn()) {
            $pdo->prepare('DELETE FROM article_reactions WHERE article_id = :id AND reaction = :r AND ua_hash = :h')
                ->execute(['id' => $articleId, 'r' => $reaction, 'h' => $hash]);
            $active = false;
        } else {
            $pdo->prepare('INSERT IGNORE INTO article_reactions (article_id, reaction, ua_hash) VALUES (:id, :r, :h)')
                ->execute(['id' => $articleId, 'r' => $reaction, 'h' => $hash]);
        }
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }

    if (function_exists('record_event')) {
        record_event('article_reaction', [
            'slug' => (string) ($_POST['slug'] ?? ''),
            'source' => $reaction . ($active ? '' : ':undo'),
            'path' => 'article-reaction',
        ]);
    }

    return [
        'ok' => true,
        'active' => $active,
        'reaction' => $reaction,
        'counts' => reaction_counts_for_article($articleId),
    ];
}

/**
 * Admin analytics: totals per reaction + leaderboards to guide content direction.
 */
function reaction_analytics(int $days = 30): array
{
    ensure_reactions_schema();
    $types = reaction_types();
    $out = [
        'days' => $days,
        'totals' => array_fill_keys(array_keys($types), 0),
        'total_all' => 0,
        'recent' => array_fill_keys(array_keys($types), 0),
        'top_helpful' => [],
        'top_more' => [],
        'top_complex' => [],
        'most_engaged' => [],
    ];
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        foreach ($pdo->query('SELECT reaction, COUNT(*) AS n FROM article_reactions GROUP BY reaction')->fetchAll() as $row) {
            $key = (string) $row['reaction'];
            if (array_key_exists($key, $out['totals'])) {
                $out['totals'][$key] = (int) $row['n'];
                $out['total_all'] += (int) $row['n'];
            }
        }

        $recent = $pdo->prepare('SELECT reaction, COUNT(*) AS n FROM article_reactions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) GROUP BY reaction');
        $recent->bindValue('days', $days, PDO::PARAM_INT);
        $recent->execute();
        foreach ($recent->fetchAll() as $row) {
            $key = (string) $row['reaction'];
            if (array_key_exists($key, $out['recent'])) {
                $out['recent'][$key] = (int) $row['n'];
            }
        }

        foreach (['helpful' => 'top_helpful', 'more' => 'top_more', 'complex' => 'top_complex'] as $reaction => $bucket) {
            $statement = $pdo->prepare("SELECT r.article_id, a.title, a.slug, COUNT(*) AS n
                FROM article_reactions r
                INNER JOIN articles a ON a.id = r.article_id
                WHERE r.reaction = :r
                GROUP BY r.article_id, a.title, a.slug
                ORDER BY n DESC, r.article_id DESC
                LIMIT 5");
            $statement->execute(['r' => $reaction]);
            $out[$bucket] = $statement->fetchAll() ?: [];
        }

        $engaged = $pdo->query("SELECT r.article_id, a.title, a.slug,
                SUM(r.reaction = 'helpful') AS helpful,
                SUM(r.reaction = 'more') AS more,
                SUM(r.reaction = 'complex') AS complex,
                COUNT(*) AS total
            FROM article_reactions r
            INNER JOIN articles a ON a.id = r.article_id
            GROUP BY r.article_id, a.title, a.slug
            ORDER BY total DESC, r.article_id DESC
            LIMIT 12")->fetchAll();
        $out['most_engaged'] = $engaged ?: [];
    } catch (Throwable $exception) {
    }
    return $out;
}
