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
        return ['ok' => false, 'message' => '数据库暂不可用。'];
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
        return ['ok' => true, 'message' => '设置已保存。'];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '保存失败：' . $exception->getMessage()];
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
        ['label' => 'SEO 标题有可用兜底', 'passed' => $seoTitle !== '' || $title !== ''],
        ['label' => '描述长度适合搜索展示', 'passed' => mb_strlen($description, 'UTF-8') >= 40 && mb_strlen($description, 'UTF-8') <= 220],
        ['label' => 'Canonical slug 格式正确', 'passed' => (bool) preg_match('/^[a-z0-9-]+$/', $slug)],
        ['label' => '社交分享图可用', 'passed' => $image !== '' || function_exists('default_og_image')],
        ['label' => '头图 alt 文案可用', 'passed' => trim((string) ($article['hero_image_alt'] ?? '')) !== '' || $title !== ''],
        ['label' => '发布日期可用', 'passed' => !empty($article['published_at']) || (string) ($article['status'] ?? '') !== 'published'],
        ['label' => 'NewsArticle 结构化数据可生成', 'passed' => function_exists('article_jsonld') && $slug !== ''],
        ['label' => '关键词自然、无堆砌（反作弊）', 'passed' => !function_exists('seo_anti_stuffing') || (bool) (seo_anti_stuffing($article)['ok'] ?? true), 'note' => function_exists('seo_anti_stuffing') && empty(seo_anti_stuffing($article)['ok']) ? ('「' . (string) seo_anti_stuffing($article)['term'] . '」出现 ' . (int) seo_anti_stuffing($article)['count'] . ' 次，超过建议上限 ' . (int) seo_anti_stuffing($article)['max']) : ''],
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

function week_six_qa_checklist(): array
{
    return [
        ['label' => '社交分发中心 /admin/social 加载，状态 tab 计数正确，可按渠道和关键词过滤。', 'tip' => '/admin/social'],
        ['label' => '单篇社交工作台显示 5 个渠道卡（微信/小红书/LinkedIn/X/Newsletter 短推荐），字数计数器超限变红。', 'tip' => '/admin/articles/{id}/social'],
        ['label' => 'AI 生成文案：首次无确认弹窗，重新生成有确认弹窗，过程显示不可关闭进度 modal。', 'tip' => '每渠道生成消耗 1 个 AI 额度'],
        ['label' => '复制文案 / 复制文案+hashtags 按钮可用，点击后短暂显示"已复制 ✓"。', 'tip' => '粘贴到记事本验证'],
        ['label' => '社交文案状态流转：草稿 → 可发布 → 已手动发布 → 归档，chip 颜色随之变化。', 'tip' => '下拉选择即提交'],
        ['label' => '微信版面导出 /admin/articles/{id}/wechat-export 渲染干净版面，复制 HTML / 复制纯文本 / 查看源代码都可用。', 'tip' => '粘贴到富文本编辑器验证样式'],
        ['label' => '分享卡片 3 种 SVG（标题卡/60秒看懂卡/金句卡）渲染，可下载、可复制地址。', 'tip' => '/admin/articles/{id}/share-cards'],
        ['label' => '社交图覆盖保存后，文章 og:image 按 覆盖→英雄图→栏目兜底→自动卡 顺序解析。', 'tip' => '查看文章源代码 og:image'],
        ['label' => '60秒看懂：可 AI 生成、手动编辑、复制文本、删除；前台文章页渲染速读卡 + 复制速读按钮。', 'tip' => '/admin/articles/{id}/short-format 和 /article/{slug}#short-format'],
        ['label' => '文章页分享按钮带 UTM 参数（utm_source=渠道, utm_medium=social）；底部有读者分享提示块。', 'tip' => '查看文章源代码的 share 链接'],
        ['label' => '传播分析 /admin/social-analytics 显示分享数、渠道、社交回流、推荐订阅。', 'tip' => '先在无痕标签里点几次分享按钮再回来看'],
        ['label' => '移动端布局：社交卡、分享卡网格、60秒看懂卡在窄屏下单列、无横向溢出。', 'tip' => '手机或浏览器 DevTools 切到 375px 宽'],
        ['label' => '所有删除/重置/广播按钮使用统一的品牌确认 modal（不是浏览器原生弹窗）。', 'tip' => '随便点一个删除按钮'],
        ['label' => '生产 smoke 全部通过；release marker 已更新到 week-6 系列。', 'tip' => '/admin/smoke?format=json 与 /health.php'],
    ];
}

