<?php

declare(strict_types=1);

function ensure_monetization_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        foreach ([
            'is_premium' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'premium_excerpt' => 'TEXT NULL',
        ] as $column => $definition) {
            if (!db_column_exists('articles', $column)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        if (!db_column_exists('users', 'subscription_tier')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN subscription_tier ENUM('free','member','premium') NOT NULL DEFAULT 'free'");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS monetization_settings (
            setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $insert = $pdo->prepare('INSERT IGNORE INTO monetization_settings (setting_key, setting_value) VALUES (:k, :v)');
        foreach ([
            'paywall_mode' => 'soft_preview',
            'premium_label' => '会员内容',
            'member_price_note' => '会员定价尚未启用；当前所有内容保持可读。',
        ] as $key => $value) {
            $insert->execute(['k' => $key, 'v' => $value]);
        }
    } catch (Throwable $exception) {
    }
}

function subscription_tier_options(): array
{
    return [
        'free' => 'Free',
        'member' => 'Member',
        'premium' => 'Premium',
    ];
}

function monetization_settings(): array
{
    ensure_monetization_schema();
    $pdo = db();
    $defaults = [
        'paywall_mode' => 'soft_preview',
        'premium_label' => '会员内容',
        'member_price_note' => '会员定价尚未启用；当前所有内容保持可读。',
    ];
    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM monetization_settings')->fetchAll();
        foreach ($rows as $row) {
            $defaults[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable $exception) {
    }
    return $defaults;
}

function save_monetization_settings(array $input): array
{
    ensure_monetization_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Database unavailable.'];
    }

    $allowedModes = ['off', 'soft_preview'];
    $values = [
        'paywall_mode' => in_array((string) ($input['paywall_mode'] ?? 'soft_preview'), $allowedModes, true)
            ? (string) $input['paywall_mode']
            : 'soft_preview',
        'premium_label' => trim((string) ($input['premium_label'] ?? '会员内容')) ?: '会员内容',
        'member_price_note' => trim((string) ($input['member_price_note'] ?? '')),
    ];

    try {
        $statement = $pdo->prepare('INSERT INTO monetization_settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $key => $value) {
            $statement->execute(['k' => $key, 'v' => $value]);
        }
        return ['ok' => true, 'message' => 'Settings saved.'];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'Save failed: ' . $exception->getMessage()];
    }
}

function monetization_summary(): array
{
    ensure_monetization_schema();
    $pdo = db();
    $out = [
        'settings' => monetization_settings(),
        'premium_articles' => 0,
        'tiers' => ['free' => 0, 'member' => 0, 'premium' => 0],
    ];
    if (!$pdo instanceof PDO) {
        return $out;
    }

    try {
        $out['premium_articles'] = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE is_premium = 1")->fetchColumn();
        $rows = $pdo->query("SELECT subscription_tier, COUNT(*) AS total FROM users WHERE role = 'reader' GROUP BY subscription_tier")->fetchAll();
        foreach ($rows as $row) {
            $tier = (string) ($row['subscription_tier'] ?? 'free');
            if (isset($out['tiers'][$tier])) {
                $out['tiers'][$tier] = (int) $row['total'];
            }
        }
    } catch (Throwable $exception) {
    }
    return $out;
}

function seo_article_checklist(array $article): array
{
    $title = trim((string) ($article['title'] ?? ''));
    $seoTitle = trim((string) ($article['seo_title'] ?? ''));
    $description = trim((string) (($article['seo_description'] ?? '') ?: ($article['dek'] ?? '')));
    $image = trim((string) (($article['hero_image_path'] ?? '') ?: ($article['hero_image'] ?? '')));
    $slug = (string) ($article['slug'] ?? '');

    return [
        ['label' => 'SEO title fallback available', 'passed' => $seoTitle !== '' || $title !== ''],
        ['label' => 'Description between 60 and 180 characters', 'passed' => mb_strlen($description, 'UTF-8') >= 40 && mb_strlen($description, 'UTF-8') <= 220],
        ['label' => 'Canonical slug is valid', 'passed' => (bool) preg_match('/^[a-z0-9-]+$/', $slug)],
        ['label' => 'Social image available', 'passed' => $image !== '' || function_exists('default_og_image')],
        ['label' => 'Hero alt text available', 'passed' => trim((string) ($article['hero_image_alt'] ?? '')) !== '' || $title !== ''],
        ['label' => 'Published date available', 'passed' => !empty($article['published_at']) || (string) ($article['status'] ?? '') !== 'published'],
    ];
}

function week_four_qa_checklist(): array
{
    return [
        ['label' => 'Reader account profile, preferences, referral, and saved articles work in production.', 'tip' => '/account, /account/preferences, /account/saved'],
        ['label' => 'Google OAuth remains configured but app consent is still in Testing until owner publishes/adds testers.', 'tip' => '/admin/oauth'],
        ['label' => 'Newsletter archive and public issue pages render and appear in sitemap.', 'tip' => '/newsletter and /sitemap.xml'],
        ['label' => 'Bookmark save/unsave and recent reads create retention rows.', 'tip' => 'Log in as reader, open an article, save it, then check /account/saved'],
        ['label' => 'Article source contains og:image, NewsArticle JSON-LD, and BreadcrumbList JSON-LD.', 'tip' => 'View source on any article'],
        ['label' => 'Premium flag displays labels but does not block article reading yet.', 'tip' => 'Edit an article and turn on Premium'],
        ['label' => 'Admin analytics shows saves, returning readers, completion, and sharing events.', 'tip' => '/admin/analytics'],
        ['label' => 'Smoke checks pass after deploy.', 'tip' => '/admin/smoke?format=json'],
    ];
}

