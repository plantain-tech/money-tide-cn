<?php $pageTitle = '个人资料 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">账户安全</p>
        <h1>个人资料</h1>
        <div class="account-security-strip">
            <div>
                <span>登录方式</span>
                <strong><?= e(($security['primary_provider'] ?? 'email') === 'google' ? 'Google' : '邮箱密码') ?></strong>
            </div>
            <div>
                <span>密码状态</span>
                <strong><?= !empty($security['has_password']) ? '已设置' : '未设置' ?></strong>
            </div>
        </div>
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
                <legend><?= !empty($security['has_password']) ? '修改密码（可选）' : '设置邮箱密码（可选）' ?></legend>
                <label>
                    当前密码<?= !empty($security['has_password']) ? '' : '（Google 账号首次设置可留空）' ?>
                    <input type="password" name="current_password" autocomplete="current-password" <?= !empty($security['has_password']) ? '' : 'placeholder="首次设置密码可留空"' ?>>
                </label>
                <label>
                    新密码（至少 6 位）
                    <input type="password" name="new_password" minlength="6" autocomplete="new-password">
                </label>
                <small><?= !empty($security['has_password']) ? '如果不修改密码，留空即可。' : 'Google 登录账号也可以设置一个邮箱密码，之后两种方式都能登录。' ?></small>
            </fieldset>

            <div class="cms-form-actions">
                <button class="button" type="submit">保存资料</button>
                <a class="ghost-link" href="<?= e(url('account')) ?>">返回</a>
            </div>
        </form>
    </div>
</section>