function week_seven_backlog(): array
{
    return [
        ['title' => '分享卡转 PNG', 'detail' => '可选：用 GD/Imagick 把 SVG 分享卡栅格化成 PNG，方便某些只接受位图的平台（小红书图文）。'],
        ['title' => '社交发布排期', 'detail' => '给 social_posts 加 scheduled_at，配合 cron 在到点时提醒编辑（不自动发，仍人工）。'],
        ['title' => 'Newsletter 定时自动广播', 'detail' => '基于 newsletter_issues.scheduled_at 的 cron，把 ready 期号在到点时自动发送。'],
        ['title' => '个性化早报', 'detail' => '按 reader_preference_topics 给不同读者拼装不同文章组合的 newsletter。'],
        ['title' => '前台 Search', 'detail' => 'title/dek/brief/body 的全文检索页，含分页和栏目过滤。'],
        ['title' => 'Public RSS / Atom feed', 'detail' => '/feed/all.xml 和 /feed/category/{slug}.xml，方便聚合器和老读者。'],
        ['title' => 'Comment 模块', 'detail' => '读者评论 + admin 审核队列 + 简单反垃圾。'],
        ['title' => '编辑日历', 'detail' => '把文章和 newsletter 按时间排进月历视图，便于排期。'],
        ['title' => '邮件真实投递上线', 'detail' => '把 EMAIL_PROVIDER 从 log 切到 Resend/Brevo/Mailgun，配置发件域名 DNS。'],
        ['title' => 'Google OAuth 正式发布', 'detail' => '在 Google Cloud 把应用从 Testing 切到 In production，开放公众注册。'],
        ['title' => 'Payments 与会员墙', 'detail' => 'Stripe/Paddle 接入 + 硬付费墙 + premium 内容门控。'],
    ];
}

function week_seven_qa_checklist(): array
{
    return [
        ['label' => '公开搜索可用，页头搜索框、移动菜单入口、空结果兜底和关键词高亮都正常。', 'tip' => '/search?q=AI；再试一个无结果关键词，确认会显示最新文章兜底。'],
        ['label' => 'RSS feed 可访问，包含标题、链接、摘要、发布时间和栏目。', 'tip' => '/feed/all.xml 与 /feed/category/tech.xml。'],
        ['label' => '文章页、首页和栏目页的 head 中包含 RSS alternate 链接。', 'tip' => '查看源代码，确认有 application/rss+xml。'],
        ['label' => '编辑日历可以按月/周查看文章和早报，状态/栏目/编辑筛选不会造成横向溢出。', 'tip' => '/admin/calendar，在手机宽度下检查。'],
        ['label' => '社交发布队列只做人工提醒，不会自动发帖。', 'tip' => '/admin/social/schedule，只能编辑、复制、标记状态。'],
        ['label' => '早报排期只做发送前准备，不会自动广播。', 'tip' => '/admin/newsletter/schedule，真正发送仍必须进入期号后手动点击广播。'],
        ['label' => '读者反馈按钮在文章页可点击，管理后台能看到“有帮助 / 想看更多 / 太复杂”的统计。', 'tip' => '/article/{slug} 与 /admin/analytics。'],
        ['label' => '移动端搜索、日历、社交队列、早报队列、检查清单没有横向滚动或按钮文字挤压。', 'tip' => '用 375px 宽度检查 /search、/admin/calendar、/admin/social/schedule、/admin/newsletter/schedule。'],
        ['label' => '所有高风险动作仍由人工触发：发布文章、发送早报、社交平台发布都没有自动化。', 'tip' => '发布只走 /admin/articles/{id}/status；早报广播只走 /admin/newsletter/{id}/send。'],
        ['label' => '生产 smoke 全部通过，release marker 已更新到 week-7-day-7。', 'tip' => '/admin/smoke?format=json 与 /health.php。'],
    ];
}

function week_eight_backlog(): array
{
    return [
        ['title' => '真实邮件投递上线', 'detail' => '接入 Resend/Brevo/Mailgun，配置发件域名 DNS、退订头和发送日志，让钱潮早报真正投递到用户邮箱。'],
        ['title' => 'Google OAuth 正式公开', 'detail' => '确认 Google Cloud OAuth consent screen 已从 Testing 切到 Production，并补齐隐私政策、服务条款链接。'],
        ['title' => '全站性能和缓存', 'detail' => '为首页、文章页、RSS、分享卡加入更清晰的缓存策略，压缩静态资源，减少 Hostinger shared hosting 压力。'],
        ['title' => '图片压缩与媒体治理', 'detail' => '上传图片自动限制尺寸、生成轻量版本、补齐 alt 文案检查，避免文章页加载过慢。'],
        ['title' => '自动备份与恢复演练', 'detail' => '每日导出数据库和关键上传文件，保存到远端或下载包，并写一份恢复流程。'],
        ['title' => '错误日志与告警', 'detail' => '把 /admin/smoke?format=json 接入监控提醒；关键 AI、邮件、数据库错误进入 admin diagnostics。'],
        ['title' => '评论或深度反馈 Beta', 'detail' => '在现有 reactions 基础上，评估是否加短评论、审核队列和反垃圾规则。'],
        ['title' => '个性化早报', 'detail' => '根据 reader_preference_topics 拼装不同早报版本，先做预览和人工发送，不自动群发。'],
        ['title' => '付费会员前置验证', 'detail' => '继续保持内容可读，先测试 premium 标签、会员权益页和转化 CTA，再决定 Stripe/Paddle。'],
        ['title' => 'Week 8 发布复盘', 'detail' => '汇总搜索词、RSS访问、阅读反馈、保存/分享和订阅来源，决定第一个增长重点。'],
    ];
}
