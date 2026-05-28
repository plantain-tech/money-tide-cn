<?php
$pageTitle = '邮件投递 - 钱潮 Money Tide';
$providerStatus = $status['provider_status'];
$recommended = $status['recommended'];
$ready = !empty($status['ready_for_real_send']);
$configuredProvider = (string) $status['provider'];
?>
<section class="admin-shell email-delivery-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 8 周 · 第 1 天</p>
            <h1>邮件投递设置</h1>
            <p>把钱潮早报从测试记录模式推进到真实邮件投递。当前推荐免费方案：<strong><?= e((string) $recommended['label']) ?></strong>。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/newsletter')) ?>">早报期号</a>
            <a class="ghost-link" href="<?= e(url('admin/newsletter/schedule')) ?>">排期队列</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>测试结果</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="email-delivery-hero">
        <article>
            <span>当前服务</span>
            <strong><?= e((string) ($catalog[$configuredProvider]['label'] ?? $configuredProvider)) ?></strong>
            <p><?= e((string) $providerStatus['message']) ?></p>
        </article>
        <article class="<?= $ready ? 'is-ready' : 'is-warning' ?>">
            <span>真实投递</span>
            <strong><?= $ready ? '已准备好' : '尚未开启' ?></strong>
            <p><?= $ready ? '可以发送生产测试邮件。' : '设置 Brevo secrets 并完成域名验证后开启。' ?></p>
        </article>
        <article>
            <span>免费额度</span>
            <strong><?= e((string) $recommended['free_limit']) ?></strong>
            <p><?= e((string) $recommended['fit']) ?></p>
        </article>
    </div>

    <section class="newsletter-block">
        <h2>推荐选择：Brevo 免费计划</h2>
        <p>钱潮现在是 newsletter-first 产品。Brevo 免费计划每天 300 封邮件，适合先验证早报投递、退订、偏好管理和内容节奏。</p>
        <div class="email-provider-grid">
            <?php foreach ($catalog as $key => $meta): ?>
                <article class="email-provider-card <?= $key === 'brevo' ? 'is-recommended' : '' ?> <?= $key === $configuredProvider ? 'is-current' : '' ?>">
                    <div>
                        <span><?= $key === 'brevo' ? '推荐' : ($key === $configuredProvider ? '当前' : '可选') ?></span>
                        <h3><?= e((string) $meta['label']) ?></h3>
                    </div>
                    <strong><?= e((string) $meta['free_limit']) ?></strong>
                    <p><?= e((string) $meta['fit']) ?></p>
                    <?php if (!empty($meta['setup_url'])): ?>
                        <a class="ghost-link" href="<?= e((string) $meta['setup_url']) ?>" target="_blank" rel="noopener">查看官方说明</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="newsletter-block">
        <h2>GitHub Secrets 需要这样填写</h2>
        <div class="secret-grid">
            <div><strong>EMAIL_PROVIDER</strong><code>brevo</code></div>
            <div><strong>EMAIL_API_KEY</strong><code>你的 Brevo SMTP/API key</code></div>
            <div><strong>EMAIL_FROM_ADDRESS</strong><code>news@你的域名</code></div>
            <div><strong>EMAIL_FROM_NAME</strong><code>钱潮 Money Tide</code></div>
            <div><strong>UNSUBSCRIBE_SECRET</strong><code>32 位以上随机字符串</code></div>
        </div>
        <p><small>保存 secrets 后，推送任意一次代码或手动运行 GitHub Actions，生产配置才会更新。</small></p>
    </section>

    <section class="newsletter-block">
        <h2>上线前检查</h2>
        <ul class="schedule-checklist email-checklist">
            <?php foreach ($status['checks'] as $item): ?>
                <li class="<?= !empty($item['ok']) ? 'is-pass' : 'is-warning' ?>">
                    <strong><?= !empty($item['ok']) ? 'OK' : 'Check' ?></strong>
                    <span><?= e((string) $item['label']) ?></span>
                    <small><?= e((string) $item['tip']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="newsletter-block">
        <h2>域名/DNS 验证</h2>
        <ol class="qa-list">
            <li><strong>在 Brevo 添加发件域名。</strong><small>进入 Brevo Sender & IP / Domains，添加你要用的发件域名。</small></li>
            <li><strong>按 Brevo 页面提示添加 DNS。</strong><small>通常包括 DKIM、SPF/Return-Path 和 DMARC。具体值以 Brevo 后台给你的为准。</small></li>
            <li><strong>等待 Brevo 显示 verified。</strong><small>DNS 生效可能需要几分钟到数小时。</small></li>
            <li><strong>回到本页发送生产测试邮件。</strong><small>收到邮件后，检查发件人、主题、正文、垃圾邮件箱和退订链接。</small></li>
        </ol>
    </section>

    <section class="newsletter-block">
        <h2>发送生产测试邮件</h2>
        <form class="newsletter-test-form email-delivery-test-form" method="post" action="<?= e(url('admin/email-delivery')) ?>">
            <input type="email" name="test_email" placeholder="你的测试邮箱" required>
            <button class="button button-small" type="submit">发送测试邮件</button>
        </form>
        <?php if (!empty($testResult['unsubscribe_url'])): ?>
            <p><small>测试退订链接：<a href="<?= e((string) $testResult['unsubscribe_url']) ?>" target="_blank" rel="noopener"><?= e((string) $testResult['unsubscribe_url']) ?></a></small></p>
        <?php else: ?>
            <p><small>测试邮件会包含一键退订链接。当前如果仍是 log 模式，系统只会验证流程，不会实际投递。</small></p>
        <?php endif; ?>
    </section>
</section>
