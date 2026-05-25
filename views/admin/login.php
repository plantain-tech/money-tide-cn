<?php $pageTitle = '编辑登录 - 钱潮 Money Tide'; ?>
<section class="admin-auth">
    <div class="admin-auth-panel">
        <p class="eyebrow">编辑后台</p>
        <h1>登录钱潮工作台</h1>
        <p>管理订阅、文章和 AI 编辑任务。</p>

        <?php if ($error): ?>
            <div class="form-message form-message-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="admin-form" method="post" action="<?= e(url('admin/login')) ?>">
            <label>
                邮箱
                <input type="email" name="email" autocomplete="email" required>
            </label>
            <label>
                密码
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button full-width" type="submit">登录</button>
        </form>
    </div>
</section>
