<?php
$pageTitle = seo_title(($article['seo_title'] ?? '') ?: $article['title']);
$pageDescription = seo_description((string) ($article['seo_description'] ?? ''), (string) ($article['dek'] ?? ''));
$canonicalPath = 'article/' . $article['slug'];
$canonicalUrl = canonical_url($canonicalPath);
$encodedUrl = rawurlencode($canonicalUrl);
$encodedTitle = rawurlencode($article['title']);
$ogType = 'article';
$ogImage = $socialImage ?? ($article['hero_image'] ?? default_og_image());
$shortFormat = $shortFormat ?? null;
$reader = $reader ?? (function_exists('reader_session') ? reader_session() : null);
$isSaved = !empty($isSaved);
$newsletterCta = $newsletterCta ?? newsletter_cta_for_category((string) ($article['category'] ?? ''));
$monetization = $monetization ?? monetization_settings();
$sameCategoryRelated = array_values(array_filter($related, static fn (array $item): bool => $item['category'] === $article['category']));
$otherRelated = array_values(array_filter($related, static fn (array $item): bool => $item['category'] !== $article['category']));
$relatedArticles = array_slice(array_merge($sameCategoryRelated, $otherRelated), 0, 3);
$readNext = $relatedArticles[0] ?? ($related[0] ?? null);
$newsArticleSchema = [
    '@type' => 'NewsArticle',
    'headline' => $article['title'],
    'description' => $pageDescription,
    'datePublished' => $article['published_at'],
    'dateModified' => ($article['updated_at'] ?? '') ?: $article['published_at'],
    'author' => ['@type' => 'Person', 'name' => $article['author_name'] ?? '钱潮编辑部'],
    'publisher' => ['@type' => 'Organization', 'name' => '钱潮 Money Tide'],
    'image' => $ogImage,
    'mainEntityOfPage' => $canonicalUrl,
    'inLanguage' => 'zh-CN',
];
$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        $newsArticleSchema,
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => canonical_url()],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $article['category_name'], 'item' => canonical_url('category/' . $article['category'])],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $canonicalUrl],
            ],
        ],
    ],
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
        <?php if (!empty($article['is_premium'])): ?>
            <span class="premium-pill"><?= e((string) ($monetization['premium_label'] ?? '会员内容')) ?></span>
        <?php endif; ?>
        <h1><?= e($article['title']) ?></h1>
        <p><?= e($article['dek']) ?></p>
        <?php
            $utmTwitter = rawurlencode(function_exists('share_utm_url') ? share_utm_url($canonicalUrl, 'twitter') : $canonicalUrl);
            $utmLinkedin = rawurlencode(function_exists('share_utm_url') ? share_utm_url($canonicalUrl, 'linkedin') : $canonicalUrl);
            $utmCopy = function_exists('share_utm_url') ? share_utm_url($canonicalUrl, 'copy') : $canonicalUrl;
        ?>
        <div class="article-meta-row">
            <small><?= e($article['published_at']) ?> · <?= e($article['read_time']) ?> · 作者：<?= e($article['author_name'] ?? '钱潮编辑部') ?></small>
            <div class="share-actions" aria-label="分享文章">
                <a href="https://twitter.com/intent/tweet?text=<?= e($encodedTitle) ?>&url=<?= e($utmTwitter) ?>" target="_blank" rel="noopener" data-share-event="<?= e($article['slug']) ?>">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($utmLinkedin) ?>" target="_blank" rel="noopener" data-share-event="<?= e($article['slug']) ?>">in</a>
                <button type="button" data-share-copy="<?= e($utmCopy) ?>" data-share-slug="<?= e($article['slug']) ?>">复制链接</button>
            </div>
        </div>
        <div class="article-retention-actions">
            <?php if (is_array($reader)): ?>
                <form method="post" action="<?= e(url('account/bookmarks/toggle')) ?>">
                    <input type="hidden" name="slug" value="<?= e($article['slug']) ?>">
                    <input type="hidden" name="return" value="article/<?= e($article['slug']) ?>">
                    <button class="button button-small <?= $isSaved ? 'button-ghost' : '' ?>" type="submit"><?= $isSaved ? '已保存' : '保存文章' ?></button>
                </form>
                <a class="ghost-link" href="<?= e(url('account/saved')) ?>">我的收藏</a>
            <?php else: ?>
                <a class="button button-small button-ghost" href="<?= e(url('account/login')) ?>">登录后保存</a>
            <?php endif; ?>
        </div>
        <figure class="article-hero-media">
            <img src="<?= e($ogImage) ?>" alt="<?= e($article['hero_image_alt'] ?? $article['title']) ?>" loading="eager" decoding="async" fetchpriority="high">
        </figure>
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

    <?php if ($shortFormat && (trim((string) ($shortFormat['summary'] ?? '')) !== '' || !empty($shortFormat['bullets']))): ?>
        <section class="short-format-card reveal-on-scroll" id="short-format">
            <div class="short-format-head">
                <span class="short-format-badge">60 秒看懂</span>
                <button type="button" class="short-format-copy" data-shortformat-copy>复制速读</button>
            </div>
            <?php if (trim((string) ($shortFormat['summary'] ?? '')) !== ''): ?>
                <p class="short-format-summary"><?= e((string) $shortFormat['summary']) ?></p>
            <?php endif; ?>
            <?php if (!empty($shortFormat['bullets'])): ?>
                <ul class="short-format-bullets">
                    <?php foreach ((array) $shortFormat['bullets'] as $b): ?>
                        <?php if (trim((string) $b) !== ''): ?><li><?= e((string) $b) ?></li><?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="short-format-meta">
                <?php if (trim((string) ($shortFormat['key_number'] ?? '')) !== ''): ?>
                    <div class="short-format-number">
                        <span>关键数字</span>
                        <strong><?= e((string) $shortFormat['key_number']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (trim((string) ($shortFormat['risk_note'] ?? '')) !== ''): ?>
                    <div class="short-format-risk">
                        <span>注意</span>
                        <p><?= e((string) $shortFormat['risk_note']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <textarea hidden data-shortformat-text><?php
                $sfText = '【60秒看懂】' . (string) $article['title'] . "\n\n";
                if (trim((string) ($shortFormat['summary'] ?? '')) !== '') { $sfText .= (string) $shortFormat['summary'] . "\n\n"; }
                foreach ((array) ($shortFormat['bullets'] ?? []) as $b) { if (trim((string) $b) !== '') { $sfText .= '· ' . (string) $b . "\n"; } }
                if (trim((string) ($shortFormat['key_number'] ?? '')) !== '') { $sfText .= "\n关键数字：" . (string) $shortFormat['key_number']; }
                if (trim((string) ($shortFormat['risk_note'] ?? '')) !== '') { $sfText .= "\n注意：" . (string) $shortFormat['risk_note']; }
                $sfText .= "\n\n" . $canonicalUrl;
                echo e($sfText);
            ?></textarea>
        </section>
    <?php endif; ?>

    <div class="article-content-layout">
        <aside class="article-side-rail" aria-label="阅读工具">
            <strong>分享</strong>
            <div class="share-actions vertical">
                <a href="https://twitter.com/intent/tweet?text=<?= e($encodedTitle) ?>&url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener" data-share-event="<?= e($article['slug']) ?>">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener" data-share-event="<?= e($article['slug']) ?>">in</a>
                <button type="button" data-share-copy="<?= e($canonicalUrl) ?>" data-share-slug="<?= e($article['slug']) ?>">复制</button>
            </div>
        </aside>

        <div class="article-body article-reading-body reveal-on-scroll">
            <?php if (function_exists('monetize_body_has_affiliate') && monetize_body_has_affiliate((array) $article['body'])): ?>
                <p class="affiliate-disclosure">🔗 <?= e(monetize_affiliate_disclosure()) ?></p>
            <?php endif; ?>
            <?php $monetizeState = function_exists('monetize_new_state') ? monetize_new_state() : ['used' => [], 'count' => 0]; ?>
            <?php foreach ($article['body'] as $paragraph): ?>
                <p><?= function_exists('monetize_render_paragraph') ? monetize_render_paragraph((string) $paragraph, $monetizeState) : e($paragraph) ?></p>
            <?php endforeach; ?>

            <?php if (function_exists('monetize_sponsor_html')): ?>
                <?= monetize_sponsor_html('article') ?>
            <?php endif; ?>

            <?php if (function_exists('adsense_slot_html')): ?>
                <?= adsense_slot_html('article') ?>
            <?php endif; ?>

            <?php if (!empty($article['is_premium'])): ?>
                <aside class="premium-soft-wall">
                    <p class="eyebrow"><?= e((string) ($monetization['premium_label'] ?? '会员内容')) ?></p>
                    <h2>这篇文章已经按会员内容结构标记。</h2>
                    <p><?= e((string) ($article['premium_excerpt'] ?: ($monetization['member_price_note'] ?? '当前仍对所有读者开放阅读。'))) ?></p>
                </aside>
            <?php endif; ?>

            <aside class="article-inline-newsletter">
                <div>
                    <p class="eyebrow"><?= e($newsletterCta['label']) ?></p>
                    <h2><?= e($newsletterCta['title']) ?></h2>
                    <p><?= e($newsletterCta['copy']) ?></p>
                </div>
                <form class="inline-form" method="post" action="<?= e(url('api/newsletter/subscribe')) ?>" data-newsletter-form>
                    <input type="email" name="email" placeholder="你的邮箱" required>
                    <input type="hidden" name="source" value="article-inline-<?= e($article['slug']) ?>">
                    <button type="submit">订阅</button>
                </form>
            </aside>
            <span data-article-complete="<?= e($article['slug']) ?>"></span>
        </div>
    </div>

    <?php
        $articleDbId = $articleDbId ?? (int) ($article['id'] ?? 0);
        $reactionCounts = $reactionCounts ?? [];
        $reactionsActive = $reactionsActive ?? [];
        $reactionTypes = function_exists('reaction_types') ? reaction_types() : [];
    ?>
    <?php if ($articleDbId > 0 && $reactionTypes): ?>
        <section class="reaction-bar reveal-on-scroll" data-reaction-bar data-article-id="<?= e((string) $articleDbId) ?>" data-slug="<?= e($article['slug']) ?>">
            <div class="reaction-bar-head">
                <p class="eyebrow">这篇对你怎么样？</p>
                <h2>一秒反馈，帮我们决定写什么</h2>
            </div>
            <div class="reaction-bar-buttons">
                <?php foreach ($reactionTypes as $key => $label): ?>
                    <?php $isActive = in_array($key, $reactionsActive, true); ?>
                    <button
                        type="button"
                        class="reaction-chip<?= $isActive ? ' is-active' : '' ?>"
                        data-reaction="<?= e($key) ?>"
                        aria-pressed="<?= $isActive ? 'true' : 'false' ?>">
                        <span class="reaction-chip-label"><?= e($label) ?></span>
                        <span class="reaction-chip-count" data-reaction-count><?= e((string) (int) ($reactionCounts[$key] ?? 0)) ?></span>
                        <span class="reaction-chip-burst" aria-hidden="true"></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="reaction-bar-foot" data-reaction-foot hidden>谢谢反馈，已记录。</p>
        </section>
    <?php endif; ?>

    <?php if ($readNext): ?>
        <aside class="read-next-card reveal-on-scroll">
            <div>
            <p class="eyebrow">继续阅读</p>
                <h2><a href="<?= e(url('article/' . $readNext['slug'])) ?>"><?= e($readNext['title']) ?></a></h2>
                <p><?= e($readNext['brief']) ?></p>
            </div>
            <a class="button button-small" href="<?= e(url('article/' . $readNext['slug'])) ?>">继续阅读</a>
        </aside>
    <?php endif; ?>

    <?php $tags = $tags ?? []; if ($tags): ?>
        <aside class="article-tag-bar">
            <span class="eyebrow">标签</span>
            <?php foreach ($tags as $tag): ?>
                <a class="topic-chip" href="<?= e(url('tag/' . $tag['slug'])) ?>">#<?= e($tag['name']) ?></a>
            <?php endforeach; ?>
        </aside>
    <?php endif; ?>

    <aside class="article-share-prompt reveal-on-scroll">
        <div>
            <p class="eyebrow">觉得有用？</p>
            <h2>把这篇分享给会用到的人</h2>
            <p>转发给同事或朋友，或复制带追踪参数的链接，方便我们了解内容传播。</p>
        </div>
        <div class="article-share-prompt-actions">
            <a class="button button-small" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= e($encodedTitle) ?>&url=<?= e($utmTwitter) ?>" data-share-event="<?= e($article['slug']) ?>">分享到 X</a>
            <a class="button button-small button-ghost" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($utmLinkedin) ?>" data-share-event="<?= e($article['slug']) ?>">分享到 LinkedIn</a>
            <button class="button button-small button-ghost" type="button" data-share-copy="<?= e($utmCopy) ?>" data-share-slug="<?= e($article['slug']) ?>">复制链接</button>
        </div>
    </aside>
</article>

<section class="related-section reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">相关阅读</p>
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