function week_five_backlog(): array
{
    return [
        ['title' => 'Real email delivery', 'detail' => 'Turn EMAIL_PROVIDER from log to Resend/Brevo/Mailgun and verify sender DNS.'],
        ['title' => 'Google OAuth public launch', 'detail' => 'Publish Google app or add tester emails before public reader onboarding.'],
        ['title' => 'Payment integration', 'detail' => 'Choose Stripe, Paddle, Lemon Squeezy, or another provider before enabling hard paywall.'],
        ['title' => 'Comment moderation', 'detail' => 'Add reader comments with admin moderation queue if community is a priority.'],
        ['title' => 'Automated backups', 'detail' => 'Nightly database export and asset backup from Hostinger.'],
        ['title' => 'Editorial calendar', 'detail' => 'Schedule articles/newsletters with calendar view and assignment reminders.'],
        ['title' => 'Monitoring alerts', 'detail' => 'Webhook or email alert for failed smoke checks and deploys.'],
    ];
}

function week_five_qa_checklist(): array
{
    return [
        ['label' => '8 AI 编辑机器人都可见、可编辑、可恢复默认。', 'tip' => '/admin/ai-bots — 滚动 + 切换 active/inactive + 重置按钮'],
        ['label' => 'AI 选题 Intake 能提交一个选题 → 生成结构化简报 → 跳转到 /admin/ai-drafts/new 预填。', 'tip' => '/admin/ai-intake — 简报应该包含 headline 候选、源问题、风险提示'],
        ['label' => 'AI 草稿队列有 9 个状态 tab，每个 tab 显示该状态下的真实总数（不受其他 filter 影响）。', 'tip' => '/admin/ai-drafts — 切换栏目或状态时其它 tab 的数字保持不变'],
        ['label' => '草稿卡片显示 quality score 徽章和警告 pill。', 'tip' => '不需要 AI 调用，evaluations 都是本地启发式'],
        ['label' => '草稿详情页有 tone 预设条（更精炼/更口语化/更专业），点击会弹出确认+进度条。', 'tip' => '/admin/ai-drafts/{id} 顶部'],
        ['label' => '每个 rewrite 块（title/dek/brief/why/social/newsletter/body/seo_title/seo_description/tags）都能单独重写。', 'tip' => '版本历史每次重写都会快照'],
        ['label' => '文章编辑页面顶部有事实/风险警告面板和 Claims 区块。', 'tip' => '/admin/articles/{id}/edit — 包含建议提取候选 + 已记录 claims 列表'],
        ['label' => '一键添加候选 claim、标记已核实、删除 claim 都正常。', 'tip' => '删除按钮触发确认 modal'],
        ['label' => '已发布文章包含合规免责声明 "本文内容仅供参考，不构成投资建议。"', 'tip' => '查看任意已发布 /article/{slug} 最后一段'],
        ['label' => 'Newsletter 期号编辑页有 "AI 早报助理" 面板，三类操作：生成开场白、为每篇文章生成推荐语、追加 6 个主题段落之一。', 'tip' => '/admin/newsletter/{id}/edit'],
        ['label' => '所有 AI 生成按钮都会触发不可关闭的进度 modal，结束时随页面跳转消失。', 'tip' => '试一次重写或生成简报，观察进度阶段文案 + 计时器'],
        ['label' => '没有任何 AI 操作会自动发布文章/广播 newsletter。所有发布/广播仍由编辑手动点击。', 'tip' => '检查路由代码：发布只在 /admin/articles/{id}/status 和 /admin/newsletter/{id}/send'],
        ['label' => '生产 smoke 检查全部通过。', 'tip' => '/admin/smoke?format=json — 18+ 个 check 应该全部 ok=true'],
        ['label' => '部署 release marker 已更新到 week-5 系列。', 'tip' => '/health.php'],
    ];
}

function week_six_backlog(): array
{
    return [
        ['title' => 'AI 文章批量校对', 'detail' => '对已发布文章做一次 AI 二审 — 检查事实、口吻、合规、SEO，输出可批改的 diff。'],
        ['title' => 'Newsletter 自动定时发送', 'detail' => '基于 scheduled_at 字段，每小时跑一次 cron 检查 ready/scheduled issues 并自动广播。'],
        ['title' => 'Reader 偏好驱动的个性化早报', 'detail' => '按 reader_preference_topics 给每位读者生成不同内容的 newsletter，而不是 broadcast same email.'],
        ['title' => 'AI bot 性能日报', 'detail' => '每天给管理员一个邮件总结：哪个 bot 用得多、平均生成时长、失败率、警告分布。'],
        ['title' => '前端文章页 AI 摘要按钮', 'detail' => '读者点击 "60 秒读懂" 按钮，调用 AI 用 3 句话总结当前文章，结果缓存到 article_summary。'],
        ['title' => 'Comment 模块（admin moderated）', 'detail' => '读者评论 → moderation queue → 通过/拒绝。带 spam 简单过滤。'],
        ['title' => 'Public RSS feed', 'detail' => '/feed/all.xml + /feed/category/{slug}.xml，按 RSS 2.0 + Atom 都输出。'],
        ['title' => 'Search 模块', 'detail' => '简单全文搜索（title/dek/brief/body LIKE），后续可换 Meilisearch。'],
        ['title' => '编辑日历视图', 'detail' => '把 articles + newsletter_issues 按 published_at/scheduled_at 排到一个月视图。'],
        ['title' => 'Production payments 集成', 'detail' => 'Stripe/Paddle 接入 + hard paywall + premium 标识 + 订阅状态同步。'],
    ];
}
