<?php
$pageTitle = '分享卡片 — ' . ($article['title'] ?? '') . ' - 钱潮 Money Tide';
$articleId = (int) ($article['id'] ?? 0);
$slug = (string) ($article['slug'] ?? '');
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 6 周 · 分享图</p>
            <h1>分享卡片 / OG 图</h1>
            <p>每篇文章自动生成三种 SVG 分享卡。可直接保存图片用于社交媒体，或作为 Open Graph 预览图。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/edit')) ?>">回到文章</a>
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/social')) ?>">社交分发</a>
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/short-format')) ?>">60秒看懂</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="status-banner is-ready">
        <strong>当前 OG / 社交图</strong>
        <span><?= e($currentOgImage) ?></span>
    </div>

    <div class="share-card-grid">
        <?php foreach ($cardTypes as $type => $label): ?>
            <?php $cardUrl = url('share-card/' . $slug . '/' . $type . '.svg'); ?>
            <figure class="share-card-figure">
                <figcaption><?= e($label) ?></figcaption>
                <div class="share-card-frame">
                    <img src="<?= e($cardUrl) ?>" alt="<?= e($label) ?>" loading="lazy">
                </div>
                <div class="share-card-actions">
                    <a class="button button-small" href="<?= e($cardUrl) ?>" target="_blank" rel="noopener">在新窗口打开</a>
                    <a class="button button-small button-ghost" href="<?= e($cardUrl) ?>" download="<?= e($slug . '-' . $type) ?>.svg">下载 SVG</a>
                    <button class="link-button" type="button" data-copy-url="<?= e(canonical_url(ltrim($cardUrl, '/'))) ?>">复制图片地址</button>
                </div>
            </figure>
        <?php endforeach; ?>
    </div>

    <section class="newsletter-block">
        <h2>社交图覆盖</h2>
        <p><small>留空则按 社交图覆盖 → 英雄图 → 栏目兜底 → 自动生成标题卡 的顺序选择 OG 图。可填完整 URL 或站内路径（如 /assets/img/hero-tech.svg）。</small></p>
        <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/share-cards')) ?>" class="social-content-form">
            <label class="social-field">
                <span class="social-field-label">社交图 URL / 路径</span>
                <input type="text" name="social_image_path" value="<?= e((string) ($article['social_image_path'] ?? '')) ?>" placeholder="留空使用自动顺序">
            </label>
            <div class="social-card-actions">
                <button class="button button-small" type="submit">保存覆盖</button>
            </div>
        </form>
    </section>
</section>

<script>
(function () {
    document.querySelectorAll('[data-copy-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy-url') || '';
            var done = function () {
                var o = btn.textContent;
                btn.textContent = '已复制 ✓';
                setTimeout(function () { btn.textContent = o; }, 1400);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta); done();
            }
        });
    });
})();
</script>
