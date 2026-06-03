<?php
$pageTitle = $query !== '' ? '搜索：' . $query . ' - 钱潮 Money Tide' : '搜索 - 钱潮 Money Tide';
$pageDescription = '搜索钱潮 Money Tide 的市场、商业、科技、加密、政策、全球、理财和出海文章。';
$canonicalPath = 'search';
?>
<section class="tag-shell search-shell">
    <div class="tag-header">
        <p class="eyebrow">Search</p>
        <h1>搜索钱潮</h1>
        <p>按标题、导语、正文、标签和栏目查找文章。</p>
    </div>

    <form class="search-page-form" method="get" action="<?= e(url('search')) ?>">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="搜索 AI、美联储、比特币、出海..." autofocus>
        <button class="button" type="submit">搜索</button>
    </form>

    <?php if ($query !== ''): ?>
        <div class="section-heading">
            <p class="eyebrow"><?= e((string) count($results)) ?> results</p>
            <h2>“<?= e($query) ?>” 的搜索结果</h2>
        </div>
        <?php if ($results): ?>
            <div class="latest-list search-results-list">
                <?php foreach ($results as $article): ?>
                    <article class="latest-row interactive-card">
                        <div>
                            <span class="pill"><?= e($article['category_name']) ?></span>
                            <?php if (!empty($article['is_premium'])): ?><span class="premium-pill">Premium</span><?php endif; ?>
                            <h2><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= search_highlight((string) $article['title'], $query) ?></a></h2>
                            <p><?= search_highlight((string) ($article['dek'] ?: $article['brief']), $query) ?></p>
                            <small><?= time_ago_html($article['published_at']) ?> · <?= e($article['read_time']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state reader-empty-state">
                <strong>没有找到匹配文章</strong>
                <p>可以换一个关键词，或先看看最新发布和热门话题。</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>输入关键词开始搜索</strong>
            <p>例如：AI、美联储、ETF、出海、汇率、芯片。</p>
        </div>
    <?php endif; ?>

    <?php if (!$query || !$results): ?>
        <section class="related-section">
            <div class="section-heading">
                <p class="eyebrow">Fallback</p>
                <h2>最新文章</h2>
            </div>
            <div class="story-grid">
                <?php foreach ($fallbackArticles as $article): ?>
                    <article class="story-card interactive-card">
                        <span class="pill"><?= e($article['category_name']) ?></span>
                        <h3><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3>
                        <p><?= e($article['brief']) ?></p>
                        <small><?= time_ago_html($article['published_at']) ?> · <?= e($article['read_time']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($popularTags): ?>
            <section class="topic-cloud">
                <p class="eyebrow">热门话题</p>
                <?php foreach ($popularTags as $tag): ?>
                    <a class="topic-chip" href="<?= e(url('tag/' . $tag['slug'])) ?>">#<?= e($tag['name']) ?></a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
