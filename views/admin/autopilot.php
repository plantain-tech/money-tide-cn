<?php
$pageTitle = '自动驾驶 - 钱潮 Money Tide';
$on = !empty($config['enabled']);
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">自动新闻 · 控制台</p>
            <h1>自动驾驶 Autopilot</h1>
            <p>整条 AI 流水线的总开关与监控：抓取 → 聚类 → 写稿 → AI 审核 → 发布 + 组装早报。开启后由 Cron 每天自动跑；AI 标记需核查的草稿不会自动发布，仍等你处理（你的 5%）。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/pipeline-analytics')) ?>">流水线分析</a>
            <a class="ghost-link" href="<?= e(url('admin/channels')) ?>">分发渠道</a>
            <a class="ghost-link" href="<?= e(url('admin/review-queue')) ?>">AI 审核台</a>
            <a class="ghost-link" href="<?= e(url('admin/auto-publish')) ?>">发布与组装</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <?php
    $diag = $diag ?? [];
    $diagProblem = !empty($diag['schema_error']) || !empty($diag['log_error']) || (isset($diag['runs_exists']) && !$diag['runs_exists']);
    if ($diagProblem):
    ?>
        <div class="status-banner is-error">
            <strong>⚠️ 运行记录写入诊断</strong>
            <span>
                <?php if (!empty($diag['schema_error'])): ?>建表错误：<?= e((string) $diag['schema_error']) ?>。<?php endif; ?>
                <?php if (!empty($diag['log_error'])): ?>写入错误：<?= e((string) $diag['log_error']) ?>。<?php endif; ?>
                <?php if (isset($diag['runs_exists']) && !$diag['runs_exists']): ?>pipeline_runs 表不可读。<?php endif; ?>
                （当前已存 <?= e((string) ($diag['runs_count'] ?? 0)) ?> 条记录）可先试「重建运行记录表」，或把这条信息发给开发者。
            </span>
            <form method="post" action="<?= e(url('admin/autopilot')) ?>" style="margin-top:0.6rem">
                <input type="hidden" name="action" value="rebuild_runs">
                <button class="button button-small" type="submit">🔧 重建运行记录表</button>
            </form>
        </div>
    <?php elseif (isset($diag['runs_count']) && (int) $diag['runs_count'] === 0): ?>
        <div class="status-banner is-warning">
            <strong>ℹ️ 暂无运行记录</strong>
            <span>数据库连接与建表正常，但还没有成功写入的运行。点下方「立即运行一次」后若仍为 0，会在此显示具体错误。</span>
        </div>
    <?php endif; ?>

    <!-- ── Master kill-switch ─────────────────────────────────────────────── -->
    <div class="autopilot-master <?= $on ? 'is-on' : 'is-off' ?>" data-autopilot-master>
        <div class="autopilot-master-info">
            <span class="autopilot-status-dot"></span>
            <div>
                <strong data-ap-title><?= $on ? '自动驾驶：开启' : '自动驾驶：关闭' ?></strong>
                <p data-ap-desc><?= $on ? 'Cron 会按计划自动运行整条流水线。随时可一键关闭。' : '所有自动动作已暂停。Cron 运行时会跳过。开启后才会自动发文与组装早报。' ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(url('admin/autopilot')) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="autopilot-toggle <?= $on ? 'is-on' : '' ?>" type="submit"
                data-autopilot-toggle aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="切换自动驾驶开关">
                <span class="autopilot-toggle-track"><span class="autopilot-toggle-knob"></span></span>
                <span class="autopilot-toggle-label"><?= $on ? 'ON' : 'OFF' ?></span>
            </button>
        </form>
    </div>
    <p class="ap-hint" data-ap-hint hidden aria-live="polite"></p>

    <!-- ── Day 10·6: revenue + automation overview (one screen) ──────────── -->
    <?php if (!empty($overview)): $ov = $overview; ?>
        <section class="newsletter-block">
            <div class="ops-section-head">
                <h2>📊 收入与自动化总览</h2>
                <a class="ghost-link" href="<?= e(url('admin/pipeline-analytics')) ?>">详细分析 →</a>
            </div>
            <div class="pa-kpi-grid">
                <article class="pa-kpi pa-kpi-accent">
                    <span class="pa-kpi-icon">💵</span>
                    <strong>$<span data-countup="<?= e((string) (int) round($ov['est_rev_total'])) ?>">0</span></strong>
                    <small>预计累计收入（估算·USD）· 今日 $<?= e((string) $ov['est_rev_today']) ?></small>
                </article>
                <article class="pa-kpi"><span class="pa-kpi-icon">📈</span><strong>$<?= e((string) $ov['rpm']) ?></strong><small>目标 RPM（每千次浏览·USD）</small></article>
                <article class="pa-kpi"><span class="pa-kpi-icon">👁</span><strong data-countup="<?= e((string) $ov['views_today']) ?>">0</strong><small>今日浏览 · 累计 <?= e((string) $ov['views_total']) ?></small></article>
                <article class="pa-kpi"><span class="pa-kpi-icon">📧</span><strong data-countup="<?= e((string) $ov['subscribers']) ?>">0</strong><small>订阅用户</small></article>
                <article class="pa-kpi <?= $ov['success_rate'] >= 80 ? 'pa-kpi-accent' : ($ov['success_rate'] > 0 ? 'pa-kpi-warn' : '') ?>">
                    <span class="pa-kpi-icon">✅</span><strong><span data-countup="<?= e((string) $ov['success_rate']) ?>">0</span>%</strong><small>流水线成功率（14天）</small>
                </article>
                <article class="pa-kpi"><span class="pa-kpi-icon">📄</span><strong data-countup="<?= e((string) $ov['published_today']) ?>">0</strong><small>今日发布 · 早报 <?= e((string) $ov['issues_today']) ?></small></article>
                <article class="pa-kpi <?= $ov['open_alerts'] > 0 ? 'pa-kpi-warn' : '' ?>"><span class="pa-kpi-icon">🔔</span><strong data-countup="<?= e((string) $ov['open_alerts']) ?>">0</strong><small><a href="<?= e(url('admin/pipeline-alerts')) ?>">待处理告警</a></small></article>
                <article class="pa-kpi <?= $ov['adsense'] ? 'pa-kpi-accent' : '' ?>"><span class="pa-kpi-icon">📢</span><strong style="font-size:1.1rem"><?= $ov['adsense'] ? '已开启' : '未开启' ?></strong><small><a href="<?= e(url('admin/monetization')) ?>">AdSense 广告</a></small></article>
            </div>
            <?php if ($ov['rpm'] <= 0): ?>
                <p class="news-action-hint">💡 在 <a href="<?= e(url('admin/monetization')) ?>">会员与变现</a> 设置「目标 RPM」后，这里会按浏览量估算收入。</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ── Live pipeline flow ─────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>📊 实时流水线</h2>
        <div class="pipeline-flow">
            <?php
            $steps = [
                ['icon' => '🛰', 'label' => '待处理素材', 'value' => $live['ingest_new'], 'url' => 'admin/news-items'],
                ['icon' => '🧠', 'label' => '待写稿选题', 'value' => $live['clusters_selected'], 'url' => 'admin/story-clusters'],
                ['icon' => '🔍', 'label' => '待人工审核', 'value' => $live['review_pending'], 'url' => 'admin/review-queue'],
                ['icon' => '🚀', 'label' => '待发布', 'value' => $live['publish_pending'], 'url' => 'admin/auto-publish'],
                ['icon' => '📄', 'label' => '今日已发布', 'value' => $live['published_today'], 'url' => 'admin/articles'],
                ['icon' => '📰', 'label' => '今日早报', 'value' => $live['issues_today'], 'url' => 'admin/newsletter'],
            ];
            foreach ($steps as $i => $s):
            ?>
                <a class="pipeline-step" href="<?= e(url($s['url'])) ?>" style="--i: <?= e((string) $i) ?>">
                    <span class="pipeline-step-icon"><?= e($s['icon']) ?></span>
                    <strong><?= e((string) $s['value']) ?></strong>
                    <small><?= e($s['label']) ?></small>
                </a>
                <?php if ($i < count($steps) - 1): ?><span class="pipeline-arrow" aria-hidden="true">→</span><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Manual run + settings ──────────────────────────────────────────── -->
    <div class="autopilot-cols">
        <section class="newsletter-block">
            <h2>▶️ 手动运行一次</h2>
            <p>立即跑一遍完整流水线（不受开关限制，用于测试）。运行时弹出进度窗，分步执行、实时显示进度，不会因网页超时而中断。</p>
            <button class="button" type="button" data-run-pipeline
                data-step-url="<?= e(url('admin/autopilot/step')) ?>"
                <?= empty($aiReady['ready']) ? 'disabled' : '' ?>>
                ▶️ 立即运行一次
            </button>
            <?php if (empty($aiReady['ready'])): ?>
                <p class="news-action-hint">AI 引擎当前离线，暂不可运行。请先到 <a href="<?= e(url('admin/diagnostics')) ?>">诊断</a> 检查。</p>
            <?php else: ?>
                <p class="news-action-hint">分步执行：抓取 → 逐栏目聚类 → 逐条写稿 → 审核 → 发布。每步独立短请求，自动避开主机超时，并为免费额度的 AI 留出节流间隔。</p>
            <?php endif; ?>
        </section>

        <section class="newsletter-block">
            <h2>⚙️ 批量上限</h2>
            <form method="post" action="<?= e(url('admin/autopilot')) ?>" class="cms-form">
                <input type="hidden" name="action" value="save_settings">
                <div class="cms-form-grid">
                    <label>每次写稿上限<input type="number" name="synthesize_limit" min="1" max="24" value="<?= e((string) $config['synthesize_limit']) ?>"></label>
                    <label>每次审核上限<input type="number" name="assess_limit" min="1" max="24" value="<?= e((string) $config['assess_limit']) ?>"></label>
                    <label>每次发布上限<input type="number" name="publish_limit" min="1" max="50" value="<?= e((string) $config['publish_limit']) ?>"></label>
                    <label>AI 阶段间隔（秒）<input type="number" name="stage_pause" min="0" max="60" value="<?= e((string) ($config['stage_pause'] ?? 8)) ?>"></label>
                </div>
                <p class="news-action-hint">「AI 阶段间隔」是聚类与写稿等 AI 阶段之间的等待秒数，给免费额度的 AI 服务留出恢复时间，避免连续调用被限流（这正是手动测试时「草稿 0」的常见原因）。免费额度建议 8–15 秒。</p>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存设置</button></div>
            </form>
        </section>

        <?php
            $newsTtl = function_exists('pipeline_setting') ? (int) pipeline_setting('news_ttl_days', '10') : 10;
            $clusterTtl = function_exists('pipeline_setting') ? (int) pipeline_setting('cluster_ttl_days', '4') : 4;
            $draftTtl = function_exists('pipeline_setting') ? (int) pipeline_setting('draft_ttl_days', '7') : 7;
            $lastCleanAt = function_exists('pipeline_setting') ? (string) pipeline_setting('last_cleanup_at', '') : '';
            $lastCleanSum = function_exists('pipeline_setting') ? (string) pipeline_setting('last_cleanup_summary', '') : '';
        ?>
        <section class="newsletter-block">
            <h2>🧹 内容保鲜（自动清理）</h2>
            <p class="news-action-hint">新闻时效性强：过期的「待处理素材 / 待写稿选题 / 待审草稿」会自动清理，避免堆积成垃圾、让站点始终保持新鲜。<strong>每次发布阶段都会自动执行一次</strong>；下面是过期阈值，可按需调整。</p>
            <form method="post" action="<?= e(url('admin/autopilot')) ?>" class="cms-form">
                <input type="hidden" name="action" value="save_settings">
                <div class="cms-form-grid">
                    <label>素材保留（天）<input type="number" name="news_ttl_days" min="2" max="60" value="<?= (int) $newsTtl ?>"></label>
                    <label>选题保留（天）<input type="number" name="cluster_ttl_days" min="1" max="30" value="<?= (int) $clusterTtl ?>"></label>
                    <label>待审草稿保留（天）<input type="number" name="draft_ttl_days" min="1" max="60" value="<?= (int) $draftTtl ?>"></label>
                </div>
                <p class="news-action-hint">未在期限内被写稿/审核的内容会被清理（已发布文章与早报<strong>不受影响</strong>）。建议：素材 7–14 天、选题 3–5 天、待审草稿 5–7 天。</p>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存阈值</button></div>
            </form>
            <form method="post" action="<?= e(url('admin/autopilot')) ?>" class="cms-form" style="margin-top:.6rem">
                <input type="hidden" name="action" value="cleanup">
                <div class="cms-form-actions"><button class="button" type="submit">🧹 立即清理过期内容</button></div>
            </form>
            <?php if ($lastCleanAt !== ''): ?>
                <p class="news-action-hint">上次清理：<?= e($lastCleanAt) ?><?= $lastCleanSum !== '' ? '（' . e($lastCleanSum) . '）' : '' ?></p>
            <?php endif; ?>
        </section>
    </div>

    <!-- ── Day 10·7: per-category pause ───────────────────────────────────── -->
    <?php $allCategories = $allCategories ?? []; $pausedCategories = $pausedCategories ?? []; ?>
    <?php if ($allCategories): ?>
        <section class="newsletter-block">
            <div class="ops-section-head">
                <h2>🎛 分栏目开关</h2>
                <span><?= count($pausedCategories) > 0 ? (count($pausedCategories) . ' 个栏目已暂停') : '全部启用' ?></span>
            </div>
            <p>逐个栏目精细控制：取消勾选即<strong>暂停该栏目</strong>（聚类阶段直接跳过，不再产稿/发布/分发），其它栏目照常自动运行。总开关关闭时全部停止。</p>
            <form method="post" action="<?= e(url('admin/autopilot')) ?>" class="cms-form">
                <input type="hidden" name="action" value="save_pauses">
                <div class="cat-toggle-grid">
                    <?php foreach ($allCategories as $c): $slug = (string) $c['slug']; $on = !in_array($slug, $pausedCategories, true); ?>
                        <label class="cat-toggle <?= $on ? 'is-on' : 'is-off' ?>">
                            <input type="checkbox" name="active_categories[]" value="<?= e($slug) ?>" <?= $on ? 'checked' : '' ?>>
                            <span class="cat-toggle-name"><?= e((string) ($c['name'] ?? $slug)) ?></span>
                            <span class="cat-toggle-state"><?= $on ? '运行中' : '已暂停' ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存分栏目开关</button></div>
            </form>
        </section>
    <?php endif; ?>

    <!-- ── Run result ─────────────────────────────────────────────────────── -->
    <?php if (!empty($runResult)): ?>
        <div class="status-banner <?= ($runResult['status'] ?? '') === 'ok' ? 'is-ready' : 'is-warning' ?>">
            <strong>本次运行：<?= e((string) ($runResult['status'] ?? '')) ?></strong>
            <span><?= e((string) ($runResult['message'] ?? '')) ?><?= !empty($runResult['duration']) ? ' · 用时 ' . e((string) $runResult['duration']) . ' 秒' : '' ?></span>
        </div>
    <?php endif; ?>

    <!-- ── Cron guide ─────────────────────────────────────────────────────── -->
    <?php $stagePath = str_replace('run-daily.php', 'run-stage.php', (string) $cliPath); ?>
    <section class="news-cron-guide">
        <h2>⏰ 设置 Cron（真正的自动驾驶）</h2>

        <p><strong>✅ 推荐：接力式三段 Cron（更稳，不会因主机时限中断）。</strong>把流水线拆成三棒接力，错峰运行、数据库交接：抓取聚类 → 写稿 → 发布分发。每段都短、可续跑、有防重叠锁。</p>
        <code class="monitoring-url">0,30 * * * * /usr/bin/php <?= e($stagePath) ?> ingest</code>
        <code class="monitoring-url">10,40 * * * * /usr/bin/php <?= e($stagePath) ?> synthesize</code>
        <code class="monitoring-url">20,50 * * * * /usr/bin/php <?= e($stagePath) ?> publish</code>
        <p class="news-action-hint">三条都用 Custom 模式。每 30 分钟跑一轮：第 0/30 分抓取+聚类，第 10/40 分写稿，第 20/50 分审核+发布+组装早报+推送渠道。运行记录里会看到 <code>cron:ingest</code> / <code>cron:synthesize</code> / <code>cron:publish</code> 三类记录。慢的聚类不再拖垮写稿 —— 这是「草稿 0」的根治办法。</p>

        <p style="margin-top:1.2rem"><strong>或：单段 Cron（简单，但一次性跑完，受主机时限影响）。</strong>每天早上 7 点跑一次整条流水线：</p>
        <code class="monitoring-url">0 7 * * * /usr/bin/php <?= e($cliPath) ?></code>
        <p><small>两种都先检查上面的总开关：关闭时直接跳过。建议另保留每 2–4 小时一次的抓取 Cron（cli/fetch-news.php）让素材更新更勤。脚本仅命令行可运行。</small></p>
    </section>

    <!-- ── Run history ────────────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>🕑 运行记录</h2>
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
                <div class="empty-state"><strong>还没有运行记录。</strong><p>点「立即运行一次」或等 Cron 触发后，这里会显示每次运行的明细。</p></div>
            <?php endif; ?>
        </div>
    </section>
</section>

<!-- ── Pipeline run progress modal (Day 9 polish) ─────────────────────────── -->
<div class="run-modal" data-run-modal hidden>
    <div class="run-modal-backdrop"></div>
    <div class="run-modal-card" role="dialog" aria-modal="true" aria-labelledby="runModalTitle">
        <div class="run-modal-head">
            <span class="run-modal-spark" data-run-spark>🛰</span>
            <h3 id="runModalTitle" data-run-title>正在运行流水线…</h3>
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
