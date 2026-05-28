<?php
$pageTitle = 'Social Posting Queue - 钱潮 Money Tide';
$segments = social_schedule_segments();
$queueUrl = static function (array $override = []) use ($filters): string {
    $params = array_filter(array_merge($filters, $override), static fn ($value) => $value !== '' && $value !== null);
    return url('admin/social/schedule') . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="admin-shell schedule-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 7 · Day 4</p>
            <h1>Social Posting Queue</h1>
            <p>给每条社交文案设置人工发布时间，按今天、未来、逾期和未排期查看。这里不会自动发帖。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/social')) ?>">Social center</a>
            <a class="ghost-link" href="<?= e(url('admin/calendar')) ?>">Calendar</a>
            <a class="button button-small" href="<?= e(url('admin/social/schedule.csv') . '?' . http_build_query($filters)) ?>">Export CSV</a>
        </div>
    </div>

    <div class="schedule-summary">
        <?php foreach ($segments as $key => $label): ?>
            <a class="<?= $filters['scheduled'] === $key ? 'is-active' : '' ?>" href="<?= e($queueUrl(['scheduled' => $key])) ?>">
                <span><?= e($label) ?></span>
                <strong><?= e((string) ($summary[$key] ?? 0)) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/social/schedule')) ?>">
        <input type="hidden" name="scheduled" value="<?= e($filters['scheduled']) ?>">
        <input type="search" name="q" placeholder="搜索标题或文案" value="<?= e($filters['q']) ?>">
        <select name="channel">
            <option value="">All channels</option>
            <?php foreach ($channels as $key => $meta): ?>
                <option value="<?= e($key) ?>" <?= $filters['channel'] === $key ? 'selected' : '' ?>><?= e((string) $meta['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="queue-list">
        <?php foreach ($posts as $row): ?>
            <?php
                $channel = (string) $row['channel'];
                $meta = $channels[$channel] ?? ['label' => $channel];
                $scheduledAt = !empty($row['scheduled_at']) ? strtotime((string) $row['scheduled_at']) : false;
                $isOverdue = $scheduledAt !== false && $scheduledAt < time() && in_array((string) $row['status'], ['draft', 'ready'], true);
            ?>
            <article class="queue-card <?= $isOverdue ? 'is-overdue' : '' ?>">
                <div class="queue-time">
                    <strong><?= $scheduledAt ? e(date('M j', $scheduledAt)) : 'No date' ?></strong>
                    <span><?= $scheduledAt ? e(date('H:i', $scheduledAt)) : 'Unscheduled' ?></span>
                </div>
                <div class="queue-body">
                    <p class="eyebrow"><?= e((string) $meta['label']) ?> · <?= e((string) $row['category_name']) ?></p>
                    <h2><?= e((string) $row['title']) ?></h2>
                    <p><?= e(mb_substr(trim((string) $row['content']), 0, 180, 'UTF-8')) ?><?= mb_strlen((string) $row['content'], 'UTF-8') > 180 ? '…' : '' ?></p>
                </div>
                <div class="queue-actions">
                    <span class="social-status-chip is-<?= e((string) $row['status']) ?>"><?= e($statusOptions[$row['status']] ?? $row['status']) ?></span>
                    <?php if ($isOverdue): ?><mark>Needs action</mark><?php endif; ?>
                    <a class="ghost-link" href="<?= e(url('admin/articles/' . $row['article_id'] . '/social#ch-' . $channel)) ?>">Edit / post manually</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$posts): ?>
            <div class="empty-state">
                <strong>No social posts in this queue.</strong>
                <p>Schedule posts from an article’s social workspace, or switch to another queue segment.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
