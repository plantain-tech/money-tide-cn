<?php
$pageTitle = '文章管理 - 钱潮 Money Tide';
$statusLabels = ['draft' => '草稿', 'review' => '审核', 'published' => '已发布', 'archived' => '已归档'];
$tabs = [
    '' => '全部',
    'draft' => '草稿',
    'review' => '审核',
    'published' => '已发布',
    'archived' => '已归档',
];
$baseQuery = $filters;
unset($baseQuery['status']);
$tabHref = static function (string $status) use ($baseQuery): string {
    $params = array_filter(array_merge($baseQuery, ['status' => $status]), static fn ($v) => $v !== '' && $v !== null);
    return url('admin/articles') . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">CMS</p>
            <h1>文章管理</h1>
            <p>创建、搜索、审核和发布钱潮文章。删除仅限管理员，发布和归档仅限编辑/管理员。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <?php if (can_create_article()): ?>
                <a class="button button-small" href="<?= e(url('admin/articles/new')) ?>">新建文章</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$dbReady): ?>
        <div class="status-banner is-warning"><strong>数据库未连接</strong><span>连接 MySQL 后才能管理文章。</span></div>
    <?php endif; ?>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="状态">
        <?php foreach ($tabs as $value => $label): ?>
            <?php $count = $value === '' ? ($statusCounts['all'] ?? 0) : ($statusCounts[$value] ?? 0); ?>
            <a class="admin-tab <?= $filters['status'] === $value ? 'is-active' : '' ?>" href="<?= e($tabHref($value)) ?>">
                <span><?= e($label) ?></span>
                <small><?= e((string) $count) ?></small>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/articles')) ?>">
        <input type="search" name="q" placeholder="搜索标题、slug 或副标题" value="<?= e($filters['q']) ?>">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <select name="category">
            <option value="">全部栏目</option>
            <?php foreach ($adminCategories as $category): ?>
                <option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="起始日期">
        <input type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="结束日期">
        <select name="sort" aria-label="排序">
            <?php foreach ([
                'updated_desc' => '最近更新',
                'updated_asc' => '最早更新',
                'published_desc' => '最近发布',
                'published_asc' => '最早发布',
                'title_asc' => '标题 A→Z',
            ] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head">
            <span>标题</span><span>栏目</span><span>状态</span><span>发布时间</span><span>操作</span>
        </div>
        <?php foreach ($articles as $article): ?>
            <div class="admin-table-row">
                <div>
                    <strong><?= e($article['title']) ?></strong>
                    <?php if (!empty($article['is_premium'])): ?><small><mark>Premium</mark></small><?php endif; ?>
                    <small><?= e($article['slug']) ?> · <?= e((string) $article['read_time_minutes']) ?> min</small>
                    <small>作者：<?= e((string) ($article['author_name'] ?? '钱潮编辑部')) ?><?= !empty($article['editor_name']) ? ' · 责任编辑：' . e((string) $article['editor_name']) : '' ?></small>
                </div>
                <span><?= e($article['category_name']) ?></span>
                <span><mark><?= e($statusLabels[$article['status']] ?? $article['status']) ?></mark></span>
                <span><?= e($article['published_at'] ? date('Y-m-d H:i', strtotime((string) $article['published_at'])) : '未发布') ?></span>
                <div class="admin-row-actions">
                    <?php if ($article['status'] === 'published'): ?>
                        <a href="<?= e(url('article/' . $article['slug'])) ?>">查看</a>
                    <?php endif; ?>
                    <a href="<?= e(url('admin/articles/' . $article['id'] . '/preview')) ?>">预览</a>
                    <a href="<?= e(url('admin/articles/' . $article['id'] . '/edit')) ?>">编辑</a>
                    <form method="post" action="<?= e(url('admin/articles/' . $article['id'] . '/duplicate')) ?>" class="inline-action">
                        <button type="submit" class="link-button" data-confirm="复制这篇文章为新草稿？" data-confirm-sub="副本会以草稿状态保存。" data-confirm-title="复制文章" data-confirm-confirm="复制">复制</button>
                    </form>
                    <?php if (can_delete_article() && in_array($article['status'], ['draft', 'archived'], true)): ?>
                        <form method="post" action="<?= e(url('admin/articles/' . $article['id'] . '/delete')) ?>" class="inline-action">
                            <button type="submit" class="link-button is-danger" data-confirm="永久删除这篇文章？" data-confirm-sub="这一操作无法撤销。仅限草稿和已归档文章。" data-confirm-variant="danger" data-confirm-title="删除文章" data-confirm-confirm="永久删除">删除</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$articles): ?>
            <div class="empty-state">
                <strong>没有匹配的文章。</strong>
                <p>尝试调整状态、栏目或日期筛选，或新建文章。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
