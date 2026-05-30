<?php

declare(strict_types=1);

/**
 * Week 8 Day 7: Final launch readiness + 8-week milestone wrap-up.
 */

/**
 * Week-by-week recap of the 8-week build. Each week: theme + headline shipped items.
 */
function eight_week_journey(): array
{
    return [
        [
            'week' => 'Week 1',
            'theme' => 'MVP 基础',
            'icon' => '🏗️',
            'shipped' => ['公开站点与栏目结构', '文章页与首页', '邮件订阅入口', 'Hostinger 部署管线'],
        ],
        [
            'week' => 'Week 2',
            'theme' => '编辑工作流',
            'icon' => '✍️',
            'shipped' => ['草稿→审核→发布状态机', '文章管理与搜索', '发布前检查闸门', '上线检查清单'],
        ],
        [
            'week' => 'Week 3',
            'theme' => '内容深度',
            'icon' => '📰',
            'shipped' => ['Newsletter 期号构建器', '来源库与可信度', 'AI 研究简报', '读者账号 + 标签 + Most Read'],
        ],
        [
            'week' => 'Week 4',
            'theme' => '读者与留存',
            'icon' => '👥',
            'shipped' => ['个人资料编辑', 'OAuth 骨架 + 提醒频率', '签名退订链接', '公开 newsletter 归档'],
        ],
        [
            'week' => 'Week 5',
            'theme' => 'AI 编辑部',
            'icon' => '🤖',
            'shipped' => ['栏目机器人 + 任务模板', '草稿队列 + 质量评分', '事实/风险核查', 'AI 早报助理 + 进度提示'],
        ],
        [
            'week' => 'Week 6',
            'theme' => '社交分发',
            'icon' => '📣',
            'shipped' => ['多渠道社交文案 + AI 生成', '微信版面导出', '分享卡 / OG 图', '60秒看懂 + 传播分析'],
        ],
        [
            'week' => 'Week 7',
            'theme' => '发现与计划',
            'icon' => '🔍',
            'shipped' => ['公开搜索 + RSS', '编辑日历', '社交/早报排期队列', '读者反馈（有帮助/想看更多/太复杂）'],
        ],
        [
            'week' => 'Week 8',
            'theme' => '上线就绪',
            'icon' => '🚀',
            'shipped' => ['真实邮件投递 (Brevo)', 'Google OAuth 公开', '性能/缓存 + 备份/安全', '内容运营手册 + 全站中文化'],
        ],
    ];
}

/**
 * Snapshot numbers for the milestone summary (live from DB where possible).
 */
function milestone_stats(): array
{
    $stats = [
        'articles' => 0,
        'subscribers' => 0,
        'categories' => 0,
        'tables' => 0,
        'weeks' => 8,
    ];
    if (function_exists('db_count')) {
        $stats['articles'] = db_count('articles', "status = 'published'");
        $stats['subscribers'] = db_count('subscribers', "status = 'active'");
    }
    if (function_exists('get_categories')) {
        $stats['categories'] = count(get_categories());
    }
    if (function_exists('database_diagnostics')) {
        $diag = database_diagnostics();
        $stats['tables'] = count($diag['tables'] ?? []);
    }
    return $stats;
}

/**
 * Final launch readiness gate: rolls up the smoke checks into a single go/no-go.
 */
function launch_readiness_summary(): array
{
    $checks = function_exists('admin_smoke_checks') ? admin_smoke_checks() : [];
    $total = count($checks);
    $passed = count(array_filter($checks, static fn (array $c): bool => !empty($c['ok'])));
    $failed = $total - $passed;
    return [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'ready' => $failed === 0,
        'pass_rate' => $total > 0 ? round($passed / $total * 100) : 0,
        'failures' => array_values(array_map(
            static fn (array $c): array => ['name' => $c['name'], 'detail' => $c['detail']],
            array_filter($checks, static fn (array $c): bool => empty($c['ok']))
        )),
    ];
}

/**
 * Pillars of what makes this launch-ready — the guarantees the owner can rely on.
 */
function launch_readiness_pillars(): array
{
    return [
        ['icon' => '🛡️', 'title' => '安全可控', 'detail' => '无任何自动发布、自动广播或自动社交发帖。所有对外动作都需要人工点击。'],
        ['icon' => '📧', 'title' => '邮件可达', 'detail' => 'Brevo 真实投递，发件域名通过 DKIM/DMARC 验证，退订链接合规。'],
        ['icon' => '🔐', 'title' => '账号可用', 'detail' => 'Google OAuth + 邮箱密码双通道，角色权限分级（writer/editor/admin）。'],
        ['icon' => '⚡', 'title' => '性能就绪', 'detail' => '静态资源长效缓存、gzip 压缩、LCP 图片优先加载、RSS/sitemap 缓存。'],
        ['icon' => '💾', 'title' => '可备份恢复', 'detail' => '分级 CSV 导出 + Hostinger 快照指引 + 密钥离线保管文档。'],
        ['icon' => '🇨🇳', 'title' => '本地化完整', 'detail' => '前台与后台全部自然中文，移动端无溢出，触控友好。'],
    ];
}
