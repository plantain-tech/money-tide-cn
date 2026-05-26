<?php
$pageTitle = '订阅管理 - 钱潮 Money Tide';
$status = (string) ($data['subscriber']['status'] ?? 'active');
?>
<section class="account-shell">
    <div class="account-card">
        <h1>订阅管理</h1>
        <?php if ($flash !== ''): ?>
            <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
        <?php endif; ?>
        <p>当前订阅状态：<strong><?= e($status) ?></strong></p>

        <?php if ($status === 'unsubscribed'): ?>
            <form method="post" action="<?= e(url('account/unsubscribe')) ?>">
                <input type="hidden" name="action" value="resubscribe">
                <button class="button" type="submit">恢复订阅</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('account/unsubscribe')) ?>" onsubmit="return confirm('确认退订所有钱潮邮件？')">
                <button class="button button-danger" type="submit">退订所有邮件</button>
            </form>
            <p><small>退订后不会收到任何 newsletter；账号本身保留。</small></p>
        <?php endif; ?>

        <p><a class="ghost-link" href="<?= e(url('account')) ?>">返回账号</a></p>
    </div>
</section>
