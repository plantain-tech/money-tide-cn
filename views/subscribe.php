<?php
$pageTitle = '免费订阅 - 钱潮 Money Tide';
$referralCode = normalize_referral_code((string) ($_GET['ref'] ?? ''));
// Friendly subscribe-page labels; any category without an override falls back to
// its own name. Built from the live category list so every section (incl. 出海)
// always appears here and new categories show up automatically.
$topicLabels = [
    'markets' => '全球市场',
    'business' => '商业公司',
    'tech' => 'AI科技',
    'crypto' => '加密资产',
    'policy' => '政策监管',
    'world' => '全球事件',
    'wealth' => '个人理财',
    'global-china' => '出海',
];
$topics = [];
foreach ((function_exists('get_categories') ? get_categories() : ($categories ?? [])) as $cat) {
    $slug = (string) ($cat['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $topics[$slug] = $topicLabels[$slug] ?? (string) ($cat['name'] ?? $slug);
}
?>
<section class="subscribe-hero reveal-on-scroll">
    <div class="subscribe-copy">
        <p class="eyebrow">免费订阅</p>
        <h1>每天早上，用中文看懂钱往哪里流。</h1>
        <p>钱潮早报把全球市场、商业、科技、加密、政策和理财信息压缩成一封可以快速读完的中文邮件。</p>
        <div class="subscribe-proof-grid">
            <div><strong>5 分钟</strong><span>读完今日关键市场信号</span></div>
            <div><strong>7 个频道</strong><span>覆盖财经、科技与全球趋势</span></div>
            <?php // 原“首发阶段免费订阅”文案保留备查，未来如恢复限时定价可改回此处显示。 ?>
            <div><strong>0 元</strong><span>永久免费订阅</span></div>
        </div>
    </div>

    <div class="subscribe-panel growth-subscribe-panel">
        <h2>创建账户并订阅</h2>
        <div class="social-stack">
            <a href="<?= e(url('account/oauth/google')) ?>">使用 Google 继续</a>
        </div>
        <div class="divider"><span>或使用邮箱</span></div>
        <form class="subscribe-form" method="post" action="<?= e(url('api/newsletter/subscribe')) ?>" data-newsletter-form>
            <label>
                邮箱
                <input type="email" name="email" placeholder="you@example.com" required>
            </label>
            <input type="hidden" name="source" value="subscribe-page">
            <input type="hidden" name="ref" value="<?= e($referralCode) ?>" data-referral-input>
            <fieldset>
                <legend>选择你关心的主题</legend>
                <?php foreach ($topics as $value => $label): ?>
                    <label><input type="checkbox" name="topics[]" value="<?= e($value) ?>" <?= in_array($value, ['markets', 'tech', 'business'], true) ? 'checked' : '' ?>> <?= e($label) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <button class="button full-width" type="submit">开始免费订阅</button>
        </form>
        <?php if ($referralCode !== ''): ?>
            <p class="referral-note">你正在通过好友邀请订阅：<?= e($referralCode) ?></p>
        <?php endif; ?>
        <p class="privacy-note">我们会保护你的邮箱。财经内容仅供信息参考，不构成投资建议。</p>
    </div>
</section>

<section class="subscribe-growth-band reveal-on-scroll">
    <div>
        <p class="eyebrow">What You Get</p>
        <h2>一封邮件，三层价值。</h2>
    </div>
    <div class="growth-benefit-grid">
        <article>
            <strong>先知道发生了什么</strong>
            <p>快速扫过隔夜市场、公司新闻和政策变化。</p>
        </article>
        <article>
            <strong>再理解为什么重要</strong>
            <p>把价格、公司、产业链和中文读者关心的问题连起来。</p>
        </article>
        <article>
            <strong>最后知道继续看哪里</strong>
            <p>每封邮件带你回到最值得读的文章和频道。</p>
        </article>
    </div>
</section>
