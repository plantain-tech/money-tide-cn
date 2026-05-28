<?php $pageTitle = 'Week 2 Launch Checklist - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 2 Day 7</p>
            <h1>Launch Checklist</h1>
            <p><?= $ready ? 'Week 2 release is launch-ready.' : 'A few launch checks still need attention.' ?></p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/qa')) ?>">生产 QA</a>
            <a class="button button-small" href="<?= e(url()) ?>">查看网站</a>
        </div>
    </div>

    <div class="status-banner <?= $ready ? 'is-ready' : 'is-warning' ?>">
        <strong><?= $ready ? '可以上线' : '需要复查' ?></strong>
        <span>Week 2 launch gate for reader experience, editorial workflow, AI agency, newsletter growth, and analytics.</span>
    </div>

    <div class="admin-table launch-table">
        <div class="admin-table-row qa-table-head">
            <span>Item</span><span>Status</span><span>Detail</span>
        </div>
        <?php foreach ($items as $item): ?>
            <div class="admin-table-row qa-table-row">
                <strong><?= e($item['label']) ?></strong>
                <span class="status-badge <?= $item['ok'] ? 'status-ok' : 'status-warn' ?>"><?= $item['ok'] ? 'OK' : 'Review' ?></span>
                <span><?= e($item['detail']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
