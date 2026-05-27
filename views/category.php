<?php
$pageTitle = seo_title($category['name']);
$pageDescription = seo_description((string) ($category['summary'] ?? ''), '钱潮 Money Tide 分类频道。');
$canonicalPath = 'category/' . $category['slug'];
$ogImage = category_fallback_image((string) $category['slug']);
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $category['name'],
    'description' => $pageDescription,
    'url' => canonical_url($canonicalPath),
    'inLanguage' => 'zh-CN',
];
?>
<section class="page-hero compact">
    <p class="eyebrow">频道</p>
    <h1><?= e($category['name']) ?></h1>
    <p><?= e($category['summary']) ?></p>
</section>

<section class="story-list">
    <?php foreach ($articles as $article): ?>
        <article class="list-card">
            <span class="pill"><?= e($article['category_name']) ?></span>
            <?php if (!empty($article['is_premium'])): ?><span class="premium-pill">会员内容</span><?php endif; ?>
            <h2><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h2>
            <p><?= e($article['dek']) ?></p>
            <small><?= e($article['read_time']) ?> · <?= e($article['published_at']) ?></small>
        </article>
    <?php endforeach; ?>
    <?php if (!$articles): ?>
        <div class="empty-state reader-empty-state">
            <strong>这个频道还没有文章。</strong>
            <p>发布后，相关内容会自动出现在这里。</p>
        </div>
    <?php endif; ?>
</section>
