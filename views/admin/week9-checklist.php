<?php
$pageTitle = '第 9 周签收 · 自主内容引擎 - 钱潮 Money Tide';
$verdict = $readiness['verdict'] ?? 'warn';
$bannerClass = $verdict === 'ok' ? 'is-ready' : ($verdict === 'warn' ? 'is-warning' : 'is-error');
$days = [
    ['d' => 'D1', 't' => '新闻摄取', 'x' => 'RSS 源配置、定时抓取标题/摘要作为合成素材，自动新闻流水线的起点。'],
    ['d' => 'D2', 't' => '选题聚类', 'x' => 'AI 去重、聚类、按价值打分，挑出每栏目当天值得写的选题。'],
    ['d' => 'D3', 't' => 'AI 写稿', 'x' => '从 cluster 合成原创中文草稿，专有名词中英对照，绝不照搬原文。'],
    ['d' => 'D4', 't' => '审核闸门', 'x' => '按阈值自动通过强草稿，可疑的留人工一键批准/退回（你的 5%）。'],
    ['d' => 'D5', 't' => '发布 + 早报', 'x' => '已批准草稿发布为正式文章，再按 8 个栏目组装当日早报。'],
    ['d' => 'D6', 't' => '自动驾驶', 'x' => '一条流水线串起六个阶段，总开关 + 实时监控 + Cron 编排。'],
    ['d' => 'D7', 't' => '复盘加固', 'x' => '端到端 dry run、阈值调参、健康灯、生产 smoke、Sprint 签收。'],
];
?>
<section class="admin-shell week-checklist-shell" data-autonomy-finale>
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Sprint 1 · 自主内容引擎</p>
            <h1>第 9 周签收 <span class="finale-spark">✦</span></h1>
            <p>复盘并加固整条 AI 流水线：真实数据端到端 dry run、调阈值、健康灯全绿、生产 smoke 通过，然后签收。AI 标记需核查的草稿永远不会自动发布 —— 你的 5% 始终在线。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a>
            <a class="ghost-link" href="<?= e(url('admin/pipeline-analytics')) ?>">流水线分析</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
            <a class="ghost-link" href="<?= e(url('admin/milestone')) ?>">里程碑</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready ap-flash"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <!-- ── Readiness hero ─────────────────────────────────────────────── -->
    <div class="autonomy-hero autonomy-<?= e($verdict) ?>">
        <div class="autonomy-ring" style="--pct: <?= e((string) ($readiness['pct'] ?? 0)) ?>">
            <div class="autonomy-ring-core">
                <strong data-countup="<?= e((string) ($readiness['pct'] ?? 0)) ?>">0</strong><span>%</span>
            </div>
        </div>
        <div class="autonomy-hero-body">
            <span class="autonomy-verdict-chip autonomy-chip-<?= e($verdict) ?>"><?= e((string) ($readiness['label'] ?? '')) ?></span>
            <h2><?= e((string) ($readiness['ok'] ?? 0)) ?>/<?= e((string) ($readiness['total'] ?? 0)) ?> 阶段绿灯</h2>
            <p>绿灯 <?= e((string) ($readiness['ok'] ?? 0)) ?> · 提醒 <?= e((string) ($readiness['warn'] ?? 0)) ?> · 阻塞 <?= e((string) ($readiness['down'] ?? 0)) ?>。下面每盏灯都可点击直达对应控制台。</p>
        </div>
    </div>

    <!-- ── Live pipeline health lights ────────────────────────────────── -->
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

    <!-- ── Dry run + threshold ────────────────────────────────────────── -->
    <div class="autopilot-cols">
        <section class="newsletter-block">
            <h2>▶️ 运行一次完整 Dry Run</h2>
            <p>用真实数据端到端跑一遍六个阶段（强制运行，保守批量，给 Day 9·7 演示用）。仍走人工闸门 —— 可疑草稿不会自动发布。</p>
            <button class="button" type="button" data-run-pipeline
                data-step-url="<?= e(url('admin/autopilot/step')) ?>"
                <?= empty($aiReady['ready']) ? 'disabled' : '' ?>>
                🚀 运行完整 Dry Run
            </button>
            <?php if (empty($aiReady['ready'])): ?>
                <p class="news-action-hint">AI 引擎当前离线，dry run 暂不可用。请先到 <a href="<?= e(url('admin/diagnostics')) ?>">诊断</a> 检查 provider。</p>
            <?php else: ?>
                <p class="news-action-hint">弹出进度窗分步执行、实时显示进度，不会因网页超时而中断；每步进度都会保存。</p>
            <?php endif; ?>
        </section>

        <section class="newsletter-block">
            <h2>🎚 调自动通过阈值</h2>
            <p>AI 置信度 ≥ 阈值的草稿自动通过；低于阈值的留给你人工核查。阈值越高 = 越谨慎、转人工越多；越低 = 越自动、越大胆。</p>
            <form method="post" action="<?= e(url('admin/week9-checklist')) ?>" class="cms-form" data-threshold-form>
                <input type="hidden" name="action" value="save_threshold">
                <div class="threshold-control">
                    <output class="threshold-bubble" data-threshold-bubble><?= e((string) $threshold) ?></output>
                    <input type="range" name="review_threshold" min="30" max="100" step="1"
                        value="<?= e((string) $threshold) ?>" data-threshold-range>
                    <div class="threshold-scale"><span>30 最激进</span><span>65 平衡</span><span>100 谨慎</span></div>
                </div>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存阈值</button></div>
                <p class="news-action-hint">当前生效阈值 <strong><?= e((string) $threshold) ?></strong> 分。越低发布越多、但质量风险越高：30–40 = 最大产量（适合冲量/测试），50–65 = 平衡，75–85 = 稳健。低阈值时建议多留意 AI 审核台与已发布文章质量。</p>
            </form>
        </section>
    </div>

    <!-- ── Sprint 1 day-by-day ────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>📦 Sprint 1 交付概览</h2>
        <div class="week-release-grid">
            <?php foreach ($days as $day): ?>
                <article class="week-release-card">
                    <span><?= e($day['d']) ?></span>
                    <strong><?= e($day['t']) ?></strong>
                    <p><?= e($day['x']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Sign-off checklist ─────────────────────────────────────────── -->
    <section class="newsletter-block">
        <div class="status-banner <?= e($bannerClass) ?>">
            <strong>签收前自检：<?= e((string) ($readiness['ok'] ?? 0)) ?>/<?= e((string) ($readiness['total'] ?? 0)) ?> 绿灯</strong>
            <span><?= $verdict === 'ok' ? '全线绿灯，Sprint 1 自主内容引擎可以签收。' : ($verdict === 'warn' ? '可运行，但有提醒项 —— 逐条对照下方清单确认后再签收。' : '有阻塞项，请先修复红灯阶段再签收。') ?></span>
        </div>
        <h2>✅ Sprint 1 完成定义</h2>
        <ol class="qa-list qa-list-checkable">
            <?php foreach ($items as $item): ?>
                <li>
                    <strong><?= e($item['label']) ?></strong>
                    <small><?= e($item['tip']) ?></small>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <!-- ── Recent runs ────────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>🕑 最近运行记录</h2>
        <div class="admin-table">
            <div class="admin-table-row admin-table-head"><span>时间</span><span>触发</span><span>状态</span><span>结果</span><span>用时</span></div>
            <?php foreach ($runs as $run): ?>
                <div class="admin-table-row">
                    <span><?= e(date('m-d H:i', strtotime((string) $run['started_at']))) ?></span>
                    <span><?= e((string) $run['trigger_type']) ?></span>
                    <span><mark class="<?= $run['status'] === 'ok' ? 'status-ok' : ($run['status'] === 'paused' ? '' : 'status-warn') ?>"><?= e((string) $run['status']) ?></mark></span>
                    <span><?= e((string) ($run['summary'] ?? '')) ?></span>
                    <span><?= e((string) $run['duration_sec']) ?>s</span>
                </div>
            <?php endforeach; ?>
            <?php if (!$runs): ?>
                <div class="empty-state"><strong>还没有运行记录。</strong><p>点上面「运行完整 Dry Run」或等 Cron 触发后，这里会显示明细。</p></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── Week 10 backlog ────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <div class="ops-section-head">
            <h2>🧭 第 10 周 Backlog</h2>
            <a class="ghost-link" href="<?= e(url('admin/milestone')) ?>">完整路线图 →</a>
        </div>
        <p>引擎跑通后的下一冲刺方向（仍守住"对外发布需人工确认"的底线）。</p>
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

<!-- ── Staged run progress modal (shared orchestrator in app.js) ──────────── -->
<div class="run-modal" data-run-modal hidden>
    <div class="run-modal-backdrop"></div>
    <div class="run-modal-card" role="dialog" aria-modal="true" aria-labelledby="runModalTitle9">
        <div class="run-modal-head">
            <span class="run-modal-spark" data-run-spark>🛰</span>
            <h3 id="runModalTitle9" data-run-title>正在运行流水线…</h3>
            <p data-run-subtitle>请稍候，分步执行中，请勿关闭本页。</p>
        </div>
        <div class="run-progress">
            <div class="run-progress-bar"><span class="run-progress-fill" data-run-fill style="width:0%"></span></div>
            <div class="run-progress-meta">
                <span data-run-percent>0%</span>
                <span data-run-stepcount>步骤 0 / 0</span>
            </div>
        </div>
        <ul class="run-steps" data-run-steps>
            <li data-run-step="ingest"><span class="run-step-ic">🛰</span><span class="run-step-label">抓取新闻素材</span><span class="run-step-state"></span></li>
            <li data-run-step="cluster"><span class="run-step-ic">🧩</span><span class="run-step-label">聚类选题</span><span class="run-step-state"></span></li>
            <li data-run-step="synthesize"><span class="run-step-ic">✍️</span><span class="run-step-label">AI 写稿</span><span class="run-step-state"></span></li>
            <li data-run-step="assess"><span class="run-step-ic">🔍</span><span class="run-step-label">AI 审核闸门</span><span class="run-step-state"></span></li>
            <li data-run-step="publish"><span class="run-step-ic">🚀</span><span class="run-step-label">发布与早报组装</span><span class="run-step-state"></span></li>
        </ul>
        <p class="run-modal-detail" data-run-detail aria-live="polite">准备中…</p>
        <div class="run-modal-foot" data-run-foot hidden>
            <p class="run-modal-summary" data-run-summary></p>
            <button class="button" type="button" data-run-close>完成并刷新</button>
        </div>
    </div>
</div>
