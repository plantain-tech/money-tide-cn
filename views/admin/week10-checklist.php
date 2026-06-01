<?php
$pageTitle = '第 10 周签收 · 自主上线 - 钱潮 Money Tide';
$verdict = $readiness['verdict'] ?? 'warn';
$bannerClass = $verdict === 'ok' ? 'is-ready' : ($verdict === 'warn' ? 'is-warning' : 'is-error');
$on = function_exists('autopilot_enabled') ? autopilot_enabled() : !empty($config['enabled']);
$days = [
    ['d' => 'D1', 't' => '流水线分析', 'x' => '趋势、转化漏斗、自动通过率、限流迹象，一屏可观测。'],
    ['d' => 'D2', 't' => '告警与自愈', 'x' => '失败/限流/停摆告警 + 写稿退避重试，引擎安全网。'],
    ['d' => 'D3', 't' => 'Telegram 自动', 'x' => '发文即推送标题+摘要+链接，每日早报，消息 ID 记录。'],
    ['d' => 'D4', 't' => 'X 自动', 'x' => 'X API v2 适配器、AI 文案+UTM、月度预算保护。'],
    ['d' => 'D5', 't' => 'WeChat + 分发包', 'x' => '公众号半自动草稿 + 小红书/头条/百家号/知乎一键复制。'],
    ['d' => 'D6', 't' => '变现 + SEO', 'x' => '联盟链接/赞助位/AdSense（合规）+ 结构化数据 + 收入总览。'],
    ['d' => 'D7', 't' => '上线 + 复盘', 'x' => '端到端 smoke、自主模式 + 分栏目暂停、复盘、第 11 周 backlog。'],
];
$stateClass = ['ok' => 'is-ok', 'warn' => 'is-warn', 'off' => 'is-off', 'down' => 'is-down'];
?>
<section class="admin-shell week-checklist-shell" data-autonomy-finale>
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Sprint 2 · 自主上线</p>
            <h1>第 10 周签收 <span class="finale-spark">🚀</span></h1>
            <p>把 Sprint 2 收尾：端到端跑通并分发到免费渠道、确认总开关与分栏目暂停有效、复盘并列出第 11 周方向。5% 人工底线不变：AI 标记需核查的草稿仍不会自动发布。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a>
            <a class="ghost-link" href="<?= e(url('admin/channels')) ?>">分发渠道</a>
            <a class="ghost-link" href="<?= e(url('admin/pipeline-analytics')) ?>">流水线分析</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready ap-flash"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <!-- Readiness hero -->
    <div class="autonomy-hero autonomy-<?= e($verdict) ?>">
        <div class="autonomy-ring" style="--pct: <?= e((string) ($readiness['pct'] ?? 0)) ?>">
            <div class="autonomy-ring-core"><strong data-countup="<?= e((string) ($readiness['pct'] ?? 0)) ?>">0</strong><span>%</span></div>
        </div>
        <div class="autonomy-hero-body">
            <span class="autonomy-verdict-chip autonomy-chip-<?= e($verdict) ?>"><?= e((string) ($readiness['label'] ?? '')) ?></span>
            <h2><?= e((string) ($readiness['ok'] ?? 0)) ?>/<?= e((string) ($readiness['total'] ?? 0)) ?> 阶段绿灯 · 自主模式 <?= $on ? '🟢 已开启' : '🔴 已关闭' ?></h2>
            <p>下面确认渠道、跑一次端到端 dry run、对照签收清单。自主开关在 <a href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a> 页一键切换。</p>
        </div>
    </div>

    <!-- Channel health -->
    <section class="newsletter-block">
        <h2>📡 分发渠道状态（全部免费优先）</h2>
        <div class="autonomy-grid">
            <?php foreach ($channels as $ch): ?>
                <a class="autonomy-light <?= e($stateClass[$ch['state']] ?? 'is-warn') ?>" href="<?= e(url('admin/channels')) ?>">
                    <span class="autonomy-light-dot"></span>
                    <span class="autonomy-light-icon"><?= e($ch['icon']) ?></span>
                    <strong><?= e($ch['label']) ?> <?= !empty($ch['free']) ? '<small style="color:#1f9d55">免费</small>' : '<small style="color:#d9821a">付费</small>' ?></strong>
                    <span class="autonomy-light-value"><?= $ch['state'] === 'ok' ? '已开启' : ($ch['state'] === 'warn' ? '已配置' : '未开启') ?></span>
                    <small><?= e($ch['detail']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Live pipeline health -->
    <section class="newsletter-block">
        <h2>🚦 流水线健康灯</h2>
        <div class="autonomy-grid">
            <?php foreach ($checks as $i => $c): ?>
                <a class="autonomy-light is-<?= e($c['state']) ?>" href="<?= e(url($c['url'])) ?>" style="--i: <?= e((string) $i) ?>">
                    <span class="autonomy-light-dot"></span>
                    <span class="autonomy-light-icon"><?= e($c['icon']) ?></span>
                    <strong><?= e($c['label']) ?></strong>
                    <span class="autonomy-light-value"><?= e((string) $c['value']) ?></span>
                    <small><?= e((string) $c['detail']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- End-to-end dry run -->
    <section class="newsletter-block">
        <h2>▶️ 端到端 Dry Run</h2>
        <p>强制跑一遍六阶段（保守批量），验证从抓取到发布到分发全链路。Telegram 等已开启渠道会收到分发。</p>
        <form method="post" action="<?= e(url('admin/week10-checklist')) ?>" class="news-fetch-form" data-news-fetch>
            <input type="hidden" name="action" value="dry_run">
            <button class="button" type="submit" data-news-fetch-btn data-loading-label="运行中…（约 1–2 分钟）" <?= empty($aiReady['ready']) ? 'disabled' : '' ?>>
                <span class="news-fetch-label">🚀 运行端到端 Dry Run</span>
                <span class="news-fetch-spinner" hidden></span>
            </button>
        </form>
        <?php if (!empty($dryRun)): ?>
            <div class="dryrun-result" data-dryrun>
                <div class="dryrun-flow">
                    <?php foreach ($dryRun['cards'] as $j => $card): ?>
                        <div class="dryrun-card" style="--j: <?= e((string) $j) ?>">
                            <span class="dryrun-icon"><?= e($card['icon']) ?></span>
                            <strong data-countup="<?= e((string) $card['value']) ?>">0</strong>
                            <small><?= e($card['label']) ?></small>
                            <em><?= e($card['unit']) ?><?= !empty($card['extra']) ? ' · ' . e($card['extra']) : '' ?></em>
                        </div>
                        <?php if ($j < count($dryRun['cards']) - 1): ?><span class="dryrun-arrow" aria-hidden="true">→</span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <p class="dryrun-summary">⏱ 用时 <?= e((string) ($dryRun['duration'] ?? 0)) ?> 秒 · <?= e((string) ($dryRun['message'] ?? '')) ?></p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Sign-off -->
    <section class="newsletter-block">
        <div class="status-banner <?= e($bannerClass) ?>">
            <strong>上线前自检：<?= e((string) ($readiness['ok'] ?? 0)) ?>/<?= e((string) ($readiness['total'] ?? 0)) ?> 绿灯</strong>
            <span><?= $verdict === 'ok' ? '全线绿灯，Sprint 2 自主上线可以签收。' : ($verdict === 'warn' ? '可上线，有提醒项 —— 对照下方清单确认。' : '有阻塞项，请先修复红灯阶段。') ?></span>
        </div>
        <h2>✅ Sprint 2 完成定义</h2>
        <ol class="qa-list qa-list-checkable">
            <?php foreach ($items as $item): ?>
                <li><strong><?= e($item['label']) ?></strong><small><?= e($item['tip']) ?></small></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <!-- Week 11 backlog -->
    <section class="newsletter-block">
        <div class="ops-section-head">
            <h2>🧭 第 11 周 Backlog</h2>
            <a class="ghost-link" href="<?= e(url('admin/milestone')) ?>">里程碑 →</a>
        </div>
        <p>自主上线之后：接真实数据、增长实验、质量与韧性加固（仍守住人工审核与对外确认底线）。</p>
        <div class="milestone-roadmap">
            <?php foreach ($backlog as $item): ?>
                <article class="milestone-roadmap-card">
                    <div class="milestone-roadmap-head">
                        <span class="milestone-roadmap-icon"><?= e($item['icon'] ?? '•') ?></span>
                        <div>
                            <?php if (!empty($item['pillar'])): ?><span class="milestone-roadmap-pillar"><?= e($item['pillar']) ?></span><?php endif; ?>
                            <strong><?= e($item['title']) ?></strong>
                        </div>
                    </div>
                    <p><?= e($item['detail']) ?></p>
                    <?php if (!empty($item['phase'])): ?>
                        <div class="milestone-roadmap-meta">
                            <span class="milestone-chip"><?= e($item['phase']) ?></span>
                            <?php if (!empty($item['effort'])): ?><span class="milestone-chip milestone-chip-effort">工作量：<?= e($item['effort']) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
