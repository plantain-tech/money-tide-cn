<?php
$pageTitle = $article['title'] . ' - 钱潮 Money Tide';
$pageDescription = $article['dek'];
$canonicalPath = 'article/' . $article['slug'];
$canonicalUrl = canonical_url($canonicalPath);
$encodedUrl = rawurlencode($canonicalUrl);
$encodedTitle = rawurlencode($article['title']);
$ogType = 'article';
$sameCategoryRelated = array_values(array_filter($related, static fn (array $item): bool => $item['category'] === $article['category']));
$otherRelated = array_values(array_filter($related, static fn (array $item): bool => $item['category'] !== $article['category']));
$relatedArticles = array_slice(array_merge($sameCategoryRelated, $otherRelated), 0, 3);
$readNext = $relatedArticles[0] ?? ($related[0] ?? null);
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $article['title'],
    'description' => $article['dek'],
    'datePublished' => $article['published_at'],
    'author' => ['@type' => 'Organization', 'name' => '钱潮 Money Tide'],
    'publisher' => ['@type' => 'Organization', 'name' => '钱潮 Money Tide'],
    'mainEntityOfPage' => $canonicalUrl,
    'inLanguage' => 'zh-CN',
];
?>
<article class="article-shell article-reading-shell">
    <nav class="article-breadcrumbs" aria-label="文章路径">
        <a href="<?= e(url()) ?>">首页</a>
        <span>/</span>
        <a href="<?= e(url('category/' . $article['category'])) ?>"><?= e($article['category_name']) ?></a>
        <span>/</span>
        <span>正文</span>
    </nav>

    <header class="article-header article-reading-header reveal-on-scroll">
        <a class="pill" href="<?= e(url('category/' . $article['category'])) ?>"><?= e($article['category_name']) ?></a>
        <h1><?= e($article['title']) ?></h1>
        <p><?= e($article['dek']) ?></p>
        <div class="article-meta-row">
            <small><?= e($article['published_at']) ?> · <?= e($article['read_time']) ?></small>
            <div class="share-actions" aria-label="分享文章">
                <a href="https://twitter.com/intent/tweet?text=<?= e($encodedTitle) ?>&url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener">in</a>
                <button type="button" data-share-copy="<?= e($canonicalUrl) ?>">复制链接</button>
            </div>
        </div>
    </header>

    <section class="article-summary article-reading-summary reveal-on-scroll">
        <div>
            <span>一句话看懂</span>
            <p><?= e($article['brief']) ?></p>
        </div>
        <div>
            <span>为什么重要</span>
            <p><?= e($article['why']) ?></p>
        </div>
    </section>

    <?php if (!empty($article['numbers'])): ?>
        <section class="key-numbers reveal-on-scroll">
            <?php foreach ($article['numbers'] as $number => $label): ?>
                <div>
                    <strong><?= e((string) $number) ?></strong>
                    <span><?= e((string) $label) ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="article-content-layout">
        <aside class="article-side-rail" aria-label="阅读工具">
            <strong>分享</strong>
            <div class="share-actions vertical">
                <a href="https://twitter.com/intent/tweet?text=<?= e($encodedTitle) ?>&url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener">in</a>
                <button type="button" data-share-copy="<?= e($canonicalUrl) ?>">复制</button>
            </div>
        </aside>

        <div class="article-body article-reading-body reveal-on-scroll">
            <?php foreach ($article['body'] as $paragraph): ?>
                <p><?= e($paragraph) ?></p>
            <?php endforeach; ?>

            <aside class="article-inline-newsletter">
                <div>
                    <p class="eyebrow">钱潮早报</p>
                    <h2>把下一组市场信号发到你的邮箱。</h2>
                    <p>每天 5 分钟，读懂全球市场、科技、加密与中国公司出海。</p>
                </div>
                <form class="inline-form" method="post" action="<?= e(url('api/newsletter/subscribe')) ?>" data-newsletter-form>
                    <input type="email" name="email" placeholder="你的邮箱" required>
                    <input type="hidden" name="source" value="article-inline-<?= e($article['slug']) ?>">
                    <button type="submit">订阅</button>
                </form>
            </aside>
        </div>
    </div>

    <?php if ($readNext): ?>
        <aside class="read-next-card reveal-on-scroll">
            <div>
                <p class="eyebrow">Read Next</p>
                <h2><a href="<?= e(url('article/' . $readNext['slug'])) ?>"><?= e($readNext['title']) ?></a></h2>
                <p><?= e($readNext['brief']) ?></p>
            </div>
            <a class="button button-small" href="<?= e(url('article/' . $readNext['slug'])) ?>">继续阅读</a>
        </aside>
    <?php endif; ?>
</article>

<section class="related-section reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">Related Articles</p>
        <h2>相关信号</h2>
    </div>
    <?php if ($relatedArticles): ?>
        <div class="story-grid">
            <?php foreach ($relatedArticles as $relatedArticle): ?>
                <article class="story-card interactive-card">
                    <span class="pill"><?= e($relatedArticle['category_name']) ?></span>
                    <h3><a href="<?= e(url('article/' . $relatedArticle['slug'])) ?>"><?= e($relatedArticle['title']) ?></a></h3>
                    <p><?= e($relatedArticle['brief']) ?></p>
                    <small><?= e($relatedArticle['read_time']) ?> · <?= e($relatedArticle['published_at']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>暂无相关文章</strong>
            <p>发布更多文章后，系统会在这里展示可继续阅读的内容。</p>
        </div>
    <?php endif; ?>
</section>
