<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·7 — Review + harden (autonomous content engine finale).
 *
 * This module is the "control room" for the whole autonomous pipeline:
 *   - autonomy_health_checks(): a focused, traffic-light read of every stage
 *     (ingest -> cluster -> synthesize -> gate -> publish -> assemble -> cron),
 *     lighter and more actionable than the full /admin/smoke sweep.
 *   - autonomy_dry_run(): one-click end-to-end dry run with safe caps, returning
 *     a per-stage breakdown for the demo.
 *   - sprint_one_signoff_checklist() / week_ten_backlog(): definition-of-done +
 *     what comes next.
 *
 * The 5% human gate still holds: drafts the AI flags for review never auto-publish.
 */

/**
 * Tunable review threshold, stored in pipeline_settings so it can be changed
 * live from the UI (falls back to the AI_REVIEW_THRESHOLD secret / default 80).
 */
function autonomy_review_threshold(): int
{
    $override = function_exists('pipeline_setting') ? (int) pipeline_setting('review_threshold', '0') : 0;
    if ($override > 0) {
        return max(50, min(100, $override));
    }
    return function_exists('auto_review_threshold') ? auto_review_threshold() : 80;
}

/**
 * One green/amber/red light per pipeline stage. Each check returns:
 *   key, label, icon, state ('ok'|'warn'|'down'), value (headline number/text),
 *   detail (one-line explanation), url (where to act).
 */
