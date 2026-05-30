<?php $pageTitle = '登录 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">读者账户</p>
        <h1>登录</h1>
        <p>登录后可以保存文章、管理早报偏好，并继续阅读最近打开过的内容。</p>
        <?php if ($error): ?>
            <div class="form-message form-message-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('account/login')) ?>" class="account-form">
            <label>
                邮箱
                <input type="email" name="email" value="<?= e((string) ($email ?? '')) ?>" required autocomplete="email">
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
                <?php if ($info['configured']): ?>
                    <a class="button button-small button-ghost oauth-google-button" href="<?= e(url('account/oauth/' . $key)) ?>">
                        使用 <?= e($info['label']) ?> 继续
                    </a>
                <?php else: ?>
                    <p class="account-oauth-note"><small><?= e($info['label']) ?> 登录暂未开放。</small></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <p class="account-alt">还没有账号？<a href="<?= e(url('account/signup')) ?>">注册</a></p>
    </div>
</section>
