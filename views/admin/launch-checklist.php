<?php $pageTitle = '上线检查清单 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">上线检查</p>
            <h1>上线检查清单</h1>
            <p><?= $ready ? '核心功能已就绪，可以公开试运营。' : '还有几项检查需要确认。' ?></p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/qa')) ?>">生产 QA</a>
            <a class="button button-small" href="<?= e(url()) ?>">查看网站</a>
        </div>
    </div>

    <div class="status-banner <?= $ready ? 'is-ready' : 'is-warning' ?>">
        <strong><?= $ready ? '可以上线' : '需要复查' ?></strong>
        <span>覆盖读者体验、编辑流程、AI 能力、订阅增长与数据分析的上线检查。</span>
    </div>

    <div class="admin-table launch-table">
        <div class="admin-table-row qa-table-head">
            <span>项目</span><span>状态</span><span>详情</span>
        </div>
        <?php foreach ($items as $item): ?>
            <div class="admin-table-row qa-table-row">
                <strong><?= e($item['label']) ?></strong>
                <span class="status-badge <?= $item['ok'] ? 'status-ok' : 'status-warn' ?>"><?= $item['ok'] ? '通过' : '待复查' ?></span>
                <span><?= e($item['detail']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
