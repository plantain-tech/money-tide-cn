<?php $pageTitle = '订阅用户 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Newsletter</p>
            <h1>订阅用户</h1>
            <p>查看邮箱、来源、状态和读者主题偏好。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="button button-small" href="<?= e(url('admin/subscribers.csv?q=' . rawurlencode($filters['q']) . '&status=' . rawurlencode($filters['status']) . '&source=' . rawurlencode($filters['source']))) ?>">导出 CSV</a>
        </div>
    </div>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/subscribers')) ?>">
        <input type="search" name="q" placeholder="搜索邮箱" value="<?= e($filters['q']) ?>">
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach (['active' => 'active', 'pending' => 'pending', 'unsubscribed' => 'unsubscribed'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="source" placeholder="来源" value="<?= e($filters['source']) ?>">
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="admin-table">
        <div class="admin-table-row subscriber-table-head">
            <span>邮箱</span><span>状态</span><span>来源</span><span>偏好</span><span>时间</span>
        </div>
        <?php foreach ($subscribers as $subscriber): ?>
            <div class="admin-table-row subscriber-table-row">
                <strong><?= e($subscriber['email']) ?></strong>
                <span><mark><?= e($subscriber['status']) ?></mark></span>
                <span><?= e((string) $subscriber['source']) ?></span>
                <span><?= e((string) $subscriber['topics']) ?></span>
                <span><?= e(date('Y-m-d H:i', strtotime((string) $subscriber['created_at']))) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$subscribers): ?>
            <div class="empty-state">
                <strong>没有找到订阅用户。</strong>
                <p>调整筛选条件，或先从公开订阅页测试一次。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
