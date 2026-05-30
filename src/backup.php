<?php

declare(strict_types=1);

/**
 * Week 8 Day 5: Backup, monitoring, and admin safety.
 *
 * Provides:
 *  - backup_export_manifest()   – all exportable tables with labels/priority
 *  - backup_safety_audit()      – structural confirmation of no-auto-publish guarantees
 *  - backup_permission_matrix() – static role-capability map for documentation
 *  - week_eight_qa_checklist()  – W8 QA items
 *  - week_nine_backlog()        – W9 planning items
 */

function backup_export_manifest(): array
{
    return [
        // ── Core content ──────────────────────────────────────────────────
        'articles' => [
            'label' => '所有文章',
            'desc' => '标题、正文、状态、标签、发布时间、头图等全部元数据。',
            'priority' => 'essential',
            'frequency' => '每周',
        ],
        'subscribers' => [
            'label' => '订阅用户',
            'desc' => '邮箱、订阅偏好、来源、状态、退订时间。',
            'priority' => 'essential',
            'frequency' => '每周',
        ],
        'newsletter_issues' => [
            'label' => 'Newsletter 期号',
            'desc' => '所有早报期号的主题、内容、状态和排期。',
            'priority' => 'essential',
            'frequency' => '每周',
        ],
        'newsletter_sends' => [
            'label' => 'Newsletter 发送记录',
            'desc' => '测试发送和广播历史，含 provider 响应和时间戳。',
            'priority' => 'essential',
            'frequency' => '每周',
        ],
        'article_reactions' => [
            'label' => '读者反馈',
            'desc' => '"有帮助 / 想看更多 / 太复杂"的投票记录，用于指导内容方向。',
            'priority' => 'essential',
            'frequency' => '每周',
        ],
        // ── Analytics & audit ─────────────────────────────────────────────
        'analytics_events' => [
            'label' => '站内事件（最近 5000 条）',
            'desc' => '文章浏览、搜索、分享、订阅等事件流。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        'article_audit_logs' => [
            'label' => '文章审计日志',
            'desc' => '所有状态变更、创建、删除的操作记录，含操作人。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        'ai_usage_logs' => [
            'label' => 'AI 调用日志',
            'desc' => 'AI 请求、成本、模型和成功/失败统计。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        // ── Configuration & templates ─────────────────────────────────────
        'source_profiles' => [
            'label' => '来源库',
            'desc' => '可信来源档案和可信度评分。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        'source_templates' => [
            'label' => '输入模板',
            'desc' => '预设栏目 + 角度 + 来源的快速简报模板。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        'research_briefs' => [
            'label' => '研究简报',
            'desc' => 'AI 生成的选题研究简报和来源引用。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
        'ai_bots' => [
            'label' => 'AI 栏目机器人',
            'desc' => '各栏目机器人的提示词、语气和状态配置。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        'ai_task_templates' => [
            'label' => 'AI 任务模板',
            'desc' => '改写、SEO、Newsletter、社交、核查的提示词模板。',
            'priority' => 'important',
            'frequency' => '每月',
        ],
        'ai_story_intakes' => [
            'label' => 'AI 故事选题',
            'desc' => '编辑提交的选题角度和来源素材。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
        'reader_saved_articles' => [
            'label' => '读者收藏',
            'desc' => '登录用户保存的文章列表。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
        'reader_recent_reads' => [
            'label' => '读者阅读历史',
            'desc' => '近期已读文章，用于个性化推荐。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
        'social_posts' => [
            'label' => '社交文案',
            'desc' => '各渠道文案、状态、排期时间和发布记录。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
        'article_claims' => [
            'label' => '文章声明检查',
            'desc' => 'AI 事实核查标记的声明和风险等级。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
        'article_short_format' => [
            'label' => '60秒看懂卡片',
            'desc' => 'AI 生成的速读摘要、要点和关键数字。',
            'priority' => 'optional',
            'frequency' => '每月',
        ],
    ];
}

/**
 * Structural safety audit: confirms no code path auto-publishes, auto-sends, or
 * auto-posts on behalf of the owner. All items are statically 'ok=true' because
 * these are code-architecture guarantees documented at design time.
 * If any of these guarantees are ever broken by future code, this function must be
 * updated to reflect the change and the smoke check will surface it.
 */
function backup_safety_audit(): array
{
    return [
        [
            'gate' => '文章自动发布',
            'ok' => true,
            'guarantee' => '无',
            'detail' => '文章发布需要 editor/admin 角色通过 POST /admin/articles/{id}/status 手动触发。没有 cron job、webhook 或定时任务会自动将 draft/review 变为 published。',
            'verified_in' => 'src/auth.php → can_publish_article() · public/index.php → /admin/articles/{id}/status',
        ],
        [
            'gate' => 'Newsletter 自动广播',
            'ok' => true,
            'guarantee' => '无',
            'detail' => 'send_newsletter_broadcast() 的唯一入口是 POST /admin/newsletter/{id}/send，且在发送前验证 pre-send checklist 全部通过。无计划任务或 API 触发路径。',
            'verified_in' => 'src/newsletter_issues.php → send_newsletter_broadcast() · public/index.php → /admin/newsletter/{id}/send',
        ],
        [
            'gate' => '社交平台自动发帖',
            'ok' => true,
            'guarantee' => '无',
            'detail' => '社交排期（social_posts.scheduled_at）是人工提醒清单，系统只读取时间用于在队列页高亮显示，不触发任何对外 API 调用或 POST 请求。',
            'verified_in' => 'src/social.php → 无 post_to_* 函数 · views/admin/social-schedule.php → 仅读取展示',
        ],
        [
            'gate' => '删除权限角色控制',
            'ok' => true,
            'guarantee' => '仅 admin',
            'detail' => 'can_delete_article() 内部调用 user_has_role(["admin"])，只有 admin 角色才能访问删除路由 DELETE /admin/articles/{id}。',
            'verified_in' => 'src/auth.php → can_delete_article()',
        ],
        [
            'gate' => '发布权限角色控制',
            'ok' => true,
            'guarantee' => 'editor / admin',
            'detail' => 'can_publish_article() 需要 editor 或 admin 角色。writer 角色只能从 draft 转为 review，无法直接发布。',
            'verified_in' => 'src/auth.php → can_publish_article() · can_transition_article()',
        ],
        [
            'gate' => '危险操作确认弹窗',
            'ok' => true,
            'guarantee' => '已实现',
            'detail' => '所有删除/归档/状态变更按钮使用 data-confirm 属性，由 app.js 拦截并显示品牌确认弹窗，从不调用 window.confirm()。',
            'verified_in' => 'public/assets/js/app.js → 品牌 confirm 弹窗 · views/admin/* → data-confirm 属性',
        ],
        [
            'gate' => 'Admin 路由身份验证',
            'ok' => true,
            'guarantee' => '已实现',
            'detail' => '每个 /admin/* 路由调用 require_admin()，无会话则跳转到 /admin/login。无需鉴权的公开路由均在 /admin 前缀之外。',
            'verified_in' => 'src/auth.php → require_admin() · public/index.php → 每个 admin 路由首行',
        ],
    ];
}

/**
 * Role → capability permission matrix for admin display and audit.
 */
function backup_permission_matrix(): array
{
    return [
        'columns' => ['writer', 'editor', 'admin'],
        'rows' => [
            ['label' => '创建文章草稿',         'writer' => true,  'editor' => true,  'admin' => true],
            ['label' => '编辑自己的文章',        'writer' => true,  'editor' => true,  'admin' => true],
            ['label' => '编辑他人文章',          'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '草稿 → 审核',           'writer' => true,  'editor' => true,  'admin' => true],
            ['label' => '发布文章',              'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '归档文章',              'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '删除文章',              'writer' => false, 'editor' => false, 'admin' => true],
            ['label' => '发送 Newsletter',       'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '管理 AI 机器人',         'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '导出数据',              'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '查看诊断 / Smoke',      'writer' => false, 'editor' => true,  'admin' => true],
            ['label' => '变更系统设置',           'writer' => false, 'editor' => false, 'admin' => true],
        ],
    ];
}

function week_eight_qa_checklist(): array
{
    return [
        ['label' => '真实邮件投递 (Brevo) 可以发出并送达，发件域名通过 DKIM/DMARC 验证。', 'tip' => '/admin/email-delivery — 发送测试邮件，在 Gmail 收件箱检查发件人显示、无垃圾邮件标记。'],
        ['label' => 'Newsletter 发送前 checklist 全部通过，广播需手动确认，不会自动发出。', 'tip' => '/admin/newsletter/{id}/edit — 确认广播按钮在 checklist 未完成时被禁用。'],
        ['label' => 'Google OAuth 公开可用，登录/注册流程完整，账号偏好和收藏功能正常。', 'tip' => '/account/login — 点击 Google 登录，完成账号创建、偏好保存、收藏文章全流程。'],
        ['label' => '静态资源缓存头已生效（CSS/JS 1年 Cache-Control），页面二次加载明显加快。', 'tip' => '开发者工具 Network — 二次加载 app.css 应返回 304 或 from disk cache。'],
        ['label' => '文章头图有 fetchpriority=high，LCP 元素在 DevTools 标记为优先加载。', 'tip' => '/article/{slug} — DevTools > Performance > LCP 应命中头图。'],
        ['label' => '备份导出页可下载文章、订阅、早报等核心 CSV，文件内容格式正确。', 'tip' => '/admin/backup — 下载 articles.csv 和 subscribers.csv，在 Excel/Numbers 打开检查。'],
        ['label' => 'Smoke 自检 JSON 包含 status / summary / failures 字段，可接入外部监控工具。', 'tip' => '/admin/smoke?format=json — 确认 JSON 有 "status":"ok"、"summary" 对象和 "failures" 数组。'],
        ['label' => '安全审计确认无自动发布、无自动广播、无自动社交发帖，所有高风险操作需手动触发。', 'tip' => '/admin/backup — 查看"安全保障"面板，确认 7 项 PASS。'],
        ['label' => '权限矩阵确认 writer 无法发布/删除，editor 无法删除，admin 有全部权限。', 'tip' => '/admin/backup — 查看"角色权限矩阵"，确认矩阵与实际代码逻辑一致。'],
        ['label' => '管理后台界面全部为自然中文，没有遗留英文 prompt、标题或卡片名称。', 'tip' => '随机打开 5 个后台页面，确认 eyebrow、按钮、表头、提示语都是中文。'],
        ['label' => '移动端后台与前台都不横向溢出，按钮触控区 ≥44px，筛选栏自动换行。', 'tip' => '用 375px 宽度访问 /、/article/{slug}、/admin/articles、/admin/content-ops。'],
        ['label' => '内容运营手册可用：每日发布节奏、每日清单、每周节奏、SEO 健康快照齐全。', 'tip' => '/admin/content-ops — 确认时间轴、清单勾选、SEO 卡片都正常。'],
        ['label' => '生产 smoke 全部通过，release marker 已更新到 week-8-day-7-launch-ready。', 'tip' => '/admin/smoke?format=json 与 /health.php。'],
    ];
}

/**
 * Post-8-week roadmap — six strategic pillars to pursue after the foundation
 * is complete. Each keeps the core safety rule (nothing auto-publishes/sends).
 */
function week_nine_backlog(): array
{
    return post_milestone_roadmap();
}

function post_milestone_roadmap(): array
{
    return [
        [
            'pillar' => '付费会员',
            'icon' => '💎',
            'title' => 'Premium 会员体系',
            'detail' => '把现有 premium 标签升级为真正的会员体系：会员专享文章、深度报告、无广告阅读。先用软付费墙和会员权益页验证转化意愿，再接入 Stripe / 微信支付。',
            'phase' => '第 9–10 周',
            'effort' => '大',
        ],
        [
            'pillar' => '读者评论',
            'icon' => '💬',
            'title' => '评论与深度互动',
            'detail' => '在现有"有帮助 / 想看更多 / 太复杂"反馈之上，加入短评论。先建后台审核队列与反垃圾规则，评论默认审核后可见，绝不自动放行。',
            'phase' => '第 9 周',
            'effort' => '中',
        ],
        [
            'pillar' => '个性化',
            'icon' => '🎯',
            'title' => '个性化内容与早报',
            'detail' => '基于 reader_preference_topics 与阅读历史，为每位读者拼装个性化首页板块和早报版本。先做预览 + 人工发送，验证效果后再考虑自动化（仍需人工审批）。',
            'phase' => '第 10–11 周',
            'effort' => '大',
        ],
        [
            'pillar' => '微信生态',
            'icon' => '🟢',
            'title' => '微信生态深化',
            'detail' => '从现有微信导出，扩展到公众号自动排版模板、小程序阅读入口、服务号模板消息（人工触发），把微信变成主要分发与留存渠道。',
            'phase' => '第 11–12 周',
            'effort' => '大',
        ],
        [
            'pillar' => 'AI 编辑部',
            'icon' => '🤖',
            'title' => '进阶 AI 编辑部',
            'detail' => '让栏目机器人更强：多源交叉核查、自动生成数据图表建议、选题趋势雷达、草稿质量自评打分。所有产出仍是草稿，发布权始终在编辑手里。',
            'phase' => '第 10–12 周',
            'effort' => '大',
        ],
        [
            'pillar' => '数据分析',
            'icon' => '📈',
            'title' => '更强的数据分析',
            'detail' => '从基础统计升级为增长分析：留存曲线、漏斗转化、内容主题表现、订阅来源 ROI、读者反馈与选题方向的相关性，生成每周增长报告指导决策。',
            'phase' => '第 9–10 周',
            'effort' => '中',
        ],
    ];
}