function autonomy_health_checks(): array
{
    $checks = [];

    // 0) AI provider — the engine. Down = nothing downstream can run.
    $provider = function_exists('ai_provider_status') ? ai_provider_status() : ['ready' => false, 'label' => '未知', 'model' => ''];
    $usage = function_exists('ai_usage_summary') ? ai_usage_summary() : ['used_today' => 0, 'daily_limit' => 0];
    $usageLeft = (int) ($usage['daily_limit'] ?? 0) - (int) ($usage['used_today'] ?? 0);
    $checks[] = [
        'key' => 'provider',
        'label' => 'AI 引擎',
        'icon' => '🧠',
        'state' => !empty($provider['ready']) ? ($usageLeft > 5 ? 'ok' : 'warn') : 'down',
        'value' => !empty($provider['ready']) ? '在线' : '离线',
        'detail' => ($provider['label'] ?? '') . ' · ' . ($provider['model'] ?? '') . ' · 今日剩余额度 ' . max(0, $usageLeft) . '/' . (int) ($usage['daily_limit'] ?? 0),
        'url' => 'admin/diagnostics',
    ];

    // 1) Ingest — fresh raw material.
    if (function_exists('news_ingest_summary')) {
        $news = news_ingest_summary();
        $active = (int) ($news['sources_active'] ?? 0);
        $fresh = (int) ($news['items_new'] ?? 0);
        $checks[] = [
            'key' => 'ingest',
            'label' => '新闻摄取',
            'icon' => '🛰',
            'state' => $active < 1 ? 'down' : ($fresh > 0 ? 'ok' : 'warn'),
            'value' => $fresh . ' 待处理',
            'detail' => $active . '/' . (int) ($news['sources_total'] ?? 0) . ' 源启用 · 共 ' . (int) ($news['items_total'] ?? 0) . ' 条已抓取',
            'url' => 'admin/news-sources',
        ];
    }

    // 2) Cluster — today's selected story angles.
    if (function_exists('clustering_summary')) {
        $cl = clustering_summary();
        $selected = (int) ($cl['selected'] ?? 0);
        $checks[] = [
            'key' => 'cluster',
            'label' => '选题聚类',
            'icon' => '🧩',
            'state' => (int) ($cl['total'] ?? 0) > 0 ? 'ok' : 'warn',
            'value' => $selected . ' 已选用',
            'detail' => (int) ($cl['total'] ?? 0) . ' 个 cluster · 今日新增 ' . (int) ($cl['today'] ?? 0),
            'url' => 'admin/story-clusters',
        ];
    }

    // 3) Synthesize — drafts produced.
    if (function_exists('synthesis_summary')) {
        $sy = synthesis_summary();
        $pending = (int) ($sy['pending_selected'] ?? 0);
        $checks[] = [
            'key' => 'synthesize',
            'label' => 'AI 写稿',
            'icon' => '✍️',
            'state' => (int) ($sy['synthesized'] ?? 0) > 0 ? 'ok' : ($pending > 0 ? 'warn' : 'warn'),
            'value' => (int) ($sy['synthesized'] ?? 0) . ' 篇草稿',
            'detail' => $pending . ' 个已选 cluster 待写稿',
            'url' => 'admin/story-clusters',
        ];
    }

    // 4) Fact-check gate — the 5% human checkpoint.
    if (function_exists('auto_review_summary')) {
        $rev = auto_review_summary();
        $pendingReview = (int) ($rev['pending_review'] ?? 0);
        $checks[] = [
            'key' => 'gate',
            'label' => 'AI 审核闸门',
            'icon' => '🔍',
            'state' => 'ok',
            'value' => '阈值 ' . autonomy_review_threshold(),
            'detail' => (int) ($rev['auto_approved'] ?? 0) . ' 自动通过 · ' . $pendingReview . ' 待人工核查（你的 5%）',
            'url' => 'admin/review-queue',
        ];
    }

    // 5) Publish + 6) Assemble — today's output.
    if (function_exists('auto_publish_summary')) {
        $pub = auto_publish_summary();
        $checks[] = [
            'key' => 'publish',
            'label' => '自动发布',
            'icon' => '🚀',
            'state' => 'ok',
            'value' => (int) ($pub['published_today'] ?? 0) . ' 篇今日',
            'detail' => (int) ($pub['approved_pending'] ?? 0) . ' 篇已批准待发布',
            'url' => 'admin/auto-publish',
        ];
        $issues = (int) ($pub['issues_today'] ?? 0);
        $checks[] = [
            'key' => 'assemble',
            'label' => '早报组装',
            'icon' => '📰',
            'state' => $issues > 0 ? 'ok' : 'warn',
            'value' => $issues . ' 份今日',
            'detail' => $issues > 0 ? '今日已组装早报' : '今日尚未组装早报（运行流水线后生成）',
            'url' => 'admin/newsletter',
        ];
    }

    // 7) Autopilot + cron — is it actually self-driving?
    if (function_exists('pipeline_config')) {
        $cfg = pipeline_config();
        $enabled = !empty($cfg['enabled']);
        $lastRun = (string) ($cfg['last_run_at'] ?? '');
        $stale = $lastRun === '' || strtotime($lastRun) < strtotime('-36 hours');
        $checks[] = [
            'key' => 'autopilot',
            'label' => '自动驾驶',
            'icon' => '🛸',
            'state' => $enabled ? ($stale ? 'warn' : 'ok') : 'warn',
            'value' => $enabled ? '已开启' : '已关闭',
            'detail' => $enabled
                ? ('上次运行 ' . ($lastRun !== '' ? $lastRun : '从未') . ($stale ? ' · 超过 36 小时未跑，检查 Cron' : ''))
                : '总开关关闭 —— Cron 会跳过，处于手动模式',
            'url' => 'admin/autopilot',
        ];
    }

    return $checks;
}

/**
 * Roll the health lights into a single readiness verdict.
 */
function autonomy_readiness(?array $checks = null): array
{
    $checks = $checks ?? autonomy_health_checks();
    $total = count($checks);
    $ok = 0;
    $warn = 0;
    $down = 0;
    foreach ($checks as $c) {
        $state = $c['state'] ?? 'warn';
        if ($state === 'ok') {
            $ok++;
        } elseif ($state === 'down') {
            $down++;
        } else {
            $warn++;
        }
    }
    $verdict = $down > 0 ? 'down' : ($warn > 0 ? 'warn' : 'ok');
    return [
        'total' => $total,
        'ok' => $ok,
        'warn' => $warn,
        'down' => $down,
        'verdict' => $verdict,
        'pct' => $total > 0 ? (int) round($ok / $total * 100) : 0,
        'label' => $verdict === 'ok' ? '全线绿灯' : ($verdict === 'warn' ? '可运行 · 有提醒' : '有阻塞 · 需修复'),
    ];
}

