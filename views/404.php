<?php
$pageTitle = '页面未找到 - 钱潮 Money Tide';
$pageDescription = '你访问的页面不存在或已经移动。';
?>
<section class="page-hero compact not-found-page">
    <p class="eyebrow">404</p>
    <h1>这股潮水暂时不在这里。</h1>
    <p>你访问的页面不存在或已经移动。可以回到首页，或者查看最新文章。</p>
    <div class="hero-actions">
        <a class="button" href="<?= e(url()) ?>">返回首页</a>
        <a class="text-link" href="<?= e(url('latest')) ?>">查看最新文章</a>
    </div>
</section>
