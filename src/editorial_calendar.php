<?php

declare(strict_types=1);

function calendar_view_modes(): array
{
    return [
        'month' => '月视图',
        'week' => '周视图',
    ];
}

function calendar_status_options(): array
{
    return [
        '' => '全部状态',
        'draft' => '草稿',
        'review' => '审核中',
        'ready' => '可发布',
        'scheduled' => '已排期',
        'published' => '已发布',
        'sent' => '已发送',
        'archived' => '已归档',
    ];
}

function calendar_filters_from_request(array $input): array
{
    $view = (string) ($input['view'] ?? 'month');
    if (!array_key_exists($view, calendar_view_modes())) {
        $view = 'month';
    }

    $date = calendar_valid_date((string) ($input['date'] ?? ''));
    if ($date === '') {
        $date = date('Y-m-d');
    }

    $status = trim((string) ($input['status'] ?? ''));
    if (!array_key_exists($status, calendar_status_options())) {
        $status = '';
    }

    return [
        'view' => $view,
        'date' => $date,
        'status' => $status,
        'category' => trim((string) ($input['category'] ?? '')),
        'editor_id' => max(0, (int) ($input['editor_id'] ?? 0)),
    ];
}

function calendar_valid_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt instanceof DateTimeImmutable ? $dt->format('Y-m-d') : '';
}

function calendar_range(string $view, string $date): array
{
    $anchor = new DateTimeImmutable($date ?: date('Y-m-d'));
    if ($view === 'week') {
        $start = $anchor->modify('monday this week');
        $end = $start->modify('+6 days');
        $previous = $start->modify('-7 days');
        $next = $start->modify('+7 days');
        $label = $start->format('M j') . ' - ' . $end->format('M j, Y');
    } else {
        $start = $anchor->modify('first day of this month');
        $end = $anchor->modify('last day of this month');
        $previous = $start->modify('-1 month');
        $next = $start->modify('+1 month');
        $label = $start->format('F Y');
    }

    return [
        'view' => $view,
        'anchor' => $anchor->format('Y-m-d'),
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'previous_date' => $previous->format('Y-m-d'),
        'next_date' => $next->format('Y-m-d'),
        'label' => $label,
    ];
}

function editorial_calendar_events(array $filters, array $range): array
{
    $events = array_merge(
        calendar_article_events($filters, $range),
        calendar_newsletter_events($filters, $range)
    );

    usort($events, static function (array $a, array $b): int {
        $dateCompare = strcmp((string) $a['datetime'], (string) $b['datetime']);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return strcmp((string) $a['title'], (string) $b['title']);
    });

    $grouped = [];
    foreach ($events as $event) {
        $grouped[(string) $event['date']][] = $event;
    }

    return $grouped;
}

function calendar_article_events(array $filters, array $range): array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $sql = "SELECT a.id, a.slug, a.title, a.status, a.published_at,
                c.name AS category_name, c.slug AS category_slug,
                COALESCE(u.display_name, u.email) AS editor_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.editor_id
            WHERE a.published_at IS NOT NULL
              AND DATE(a.published_at) BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $range['start'],
        'end_date' => $range['end'],
    ];

    if ($filters['status'] !== '') {
        $sql .= ' AND a.status = :article_status';
        $params['article_status'] = $filters['status'];
    }
    if ($filters['category'] !== '') {
        $sql .= ' AND c.slug = :category';
        $params['category'] = $filters['category'];
    }
    if ((int) $filters['editor_id'] > 0) {
        $sql .= ' AND a.editor_id = :editor_id';
        $params['editor_id'] = (int) $filters['editor_id'];
    }

    $sql .= ' ORDER BY a.published_at ASC, a.id ASC LIMIT 300';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $events = [];
        foreach ($statement->fetchAll() as $row) {
            $timestamp = strtotime((string) $row['published_at']);
            if ($timestamp === false) {
                continue;
            }
            $events[] = [
                'type' => 'article',
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'date' => date('Y-m-d', $timestamp),
                'time' => date('H:i', $timestamp),
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'category_name' => (string) $row['category_name'],
                'category_slug' => (string) $row['category_slug'],
                'editor_name' => (string) ($row['editor_name'] ?? ''),
                'edit_url' => url('admin/articles/' . (int) $row['id'] . '/edit'),
                'preview_url' => url('admin/articles/' . (int) $row['id'] . '/preview'),
                'public_url' => (string) $row['status'] === 'published' ? url('article/' . (string) $row['slug']) : '',
            ];
        }
        return $events;
    } catch (Throwable $exception) {
        return [];
    }
}

function calendar_newsletter_events(array $filters, array $range): array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $sql = "SELECT id, subject, status, scheduled_at, sent_at, created_at
            FROM newsletter_issues
            WHERE DATE(COALESCE(scheduled_at, sent_at, created_at)) BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $range['start'],
        'end_date' => $range['end'],
    ];

    if ($filters['status'] !== '') {
        $sql .= ' AND status = :newsletter_status';
        $params['newsletter_status'] = $filters['status'];
    }

    $sql .= ' ORDER BY COALESCE(scheduled_at, sent_at, created_at) ASC, id ASC LIMIT 200';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $events = [];
        foreach ($statement->fetchAll() as $row) {
            $dateValue = (string) ($row['scheduled_at'] ?: ($row['sent_at'] ?: $row['created_at']));
            $timestamp = strtotime($dateValue);
            if ($timestamp === false) {
                continue;
            }
            $events[] = [
                'type' => 'newsletter',
                'id' => (int) $row['id'],
                'title' => (string) $row['subject'],
                'status' => (string) $row['status'],
                'date' => date('Y-m-d', $timestamp),
                'time' => date('H:i', $timestamp),
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'category_name' => 'Newsletter',
                'category_slug' => 'newsletter',
                'editor_name' => '',
                'edit_url' => url('admin/newsletter/' . (int) $row['id'] . '/edit'),
                'preview_url' => url('admin/newsletter/' . (int) $row['id'] . '/preview'),
                'public_url' => '',
            ];
        }
        return $events;
    } catch (Throwable $exception) {
        return [];
    }
}

function calendar_month_days(array $range): array
{
    $monthStart = new DateTimeImmutable((string) $range['start']);
    $monthEnd = new DateTimeImmutable((string) $range['end']);
    $gridStart = $monthStart->modify('monday this week');
    $gridEnd = $monthEnd->modify('sunday this week');
    $today = date('Y-m-d');
    $days = [];

    for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')) {
        $days[] = [
            'date' => $day->format('Y-m-d'),
            'day' => $day->format('j'),
            'weekday' => $day->format('D'),
            'is_current_month' => $day->format('m') === $monthStart->format('m'),
            'is_today' => $day->format('Y-m-d') === $today,
        ];
    }

    return $days;
}

function calendar_week_days(array $range): array
{
    $start = new DateTimeImmutable((string) $range['start']);
    $today = date('Y-m-d');
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $day = $start->modify('+' . $i . ' days');
        $days[] = [
            'date' => $day->format('Y-m-d'),
            'label' => $day->format('D, M j'),
            'is_today' => $day->format('Y-m-d') === $today,
        ];
    }
    return $days;
}

function calendar_stats(array $events): array
{
    $stats = [
        'article' => 0,
        'newsletter' => 0,
        'total' => 0,
    ];
    foreach ($events as $dayEvents) {
        foreach ($dayEvents as $event) {
            $type = (string) ($event['type'] ?? '');
            if (isset($stats[$type])) {
                $stats[$type]++;
            }
            $stats['total']++;
        }
    }
    return $stats;
}
