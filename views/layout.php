<?php
$pageTitle = $pageTitle ?? ($site['name'] ?? '钱潮 Money Tide');
$pageDescription = $pageDescription ?? ($site['description'] ?? '');
$canonicalPath = $canonicalPath ?? trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$canonicalUrl = $canonicalUrl ?? canonical_url($canonicalPath);
$ogType = $ogType ?? 'website';
$ogImage = $ogImage ?? default_og_image();
$schema = $schema ?? [
    '@context' => 'https://schema.org',
    '@type' => 'NewsMediaOrganization',
    'name' => 'Money Tide',
    'alternateName' => '钱潮',
    'url' => canonical_url(),
    'inLanguage' => 'zh-CN',
];
$gaMeasurementId = (string) app_config('analytics.ga_measurement_id', '');
$plausibleDomain = (string) app_config('analytics.plausible_domain', '');
$currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
// Cache-busting version strings — update automatically when files change on disk.
$cssVersion = @filemtime(APP_BASE_PATH . '/public/assets/css/app.css') ?: '1';
$jsVersion  = @filemtime(APP_BASE_PATH . '/public/assets/js/app.js')  ?: '1';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <link rel="alternate" type="application/rss+xml" title="钱潮 Money Tide 最新文章" href="<?= e(canonical_url('feed/all.xml')) ?>">
    <?php if (!empty($category['slug'])): ?>
        <link rel="alternate" type="application/rss+xml" title="钱潮 <?= e((string) $category['name']) ?> RSS" href="<?= e(canonical_url('feed/category/' . $category['slug'] . '.xml')) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="钱潮 Money Tide">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <meta name="theme-color" content="#dcff00">
    <?php if ($gaMeasurementId !== ''): ?>
        <link rel="dns-prefetch" href="https://www.googletagmanager.com">
        <link rel="preconnect" href="https://www.googletagmanager.com">
    <?php endif; ?>
    <?php if ($plausibleDomain !== ''): ?>
        <link rel="dns-prefetch" href="https://plausible.io">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=<?= $cssVersion ?>">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php // Day 10·6: AdSense loader — PUBLIC pages only, never in the admin console.
    if (strpos($currentPath, 'admin') !== 0 && function_exists('adsense_head_script')): ?>
        <?= adsense_head_script() ?>
    <?php endif; ?>
    <?php if ($plausibleDomain !== ''): ?>
        <script defer data-domain="<?= e($plausibleDomain) ?>" src="https://plausible.io/js/script.js"></script>
    <?php endif; ?>
    <?php if ($gaMeasurementId !== ''): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaMeasurementId) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?= e($gaMeasurementId) ?>');
        </script>
    <?php endif; ?>
</head>
<?php $isAdminArea = strpos($currentPath, 'admin') === 0; ?>
<body class="<?= $isAdminArea ? 'is-admin-area' : '' ?>">
    <?php if ($isAdminArea): ?>
        <!-- Admin console bar: no public nav; a clean "view site" guide instead. -->
        <header class="admin-bar">
            <a class="admin-bar-brand" href="<?= e(url('admin')) ?>" aria-label="钱潮 编辑后台">
                <span class="brand-mark">钱潮</span>
                <span class="admin-bar-tag">编辑后台</span>
            </a>
            <div class="admin-bar-actions">
                <a class="admin-bar-view" href="<?= e(url()) ?>" target="_blank" rel="noopener" title="在新标签页打开网站首页，便于预览">
                    <span class="admin-bar-view-ic" aria-hidden="true">🏠</span>
                    <span class="admin-bar-view-text">查看网站</span>
                    <span class="admin-bar-view-arrow" aria-hidden="true">↗</span>
                </a>
                <a class="admin-bar-logout ghost-link" href="<?= e(url('admin/logout')) ?>">退出</a>
            </div>
        </header>
    <?php else: ?>
        <header class="site-header">
            <a class="brand" href="<?= e(url()) ?>" aria-label="钱潮 Money Tide 首页">
                <span class="brand-mark">钱潮</span>
                <span class="brand-name">Money Tide</span>
            </a>
            <nav class="top-nav" aria-label="主导航">
                <?php foreach (array_slice($categories, 0, 10) as $navCategory): ?>
                    <?php $navPath = 'category/' . $navCategory['slug']; ?>
                    <a class="<?= $currentPath === $navPath ? 'is-active' : '' ?>" href="<?= e(url($navPath)) ?>"><?= e($navCategory['name']) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="header-actions">
                <form class="header-search" method="get" action="<?= e(url('search')) ?>" role="search">
                    <input type="search" name="q" placeholder="搜索" value="<?= e((string) ($_GET['q'] ?? '')) ?>" aria-label="搜索文章">
                </form>
                <a class="ghost-link" href="<?= e(url('latest')) ?>">最新</a>
                <a class="ghost-link" href="<?= e(url('topics')) ?>">话题</a>
                <a class="ghost-link" href="<?= e(url('newsletter')) ?>">早报</a>
                <?php if (function_exists('reader_session') && reader_session() !== null): ?>
                    <a class="ghost-link" href="<?= e(url('account')) ?>">账号</a>
                <?php else: ?>
                    <a class="ghost-link" href="<?= e(url('account/login')) ?>">登录</a>
                <?php endif; ?>
                <a class="button button-small" href="<?= e(url('subscribe')) ?>">免费订阅</a>
            </div>
        </header>
    <?php endif; ?>

    <main>
        <?= $content ?>
    </main>

    <?php if (!$isAdminArea): ?>
        <footer class="site-footer">
            <div>
                <strong>钱潮 Money Tide</strong>
                <p>面向中文读者的全球财经、科技与商业简报。</p>
            </div>
            <nav aria-label="页脚导航">
                <a href="<?= e(url('about')) ?>">关于</a>
                <a href="<?= e(url('editorial-standards')) ?>">编辑标准</a>
                <a href="<?= e(url('disclaimer')) ?>">免责声明</a>
                <a href="<?= e(url('subscribe')) ?>">订阅</a>
                <a href="<?= e(url('search')) ?>">搜索</a>
                <a href="<?= e(url('feed/all.xml')) ?>">RSS</a>
            </nav>
        </footer>
    <?php endif; ?>
    <?php if (strpos($currentPath, 'admin') !== 0 && $currentPath !== 'subscribe'): ?>
        <aside class="newsletter-slidein" data-newsletter-slidein hidden aria-live="polite">
            <button class="slidein-close" type="button" data-newsletter-slidein-close aria-label="关闭订阅提示">×</button>
            <p class="eyebrow">钱潮早报</p>
            <h2>每天 5 分钟，看懂全球市场。</h2>
            <form class="slidein-form" method="post" action="<?= e(url('api/newsletter/subscribe')) ?>" data-newsletter-form>
                <input type="email" name="email" placeholder="你的邮箱" required>
                <input type="hidden" name="source" value="slidein">
                <input type="hidden" name="ref" value="" data-referral-input>
                <div class="slidein-topics">
                    <label><input type="checkbox" name="topics[]" value="markets" checked> 市场</label>
                    <label><input type="checkbox" name="topics[]" value="tech" checked> 科技</label>
                    <label><input type="checkbox" name="topics[]" value="wealth"> 理财</label>
                </div>
                <button class="button button-small" type="submit">订阅</button>
            </form>
        </aside>
    <?php endif; ?>
    <button class="back-to-top" type="button" data-back-to-top aria-label="返回顶部">↑</button>
    <script src="<?= e(asset('js/app.js')) ?>?v=<?= $jsVersion ?>"></script>
</body>
</html>
