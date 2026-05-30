<?php

declare(strict_types=1);

function launch_cleanup_options(): array
{
    return [
        'qa_readers' => [
            'label' => 'QA 读者账号与订阅记录',
            'detail' => '删除 qa-reader/test/example.com 这类测试读者，并同步清理收藏、阅读历史、偏好和第三方登录绑定。',
            'recommended' => true,
        ],
        'test_newsletters' => [
            'label' => '测试早报期号与发送日志',
            'detail' => '删除主题含“测试/test/qa”的早报期号、关联文章和发送日志；不影响正式期号。',
            'recommended' => true,
        ],
        'ai_drafts' => [
            'label' => 'AI 草稿与 AI 草稿版本',
            'detail' => '清空 AI 草稿、版本、核查记录和 story intake 测试简报；保留 AI Bots 与提示词模板配置。',
            'recommended' => true,
        ],
        'social_drafts' => [
            'label' => '社交分发草稿',
            'detail' => '清空 social_posts 中尚未人工标记 posted 的草稿/排期记录，保留文章本身。',
            'recommended' => true,
        ],
        'reader_activity' => [
            'label' => '读者收藏、最近阅读与反馈',
            'detail' => '清空收藏、最近阅读、文章反馈等测试互动数据，让正式发布从干净基线开始。',
            'recommended' => true,
        ],
        'analytics_events' => [
            'label' => '站内分析事件',
            'detail' => '清空内置 analytics_events 的测试访问、搜索、分享、订阅事件；Google Analytics 不受影响。',
            'recommended' => true,
        ],
        'test_article_drafts' => [
            'label' => '测试文章草稿',
            'detail' => '删除标题/slug 含 test/qa/测试/副本/copy 的非发布文章；不会删除已发布文章。',
            'recommended' => false,
        ],
        'newsletter_test_sends' => [
            'label' => '单封测试发送日志',
            'detail' => '删除 send_type=test 的早报发送日志；保留正式 broadcast 发送记录。',
            'recommended' => false,
        ],
    ];
}

function launch_cleanup_table_exists(string $table): bool
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        return false;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        return false;
    }
}

function launch_cleanup_count(string $table, string $where = '1=1'): int
{
    if (!launch_cleanup_table_exists($table)) {
        return 0;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function launch_cleanup_preview(): array
{
    return [
        'qa_readers' => launch_cleanup_count('users', launch_cleanup_reader_where()) + launch_cleanup_count('subscribers', launch_cleanup_subscriber_where()),
        'test_newsletters' => launch_cleanup_count('newsletter_issues', launch_cleanup_newsletter_where()),
        'ai_drafts' => launch_cleanup_count('ai_drafts') + launch_cleanup_count('ai_story_intakes'),
        'social_drafts' => launch_cleanup_count('social_posts', "status <> 'posted'"),
        'reader_activity' => launch_cleanup_count('reader_saved_articles') + launch_cleanup_count('reader_recent_reads') + launch_cleanup_count('article_reactions'),
        'analytics_events' => launch_cleanup_count('analytics_events'),
        'test_article_drafts' => launch_cleanup_count('articles', launch_cleanup_test_article_where()),
        'newsletter_test_sends' => launch_cleanup_count('newsletter_sends', "send_type = 'test'"),
    ];
}

function launch_cleanup_reader_where(): string
{
    return "role = 'reader' AND (
        email LIKE 'qa-reader-%'
        OR email LIKE 'test%'
        OR email LIKE '%@example.com'
        OR email LIKE '%@example.test'
        OR email LIKE '%+qa%@gmail.com'
        OR display_name LIKE '%QA%'
        OR display_name LIKE '%测试%'
    )";
}

function launch_cleanup_subscriber_where(): string
{
    return "email LIKE 'qa-reader-%'
        OR email LIKE 'test%'
        OR email LIKE '%@example.com'
        OR email LIKE '%@example.test'
        OR email LIKE '%+qa%@gmail.com'
        OR source LIKE '%test%'
        OR source LIKE '%qa%'";
}

function launch_cleanup_newsletter_where(): string
{
    return "subject LIKE '%测试%' OR subject LIKE '%test%' OR subject LIKE '%Test%' OR subject LIKE '%QA%' OR slug LIKE '%test%' OR slug LIKE '%qa%'";
}

function launch_cleanup_test_article_where(): string
{
    return "status <> 'published' AND (
        title LIKE '%测试%'
        OR title LIKE '%test%'
        OR title LIKE '%Test%'
        OR title LIKE '%QA%'
        OR title LIKE '%副本%'
        OR title LIKE '%copy%'
        OR slug LIKE '%test%'
        OR slug LIKE '%qa%'
        OR slug LIKE '%copy%'
    )";
}

function launch_cleanup_execute(array $selected, string $confirm): array
{
    $selected = array_values(array_intersect(array_keys(launch_cleanup_options()), $selected));
    if ($confirm !== '清理上线测试数据') {
        return ['ok' => false, 'message' => '请输入确认短语：清理上线测试数据', 'results' => []];
    }
    if (!$selected) {
        return ['ok' => false, 'message' => '请选择至少一个清理项目。', 'results' => []];
    }

    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。', 'results' => []];
    }

    $results = [];
    try {
        $pdo->beginTransaction();
        foreach ($selected as $key) {
            $results[$key] = launch_cleanup_run_option($pdo, $key);
        }
        $pdo->commit();
        return ['ok' => true, 'message' => '上线测试数据清理完成。', 'results' => $results];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => '清理失败：' . $exception->getMessage(), 'results' => $results];
    }
}

