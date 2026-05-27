<?php $pageTitle = '个人资料 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <h1>个人资料</h1>
        <?php if ($flash !== ''): ?>
            <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="form-message form-message-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('account/profile')) ?>" class="account-form">
            <label>
                邮箱（不可修改）
                <input type="email" value="<?= e((string) $reader['email']) ?>" disabled>
            </label>
            <label>
                显示名
                <input type="text" name="display_name" value="<?= e((string) $reader['name']) ?>" required>
            </label>

            <fieldset class="account-topics">
                <legend>修改密码（可选）</legend>
                <label>
                    当前密码
                    <input type="password" name="current_password" autocomplete="current-password">
                </label>
                <label>
                    新密码（至少 6 位）
                    <input type="password" name="new_password" minlength="6" autocomplete="new-password">
                </label>
                <small>如果不修改密码，留空即可。</small>
            </fieldset>

            <div class="cms-form-actions">
                <button class="button" type="submit">保存资料</button>
                <a class="ghost-link" href="<?= e(url('account')) ?>">返回</a>
            </div>
        </form>
    </div>
</section>
