<?php
$pageTitle = '流水线分析 - 钱潮 Money Tide';
$s = $summary;
$series = $s['series'] ?? [];
$draftVals = array_map(static fn ($d) => (int) $d['draft'], $series);
$pubVals = array_map(static fn ($d) => (int) $d['publish'], $series);
$chartW = 720.0;
$chartH = 200.0;
// Shared Y-max so the draft and publish lines are directly comparable.
$sharedMax = max(1, $draftVals ? (int) max($draftVals) : 0, $pubVals ? (int) max($pubVals) : 0);
$draftGeo = pipeline_chart_geometry($draftVals ?: [0], $chartW, $chartH, 4.0, $sharedMax);
$pubGeo = pipeline_chart_geometry($pubVals ?: [0], $chartW, $chartH, 4.0, $sharedMax);
$levelClass = ['ok' => 'is-ok', 'warn' => 'is-warn', 'info' => 'is-info'];
?>
<section class="admin-shell" data-pipeline-analytics>
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Sprint 2 · 可观测性</p>
            <h1>流水线分析 <span class="pa-live-dot" title="实时数据"></span></h1>
            <p>把每日自动运行变成可读的趋势：各阶段产出、转化漏斗、自动通过率与限流迹象，让你用数据调阈值和节流，而不是凭感觉。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a>
            <a class="ghost-link" href="<?= e(url('admin/week9-checklist')) ?>">签收台</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
        </div>
    </div>

    <!-- Window switcher -->
    <div class="pa-window-bar">
        <span>时间窗口</span>
        <div class="pa-window-tabs">
            <?php foreach ($windows as $d => $label): ?>
                <a class="pa-window-tab <?= (int) $d === (int) $days ? 'is-active' : '' ?>"
                   href="<?= e(url('admin/pipeline-analytics')) ?>?days=<?= e((string) $d) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <a class="pa-refresh" href="<?= e(url('admin/pipeline-analytics')) ?>?days=<?= e((string) $days) ?>" title="刷新">↻ 刷新</a>
    </div>

    <?php if (empty($s['has_data'])): ?>
        <div class="empty-state pa-empty">
            <strong>这段时间还没有运行记录。</strong>
            <p>到 <a href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a> 点「立即运行一次」，或开启 Cron 自动运行，数据会在这里累积成趋势。</p>
        </div>
    <?php else: ?>

    <!-- KPI cards -->
    <div class="pa-kpi-grid">
        <article class="pa-kpi">
            <span class="pa-kpi-icon">▶️</span>
            <strong data-countup="<?= e((string) $s['run_count']) ?>">0</strong>
            <small>运行次数 · <?= e((string) $days) ?> 天</small>
        </article>
        <article class="pa-kpi pa-kpi-accent">
            <span class="pa-kpi-icon">✅</span>
            <strong><span data-countup="<?= e((string) $s['success_rate']) ?>">0</span>%</strong>
            <small>成功率 · <?= e((string) $s['ok_count']) ?>/<?= e((string) $s['run_count']) ?></small>
        </article>
        <article class="pa-kpi">
            <span class="pa-kpi-icon">✍️</span>
            <strong data-countup="<?= e((string) $s['totals']['draft']) ?>">0</strong>
            <small>AI 草稿产出</small>
        </article>
        <article class="pa-kpi">
            <span class="pa-kpi-icon">🚀</span>
            <strong data-countup="<?= e((string) $s['totals']['publish']) ?>">0</strong>
            <small>发布文章</small>
        </article>
        <article class="pa-kpi">
            <span class="pa-kpi-icon">📰</span>
            <strong data-countup="<?= e((string) $s['totals']['issue']) ?>">0</strong>
            <small>组装早报</small>
        </article>
        <article class="pa-kpi pa-kpi-accent">
            <span class="pa-kpi-icon">🎚</span>
            <strong><span data-countup="<?= e((string) $s['auto_rate']) ?>">0</span>%</strong>
            <small>自动通过率</small>
        </article>
        <article class="pa-kpi">
            <span class="pa-kpi-icon">⏱</span>
            <strong><span data-countup="<?= e((string) $s['avg_duration']) ?>">0</span>s</strong>
            <small>平均用时</small>
        </article>
        <article class="pa-kpi <?= $s['zero_draft_runs'] > 0 ? 'pa-kpi-warn' : '' ?>">
            <span class="pa-kpi-icon">⚠️</span>
            <strong data-countup="<?= e((string) $s['zero_draft_runs']) ?>">0</strong>
            <small>疑似限流（草稿 0）</small>
        </article>
    </div>

    <!-- Insights -->
    <section class="newsletter-block">
        <h2>💡 智能洞察</h2>
        <div class="pa-insights">
            <?php foreach ($insights as $ins): ?>
                <div class="pa-insight <?= e($levelClass[$ins['level']] ?? 'is-info') ?>">
                    <span class="pa-insight-icon"><?= e($ins['icon']) ?></span>
                    <p><?= e($ins['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Trend chart -->
    <section class="newsletter-block">
        <div class="ops-section-head">
            <h2>📈 产出趋势</h2>
            <div class="pa-legend">
                <span class="pa-legend-item pa-legend-draft">草稿</span>
                <span class="pa-legend-item pa-legend-pub">发布</span>
            </div>
        </div>
        <div class="pa-chart">
            <svg viewBox="0 0 <?= e((string) $chartW) ?> <?= e((string) $chartH) ?>" preserveAspectRatio="none" role="img" aria-label="产出趋势图">
                <defs>
                    <linearGradient id="paDraftFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgba(37,84,255,0.28)"/>
                        <stop offset="100%" stop-color="rgba(37,84,255,0)"/>
                    </linearGradient>
                </defs>
                <?php for ($g = 1; $g <= 3; $g++): $gy = $chartH / 4 * $g; ?>
                    <line x1="0" y1="<?= e((string) $gy) ?>" x2="<?= e((string) $chartW) ?>" y2="<?= e((string) $gy) ?>" stroke="#e4ddcf" stroke-width="1"/>
                <?php endfor; ?>
                <?php if ($draftGeo['area'] !== ''): ?>
                    <path d="<?= e($draftGeo['area']) ?>" fill="url(#paDraftFill)"/>
                <?php endif; ?>
                <?php if ($draftGeo['points'] !== ''): ?>
                    <polyline class="pa-line pa-line-draft" points="<?= e($draftGeo['points']) ?>" fill="none" stroke="#2554ff" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                <?php endif; ?>
                <?php if ($pubGeo['points'] !== ''): ?>
                    <polyline class="pa-line pa-line-pub" points="<?= e($pubGeo['points']) ?>" fill="none" stroke="#1f9d55" stroke-width="3" stroke-dasharray="2 0" stroke-linejoin="round" stroke-linecap="round"/>
                <?php endif; ?>
            </svg>
            <div class="pa-chart-axis">
                <span><?= e((string) ($series[0]['date'] ?? '')) ?></span>
                <span><?= e((string) ($series[count($series) - 1]['date'] ?? '')) ?></span>
            </div>
        </div>
    </section>

    <!-- Conversion funnel -->
    <section class="newsletter-block">
        <h2>🔻 转化漏斗（<?= e((string) $days) ?> 天累计）</h2>
        <p>从原始素材到对外早报，每一步还剩多少。漏斗收窄最明显的环节，就是最值得优化的瓶颈。</p>
        <div class="pa-funnel">
            <?php foreach ($s['funnel'] as $i => $f): ?>
                <div class="pa-funnel-row" style="--i: <?= e((string) $i) ?>">
                    <div class="pa-funnel-label"><span><?= e($f['icon']) ?></span><?= e($f['label']) ?></div>
                    <div class="pa-funnel-track">
                        <div class="pa-funnel-bar" style="--w: <?= e((string) $f['pct']) ?>%">
                            <span class="pa-funnel-value" data-countup="<?= e((string) $f['value']) ?>">0</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pa-yield">
            <span>聚类→草稿 <strong><?= e((string) $s['draft_yield']) ?>%</strong></span>
            <span>草稿→发布 <strong><?= e((string) $s['publish_yield']) ?>%</strong></span>
            <span>自动通过 <strong><?= e((string) $s['auto_rate']) ?>%</strong></span>
        </div>
    </section>

    <?php endif; ?>

    <!-- Recent runs -->
    <section class="newsletter-block">
        <h2>🕑 最近运行</h2>
        <div class="admin-table pa-runs">
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
                <div class="empty-state"><strong>还没有运行记录。</strong><p>运行一次流水线后这里会显示明细。</p></div>
            <?php endif; ?>
        </div>
    </section>
</section>
