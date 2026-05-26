<?php $pageTitle = '登录 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <h1>登录</h1>
        <?php if ($error): ?>
            <div class="form-message form-message-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('account/login')) ?>" class="account-form">
            <label>
                邮箱
                <input type="email" name="email" required autocomplete="email">
            </label>
            <label>
                密码
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="button" type="submit">登录</button>
        </form>
        <p class="account-alt">还没有账号？<a href="<?= e(url('account/signup')) ?>">注册</a></p>
    </div>
</section>
