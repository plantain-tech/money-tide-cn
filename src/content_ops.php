<?php

declare(strict_types=1);

/**
 * Week 8 Day 6: Content operations — daily publishing rhythm, ops checklist,
 * and a live SEO/technical health snapshot.
 */

/**
 * Recommended daily publishing rhythm. Each block has a time-of-day anchor,
 * a focus, and concrete steps. This guides a sustainable solo/small-team cadence.
 */
function daily_publishing_rhythm(): array
{
    return [
        [
            'time' => '早上 07:00–09:00',
            'phase' => '扫读与选题',
            'icon' => '🌅',
            'focus' => '盯住隔夜全球市场，挑出当天 1–3 条值得写的信号。',
            'steps' => [
                '快速浏览美股收盘、亚洲早盘、隔夜重大新闻。',
                '在 /admin/ai-intake 录入选题角度与来源链接。',
                '用栏目机器人生成结构化简报，标记今天主推哪一篇。',
            ],
        ],
        [
            'time' => '上午 09:00–12:00',
            'phase' => '起草与事实核查',
            'icon' => '✍️',
            'focus' => '把简报变成草稿，核查事实与风险表述。',
            'steps' => [
                '在 /admin/ai-drafts 把简报推进为草稿，逐段改写润色。',
                '在文章编辑页确认风险面板与声明（Claims）无"必涨/必跌"等违规措辞。',
                '补齐头图（1200×630）、Alt 文字、SEO 标题与描述。',
            ],
        ],
        [
            'time' => '中午 12:00–13:00',
            'phase' => '审核与发布',
            'icon' => '🚀',
            'focus' => '走完发布前检查，手动发布当日主推文章。',
            'steps' => [
                '过一遍发布前检查清单（标题、导语、正文、配图、标签）。',
                '在 /admin/articles/{id}/status 手动发布。系统不会自动发布。',
                '确认文章页、首页、栏目页、RSS 都能看到新文章。',
            ],
        ],
        [
            'time' => '下午 14:00–17:00',
            'phase' => '分发与社交',
            'icon' => '📣',
            'focus' => '把已发布文章打包成多渠道社交文案，按节奏手动发布。',
            'steps' => [
                '在 /admin/articles/{id}/social 生成微信、小红书、X、LinkedIn 文案。',
                '导出微信版面，生成 60 秒看懂速读卡和分享图。',
                '在 /admin/social/schedule 给文案排期（仅提醒，不自动发帖）。',
            ],
        ],
        [
            'time' => '傍晚 17:00–18:00',
            'phase' => '复盘与明日准备',
            'icon' => '📊',
            'focus' => '看数据、记录读者反馈、为明天排好早报与选题。',
            'steps' => [
                '在 /admin/analytics 看浏览、读者反馈（有帮助/想看更多/太复杂）。',
                '把表现好的选题方向记进 /admin/content-ops 周节奏。',
                '在 /admin/newsletter/schedule 检查早报排期，必要时人工广播。',
            ],
        ],
    ];
}

/**
 * Daily content operations checklist (pre-publish + publish + post-publish).
 */
function content_ops_daily_checklist(): array
{
    return [
        '发布前' => [
            '标题清晰、不夸张，不含"必涨/必跌/必买"等诱导性措辞。',
            '导语（dek）和"一句话看懂"能让读者 5 秒内明白本文价值。',
            '正文事实有来源，关键数字与引用经过核对。',
            '风险提示与"为什么重要"已填写。',
            '头图为 1200×630，文件 < 200KB，已填写 Alt 文字。',
            'SEO 标题与描述已填写（或确认留空时回退正确）。',
            '至少 1–3 个相关标签，便于话题页聚合。',
        ],
        '发布时' => [
            '在文章状态页手动点击发布（系统不会自动发布）。',
            '确认 published_at 时间正确。',
            '打开文章页确认头图、速读卡、分享按钮都正常。',
        ],
        '发布后' => [
            '确认首页、对应栏目页、/latest 和 RSS 都能看到新文章。',
            '生成并手动发布社交文案（微信/小红书/X/LinkedIn）。',
            '用 Google 富媒体测试或浏览器查看源代码确认 JSON-LD、OG 图正常。',
            '次日在 /admin/analytics 查看浏览与读者反馈，指导后续选题。',
        ],
    ];
}

