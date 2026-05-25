<?php $pageTitle = '文章管理 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">CMS</p>
            <h1>文章管理</h1>
            <p>创建、搜索、审核和发布钱潮文章。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="button button-small" href="<?= e(url('admin/articles/new')) ?>">新建文章</a>
        </div>
    </div>

    <?php if (!$dbReady): ?>
        <div class="status-banner is-warning"><strong>数据库未连接</strong><span>连接 MySQL 后才能管理文章。</span></div>
    <?php endif; ?>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/articles')) ?>">
        <input type="search" name="q" placeholder="搜索标题或 slug" value="<?= e($filters['q']) ?>">
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach (['draft' => '草稿', 'review' => '审核', 'published' => '已发布', 'archived' => '已归档'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="category">
            <option value="">全部栏目</option>
            <?php foreach ($adminCategories as $category): ?>
                <option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
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
                    <small><?= e($article['slug']) ?> · <?= e((string) $article['read_time_minutes']) ?> min</small>
                </div>
                <span><?= e($article['category_name']) ?></span>
                <span><mark><?= e($article['status']) ?></mark></span>
                <span><?= e($article['published_at'] ? date('Y-m-d H:i', strtotime((string) $article['published_at'])) : '未发布') ?></span>
                <div class="admin-row-actions">
                    <?php if ($article['status'] === 'published'): ?>
                        <a href="<?= e(url('article/' . $article['slug'])) ?>">查看</a>
                    <?php endif; ?>
                    <a href="<?= e(url('admin/articles/' . $article['id'] . '/edit')) ?>">编辑</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$articles): ?>
            <div class="empty-state">
                <strong>还没有数据库文章。</strong>
                <p>你可以新建文章，或从工作台一键生成 3 篇启动文章。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
