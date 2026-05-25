<?php $pageTitle = '启动文章 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">CMS</p>
            <h1>启动文章</h1>
            <p><?= e($seedResult['message'] ?? '操作完成。') ?></p>
        </div>
        <div class="admin-actions">
            <a class="button button-small" href="<?= e(url('admin/articles')) ?>">查看文章</a>
            <a class="ghost-link" href="<?= e(url()) ?>">查看首页</a>
        </div>
    </div>
</section>
