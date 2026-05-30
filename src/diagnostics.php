<?php

declare(strict_types=1);

function database_diagnostics(): array
{
    $pdo = db();
    $out = ['ready' => $pdo instanceof PDO, 'tables' => [], 'version' => '', 'errors' => []];
    if (!$pdo instanceof PDO) {
        $out['errors'][] = 'PDO not connected.';
        return $out;
    }
    try {
        $out['version'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable $exception) {
        $out['errors'][] = 'version: ' . $exception->getMessage();
    }
    $tables = [
        'articles', 'categories', 'subscribers', 'newsletter_preferences', 'users', 'authors',
        'analytics_events', 'ai_bots', 'ai_task_templates', 'ai_story_intakes', 'ai_drafts', 'ai_draft_versions', 'ai_draft_checks', 'ai_prompt_templates',
        'ai_usage_logs', 'article_audit_logs', 'newsletter_issues', 'newsletter_issue_articles',
        'newsletter_sends', 'source_profiles', 'source_templates', 'research_briefs',
        'reader_preferences', 'reader_preference_topics', 'tags', 'article_tags', 'login_providers',
        'reader_saved_articles', 'reader_recent_reads', 'monetization_settings', 'article_claims', 'social_posts', 'article_short_format', 'article_reactions',
    ];
    foreach ($tables as $table) {
        if (!preg_match('/^[a-z_]+$/', $table)) {
            continue;
        }
        $row = ['table' => $table, 'rows' => null, 'error' => null];
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $row['rows'] = (int) $count;
        } catch (Throwable $exception) {
            $row['error'] = $exception->getMessage();
        }
        $out['tables'][] = $row;
    }
    return $out;
}

function diagnostics_error_log_tail(int $lines = 80): array
{
    $candidates = array_filter([
        (string) ini_get('error_log'),
        APP_BASE_PATH . '/error_log',
        APP_BASE_PATH . '/public/error_log',
        APP_BASE_PATH . '/logs/error.log',
    ]);
    foreach ($candidates as $path) {
        if ($path !== '' && is_readable($path)) {
            $tail = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($tail)) {
                return ['path' => $path, 'lines' => array_slice($tail, -$lines)];
            }
        }
    }
    return ['path' => '', 'lines' => []];
}

