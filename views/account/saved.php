<?php $pageTitle = 'Saved Articles - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">Reader Library</p>
        <h1>已保存文章</h1>
        <p>把值得回看的市场信号集中放在这里。</p>
        <nav class="account-menu">
            <a class="button button-ghost" href="<?= e(url('account')) ?>">账户首页</a>
            <a class="button button-ghost" href="<?= e(url('account/preferences')) ?>">偏好设置</a>
        </nav>
    </div>
</section>

<section class="story-list">
    <?php foreach ($articles as $article): ?>
        <article class="list-card">
            <span class="pill"><?= e($article['category_name']) ?></span>
            <?php if (!empty($article['is_premium'])): ?><span class="premium-pill">会员内容</span><?php endif; ?>
            <h2><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h2>
            <p><?= e($article['dek']) ?></p>
            <small><?= e($article['read_time']) ?> · <?= e($article['published_at']) ?></small>
            <form method="post" action="<?= e(url('account/bookmarks/toggle')) ?>" class="inline-action">
                <input type="hidden" name="slug" value="<?= e($article['slug']) ?>">
                <input type="hidden" name="return" value="account/saved">
                <button class="link-button is-danger" type="submit">移除收藏</button>
            </form>
        </article>
    <?php endforeach; ?>
    <?php if (!$articles): ?>
        <div class="empty-state reader-empty-state">
            <strong>还没有保存文章。</strong>
            <p>登录后在文章页点击“保存文章”，这里就会变成你的阅读清单。</p>
            <a class="button button-small" href="<?= e(url('latest')) ?>">浏览最新文章</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($recentArticles): ?>
    <section class="related-section">
        <div class="section-heading">
            <p class="eyebrow">Recently Read</p>
            <h2>最近读过</h2>
        </div>
        <div class="story-grid">
            <?php foreach ($recentArticles as $article): ?>
                <article class="story-card interactive-card">
                    <span class="pill"><?= e($article['category_name']) ?></span>
                    <h3><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3>
                    <p><?= e($article['brief']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
