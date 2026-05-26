<?php $pageTitle = '邀请朋友 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">推荐链接</p>
        <h1>邀请朋友一起读钱潮</h1>
        <p>每位通过你的链接订阅的读者都会被记录到你的邀请数。</p>

        <?php if ($referral['referral_url'] !== ''): ?>
            <div class="referral-box">
                <input type="text" readonly value="<?= e($referral['referral_url']) ?>" id="referral-input">
                <button class="button button-small" type="button" data-share-copy="<?= e($referral['referral_url']) ?>">复制链接</button>
            </div>
            <p>邀请码：<code><?= e($referral['referral_code']) ?></code> · 已邀请：<strong><?= e((string) $referral['invited_count']) ?></strong></p>

            <div class="referral-shares">
                <a class="button button-small button-ghost" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode('每天 5 分钟，看懂全球市场。订阅钱潮 Money Tide：')) ?>&url=<?= e(rawurlencode($referral['referral_url'])) ?>">分享到 X</a>
                <a class="button button-small button-ghost" target="_blank" rel="noopener" href="mailto:?subject=<?= e(rawurlencode('一起订阅钱潮 Money Tide')) ?>&body=<?= e(rawurlencode('我在订阅钱潮 Money Tide，每天 5 分钟看懂全球市场。点开链接订阅：' . $referral['referral_url'])) ?>">通过邮件分享</a>
            </div>
        <?php else: ?>
            <p><small>正在生成你的推荐链接……</small></p>
        <?php endif; ?>

        <p><a class="ghost-link" href="<?= e(url('account')) ?>">返回账号</a></p>
    </div>
</section>