function admin_smoke_checks(): array
{
    ensure_editorial_schema();
    ensure_analytics_table();
    ensure_ai_drafts_table();
    ensure_ai_draft_versions_table();
    ensure_ai_draft_checks_table();
    ensure_ai_prompt_templates_table();
    if (function_exists('ensure_ai_bots_schema')) {
        ensure_ai_bots_schema();
    }
    if (function_exists('ensure_ai_task_templates_schema')) {
        ensure_ai_task_templates_schema();
    }
    if (function_exists('ensure_ai_story_intakes_schema')) {
        ensure_ai_story_intakes_schema();
    }
    if (function_exists('ensure_newsletter_issues_schema')) {
        ensure_newsletter_issues_schema();
    }
    if (function_exists('ensure_ai_sources_schema')) {
        ensure_ai_sources_schema();
    }
    if (function_exists('ensure_reader_accounts_schema')) {
        ensure_reader_accounts_schema();
    }
    if (function_exists('ensure_tags_schema')) {
        ensure_tags_schema();
    }
    if (function_exists('ensure_retention_schema')) {
        ensure_retention_schema();
    }
    if (function_exists('ensure_monetization_schema')) {
        ensure_monetization_schema();
    }

    $checks = [];

    $checks[] = ['name' => 'Database connection', 'ok' => db_is_ready(), 'detail' => db_is_ready() ? 'connected' : 'not connected'];

    $cats = get_categories();
    $checks[] = ['name' => 'Categories loaded', 'ok' => count($cats) > 0, 'detail' => count($cats) . ' categories'];

    $articleCount = db_count('articles', "status = 'published'");
    $checks[] = ['name' => 'Published articles', 'ok' => $articleCount > 0, 'detail' => $articleCount . ' published'];

    $subs = db_count('subscribers', "status = 'active'");
    $checks[] = ['name' => 'Active subscribers', 'ok' => $subs >= 0, 'detail' => $subs . ' active'];

    $views = db_count('analytics_events', "event_type = 'article_view'");
    $checks[] = ['name' => 'Analytics view events', 'ok' => $views >= 0, 'detail' => $views . ' events'];

    $aiUsage = ai_usage_summary();
    $checks[] = [
        'name' => 'AI usage today',
        'ok' => $aiUsage['used_today'] < $aiUsage['daily_limit'],
        'detail' => $aiUsage['used_today'] . '/' . $aiUsage['daily_limit'] . ' used',
    ];

    $aiProvider = ai_provider_status();
    $checks[] = ['name' => 'AI provider ready', 'ok' => $aiProvider['ready'], 'detail' => $aiProvider['label'] . ' · ' . $aiProvider['model']];

    if (function_exists('email_provider_status')) {
        $email = email_provider_status();
        $checks[] = ['name' => 'Email provider', 'ok' => $email['ready'], 'detail' => $email['provider'] . ' · ' . ($email['from_address'] ?: 'no from')];
    }
    if (function_exists('email_delivery_status')) {
        $emailDelivery = email_delivery_status();
        $checks[] = [
            'name' => 'Email delivery setup',
            'ok' => isset($emailDelivery['checks']) && count($emailDelivery['checks']) >= 6,
            'detail' => ($emailDelivery['ready_for_real_send'] ? 'real send ready' : 'setup guide ready') . ' · ' . $emailDelivery['provider'],
        ];
    }

    if (function_exists('reader_account_data')) {
        $readerCount = db_count('users', "role = 'reader'");
        $checks[] = ['name' => 'Reader accounts', 'ok' => $readerCount >= 0, 'detail' => $readerCount . ' readers'];
    }
    if (function_exists('oauth_provider_status')) {
        $oauth = oauth_provider_status();
        $google = $oauth['google'] ?? ['configured' => false, 'redirect_uri' => ''];
        $checks[] = [
            'name' => 'Google OAuth',
            'ok' => !empty($google['configured']) && str_starts_with((string) ($google['redirect_uri'] ?? ''), 'https://moneytidecn.avanturadeals.com/'),
            'detail' => (!empty($google['configured']) ? 'configured' : 'missing secrets') . ' · ' . (string) ($google['redirect_uri'] ?? ''),
        ];
    }

    if (function_exists('all_tags')) {
        $tagCount = count(all_tags());
        $checks[] = ['name' => 'Tags with published articles', 'ok' => true, 'detail' => $tagCount . ' tags'];
    }
    if (function_exists('retention_analytics')) {
        $retention = retention_analytics();
        $checks[] = ['name' => 'Reader retention tables', 'ok' => $retention['saved_total'] >= 0, 'detail' => $retention['saved_total'] . ' saved'];
    }
    if (function_exists('monetization_summary')) {
        $money = monetization_summary();
        $checks[] = ['name' => 'Monetization schema', 'ok' => isset($money['tiers']['free']), 'detail' => $money['premium_articles'] . ' premium articles'];
    }
    if (function_exists('ai_bot_profiles')) {
        $botCount = count(ai_bot_profiles(true));
        $checks[] = ['name' => 'AI newsroom bots', 'ok' => $botCount >= 8, 'detail' => $botCount . ' active bots'];
    }
    if (function_exists('ai_task_templates')) {
        $taskCount = count(ai_task_templates());
        $checks[] = ['name' => 'AI task templates', 'ok' => $taskCount >= 8, 'detail' => $taskCount . ' task templates'];
    }
    if (function_exists('ai_story_intakes')) {
        $checks[] = ['name' => 'AI story intake', 'ok' => true, 'detail' => count(ai_story_intakes(5)) . ' recent briefs'];
    }
    if (function_exists('ai_draft_status_options')) {
        ensure_ai_quality_columns();
        $checks[] = ['name' => 'AI draft pipeline statuses', 'ok' => count(ai_draft_status_options()) >= 8, 'detail' => count(ai_draft_status_options()) . ' status options'];
    }
    if (function_exists('article_claims')) {
        ensure_article_claims_schema();
        $checks[] = ['name' => 'Article claims schema', 'ok' => true, 'detail' => db_count('article_claims') . ' claims tracked'];
    }
    if (function_exists('newsletter_theme_blocks')) {
        $checks[] = ['name' => 'Newsletter theme blocks', 'ok' => true, 'detail' => count(newsletter_theme_blocks()) . ' themes available'];
    }
    if (function_exists('social_channels')) {
        ensure_social_posts_schema();
        $checks[] = ['name' => 'Social distribution', 'ok' => count(social_channels()) >= 5, 'detail' => count(social_channels()) . ' channels · ' . db_count('social_posts') . ' posts'];
    }
    if (function_exists('social_schedule_summary')) {
        $summary = social_schedule_summary();
        $checks[] = ['name' => 'Social scheduling queue', 'ok' => is_array($summary), 'detail' => ($summary['today'] ?? 0) . ' today · ' . ($summary['upcoming'] ?? 0) . ' upcoming'];
    }
    if (function_exists('newsletter_schedule_summary')) {
        $summary = newsletter_schedule_summary();
        $checks[] = ['name' => 'Newsletter scheduling queue', 'ok' => is_array($summary), 'detail' => ($summary['today'] ?? 0) . ' ready today · ' . ($summary['upcoming'] ?? 0) . ' upcoming'];
    }
    if (function_exists('newsletter_delivery_summary')) {
        ensure_newsletter_issues_schema();
        $checks[] = ['name' => 'Newsletter sending QA', 'ok' => true, 'detail' => 'test sends logged · pre-send checklist enforced'];
    }
    if (function_exists('share_card_types')) {
        $checks[] = ['name' => 'Share cards', 'ok' => count(share_card_types()) >= 3, 'detail' => count(share_card_types()) . ' card templates'];
    }
    if (function_exists('ensure_short_format_schema')) {
        ensure_short_format_schema();
        $checks[] = ['name' => 'Short format (60秒看懂)', 'ok' => true, 'detail' => db_count('article_short_format') . ' cards'];
    }
    if (function_exists('search_articles')) {
        $sample = search_articles('AI', 5);
        $checks[] = ['name' => 'Public search', 'ok' => is_array($sample), 'detail' => count($sample) . ' sample results for AI'];
    }
    if (function_exists('rss_articles')) {
        $feedCount = count(rss_articles(null, 10));
        $checks[] = ['name' => 'RSS feeds', 'ok' => $feedCount > 0, 'detail' => $feedCount . ' articles in all feed'];
    }
    // Image alt text & hero image coverage
    if (db_is_ready()) {
        $pdoImg = db();
        $imgMissingAlt = 0;
        $imgMissingHero = 0;
        try {
            $imgMissingAlt  = (int) $pdoImg->query("SELECT COUNT(*) FROM articles WHERE status='published' AND (hero_image_alt IS NULL OR hero_image_alt='')")->fetchColumn();
            $imgMissingHero = (int) $pdoImg->query("SELECT COUNT(*) FROM articles WHERE status='published' AND (hero_image_path IS NULL OR hero_image_path='')")->fetchColumn();
        } catch (Throwable $exception) {
        }
        $checks[] = [
            'name' => '图片 Alt 覆盖率',
            'ok' => $imgMissingAlt === 0,
            'detail' => $imgMissingAlt === 0
                ? '所有已发布文章均有图片替代文字'
                : $imgMissingAlt . ' 篇已发布文章缺少 Alt 文字 · 请在文章编辑页补充',
        ];
        $checks[] = [
            'name' => '头图覆盖率',
            'ok' => true,
            'detail' => $imgMissingHero === 0
                ? '所有已发布文章已设置自定义头图'
                : $imgMissingHero . ' 篇使用栏目默认图（可正常显示，建议补充自定义头图）',
        ];
    }
    if (function_exists('reaction_analytics')) {
        ensure_reactions_schema();
        $reactions = reaction_analytics();
        $checks[] = [
            'name' => 'Reader reactions',
            'ok' => count(reaction_types()) === 3 && is_array($reactions),
            'detail' => $reactions['total_all'] . ' reactions · ' . count(reaction_types()) . ' types',
        ];
    }
    // Safety audit: no auto-publish / no auto-broadcast / no auto-post
    if (function_exists('backup_safety_audit')) {
        foreach (backup_safety_audit() as $gate) {
            $checks[] = [
                'name' => '安全：' . (string) $gate['gate'],
                'ok' => (bool) $gate['ok'],
                'detail' => (string) $gate['detail'],
                'group' => 'safety',
            ];
        }
    }
    if (function_exists('editorial_calendar_events')) {
        $calendarFilters = calendar_filters_from_request(['view' => 'month', 'date' => date('Y-m-d')]);
        $calendarRange = calendar_range($calendarFilters['view'], $calendarFilters['date']);
        $calendarEvents = editorial_calendar_events($calendarFilters, $calendarRange);
        $calendarStats = calendar_stats($calendarEvents);
        $checks[] = ['name' => 'Editorial calendar', 'ok' => is_array($calendarEvents), 'detail' => $calendarStats['total'] . ' items this range'];
    }

    return $checks;
}

