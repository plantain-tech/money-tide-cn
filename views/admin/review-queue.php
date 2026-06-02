<?php
$pageTitle = 'AI 审核台 - 钱潮 Money Tide';
$catName = [];
foreach ($adminCategories as $c) {
    $catName[(string) $c['slug']] = (string) $c['name'];
}
$confClass = static function (int $s): string {
    if ($s >= 80) { return 'score-hot'; }
    if ($s >= 65) { return 'score-warm'; }
    if ($s >= 50) { return 'score-mid'; }
    return 'score-low';
};
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">自动新闻 · 事实核查闸门</p>
            <h1>AI 审核台</h1>
            <p>AI 给每篇草稿打置信度分并标记需核查点。置信度 ≥ <?= e((string) $summary['threshold']) ?> 且无高风险标记 → 自动通过；否则进入下面的人工队列，由你一键 批准 / 退回。这就是「95% 自动，5% 人工」里的 5%。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/story-clusters')) ?>">选题聚类</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">草稿队列</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>
    <?php if (empty($aiReady['ready'])): ?>
        <div class="status-banner is-warning"><strong>AI 未就绪</strong><span><?= e((string) ($aiReady['message'] ?? '请检查 AI 配置。')) ?></span></div>
    <?php endif; ?>

    <!-- Stats hero -->
    <div class="news-stat-grid">
        <div class="news-stat news-stat-accent"><span>待人工审核</span><strong><?= e((string) $summary['pending_review']) ?></strong><small>你的 5%</small></div>
        <div class="news-stat"><span>自动通过</span><strong><?= e((string) $summary['auto_approved']) ?></strong><small>≥ <?= e((string) $summary['threshold']) ?> 分</small></div>
        <div class="news-stat"><span>待评估</span><strong><?= e((string) $summary['unassessed']) ?></strong><small>已起草未审核</small></div>
        <div class="news-stat"><span>平均置信度</span><strong><?= e((string) $summary['avg_confidence']) ?></strong><small>全部草稿</small></div>
        <div class="news-stat"><span>已退回</span><strong><?= e((string) $summary['rejected']) ?></strong><small>累计</small></div>
    </div>

    <!-- Assess action bar — staged AJAX with a blocking progress modal (no 503) -->
    <div class="news-action-bar">
        <button class="button" type="button"
            data-assess-run data-mode="new"
            data-step-url="<?= e(url('admin/review-queue/step')) ?>"
            data-title="AI 批量审核中…"
            <?= empty($aiReady['ready']) ? 'disabled' : '' ?>>
            🔍 AI 批量审核（最多8篇）<?= ($summary['unassessed'] ?? 0) > 0 ? '：' . (int) $summary['unassessed'] . ' 篇待评估' : '' ?>
        </button>
        <small class="news-action-hint">对「已起草未审核」的草稿运行 AI 事实核查，每篇耗 1 次 AI 额度。强草稿自动通过，可疑的留给你。<?= ($summary['unassessed'] ?? 0) < 1 ? '（当前没有待评估的新草稿——要处理下面 ' . (int) ($summary['pending_review'] ?? 0) . ' 篇人工队列，请用右侧「重新评估队列」。）' : '' ?></small>
        <button class="button button-ghost" type="button"
            data-assess-run data-mode="requeue"
            data-step-url="<?= e(url('admin/review-queue/step')) ?>"
            data-title="重新评估队列中…"
            <?= empty($aiReady['ready']) ? 'disabled' : '' ?>>
            ♻️ 重新评估队列（最多20篇）<?= ($summary['pending_review'] ?? 0) > 0 ? '：' . (int) $summary['pending_review'] . ' 篇待人工' : '' ?>
        </button>
        <small class="news-action-hint">用当前规则/阈值重新给「已转人工」的草稿打分。置信度达标的会立即转为自动通过（在下次发布阶段发布），不再卡在这里。</small>
    </div>

    <!-- Blocking, animated progress modal for the two batch actions above. -->
    <div class="run-modal" data-assess-modal hidden>
        <div class="run-modal-backdrop"></div>
        <div class="run-modal-card" role="dialog" aria-modal="true" aria-labelledby="assessModalTitle">
            <div class="run-modal-head">
                <span class="run-modal-spark" data-run-spark>🔍</span>
                <h3 id="assessModalTitle" data-run-title>AI 审核中…</h3>
                <p data-run-subtitle>正在逐篇调用 AI 事实核查，请勿关闭本页。</p>
            </div>
            <div class="run-progress">
                <div class="run-progress-bar"><span class="run-progress-fill" data-run-fill style="width:0%"></span></div>
                <div class="run-progress-meta">
                    <span data-run-percent>0%</span>
                    <span data-run-stepcount>已评估 0 / 0</span>
                </div>
            </div>
            <p class="run-modal-detail" data-run-detail aria-live="polite">准备中…</p>
            <div class="run-modal-foot" data-run-foot hidden>
                <p class="run-modal-summary" data-run-summary></p>
                <button class="button" type="button" data-run-close>完成并刷新</button>
            </div>
        </div>
    </div>

    <!-- Assess run result -->
    <?php if (!empty($assessRun)): ?>
        <section class="news-fetch-result">
            <h2>本次审核结果</h2>
            <div class="admin-table">
                <div class="admin-table-row admin-table-head"><span>草稿</span><span>结果</span></div>
                <?php foreach ($assessRun['details'] as $d): ?>
                    <div class="admin-table-row <?= $d['ok'] ? '' : 'smoke-row-fail' ?>">
                        <strong><?= e((string) ($d['title'] ?? ('#' . $d['id']))) ?></strong>
                        <span><mark class="<?= $d['ok'] ? 'status-ok' : 'status-warn' ?>"><?= e($d['message']) ?></mark></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- The human queue -->
    <h2 class="review-queue-heading">🔎 待人工审核（<?= e((string) count($queue)) ?>）</h2>
    <div class="review-list">
        <?php foreach ($queue as $item): ?>
            <?php $conf = (int) $item['confidence']; ?>
            <article class="review-card">
                <div class="review-card-main">
                    <div class="cluster-score <?= $confClass($conf) ?>" title="AI 置信度">
                        <strong><?= e((string) $conf) ?></strong>
                        <small>置信</small>
                    </div>
                    <div class="review-body">
                        <div class="cluster-meta">
                            <span class="news-item-cat"><?= e($catName[(string) $item['section_slug']] ?? (string) $item['section_slug']) ?></span>
                            <span class="review-flag-count"><?= e((string) count($item['flags'])) ?> 个需核查点</span>
                        </div>
                        <h3><a href="<?= e(url('admin/ai-drafts/' . (int) $item['draft_id'])) ?>"><?= e((string) $item['title']) ?></a></h3>
                        <?php if (!empty($item['dek'])): ?><p class="review-dek"><?= e((string) $item['dek']) ?></p><?php endif; ?>
                        <?php if (!empty($item['assessment'])): ?>
                            <p class="review-assessment"><strong>AI 结论：</strong><?= e((string) $item['assessment']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($item['flags'])): ?>
                            <ul class="review-flags">
                                <?php foreach ($item['flags'] as $f): ?>
                                    <li class="review-flag sev-<?= e((string) $f['severity']) ?>">
                                        <span class="review-flag-badge"><?= e($severityLabels[$f['severity']] ?? $f['severity']) ?></span>
                                        <span class="review-flag-text">
                                            <strong><?= e((string) $f['claim']) ?></strong>
                                            <?php if (!empty($f['note'])): ?><small><?= e((string) $f['note']) ?></small><?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="review-actions">
                    <a class="link-button" href="<?= e(url('admin/ai-drafts/' . (int) $item['draft_id'])) ?>" target="_blank" rel="noopener">📄 查看 / 编辑全文</a>
                    <div class="review-decide">
                        <form method="post" action="<?= e(url('admin/review-queue')) ?>" class="inline-action">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= e((string) $item['draft_id']) ?>">
                            <button type="submit" class="button button-small button-ghost"
                                data-confirm="退回这篇草稿？" data-confirm-sub="草稿状态会变为「已拒绝」，不会进入发布。" data-confirm-title="退回草稿" data-confirm-confirm="退回">↩️ 退回</button>
                        </form>
                        <form method="post" action="<?= e(url('admin/review-queue')) ?>" class="inline-action">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= e((string) $item['draft_id']) ?>">
                            <button type="submit" class="button button-small review-approve"
                                data-confirm="批准这篇草稿？" data-confirm-sub="确认已核查无误。草稿状态变为「已批准」，可进入发布流程。" data-confirm-title="批准草稿" data-confirm-confirm="批准发布">✅ 批准</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$queue): ?>
            <div class="empty-state">
                <strong>人工队列是空的 🎉</strong>
                <p>没有需要你核查的草稿。要么先点上方「🔍 AI 批量审核」评估新草稿，要么所有草稿都已自动通过或处理完毕。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
