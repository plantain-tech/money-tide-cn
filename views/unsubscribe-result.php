<?php $pageTitle = '退订 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <h1>退订</h1>
        <?php if ($result['ok']): ?>
            <div class="status-banner is-ready">
                <strong>已退订</strong>
                <span><?= e((string) ($result['email'] ?? '')) ?> 不会再收到任何 newsletter。</span>
            </div>
            <p>改变主意？随时回来 <a href="<?= e(url('subscribe')) ?>">重新订阅</a>。</p>
        <?php else: ?>
            <div class="form-message form-message-error"><?= e((string) ($result['message'] ?? '')) ?></div>
            <p><a class="ghost-link" href="<?= e(url('account')) ?>">登录账号管理订阅 →</a></p>
        <?php endif; ?>
    </div>
</section>
