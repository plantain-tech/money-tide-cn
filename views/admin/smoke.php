<?php $pageTitle = '系统自检 - 钱潮 Money Tide'; $pass = 0; $total = count($checks); foreach ($checks as $c) { if ($c['ok']) { $pass++; } } ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维</p>
            <h1>系统自检</h1>
            <p>关键模块快速自检；通过 <strong><?= e((string) $pass) ?>/<?= e((string) $total) ?></strong> 项。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke?format=json')) ?>" target="_blank" rel="noopener">JSON</a>
        </div>
    </div>

    <div class="status-banner <?= $pass === $total ? 'is-ready' : 'is-warning' ?>">
        <strong><?= $pass === $total ? '全部通过' : '部分检查未通过' ?></strong>
        <span>通过 <?= e((string) $pass) ?>/<?= e((string) $total) ?></span>
    </div>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head"><span>检查</span><span>状态</span><span>详情</span></div>
        <?php foreach ($checks as $check): ?>
            <div class="admin-table-row">
                <strong><?= e($check['name']) ?></strong>
                <span><mark class="<?= $check['ok'] ? 'status-ok' : 'status-warn' ?>"><?= $check['ok'] ? 'PASS' : 'FAIL' ?></mark></span>
                <span><?= e((string) $check['detail']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
