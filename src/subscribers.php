<?php

declare(strict_types=1);

function admin_subscribers(array $filters = []): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $sql = "SELECT s.id, s.email, s.status, s.source, s.created_at,
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

    $sql .= ' GROUP BY s.id, s.email, s.status, s.source, s.created_at ORDER BY s.created_at DESC LIMIT 500';

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
    fputcsv($output, ['email', 'status', 'source', 'topics', 'created_at']);
    foreach ($subscribers as $subscriber) {
        fputcsv($output, [
            $subscriber['email'],
            $subscriber['status'],
            $subscriber['source'],
            $subscriber['topics'],
            $subscriber['created_at'],
        ]);
    }
    fclose($output);
}
