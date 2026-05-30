<?php $pageTitle = 'Week 3 QA - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">质量检查</p>
            <h1>第 3 周检查清单</h1>
            <p>用这个清单验证整周新功能。每一项给出 URL/操作提示。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">运行 smoke 检查</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">系统诊断</a>
        </div>
    </div>

    <ol class="qa-list">
        <?php foreach ($items as $item): ?>
            <li>
                <strong><?= e($item['label']) ?></strong>
                <small><?= e($item['tip']) ?></small>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
