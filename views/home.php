<?php
$pageTitle = '钱潮 Money Tide - 每天5分钟，看懂全球市场';
$topStory = $featured;
$latestArticles = array_slice($articles, 0, 6);
$secondaryStories = array_slice($articles, 1, 4);
$editorPicks = array_slice(array_merge(array_slice($articles, 2), $articles), 0, 5);
$focusSlugs = ['markets', 'business', 'tech', 'crypto', 'policy', 'world', 'wealth'];
$categoryStats = function_exists('category_article_stats') ? category_article_stats() : [];
$categoryArticles = [];
foreach ($focusSlugs as $slug) {
    $categoryArticles[$slug] = array_values(array_filter($articles, static fn (array $article): bool => $article['category'] === $slug));
}
// Freshness light: newest published item drives a fresh/warn/stale signal.
$newestAt = $topStory['published_at'] ?? ($articles[0]['published_at'] ?? '');
$fresh = function_exists('freshness_state') ? freshness_state($newestAt) : ['state' => 'fresh', 'label' => '', 'hours' => null];
?>
<section class="home-hero reveal-on-scroll">
    <div class="hero-copy">
        <p class="eyebrow">今日钱潮
            <?php if (!empty($newestAt)): ?>
                <span class="freshness-pill is-<?= e($fresh['state']) ?>" title="最新内容更新于 <?= e((string) relative_time($newestAt)) ?>">
                    <span class="freshness-dot" aria-hidden="true"></span><?= e($fresh['label']) ?> · 最新 <?= e((string) relative_time($newestAt)) ?>
                </span>
            <?php endif; ?>
        </p>
        <h1>每天5分钟，<br>看懂全球市场。</h1>
        <p class="hero-dek">为中文读者重写全球财经新闻：更快、更清楚，也更适合在手机上读完。</p>
        <div class="hero-actions">
            <a class="button" href="<?= e(url('subscribe')) ?>">加入免费早报</a>
            <a class="text-link" href="<?= e(url('latest')) ?>">查看最新文章</a>
        </div>
    </div>
    <aside class="hero-briefing-panel" aria-label="今日编辑台">
        <span class="panel-label">EDITOR'S DESK</span>
        <strong>市场、商业、科技、加密、政策、全球与理财，一屏掌握。</strong>
        <p>综合运用智能平台，把复杂的全球市场信息，提炼成可以快速读完、即时行动的中文简报。</p>
    </aside>
</section>

<?php $market = function_exists('market_snapshot') ? market_snapshot(false) : []; ?>
<section class="market-ticker reveal-on-scroll" aria-label="实时行情" data-market-strip>
    <span class="market-live" title="实时行情（每分钟刷新）"><span class="market-live-dot" aria-hidden="true"></span>LIVE</span>
    <div class="ticker-track">
        <?php foreach (['sp500', 'nasdaq', 'dow', 'hsi', 'gold', 'btc', 'brent', 'vix'] as $k): ?>
            <?php
                $m = $market[$k] ?? null;
                $label = (string) ($m['label'] ?? strtoupper($k));
                $price = (string) ($m['price'] ?? '—');
                $change = (string) ($m['change'] ?? '');
                $pct = (string) ($m['pct'] ?? '');
                $dir = (int) ($m['dir'] ?? 0);
                $spark = is_array($m['spark'] ?? null) ? $m['spark'] : [];
                $cls = $dir > 0 ? 'is-up' : ($dir < 0 ? 'is-down' : '');
                $arrow = $dir > 0 ? '▲' : ($dir < 0 ? '▼' : '');
                $changeText = trim($change . ' ' . $pct);
            ?>
            <div class="ticker-item" data-market-item="<?= e($k) ?>">
                <div class="ticker-text">
                    <span class="ticker-label"><?= e($label) ?></span>
                    <span class="ticker-price" data-market-price><?= e($price) ?></span>
                    <span class="ticker-change <?= $cls ?>" data-market-change><?php if ($arrow !== ''): ?><span class="ticker-arrow" aria-hidden="true"><?= $arrow ?></span><?php endif; ?><?= e($changeText) ?></span>
                </div>
                <svg class="ticker-spark <?= $cls ?>" viewBox="0 0 64 22" preserveAspectRatio="none" aria-hidden="true" data-market-spark><?= function_exists('market_spark_svg') ? market_spark_svg($spark, $dir) : '' ?></svg>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="top-story-section reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">Top Story</p>
        <h2>今天先读这一篇</h2>
    </div>
    <?php if ($topStory): ?>
        <article class="top-story-card">
            <div>
                <span class="pill"><?= e($topStory['category_name']) ?></span>
                <h2><a href="<?= e(url('article/' . $topStory['slug'])) ?>"><?= e($topStory['title']) ?></a></h2>
                <p><?= e($topStory['dek']) ?></p>
                <small><?= e($topStory['read_time']) ?> · <?= time_ago_html($topStory['published_at']) ?></small>
            </div>
            <div class="signal-board" aria-hidden="true">
                <span style="height: 38%"></span><span style="height: 76%"></span><span style="height: 54%"></span><span style="height: 92%"></span><span style="height: 68%"></span><span style="height: 82%"></span>
            </div>
        </article>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>还没有置顶文章</strong>
            <p>发布第一篇文章后，这里会自动展示今日头条。</p>
        </div>
    <?php endif; ?>
