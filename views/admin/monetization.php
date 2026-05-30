<?php $pageTitle = '会员与变现 - 钱潮 Money Tide'; $settings = $summary['settings'] ?? []; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">会员变现</p>
            <h1>会员与变现准备</h1>
            <p>当前只做 premium 标记和软付费墙结构，不阻止任何读者阅读。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">文章管理</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="admin-stat-grid">
        <div><span>Premium 文章</span><strong><?= e((string) ($summary['premium_articles'] ?? 0)) ?></strong></div>
        <?php foreach (($summary['tiers'] ?? []) as $tier => $total): ?>
            <div><span><?= e((string) $tier) ?> readers</span><strong><?= e((string) $total) ?></strong></div>
        <?php endforeach; ?>
    </div>

    <form class="cms-form" method="post" action="<?= e(url('admin/monetization')) ?>">
        <label>
            付费墙模式
            <select name="paywall_mode">
                <option value="soft_preview" <?= ($settings['paywall_mode'] ?? '') === 'soft_preview' ? 'selected' : '' ?>>Soft preview：显示提示但不拦截阅读</option>
                <option value="off" <?= ($settings['paywall_mode'] ?? '') === 'off' ? 'selected' : '' ?>>Off：不显示提示</option>
            </select>
        </label>
        <label>
            Premium 标签
            <input type="text" name="premium_label" value="<?= e((string) ($settings['premium_label'] ?? '会员内容')) ?>">
        </label>
        <label>
            会员价格/说明占位
            <textarea name="member_price_note" rows="3"><?= e((string) ($settings['member_price_note'] ?? '')) ?></textarea>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit">保存设置</button>
        </div>
    </form>
</section>
