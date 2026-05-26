<?php $pageTitle = '我的账号 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">已登录</p>
        <h1>欢迎，<?= e((string) $reader['name']) ?></h1>
        <p>账号邮箱：<?= e((string) $reader['email']) ?></p>

        <div class="account-stats">
            <div>
                <span>订阅频率</span>
                <strong><?= e((string) $data['preferences']['digest_frequency']) ?></strong>
            </div>
            <div>
                <span>关注栏目</span>
                <strong><?= e((string) count($data['topics'])) ?></strong>
            </div>
            <div>
                <span>已邀请</span>
                <strong><?= e((string) $referral['invited_count']) ?></strong>
            </div>
        </div>

        <nav class="account-menu">
            <a class="button" href="<?= e(url('account/preferences')) ?>">编辑偏好</a>
            <a class="button button-ghost" href="<?= e(url('account/referral')) ?>">邀请朋友</a>
            <a class="button button-ghost" href="<?= e(url('account/unsubscribe')) ?>">订阅管理</a>
            <a class="ghost-link" href="<?= e(url('account/logout')) ?>">退出登录</a>
        </nav>
    </div>
</section>
