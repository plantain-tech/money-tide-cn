<?php
$pageTitle = '隐私政策 Privacy Policy - 钱潮 Money Tide';
$contactEmail = function_exists('app_config') ? trim((string) app_config('email.from_address', '')) : '';
if ($contactEmail === '') {
    $contactEmail = 'contact@avanturadeals.com';
}
$updated = date('Y-m-d');
?>
<section class="page-hero compact">
    <p class="eyebrow">Privacy Policy</p>
    <h1>隐私政策</h1>
    <p>本政策说明钱潮 Money Tide（moneytidecn.avanturadeals.com，下称「本站」）如何收集、使用与保护你的信息。最后更新：<?= e($updated) ?>。</p>
</section>

<section class="newsletter-block">
    <h2>我们收集哪些信息</h2>
    <ul class="qa-list">
        <li><strong>你主动提供的信息。</strong><small>订阅邮件时填写的邮箱与关注的主题；注册账号时的显示名等。</small></li>
        <li><strong>自动收集的信息。</strong><small>访问日志、设备与浏览器类型、页面浏览、来源页等，用于统计与改进内容。</small></li>
        <li><strong>Cookie 与类似技术。</strong><small>用于记住你的偏好、维持登录状态、统计访问，以及（在启用广告时）由第三方广告商投放与衡量广告。</small></li>
    </ul>
</section>

<section class="newsletter-block">
    <h2>我们如何使用信息</h2>
    <ul class="qa-list">
        <li><strong>提供与发送服务。</strong><small>投递你订阅的早报与内容、维护账号与偏好。</small></li>
        <li><strong>分析与改进。</strong><small>了解哪些内容受欢迎，优化网站体验与编辑方向。</small></li>
        <li><strong>合规与安全。</strong><small>防止滥用、保障网站安全、遵守适用法律。</small></li>
    </ul>
    <p>我们不会出售你的个人信息。</p>
</section>

<section class="newsletter-block">
    <h2>Cookie 与广告（第三方）</h2>
    <p>本站可能展示由 <strong>Google 等第三方广告商</strong>投放的广告。这些第三方供应商可能使用 Cookie（包括 Google 的 <em>DART cookie</em>）与网络信标，根据你对本站及其他网站的访问来投放与衡量广告。</p>
    <ul class="qa-list">
        <li><strong>Google 及其合作伙伴。</strong><small>作为第三方供应商，Google 使用 Cookie 在本站投放广告；你可在 <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener">Google 广告设置</a> 中管理或停用个性化广告。</small></li>
        <li><strong>停用第三方 Cookie。</strong><small>你也可在 <a href="https://www.aboutads.info" target="_blank" rel="noopener">www.aboutads.info</a> 集中停用部分第三方供应商的个性化广告 Cookie。</small></li>
        <li><strong>浏览器设置。</strong><small>你可以在浏览器中拒绝或删除 Cookie；这可能影响部分功能（如保持登录、记住偏好）。</small></li>
    </ul>
    <p>如启用 Google AdSense，相关广告均会清晰标注「广告 / Sponsored」，并遵守 Google 发布商政策。</p>
</section>

<section class="newsletter-block">
    <h2>分析</h2>
    <p>本站可能使用站内统计或第三方分析工具（如 Google Analytics / Plausible）以汇总、匿名的方式了解访问情况。这些数据用于改进内容，不用于识别个人身份。</p>
</section>

<section class="newsletter-block">
    <h2>第三方链接</h2>
    <p>本站包含指向其他网站的链接（包括新闻来源与合作/联盟链接，均带披露）。我们对第三方网站的隐私做法不负责，建议你查阅其各自的隐私政策。</p>
</section>

<section class="newsletter-block">
    <h2>你的选择与权利</h2>
    <ul class="qa-list">
        <li><strong>退订。</strong><small>每封邮件底部都有一键退订链接；也可在账号页调整订阅偏好。</small></li>
        <li><strong>访问与删除。</strong><small>你可联系我们查询或删除与你相关的个人信息。</small></li>
        <li><strong>个性化广告。</strong><small>通过上文的 Google 广告设置或 aboutads.info 管理。</small></li>
    </ul>
</section>

<section class="newsletter-block">
    <h2>数据安全与儿童隐私</h2>
    <p>我们采取合理的技术与管理措施保护你的信息。本站面向成年读者，不针对 13 岁以下儿童，也不会有意收集其个人信息。</p>
</section>

<section class="newsletter-block">
    <h2>政策更新与联系方式</h2>
    <p>本政策可能不时更新，更新后会在本页标注新的「最后更新」日期。如对本政策有任何疑问，请通过 <a href="<?= e(url('contact')) ?>">联系我们</a> 页面，或发送邮件至 <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>。</p>
</section>
