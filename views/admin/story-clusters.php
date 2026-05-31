<?php
$pageTitle = '选题聚类 - 钱潮 Money Tide';
$catName = [];
foreach ($adminCategories as $c) {
    $catName[(string) $c['slug']] = (string) $c['name'];
}
$scoreClass = static function (int $s): string {
    if ($s >= 80) { return 'score-hot'; }
    if ($s >= 60) { return 'score-warm'; }
    if ($s >= 40) { return 'score-mid'; }
    return 'score-low';
};
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">自动新闻 · 聚类与选题</p>
            <h1>选题聚类</h1>
            <p>AI 把抓取到的新闻去重、聚类，并按对中文财经读者的价值打分排序。选用的 cluster 会进入下一步（Day 9·3）由 AI 合成原创文章。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/news-items')) ?>">素材库</a>
            <a class="ghost-link" href="<?= e(url('admin/news-sources')) ?>">新闻源</a>
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
        <div class="news-stat"><span>Cluster 总数</span><strong><?= e((string) $summary['total']) ?></strong><small>累计</small></div>
        <div class="news-stat news-stat-accent"><span>已选用</span><strong><?= e((string) $summary['selected']) ?></strong><small>待生成草稿</small></div>
        <div class="news-stat"><span>候选</span><strong><?= e((string) $summary['candidate']) ?></strong><small>可手动选用</small></div>
        <div class="news-stat"><span>今日新增</span><strong><?= e((string) $summary['today']) ?></strong><small>今天聚类</small></div>
        <div class="news-stat"><span>素材待处理</span><strong><?= e((string) $newsSummary['items_new']) ?></strong><small>news status=new</small></div>
    </div>

    <!-- Cluster action bar -->
    <div class="news-action-bar">
        <form method="post" action="<?= e(url('admin/story-clusters')) ?>" class="news-fetch-form" data-news-fetch>
            <input type="hidden" name="action" value="cluster">
            <select name="category_slug" aria-label="聚类范围">
                <option value="">全部栏目</option>
                <?php foreach ($adminCategories as $c): ?>
                    <option value="<?= e((string) $c['slug']) ?>"><?= e((string) $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button" type="submit" data-news-fetch-btn <?= empty($aiReady['ready']) ? 'disabled' : '' ?>>
                <span class="news-fetch-label">🧠 AI 聚类选题</span>
                <span class="news-fetch-spinner" hidden></span>
            </button>
        </form>
        <small class="news-action-hint">每个栏目消耗 1 次 AI 额度。建议先在「素材库」抓取新闻，再聚类。</small>
    </div>

    <!-- Per-run result -->
    <?php if (!empty($clusterSummary)): ?>
        <section class="news-fetch-result">
            <h2>本次聚类结果</h2>
            <div class="admin-table">
                <div class="admin-table-row admin-table-head"><span>栏目</span><span>结果</span></div>
                <?php foreach ($clusterSummary['details'] as $d): ?>
                    <div class="admin-table-row <?= $d['ok'] ? '' : 'smoke-row-fail' ?>">
                        <strong><?= e($d['name']) ?></strong>
                        <span><mark class="<?= $d['ok'] ? 'status-ok' : 'status-warn' ?>"><?= e($d['message']) ?></mark></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Category filter chips -->
    <div class="news-cat-chips">
        <a class="news-cat-chip <?= $filters['category_slug'] === '' ? 'is-active' : '' ?>" href="<?= e(url('admin/story-clusters')) ?>">
            <span>全部</span><strong><?= e((string) $summary['total']) ?></strong>
        </a>
        <?php foreach ($adminCategories as $c): ?>
            <?php $cs = $summary['by_category'][(string) $c['slug']] ?? ['total' => 0, 'selected' => 0]; ?>
            <a class="news-cat-chip <?= $filters['category_slug'] === (string) $c['slug'] ? 'is-active' : '' ?>" href="<?= e(url('admin/story-clusters') . '?category_slug=' . urlencode((string) $c['slug'])) ?>">
                <span><?= e((string) $c['name']) ?></span><strong><?= e((string) $cs['total']) ?></strong>
                <?php if ($cs['selected'] > 0): ?><em><?= e((string) $cs['selected']) ?> 选</em><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Cluster cards -->
    <div class="cluster-list">
        <?php foreach ($clusters as $cl): ?>
            <?php $score = (int) $cl['score']; ?>
            <article class="cluster-card is-<?= e((string) $cl['status']) ?>">
                <div class="cluster-card-main">
                    <div class="cluster-score <?= $scoreClass($score) ?>">
                        <strong><?= e((string) $score) ?></strong>
                        <small>分</small>
                    </div>
                    <div class="cluster-body">
                        <div class="cluster-meta">
                            <span class="news-item-cat"><?= e($catName[(string) $cl['category_slug']] ?? (string) $cl['category_slug']) ?></span>
                            <span class="cluster-status-pill cluster-<?= e((string) $cl['status']) ?>"><?= e($statusLabels[$cl['status']] ?? $cl['status']) ?></span>
                            <span class="cluster-count"><?= e((string) $cl['item_count']) ?> 条来源</span>
                        </div>
                        <h3><?= e((string) $cl['headline']) ?></h3>
                        <?php if (!empty($cl['angle'])): ?>
                            <p class="cluster-angle"><strong>报道角度：</strong><?= e((string) $cl['angle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($cl['why_it_matters'])): ?>
                            <p class="cluster-why"><strong>为什么重要：</strong><?= e((string) $cl['why_it_matters']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($cl['members'])): ?>
                            <details class="cluster-sources">
                                <summary><?= e((string) count($cl['members'])) ?> 个来源条目</summary>
                                <ul>
                                    <?php foreach ($cl['members'] as $m): ?>
                                        <li><a href="<?= e((string) $m['url']) ?>" target="_blank" rel="noopener"><?= e((string) $m['title']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cluster-actions">
                    <?php if ($cl['status'] !== 'selected'): ?>
                        <form method="post" action="<?= e(url('admin/story-clusters')) ?>" class="inline-action">
                            <input type="hidden" name="action" value="select">
                            <input type="hidden" name="id" value="<?= e((string) $cl['id']) ?>">
                            <button type="submit" class="button button-small">✓ 选用</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($cl['status'] !== 'skipped'): ?>
                        <form method="post" action="<?= e(url('admin/story-clusters')) ?>" class="inline-action">
                            <input type="hidden" name="action" value="skip">
                            <input type="hidden" name="id" value="<?= e((string) $cl['id']) ?>">
                            <button type="submit" class="link-button">跳过</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($cl['primary_url'])): ?>
                        <a class="link-button" href="<?= e((string) $cl['primary_url']) ?>" target="_blank" rel="noopener">主来源 ↗</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('admin/story-clusters')) ?>" class="inline-action">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string) $cl['id']) ?>">
                        <button type="submit" class="link-button is-danger" data-confirm="删除这个 cluster？" data-confirm-variant="danger" data-confirm-title="删除选题 cluster" data-confirm-confirm="删除">删除</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$clusters): ?>
            <div class="empty-state">
                <strong>还没有选题 cluster。</strong>
                <p>先在 <a href="<?= e(url('admin/news-sources')) ?>">新闻源</a> 抓取新闻，再点上方「🧠 AI 聚类选题」让 AI 去重、排序、挑出当天值得写的选题。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
