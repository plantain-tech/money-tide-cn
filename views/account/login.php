<?php $pageTitle = '登录 - 钱潮 Money Tide'; $banner = (string) ($_GET['error'] ?? ''); ?>
<section class="account-shell">
    <div class="account-card">
        <h1>登录</h1>
        <?php if ($error): ?>
            <div class="form-message form-message-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($banner !== ''): ?>
            <div class="status-banner is-warning"><strong>提示</strong><span><?= e($banner) ?></span></div>
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

        <div class="account-oauth">
            <p class="eyebrow">第三方登录</p>
            <?php foreach ($oauth as $key => $info): ?>
                <a class="button button-small button-ghost" href="<?= e(url('account/oauth/' . $key)) ?>">
                    使用 <?= e($info['label']) ?> 登录
                    <?php if (!$info['configured']): ?><small>（待配置）</small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <p class="account-alt">还没有账号？<a href="<?= e(url('account/signup')) ?>">注册</a></p>
    </div>
</section>