</section>

<section class="reader-dashboard reveal-on-scroll">
    <div class="latest-panel">
        <div class="section-heading">
            <p class="eyebrow">Latest</p>
            <h2>最新发布</h2>
        </div>
        <?php if ($latestArticles): ?>
            <div class="latest-list">
                <?php foreach ($latestArticles as $index => $article): ?>
                    <article class="latest-item">
                        <span><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                        <div>
                            <strong><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></strong>
                            <small><?= e($article['category_name']) ?> · <?= time_ago_html($article['published_at']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state reader-empty-state">
                <strong>暂无最新文章</strong>
                <p>文章发布后会按时间顺序显示在这里。</p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="picks-panel">
        <div class="section-heading">
            <p class="eyebrow">Most Read / Editor Picks</p>
            <h2>编辑推荐</h2>
        </div>
        <?php if ($editorPicks): ?>
            <ol class="pick-list">
                <?php foreach ($editorPicks as $article): ?>
                    <li>
                        <a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a>
                        <span><?= e($article['category_name']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <div class="empty-state reader-empty-state">
                <strong>暂无推荐</strong>
                <p>编辑精选内容会显示在这里。</p>
            </div>
        <?php endif; ?>
    </aside>
</section>

<section class="quick-read-section reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">60秒看懂</p>
        <h2>先读这几篇</h2>
    </div>
    <?php if ($secondaryStories): ?>
        <div class="story-grid story-grid-featured">
            <?php foreach ($secondaryStories as $article): ?>
                <article class="story-card interactive-card">
                    <span class="pill"><?= e($article['category_name']) ?></span>
                    <h3><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h3>
                    <p><?= e($article['brief']) ?></p>
                    <small><?= e($article['read_time']) ?> · <?= time_ago_html($article['published_at']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>还需要更多文章</strong>
            <p>再发布几篇文章后，这里会形成更完整的快速阅读区。</p>
        </div>
    <?php endif; ?>
</section>

<section class="briefing-band reveal-on-scroll">
    <aside class="briefing-card">
        <p class="eyebrow">钱潮早报</p>
        <h2>你的全球市场信号，明早送达。</h2>
        <p>今日5件事、美股收盘、AI与科技、加密市场和中国公司出海。</p>
        <form class="inline-form" method="post" action="<?= e(url('api/newsletter/subscribe')) ?>" data-newsletter-form>
            <input type="email" name="email" placeholder="你的邮箱" required>
            <input type="hidden" name="source" value="home-briefing">
            <button type="submit">订阅</button>
        </form>
        <div class="social-login-row" aria-label="快捷登录">
            <a href="<?= e(url('account/oauth/google')) ?>">使用 Google 继续</a>
        </div>
    </aside>
</section>

<section class="category-band reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">频道</p>
        <h2>跟着钱流向读新闻</h2>
    </div>
    <div class="category-grid category-grid-strong">
        <?php foreach ($categories as $category): ?>
            <?php if (!in_array($category['slug'], $focusSlugs, true)) {
                continue;
            } ?>
            <?php
            $items = $categoryArticles[$category['slug']] ?? [];
            $stat = $categoryStats[$category['slug']] ?? null;
            // True total from DB; the recent-50 window count is only a fallback.
            $tileCount = $stat['total'] ?? count($items);
            // Teaser: the channel's newest article regardless of age, so an
            // active-but-quiet channel never looks broken/empty.
            $tileTeaser = ($stat['latest_title'] ?? '') !== '' ? $stat['latest_title'] : ($items[0]['title'] ?? '');
            ?>
            <a class="category-tile interactive-card" href="<?= e(url('category/' . $category['slug'])) ?>">
                <span class="category-count">共 <?= e((string) $tileCount) ?> 篇</span>
                <strong><?= e($category['name']) ?></strong>
                <span><?= e($category['summary']) ?></span>
                <?php if ($tileTeaser !== ''): ?>
                    <em><?= e($tileTeaser) ?></em>
                <?php else: ?>
                    <em>暂无文章，发布后自动补齐。</em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php $mostRead = $mostRead ?? []; if ($mostRead): ?>
<section class="most-read-band reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">Most Read</p>
        <h2>读者本周最常打开</h2>
    </div>
    <ol class="most-read-list">
        <?php foreach ($mostRead as $row): ?>
            <li>
                <span class="most-read-rank"></span>
                <div>
                    <a class="pill" href="<?= e(url('category/' . $row['category'])) ?>"><?= e($row['category_name']) ?></a>
                    <h3><a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e($row['title']) ?></a></h3>
                    <small><?= e((string) $row['views']) ?> 次阅读 · <?= time_ago_html($row['published_at']) ?></small>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<?php $popularTags = $popularTags ?? []; if ($popularTags): ?>
<section class="topic-band reveal-on-scroll">
    <div class="section-heading">
        <p class="eyebrow">Topics</p>
        <h2>热门话题</h2>
        <a class="ghost-link" href="<?= e(url('topics')) ?>">查看全部</a>
    </div>
    <div class="topic-cloud">
        <?php foreach ($popularTags as $tag): ?>
            <a class="topic-chip" href="<?= e(url('tag/' . $tag['slug'])) ?>">
                <strong>#<?= e($tag['name']) ?></strong>
                <small><?= e((string) $tag['article_count']) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
