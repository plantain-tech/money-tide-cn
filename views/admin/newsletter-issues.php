<?php $pageTitle = 'Newsletter 期号 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">钱潮早报</p>
            <h1>早报期号</h1>
            <p>组装、预览、测试和广播每一期。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/newsletter/schedule')) ?>">排期队列</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="button button-small" href="<?= e(url('admin/newsletter/new')) ?>">新建一期</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="status-banner <?= $providerStatus['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong>邮件服务：<?= e($providerStatus['provider']) ?></strong>
        <span><?= e($providerStatus['message']) ?><?= $providerStatus['from_address'] !== '' ? ' · 发件人：' . e($providerStatus['from_address']) : '' ?></span>
    </div>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head">
            <span>主题</span><span>状态</span><span>计划时间</span><span>送达</span><span>操作</span>
        </div>
        <?php foreach ($issues as $issue): ?>
            <div class="admin-table-row">
                <div>
                    <strong><?= e($issue['subject']) ?></strong>
                    <small>创建于 <?= e(date('Y-m-d H:i', strtotime((string) $issue['created_at']))) ?></small>
                </div>
                <span><mark><?= e($issue['status']) ?></mark></span>
                <span><?= e($issue['scheduled_at'] ? date('Y-m-d H:i', strtotime((string) $issue['scheduled_at'])) : '—') ?></span>
                <span><?= e((string) $issue['sent_count']) ?>/<?= e((string) $issue['recipients_count']) ?><?= $issue['failed_count'] > 0 ? ' · ' . e((string) $issue['failed_count']) . ' 失败' : '' ?></span>
                <div class="admin-row-actions">
                    <a href="<?= e(url('admin/newsletter/' . $issue['id'] . '/edit')) ?>">编辑</a>
                    <a href="<?= e(url('admin/newsletter/' . $issue['id'] . '/preview')) ?>" target="_blank" rel="noopener">预览</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$issues): ?>
            <div class="empty-state">
                <strong>还没有任何一期。</strong>
                <p>点击"新建一期"开始组装本期早报。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
