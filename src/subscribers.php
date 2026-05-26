<?php

declare(strict_types=1);

function admin_subscribers(array $filters = []): array
{
    ensure_newsletter_growth_columns();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $sql = "SELECT s.id, s.email, s.status, s.source, s.referral_code, s.referred_by_code, s.landing_path, s.created_at,
                GROUP_CONCAT(np.topic ORDER BY np.topic SEPARATOR ', ') AS topics
            FROM subscribers s
            LEFT JOIN newsletter_preferences np ON np.subscriber_id = s.id
            WHERE 1 = 1";
    $params = [];

    if (!empty($filters['q'])) {
        $sql .= ' AND s.email LIKE :query';
        $params['query'] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND s.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['source'])) {
        $sql .= ' AND s.source LIKE :source';
        $params['source'] = '%' . $filters['source'] . '%';
    }

    $sql .= ' GROUP BY s.id, s.email, s.status, s.source, s.referral_code, s.referred_by_code, s.landing_path, s.created_at ORDER BY s.created_at DESC LIMIT 500';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function output_subscribers_csv(array $filters = []): void
{
    $subscribers = admin_subscribers($filters);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="money-tide-subscribers-' . date('Ymd-His') . '.csv"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        return;
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['email', 'status', 'source', 'topics', 'referral_code', 'referred_by_code', 'landing_path', 'created_at']);
    foreach ($subscribers as $subscriber) {
        fputcsv($output, [
            $subscriber['email'],
            $subscriber['status'],
            $subscriber['source'],
            $subscriber['topics'],
            $subscriber['referral_code'],
            $subscriber['referred_by_code'],
            $subscriber['landing_path'],
            $subscriber['created_at'],
        ]);
    }
    fclose($output);
}

function subscriber_growth_analytics(): array
{
    ensure_newsletter_growth_columns();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['totals' => [], 'sources' => [], 'topics' => [], 'referrals' => []];
    }

    try {
        $totals = [
            'active' => (int) $pdo->query("SELECT COUNT(*) FROM subscribers WHERE status = 'active'")->fetchColumn(),
            'today' => (int) $pdo->query('SELECT COUNT(*) FROM subscribers WHERE created_at >= CURDATE()')->fetchColumn(),
            'referrals' => (int) $pdo->query("SELECT COUNT(*) FROM subscribers WHERE referred_by_code IS NOT NULL AND referred_by_code <> ''")->fetchColumn(),
        ];

        $sources = $pdo->query("SELECT COALESCE(NULLIF(source, ''), 'unknown') AS label, COUNT(*) AS total
            FROM subscribers GROUP BY label ORDER BY total DESC, label ASC LIMIT 8")->fetchAll();

        $topics = $pdo->query("SELECT topic AS label, COUNT(*) AS total
            FROM newsletter_preferences GROUP BY topic ORDER BY total DESC, topic ASC LIMIT 8")->fetchAll();

        $referrals = $pdo->query("SELECT referred_by_code AS label, COUNT(*) AS total
            FROM subscribers
            WHERE referred_by_code IS NOT NULL AND referred_by_code <> ''
            GROUP BY referred_by_code ORDER BY total DESC LIMIT 8")->fetchAll();

        return ['totals' => $totals, 'sources' => $sources, 'topics' => $topics, 'referrals' => $referrals];
    } catch (Throwable $exception) {
        return ['totals' => [], 'sources' => [], 'topics' => [], 'referrals' => []];
    }
}