function launch_cleanup_run_option(PDO $pdo, string $key): int
{
    return match ($key) {
        'qa_readers' => launch_cleanup_delete_qa_readers($pdo),
        'test_newsletters' => launch_cleanup_delete_test_newsletters($pdo),
        'ai_drafts' => launch_cleanup_delete_ai_drafts($pdo),
        'social_drafts' => launch_cleanup_delete_where($pdo, 'social_posts', "status <> 'posted'"),
        'reader_activity' => launch_cleanup_delete_reader_activity($pdo),
        'analytics_events' => launch_cleanup_delete_where($pdo, 'analytics_events', '1=1'),
        'test_article_drafts' => launch_cleanup_delete_test_article_drafts($pdo),
        'newsletter_test_sends' => launch_cleanup_delete_where($pdo, 'newsletter_sends', "send_type = 'test'"),
        default => 0,
    };
}

function launch_cleanup_delete_where(PDO $pdo, string $table, string $where): int
{
    if (!launch_cleanup_table_exists($table)) {
        return 0;
    }
    $before = launch_cleanup_count($table, $where);
    $pdo->exec("DELETE FROM {$table} WHERE {$where}");
    return $before;
}

function launch_cleanup_delete_qa_readers(PDO $pdo): int
{
    if (!launch_cleanup_table_exists('users')) {
        return 0;
    }
    $where = launch_cleanup_reader_where();
    $ids = $pdo->query("SELECT id FROM users WHERE {$where}")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $emails = $pdo->query("SELECT email FROM users WHERE {$where}")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!$ids && !$emails) {
        return 0;
    }

    $idList = implode(',', array_map('intval', $ids));
    if ($idList !== '') {
        foreach (['reader_saved_articles', 'reader_recent_reads', 'reader_preferences', 'reader_preference_topics', 'login_providers'] as $table) {
            if (launch_cleanup_table_exists($table)) {
                $pdo->exec("DELETE FROM {$table} WHERE user_id IN ({$idList})");
            }
        }
    }

    if (launch_cleanup_table_exists('subscribers')) {
        if ($emails) {
            $quoted = implode(',', array_map([$pdo, 'quote'], $emails));
            $pdo->exec("DELETE FROM subscribers WHERE email IN ({$quoted}) OR " . launch_cleanup_subscriber_where());
        } else {
            $pdo->exec("DELETE FROM subscribers WHERE " . launch_cleanup_subscriber_where());
        }
    }
    $pdo->exec("DELETE FROM users WHERE {$where}");
    return count($ids);
}

function launch_cleanup_delete_test_newsletters(PDO $pdo): int
{
    if (!launch_cleanup_table_exists('newsletter_issues')) {
        return 0;
    }
    $where = launch_cleanup_newsletter_where();
    $ids = $pdo->query("SELECT id FROM newsletter_issues WHERE {$where}")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!$ids) {
        return 0;
    }
    $idList = implode(',', array_map('intval', $ids));
    foreach (['newsletter_issue_articles', 'newsletter_sends'] as $table) {
        if (launch_cleanup_table_exists($table)) {
            $pdo->exec("DELETE FROM {$table} WHERE issue_id IN ({$idList})");
        }
    }
    $pdo->exec("DELETE FROM newsletter_issues WHERE id IN ({$idList})");
    return count($ids);
}

function launch_cleanup_delete_ai_drafts(PDO $pdo): int
{
    $count = 0;
    if (launch_cleanup_table_exists('ai_drafts')) {
        $ids = $pdo->query('SELECT id FROM ai_drafts')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $idList = implode(',', array_map('intval', $ids));
        if ($idList !== '') {
            foreach (['ai_draft_versions', 'ai_draft_checks'] as $table) {
                if (launch_cleanup_table_exists($table)) {
                    $pdo->exec("DELETE FROM {$table} WHERE draft_id IN ({$idList})");
                }
            }
        }
        $count += launch_cleanup_delete_where($pdo, 'ai_drafts', '1=1');
    }
    $count += launch_cleanup_delete_where($pdo, 'ai_story_intakes', '1=1');
    return $count;
}

function launch_cleanup_delete_reader_activity(PDO $pdo): int
{
    $count = 0;
    foreach (['reader_saved_articles', 'reader_recent_reads', 'article_reactions'] as $table) {
        $count += launch_cleanup_delete_where($pdo, $table, '1=1');
    }
    return $count;
}

function launch_cleanup_delete_test_article_drafts(PDO $pdo): int
{
    if (!launch_cleanup_table_exists('articles')) {
        return 0;
    }
    $where = launch_cleanup_test_article_where();
    $ids = $pdo->query("SELECT id FROM articles WHERE {$where}")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!$ids) {
        return 0;
    }
    $idList = implode(',', array_map('intval', $ids));
    foreach (['article_tags', 'article_audit_logs', 'article_claims', 'article_short_format', 'social_posts', 'reader_saved_articles', 'reader_recent_reads', 'newsletter_issue_articles'] as $table) {
        if (launch_cleanup_table_exists($table)) {
            $column = in_array($table, ['article_tags', 'article_claims', 'article_short_format', 'social_posts', 'reader_saved_articles', 'reader_recent_reads', 'newsletter_issue_articles'], true)
                ? 'article_id'
                : 'article_id';
            $pdo->exec("DELETE FROM {$table} WHERE {$column} IN ({$idList})");
        }
    }
    $pdo->exec("DELETE FROM articles WHERE id IN ({$idList}) AND status <> 'published'");
    return count($ids);
}
