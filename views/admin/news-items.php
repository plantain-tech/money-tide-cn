<?php
$pageTitle = '已抓取条目 - 钱潮 Money Tide';
$catName = [];
foreach ($adminCategories as $c) {
    $catName[(string) $c['slug']] = (string) $c['name'];
}
$statusLabels = ['new' => '待处理', 'clustered' => '已聚类', 'used' => '已使用', 'ignored' => '已忽略'];
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">自动新闻 · 素材库</p>
            <h1>已抓取条目</h1>
            <p>来自各新闻源的原始标题与摘要。下一步（Day 9·2）AI 会按栏目聚类、去重、挑出当天最值得写的信号。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/news-sources')) ?>">新闻源</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <!-- Category breakdown chips -->
    <div class="news-cat-chips">
        <a class="news-cat-chip <?= $filters['category_slug'] === '' ? 'is-active' : '' ?>" href="<?= e(url('admin/news-items')) ?>">
            <span>全部</span><strong><?= e((string) $summary['items_total']) ?></strong>
        </a>
        <?php foreach ($adminCategories as $c): ?>
            <?php $cstats = $summary['by_category'][(string) $c['slug']] ?? ['total' => 0, 'new' => 0]; ?>
            <a class="news-cat-chip <?= $filters['category_slug'] === (string) $c['slug'] ? 'is-active' : '' ?>" href="<?= e(url('admin/news-items') . '?category_slug=' . urlencode((string) $c['slug'])) ?>">
                <span><?= e((string) $c['name']) ?></span><strong><?= e((string) $cstats['total']) ?></strong>
                <?php if ($cstats['new'] > 0): ?><em><?= e((string) $cstats['new']) ?> 新</em><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/news-items')) ?>">
        <input type="search" name="q" placeholder="搜索标题或摘要" value="<?= e($filters['q']) ?>">
        <input type="hidden" name="category_slug" value="<?= e($filters['category_slug']) ?>">
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach ($statusLabels as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button button-small">筛选</button>
    </form>

    <div class="news-item-list">
        <?php foreach ($items as $item): ?>
            <article class="news-item-card">
                <div class="news-item-meta">
                    <span class="news-item-cat"><?= e($catName[(string) $item['category_slug']] ?? (string) $item['category_slug']) ?></span>
                    <span class="news-item-source"><?= e((string) ($item['source_name'] ?? '—')) ?></span>
                    <span class="news-item-status news-status-<?= e((string) $item['status']) ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span>
                    <span class="news-item-time"><?= $item['published_at'] ? e(date('m-d H:i', strtotime((string) $item['published_at']))) : e(date('m-d H:i', strtotime((string) $item['fetched_at']))) ?></span>
                </div>
                <h3><a href="<?= e((string) $item['url']) ?>" target="_blank" rel="noopener"><?= e((string) $item['title']) ?></a></h3>
                <?php if (!empty($item['summary'])): ?>
                    <p><?= e(mb_strimwidth((string) $item['summary'], 0, 240, '…', 'UTF-8')) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="empty-state">
                <strong>还没有抓取到条目。</strong>
                <p>去 <a href="<?= e(url('admin/news-sources')) ?>">新闻源</a> 页面点击「⚡ 立即抓取」，或等待 Cron 自动运行。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
