<?php $pageTitle = '注册 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <p class="eyebrow">读者账户</p>
        <h1>创建账号</h1>
        <p>注册后可以保存阅读偏好、领取专属推荐链接，并随时调整邮件订阅频率。</p>
        <?php if ($error): ?>
            <div class="form-message form-message-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('account/signup')) ?>" class="account-form">
            <label>
                邮箱
                <input type="email" name="email" value="<?= e((string) ($form['email'] ?? '')) ?>" required autocomplete="email">
            </label>
            <label>
                显示名（可选）
                <input type="text" name="display_name" value="<?= e((string) ($form['display_name'] ?? '')) ?>" autocomplete="nickname">
            </label>
            <label>
                密码（至少 6 位）
                <input type="password" name="password" minlength="6" required autocomplete="new-password">
            </label>
            <button class="button" type="submit">注册</button>
        </form>

        <?php if (!empty($oauth)): ?>
            <div class="account-oauth">
                <p class="eyebrow">使用第三方账号</p>
                <?php foreach ($oauth as $key => $info): ?>
                    <?php if ($info['configured']): ?>
                        <a class="button button-small button-ghost oauth-google-button" href="<?= e(url('account/oauth/' . $key)) ?>">使用 <?= e($info['label']) ?> 继续</a>
                    <?php else: ?>
                        <p class="account-oauth-note"><small><?= e($info['label']) ?> 登录将在配置 OAuth 凭证后开放。</small></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="account-alt">已有账号？<a href="<?= e(url('account/login')) ?>">登录</a></p>
    </div>
</section>