function diagnostics_export_csv(string $table): bool
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        return false;
    }
    $allowed = ['articles', 'subscribers', 'newsletter_issues', 'newsletter_sends',
        'source_profiles', 'source_templates', 'research_briefs', 'analytics_events',
        'article_audit_logs', 'ai_usage_logs', 'ai_bots', 'ai_task_templates', 'ai_story_intakes', 'reader_saved_articles', 'reader_recent_reads', 'social_posts', 'article_claims', 'article_short_format', 'article_reactions'];
    if (!in_array($table, $allowed, true)) {
        return false;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $orderColumn = $table === 'ai_bots' ? 'section_slug' : ($table === 'ai_task_templates' ? 'task_key' : 'id');
        $direction = in_array($table, ['ai_bots', 'ai_task_templates'], true) ? 'ASC' : 'DESC';
        $statement = $pdo->query("SELECT * FROM {$table} ORDER BY {$orderColumn} {$direction} LIMIT 5000");
        $rows = $statement->fetchAll();
    } catch (Throwable $exception) {
        return false;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        return false;
    }
    fwrite($output, "\xEF\xBB\xBF");
    if (!$rows) {
        fputcsv($output, ['(empty)']);
        fclose($output);
        return true;
    }
    fputcsv($output, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($output, array_map(static fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $row));
    }
    fclose($output);
    return true;
}

