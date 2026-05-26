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
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
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
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?= e(url()) ?>" aria-label="钱潮 Money Tide 首页">
            <span class="brand-mark">钱潮</span>
            <span class="brand-name">Money Tide</span>
        </a>
        <nav class="top-nav" aria-label="主导航">
            <?php foreach (array_slice($categories, 0, 7) as $navCategory): ?>
                <a href="<?= e(url('category/' . $navCategory['slug'])) ?>"><?= e($navCategory['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-actions">
            <a class="ghost-link" href="<?= e(url('latest')) ?>">最新</a>
            <a class="button button-small" href="<?= e(url('subscribe')) ?>">免费订阅</a>
        </div>
    </header>

    <main>
        <?= $content ?>
    </main>

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
        </nav>
    </footer>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
