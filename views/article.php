<?php $pageTitle = $article['title'] . ' - 钱潮 Money Tide'; ?>
<article class="article-shell">
    <header class="article-header">
        <a class="pill" href="<?= e(url('category/' . $article['category'])) ?>"><?= e($article['category_name']) ?></a>
        <h1><?= e($article['title']) ?></h1>
        <p><?= e($article['dek']) ?></p>
        <small><?= e($article['published_at']) ?> · <?= e($article['read_time']) ?></small>
    </header>

    <section class="article-summary">
        <div>
            <span>一句话看懂</span>
            <p><?= e($article['brief']) ?></p>
        </div>
        <div>
            <span>为什么重要</span>
            <p><?= e($article['why']) ?></p>
        </div>
    </section>

    <section class="key-numbers">
        <?php foreach ($article['numbers'] as $number => $label): ?>
            <div>
                <strong><?= e((string) $number) ?></strong>
                <span><?= e((string) $label) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="article-body">
        <?php foreach ($article['body'] as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
    </div>

    <aside class="article-cta">
        <h2>免费收到《钱潮早报》</h2>
        <p>每天5分钟，读懂全球市场、科技、加密与中国公司出海。</p>
        <form class="inline-form" method="post" action="<?= e(url('api/newsletter/subscribe')) ?>" data-newsletter-form>
            <input type="email" name="email" placeholder="你的邮箱" required>
            <input type="hidden" name="source" value="article-<?= e($article['slug']) ?>">
            <button type="submit">订阅</button>
        </form>
    </aside>
</article>

<section class="related-section">
    <div class="section-heading">
        <p class="eyebrow">继续阅读</p>
        <h2>相关信号</h2>
    </div>
    <div class="story-grid">
        <?php foreach (array_slice($related, 0, 3) as $relatedArticle): ?>
            <article class="story-card">
                <span class="pill"><?= e($relatedArticle['category_name']) ?></span>
                <h3><a href="<?= e(url('article/' . $relatedArticle['slug'])) ?>"><?= e($relatedArticle['title']) ?></a></h3>
                <p><?= e($relatedArticle['brief']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