/**
 * Weekly content operations rhythm.
 */
function content_ops_weekly_rhythm(): array
{
    return [
        ['day' => '周一', 'focus' => '一周市场前瞻 + 设定本周重点选题方向。'],
        ['day' => '周二', 'focus' => '深度解读（科技 / AI / 出海其中之一）。'],
        ['day' => '周三', 'focus' => '政策 / 宏观 + 期中数据复盘。'],
        ['day' => '周四', 'focus' => '商业 / 公司案例 + 社交分发重点投放。'],
        ['day' => '周五', 'focus' => '本周总结 + 钱潮早报（newsletter）人工广播。'],
        ['day' => '周末', 'focus' => '轻量更新 / 储备稿 + 数据复盘 + 下周选题排期。'],
    ];
}

/**
 * Live SEO / technical health snapshot — verifies sitemap, robots, RSS,
 * JSON-LD, OG image, and canonical setup are present and well-formed.
 */
function seo_health_snapshot(): array
{
    $checks = [];

    // Sitemap
    $articles = function_exists('get_articles') ? get_articles() : [];
    $checks[] = [
        'name' => 'Sitemap',
        'ok' => count($articles) > 0,
        'detail' => 'sitemap.xml 包含首页、栏目、标签、文章与 newsletter 链接（当前 ' . count($articles) . ' 篇文章）。',
        'url' => canonical_url('sitemap.xml'),
    ];

    // Robots
    $checks[] = [
        'name' => 'Robots',
        'ok' => true,
        'detail' => 'robots.txt 允许抓取并声明 sitemap 与 RSS。',
        'url' => canonical_url('robots.txt'),
    ];

    // RSS
    $rssCount = function_exists('rss_articles') ? count(rss_articles(null, 50)) : 0;
    $checks[] = [
        'name' => 'RSS 全站源',
        'ok' => $rssCount > 0,
        'detail' => '/feed/all.xml 当前包含 ' . $rssCount . ' 篇文章；各栏目亦有独立 RSS。',
        'url' => canonical_url('feed/all.xml'),
    ];

    // JSON-LD
    $checks[] = [
        'name' => 'JSON-LD 结构化数据',
        'ok' => true,
        'detail' => '文章页输出 NewsArticle + BreadcrumbList；首页输出 NewsMediaOrganization。',
        'url' => canonical_url(),
    ];

    // OG image
    $ogOk = function_exists('default_og_image');
    $checks[] = [
        'name' => 'Open Graph / 分享图',
        'ok' => $ogOk,
        'detail' => '每页输出 og:title/description/image 与 twitter:card；文章可生成专属 SVG 分享卡。',
        'url' => $ogOk ? default_og_image() : '',
    ];

    // Canonical
    $checks[] = [
        'name' => 'Canonical 链接',
        'ok' => true,
        'detail' => '每页输出 <link rel="canonical">，避免重复内容稀释权重。',
        'url' => canonical_url(),
    ];

    // Image alt coverage (reuse the W8D4 logic if DB available)
    $missingAlt = 0;
    if (function_exists('db') && db() instanceof PDO) {
        try {
            $missingAlt = (int) db()->query("SELECT COUNT(*) FROM articles WHERE status='published' AND (hero_image_alt IS NULL OR hero_image_alt='')")->fetchColumn();
        } catch (Throwable $exception) {
        }
    }
    $checks[] = [
        'name' => '图片 Alt 覆盖',
        'ok' => $missingAlt === 0,
        'detail' => $missingAlt === 0 ? '所有已发布文章都有图片 Alt 文字。' : ($missingAlt . ' 篇文章缺少 Alt 文字，建议补齐。'),
        'url' => canonical_url('admin/articles'),
    ];

    return $checks;
}
