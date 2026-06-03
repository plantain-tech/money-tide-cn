<?php $pageTitle = '最新 - 钱潮 Money Tide'; ?>
<section class="page-hero compact">
    <p class="eyebrow">Latest</p>
    <h1>最新信号</h1>
    <p>市场、科技、商业与政策的最新中文简报。</p>
</section>

<section class="story-list">
    <?php foreach ($articles as $article): ?>
        <article class="list-card">
            <span class="pill"><?= e($article['category_name']) ?></span>
            <h2><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h2>
            <p><?= e($article['dek']) ?></p>
            <small><?= e($article['read_time']) ?> · <?= time_ago_html($article['published_at']) ?></small>
        </article>
    <?php endforeach; ?>
</section>
