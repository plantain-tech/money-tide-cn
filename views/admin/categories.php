<?php $pageTitle = '栏目 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">CMS</p>
            <h1>栏目</h1>
            <p>Day 3 先查看栏目结构；编辑栏目可以放到后续迭代。</p>
        </div>
        <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
    </div>

    <?php if (!$dbReady): ?>
        <div class="status-banner is-warning"><strong>数据库未连接</strong><span>当前显示公开站点的 fallback 栏目。</span></div>
    <?php endif; ?>

    <div class="admin-module-grid">
        <?php foreach (($adminCategories ?: $categories) as $category): ?>
            <a class="admin-module" href="<?= e(url('category/' . $category['slug'])) ?>">
                <strong><?= e($category['name']) ?></strong>
                <span><?= e($category['summary']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
