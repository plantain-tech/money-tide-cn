<?php $pageTitle = '审计日志 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维</p>
            <h1>审计日志</h1>
            <p>所有文章创建、状态变更、删除事件，最近 200 条。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
        </div>
    </div>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head"><span>时间</span><span>文章</span><span>动作</span><span>变更</span><span>备注</span></div>
        <?php foreach ($entries as $entry): ?>
            <div class="admin-table-row">
                <span><?= e(date('Y-m-d H:i', strtotime((string) $entry['created_at']))) ?></span>
                <div>
                    <?php if ($entry['slug']): ?>
                        <a href="<?= e(url('admin/articles/' . (int) $entry['article_id'] . '/edit')) ?>"><?= e((string) $entry['title']) ?></a>
                        <small><?= e((string) $entry['slug']) ?></small>
                    <?php else: ?>
                        <small>(article #<?= e((string) (int) $entry['article_id']) ?> — 已删除)</small>
                    <?php endif; ?>
                </div>
                <span><mark><?= e((string) $entry['action']) ?></mark></span>
                <span><?= e((string) ($entry['from_status'] ?? '')) ?> → <?= e((string) ($entry['to_status'] ?? '')) ?></span>
                <span><?= e(mb_substr((string) ($entry['note'] ?? ''), 0, 80, 'UTF-8')) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$entries): ?>
            <div class="empty-state"><strong>审计日志为空。</strong><p>状态变更后会自动出现。</p></div>
        <?php endif; ?>
    </div>
</section>
