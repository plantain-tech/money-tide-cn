<?php $pageTitle = '我的账号 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">已登录</p>
        <h1>欢迎，<?= e((string) $reader['name']) ?></h1>
        <p>账号邮箱：<?= e((string) $reader['email']) ?></p>

        <div class="account-stats">
            <div>
                <span>订阅频率</span>
                <strong><?= e(reader_frequency_options()[(string) $account['preferences']['digest_frequency']] ?? (string) $account['preferences']['digest_frequency']) ?></strong>
            </div>
            <div>
                <span>关注栏目</span>
                <strong><?= e((string) count($account['topics'])) ?></strong>
            </div>
            <div>
                <span>已邀请</span>
                <strong><?= e((string) $referral['invited_count']) ?></strong>
            </div>
            <div>
                <span>已保存</span>
                <strong><?= e((string) ($savedCount ?? 0)) ?></strong>
            </div>
        </div>

        <nav class="account-menu">
            <a class="button" href="<?= e(url('account/preferences')) ?>">编辑偏好</a>
            <a class="button button-ghost" href="<?= e(url('account/saved')) ?>">已保存文章</a>
            <a class="button button-ghost" href="<?= e(url('account/profile')) ?>">个人资料</a>
            <a class="button button-ghost" href="<?= e(url('account/referral')) ?>">邀请朋友</a>
            <a class="button button-ghost" href="<?= e(url('account/unsubscribe')) ?>">订阅管理</a>
            <a class="ghost-link" href="<?= e(url('account/logout')) ?>">退出登录</a>
        </nav>
    </div>
</section>

<?php if (!empty($recentArticles)): ?>
    <section class="related-section">
        <div class="section-heading">
            <p class="eyebrow">Recently Read</p>
            <h2>继续阅读</h2>
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