/**
 * One-click end-to-end dry run with safe caps, for the Day 9·7 demo.
 * Forces past the kill-switch (so it runs even if autopilot is OFF) but uses
 * conservative limits to fit a web request, and never bypasses the human gate.
 */
function autonomy_dry_run(): array
{
    @set_time_limit(0);
    $started = microtime(true);
    $result = run_daily_pipeline('dryrun', [
        'force' => true,
        'synthesize_limit' => 4,
        'assess_limit' => 6,
        'publish_limit' => 12,
    ]);
    $stages = $result['stages'] ?? [];

    // Normalise into demo-friendly stage cards.
    $cards = [
        ['key' => 'ingest', 'icon' => '🛰', 'label' => '摄取', 'value' => (int) ($stages['ingest']['new_items'] ?? 0), 'unit' => '条新素材'],
        ['key' => 'cluster', 'icon' => '🧩', 'label' => '聚类', 'value' => (int) ($stages['cluster']['clusters'] ?? 0), 'unit' => '个 cluster'],
        ['key' => 'synthesize', 'icon' => '✍️', 'label' => '写稿', 'value' => (int) ($stages['synthesize']['drafts'] ?? 0), 'unit' => '篇草稿'],
        ['key' => 'assess', 'icon' => '🔍', 'label' => '审核', 'value' => (int) ($stages['assess']['auto'] ?? 0), 'unit' => '自动通过', 'extra' => (int) ($stages['assess']['review'] ?? 0) . ' 转人工'],
        ['key' => 'publish', 'icon' => '🚀', 'label' => '发布', 'value' => (int) ($stages['publish']['articles'] ?? 0), 'unit' => '篇文章'],
        ['key' => 'assemble', 'icon' => '📰', 'label' => '早报', 'value' => (int) ($stages['assemble']['issues'] ?? 0), 'unit' => '份早报'],
    ];

    return [
        'status' => $result['status'] ?? 'ok',
        'message' => $result['message'] ?? '',
        'duration' => (int) round(microtime(true) - $started),
        'cards' => $cards,
        'stages' => $stages,
    ];
}

/**
 * Sprint 1 (Week 9) definition-of-done. Each item is a thing the owner can
 * personally verify on production.
 */
