<?php $pageTitle = '阅读偏好 - 钱潮 Money Tide'; ?>
<section class="account-shell">
    <div class="account-card">
        <h1>阅读偏好</h1>
        <?php if ($flash !== ''): ?>
            <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('account/preferences')) ?>" class="account-form">
            <label>
                邮件频率
                <select name="digest_frequency">
                    <?php foreach (['daily' => '每天', 'weekly' => '每周', 'off' => '暂停'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($data['preferences']['digest_frequency'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <fieldset class="account-topics">
                <legend>感兴趣的栏目</legend>
                <?php foreach ($categories as $cat): ?>
                    <label class="account-topic">
                        <input type="checkbox" name="topics[]" value="<?= e($cat['slug']) ?>" <?= in_array($cat['slug'], $data['topics'], true) ? 'checked' : '' ?>>
                        <span><?= e($cat['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="cms-form-actions">
                <button class="button" type="submit">保存偏好</button>
                <a class="ghost-link" href="<?= e(url('account')) ?>">返回</a>
            </div>
        </form>
    </div>
</section>
