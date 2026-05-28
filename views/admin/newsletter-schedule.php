<?php
$pageTitle = '早报排期队列 - 钱潮 Money Tide';
$queueUrl = static function (array $override = []) use ($filters): string {
    $params = array_filter(array_merge($filters, $override), static fn ($value) => $value !== '' && $value !== null);
    return url('admin/newsletter/schedule') . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="admin-shell schedule-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 7 周 · 第 5 天</p>
            <h1>早报排期队列</h1>
            <p>查看今天可发送、未来排期、逾期和未排期的早报。排期只做提醒，广播仍必须人工点击。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/newsletter')) ?>">期号列表</a>
            <a class="ghost-link" href="<?= e(url('admin/calendar')) ?>">编辑日历</a>
            <a class="button button-small" href="<?= e(url('admin/newsletter/new')) ?>">新建期号</a>
        </div>
    </div>

    <div class="status-banner <?= $providerStatus['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong>邮件服务：<?= e((string) $providerStatus['provider']) ?></strong>
        <span><?= e((string) $providerStatus['message']) ?> · 仍需人工广播</span>
    </div>

    <div class="schedule-summary">
        <?php foreach ($segments as $key => $label): ?>
            <a class="<?= $filters['scheduled'] === $key ? 'is-active' : '' ?>" href="<?= e($queueUrl(['scheduled' => $key])) ?>">
                <span><?= e($label) ?></span>
                <strong><?= e((string) ($summary[$key] ?? 0)) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/newsletter/schedule')) ?>">
        <input type="hidden" name="scheduled" value="<?= e($filters['scheduled']) ?>">
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach (['draft' => '草稿', 'ready' => 'Ready', 'scheduled' => '已排期', 'sent' => '已发送', 'archived' => '已归档'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="queue-list">
        <?php foreach ($issues as $issue): ?>
            <?php
                $scheduledAt = !empty($issue['scheduled_at']) ? strtotime((string) $issue['scheduled_at']) : false;
                $checks = $issue['checklist'] ?? [];
                $passed = count(array_filter($checks, static fn ($item) => !empty($item['ok'])));
                $total = count($checks);
                $isOverdue = $scheduledAt !== false && $scheduledAt < time() && in_array((string) $issue['status'], ['draft', 'ready', 'scheduled'], true);
            ?>
            <article class="queue-card newsletter-queue-card <?= $isOverdue ? 'is-overdue' : '' ?>">
                <div class="queue-time">
                    <strong><?= $scheduledAt ? e(date('M j', $scheduledAt)) : '未排期' ?></strong>
                    <span><?= $scheduledAt ? e(date('H:i', $scheduledAt)) : '未设置时间' ?></span>
                </div>
                <div class="queue-body">
                    <p class="eyebrow">Newsletter · <?= e((string) $issue['status']) ?></p>
                    <h2><?= e((string) $issue['subject']) ?></h2>
                    <p><?= e((string) ($issue['intro'] ?? '')) ?></p>
                    <div class="schedule-progress">
                        <span style="width: <?= $total > 0 ? e((string) round(($passed / $total) * 100)) : '0' ?>%"></span>
                    </div>
                    <small>检查 <?= e((string) $passed) ?>/<?= e((string) $total) ?> · 文章 <?= e((string) count($issue['articles'] ?? [])) ?></small>
                </div>
                <div class="queue-actions">
                    <?php if ($isOverdue): ?><mark>需要处理</mark><?php endif; ?>
                    <a class="ghost-link" href="<?= e(url('admin/newsletter/' . $issue['id'] . '/edit')) ?>">审核 / 手动发送</a>
                    <a class="ghost-link" href="<?= e(url('admin/newsletter/' . $issue['id'] . '/preview')) ?>" target="_blank" rel="noopener">预览</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$issues): ?>
            <div class="empty-state">
                <strong>这个队列里还没有早报期号。</strong>
                <p>可以新建或排期一期早报，或切换到其他队列。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
