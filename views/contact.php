<?php
$pageTitle = '联系我们 Contact - 钱潮 Money Tide';
$contactEmail = function_exists('app_config') ? trim((string) app_config('email.from_address', '')) : '';
if ($contactEmail === '') {
    $contactEmail = 'contact@avanturadeals.com';
}
?>
<section class="page-hero compact">
    <p class="eyebrow">Contact</p>
    <h1>联系我们</h1>
    <p>关于内容、合作、广告、更正或隐私的任何问题，欢迎随时联系钱潮 Money Tide 团队。我们会尽快回复。</p>
</section>

<section class="newsletter-block">
    <h2>📧 电子邮箱</h2>
    <p>最快的方式是邮件联系：<a class="contact-email" href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></p>
    <ul class="qa-list">
        <li><strong>内容与更正。</strong><small>发现事实错误或需要更正？请附上文章链接与说明。</small></li>
        <li><strong>广告与合作。</strong><small>赞助、联盟与品牌合作咨询。</small></li>
        <li><strong>隐私与数据。</strong><small>查询、更正或删除与你相关的信息，详见 <a href="<?= e(url('privacy')) ?>">隐私政策</a>。</small></li>
    </ul>
</section>

<section class="newsletter-block">
    <h2>✍️ 给我们留言</h2>
    <?php $cr = $contactResult ?? null; ?>
    <?php if (is_array($cr)): ?>
        <div class="status-banner <?= !empty($cr['ok']) ? 'is-ready' : 'is-warning' ?> ap-flash">
            <strong><?= !empty($cr['ok']) ? '✅ 已发送' : '⚠️ 未发送' ?></strong>
            <span><?= e((string) ($cr['message'] ?? '')) ?></span>
        </div>
    <?php endif; ?>
    <p>填写下面的表单，直接发送到我们的邮箱（我们会用你填写的邮箱回复你）。</p>
    <form class="cms-form contact-form" action="<?= e(url('contact')) ?>" method="post">
        <div class="cms-form-grid">
            <label>你的称呼<input type="text" name="name" maxlength="80" placeholder="怎么称呼你"></label>
            <label>你的邮箱<input type="email" name="email" required placeholder="you@example.com"></label>
        </div>
        <label>主题<input type="text" name="subject" maxlength="140" placeholder="一句话主题"></label>
        <label>留言<textarea name="message" rows="5" required minlength="5" placeholder="想对我们说的话…"></textarea></label>
        <!-- honeypot: hidden from humans, bots fill it -->
        <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true">
        <div class="cms-form-actions"><button class="button" type="submit">发送留言</button></div>
    </form>
    <p class="news-action-hint">我们会通过你填写的邮箱回复你。也可直接发邮件到 <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>。</p>
</section>

<section class="newsletter-block">
    <h2>🔗 关于本站</h2>
    <p>钱潮 Money Tide 是面向中文读者的全球财经、科技与商业简报。更多请见
        <a href="<?= e(url('about')) ?>">关于</a> ·
        <a href="<?= e(url('editorial-standards')) ?>">编辑标准</a> ·
        <a href="<?= e(url('disclaimer')) ?>">免责声明</a> ·
        <a href="<?= e(url('privacy')) ?>">隐私政策</a>。</p>
</section>
