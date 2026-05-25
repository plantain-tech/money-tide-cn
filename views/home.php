<?php $pageTitle = '钱潮 Money Tide - 每天5分钟，看懂全球市场'; ?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">今日钱潮</p>
        <h1>每天5分钟，<br>看懂全球市场。</h1>
        <p class="hero-dek">为中文读者重写全球财经新闻：更快、更清楚，也更适合在手机上读完。</p>
        <div class="hero-actions">
            <a class="button" href="<?= e(url('subscribe')) ?>">加入免费早报</a>
            <a class="text-link" href="<?= e(url('latest')) ?>">查看最新文章</a>
        </div>
    </div>
    <?php if ($featured): ?>
        <article class="lead-card">
            <span class="pill"><?= e($featured['category_name']) ?></span>
            <h2><a href="<?= e(url('article/' . $featured['slug'])) ?>"><?= e($featured['title']) ?></a></h2>
            <p><?= e($featured['dek']) ?></p>
            <div class="mini-chart" aria-hidden="true">
                <span style="height: 42%"></span><span style="height: 72%"></span><span style="height: 52%"></span><span style="height: 88%"></span><span style="height: 64%"></span>
            </div>
        </article>
    <?php endif; ?>
</section>

<section class="market-strip" aria-label="市场快照">
    <div><span>NASDAQ</span><strong>+0.84%</strong></div>
    <div><span>HSI</span><strong>-0.31%</strong></div>
    <div><span>BTC</span><strong>+1.42%</strong></div>
    <div><span>USD/CNH</span><strong>7.24</strong></div>
    <div><span>GOLD</span><strong>+0.18%</strong></div>
</section>

<section class="content-grid">
    <div>
        <div class="section-heading">
            <p class="eyebrow">60秒看懂</p>
            <h2>先读这几篇</h2>
        </div>
        <div class="story-grid">
            <?php foreach (array_slice($articles, 1, 3) as $article): ?>
                <article class="story-card">
                    <span class="pill"><?= e($article['category_name']) ?></span>
                    <h3><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3>
                    <p><?= e($article['brief']) ?></p>
                    <small><?= e($article['read_time']) ?> · <?= e($article['published_at']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <aside class="briefing-card">
        <p class="eyebrow">钱潮早报</p>
        <h2>你的全球市场信号，明早送达。</h2>
        <p>今日5件事、美股收盘、AI与科技、加密市场和中国公司出海。</p>
        <form class="inline-form" data-newsletter-form>
            <input type="email" name="email" placeholder="你的邮箱" required>
            <button type="submit">订阅</button>
        </form>
        <div class="social-login-row" aria-label="快捷登录">
            <button type="button">Google</button>
            <button type="button">微信</button>
            <button type="button">Apple</button>
        </div>
    </aside>
</section>

<section class="category-band">
    <div class="section-heading">
        <p class="eyebrow">频道</p>
        <h2>跟着钱流向读新闻</h2>
    </div>
    <div class="category-grid">
        <?php foreach ($categories as $category): ?>
            <a class="category-tile" href="<?= e(url('category/' . $category['slug'])) ?>">
                <strong><?= e($category['name']) ?></strong>
                <span><?= e($category['summary']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