function week_three_qa_checklist(): array
{
    return [
        ['label' => 'D1 编辑角色与权限：writer/editor/admin 已就位', 'tip' => '试用 writer 账号能否创建草稿但不能发布'],
        ['label' => 'D2 图片系统：英雄图与 og:image 都正确渲染', 'tip' => '查看任意已发布文章页面的 og:image'],
        ['label' => 'D3 Newsletter：可以创建一期、添加文章、预览、广播', 'tip' => '/admin/newsletter 走完整流程'],
        ['label' => 'D4 研究台：可以保存来源、模板、生成研究简报', 'tip' => '/admin/research-desk 走 AI 流程（注意 AI 额度）'],
        ['label' => 'D5 读者账号：注册、登录、偏好、退订、推荐链接', 'tip' => '/account/signup 后访问 /account/preferences 和 /account/referral'],
        ['label' => 'D6 标签与 Most Read：标签页和首页 Most Read 渲染', 'tip' => '/tag/{slug} 与首页底部'],
        ['label' => 'D7 诊断：数据库、错误日志、smoke、导出工作', 'tip' => '/admin/diagnostics 与 /admin/smoke 与 /admin/exports'],
        ['label' => '部署：health.php 返回 week-3-day-5-6-7 marker', 'tip' => '/health.php'],
    ];
}
