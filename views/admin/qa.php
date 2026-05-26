<?php $pageTitle = '生产 QA - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Launch QA</p>
            <h1>生产检查</h1>
            <p>上线前快速确认数据库、内容、AI 和订阅基础设施。</p>
        </div>
        <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
    </div>

    <div class="admin-table">
        <div class="admin-table-row qa-table-head">
            <span>检查项</span><span>状态</span><span>详情</span>
        </div>
        <?php foreach ($checks as $check): ?>
            <div class="admin-table-row qa-table-row">
                <strong><?= e($check['name']) ?></strong>
                <span><mark><?= $check['ok'] ? 'OK' : 'Check' ?></mark></span>
                <span><?= e($check['detail']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
