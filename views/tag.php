<?php
$pageTitle = '标签：' . $tag['name'] . ' - 钱潮 Money Tide';
$pageDescription = '钱潮 Money Tide 中关于 #' . $tag['name'] . ' 的最新文章。';
$canonicalPath = 'tag/' . $tag['slug'];
?>
<section class="tag-shell">
    <div class="tag-header">
        <p class="eyebrow">Tag</p>
        <h1>#<?= e($tag['name']) ?></h1>
        <p><?= e((string) count($articles)) ?> 篇关于该话题的文章。</p>
    </div>

    <?php if ($articles): ?>
        <div class="story-grid">
            <?php foreach ($articles as $article): ?>
                <article class="story-card">
                    <span class="pill"><?= e($article['category_name']) ?></span>
                    <h3><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3>
                    <p><?= e($article['brief'] ?? $article['dek']) ?></p>
                    <small><?= e($article['read_time']) ?> · <?= time_ago_html($article['published_at']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>暂时没有相关文章。</strong>
            <p>钱潮编辑部会持续追踪这个话题。</p>
        </div>
    <?php endif; ?>

    <p><a class="ghost-link" href="<?= e(url('topics')) ?>">← 浏览全部标签</a></p>
</section>
