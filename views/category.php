<?php $pageTitle = $category['name'] . ' - 钱潮 Money Tide'; ?>
<section class="page-hero compact">
    <p class="eyebrow">频道</p>
    <h1><?= e($category['name']) ?></h1>
    <p><?= e($category['summary']) ?></p>
</section>

<section class="story-list">
    <?php foreach ($articles as $article): ?>
        <article class="list-card">
            <span class="pill"><?= e($article['category_name']) ?></span>
            <h2><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h2>
            <p><?= e($article['dek']) ?></p>
            <small><?= e($article['read_time']) ?> · <?= e($article['published_at']) ?></small>
        </article>
    <?php endforeach; ?>
</section>
