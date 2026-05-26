<?php
$pageTitle = '预览：' . ($article['title'] ?? '') . ' - 钱潮 Money Tide';
$statusLabels = ['draft' => '草稿', 'review' => '审核', 'published' => '已发布', 'archived' => '已归档'];
$previewStatus = (string) ($article['status'] ?? 'draft');
?>
<div class="preview-banner" role="status">
    <div>
        <strong>预览模式</strong>
        <span>当前状态：<?= e($statusLabels[$previewStatus] ?? $previewStatus) ?>。后台预览，未对外公开。</span>
    </div>
    <div class="preview-banner-actions">
        <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">返回列表</a>
    </div>
</div>

<article class="article-shell article-reading-shell">
    <header class="article-header article-reading-header">
        <?php if (!empty($article['category_name'])): ?>
            <span class="pill"><?= e($article['category_name']) ?></span>
        <?php endif; ?>
        <h1><?= e($article['title']) ?></h1>
        <p><?= e($article['dek']) ?></p>
        <div class="article-meta-row">
            <small><?= e($article['published_at']) ?> · <?= e($article['read_time']) ?></small>
        </div>
    </header>

    <section class="article-summary article-reading-summary">
        <div>
            <span>一句话看懂</span>
            <p><?= e($article['brief']) ?></p>
        </div>
        <div>
            <span>为什么重要</span>
            <p><?= e($article['why']) ?></p>
        </div>
    </section>

    <div class="article-body article-reading-body">
        <?php foreach ($article['body'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
    </div>
</article>