function sprint_one_signoff_checklist(): array
{
    return [
        ['label' => '新闻摄取可用：RSS 源可配置，定时 Cron 每数小时抓取新标题/摘要作为合成素材。', 'tip' => '/admin/news-sources — 确认有启用的源、"待处理"数 > 0，且 cli/fetch-news.php Cron 已设。'],
        ['label' => '选题聚类可用：AI 把原始新闻去重、聚类、打分，挑出每栏目值得写的选题。', 'tip' => '/admin/story-clusters — 点「AI 聚类」后能看到 cluster 卡片与「✍️ 生成草稿」按钮。'],
        ['label' => 'AI 写稿可用：从 cluster 合成原创中文草稿，专有名词中英对照，绝不照搬原文。', 'tip' => '/admin/story-clusters — 「批量生成」后到 /admin/ai-drafts 检查草稿正文为原创改写并署来源。'],
        ['label' => 'AI 审核闸门可用：强草稿按阈值自动通过，可疑的留人工一键批准/退回（你的 5%）。', 'tip' => '/admin/review-queue — 确认有「自动通过 / 待人工」分流，阈值可在本页调。'],
        ['label' => '自动发布 + 早报组装可用：已批准草稿发布为正式文章，再按栏目组装当日早报。', 'tip' => '/admin/auto-publish — 运行后 /admin/articles 有新发布文章，/admin/newsletter 有当日早报。'],
        ['label' => '自动驾驶编排可用：一条流水线串起全部六个阶段，总开关 + 实时监控 + 运行记录。', 'tip' => '/admin/autopilot — 「立即运行一次」六个阶段都有产出；开关可一键开/关并即时动画反馈。'],
        ['label' => 'AI 阶段间隔（节流）已配置，免费额度下不会因连续调用被限流导致「草稿 0」。', 'tip' => '/admin/autopilot — 「AI 阶段间隔」建议 8–15 秒；本页「写稿」灯应为绿。'],
        ['label' => '5% 人工底线成立：AI 标记需核查的草稿绝不自动发布，必须人工批准。', 'tip' => '/admin/diagnostics 或本页安全灯 — 确认「无自动发布需审草稿」保障通过。'],
        ['label' => 'CLI 脚本仅命令行可运行，网页访问 cli/run-daily.php、cli/fetch-news.php 返回 403。', 'tip' => '浏览器直接访问 /cli/run-daily.php 应为 403。'],
        ['label' => '部署可靠：GitHub Actions 经 SSH 密钥自动部署到生产，约 30 秒、稳定不再因 FTP 失败。', 'tip' => 'push 后 /health.php 的 release 会更新；本次为 week-9-autonomous-content。'],
        ['label' => '一次完整 dry run 全绿：六阶段端到端跑通，本页健康灯全绿或仅余正常提醒。', 'tip' => '本页「运行一次完整 dry run」后查看阶段卡片与健康灯。'],
        ['label' => '生产 smoke 全部通过，release marker 已更新到 week-9-autonomous-content。', 'tip' => '/admin/smoke?format=json 与 /health.php。'],
    ];
}

/**
 * Week 10 backlog — the next sprint. Keeps the 5% human gate on anything that
 * goes out to the public (social, email broadcast stay human-confirmed).
 */
function week_ten_backlog(): array
{
    return [
        [
            'pillar' => '分发自动化',
            'icon' => '📣',
            'title' => '自动社媒草稿（合规版）',
            'detail' => '流水线发文后，自动为每篇生成微博/X/小红书文案草稿进入排期队列；真正对外发布仍需人工一键确认，绝不自动发帖。',
            'phase' => '第 10 周',
            'effort' => '中',
        ],
        [
            'pillar' => '质量',
            'icon' => '🧪',
            'title' => '多源交叉核查',
            'detail' => '写稿前要求每个 cluster 至少两个独立来源，AI 标注事实冲突点，降低单源误差，提高自动通过的可信度。',
            'phase' => '第 10 周',
            'effort' => '中',
        ],
        [
            'pillar' => '个性化',
            'icon' => '🎯',
            'title' => '分栏目 / 分读者早报',
            'detail' => '在现有 8 栏目早报之上，按读者订阅偏好拼装个性化版本；先人工预览后发送，验证后再考虑自动化（仍需审批）。',
            'phase' => '第 10–11 周',
            'effort' => '大',
        ],
        [
            'pillar' => '可观测性',
            'icon' => '📊',
            'title' => '流水线分析仪表盘',
            'detail' => '把每日运行记录升级为趋势图：各阶段产出、自动通过率、转人工率、限流次数、端到端耗时，指导阈值与节流调参。',
            'phase' => '第 10 周',
            'effort' => '中',
        ],
        [
            'pillar' => '韧性',
            'icon' => '🛡',
            'title' => '失败告警与自愈',
            'detail' => 'Cron 运行失败或某阶段连续为 0 时发邮件/站内告警；对限流自动退避重试，记录每次失败原因便于排查。',
            'phase' => '第 10 周',
            'effort' => '中',
        ],
        [
            'pillar' => '变现',
            'icon' => '💡',
            'title' => '被动收入关键词与广告位',
            'detail' => '在合成文章与早报中按栏目嵌入相关关键词与清晰标注的广告位（AdSense 合规，不做隐藏广告），并跟踪点击与转化。',
            'phase' => '第 10–11 周',
            'effort' => '中',
        ],
    ];
}
