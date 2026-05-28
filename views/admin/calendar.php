<?php
$pageTitle = 'Editorial Calendar - 钱潮 Money Tide';
$eventTypeLabels = ['article' => '文章', 'newsletter' => '早报'];
$statusLabels = [
    'draft' => '草稿',
    'review' => '审核',
    'ready' => 'Ready',
    'scheduled' => 'Scheduled',
    'published' => '已发布',
    'sent' => 'Sent',
    'archived' => '已归档',
];
$calendarUrl = static function (array $override = []) use ($filters): string {
    $params = array_filter(array_merge($filters, $override), static fn ($value) => $value !== '' && $value !== 0 && $value !== null);
    return url('admin/calendar') . ($params ? '?' . http_build_query($params) : '');
};
$renderEvent = static function (array $event) use ($eventTypeLabels, $statusLabels): void {
    $type = (string) $event['type'];
    ?>
    <article class="calendar-event calendar-event-<?= e($type) ?>">
        <div class="calendar-event-top">
            <span><?= e($eventTypeLabels[$type] ?? $type) ?></span>
            <mark><?= e($statusLabels[$event['status']] ?? (string) $event['status']) ?></mark>
        </div>
        <strong><?= e((string) $event['title']) ?></strong>
        <small>
            <?= e((string) $event['time']) ?>
            <?php if (!empty($event['category_name'])): ?> · <?= e((string) $event['category_name']) ?><?php endif; ?>
            <?php if (!empty($event['editor_name'])): ?> · <?= e((string) $event['editor_name']) ?><?php endif; ?>
        </small>
        <div class="calendar-event-actions">
            <a href="<?= e((string) $event['edit_url']) ?>">编辑</a>
            <a href="<?= e((string) $event['preview_url']) ?>" target="_blank" rel="noopener">预览</a>
            <?php if (!empty($event['public_url'])): ?>
                <a href="<?= e((string) $event['public_url']) ?>" target="_blank" rel="noopener">前台</a>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>
<section class="admin-shell calendar-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 7 · Day 3</p>
            <h1>Editorial Calendar</h1>
            <p>按发布时间查看文章，按计划/发送时间查看 newsletter，并快速跳转到编辑页。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="button button-small" href="<?= e(url('admin/articles/new')) ?>">新建文章</a>
            <a class="button button-small" href="<?= e(url('admin/newsletter/new')) ?>">新建早报</a>
        </div>
    </div>

    <div class="calendar-summary">
        <div><span>当前范围</span><strong><?= e($range['label']) ?></strong></div>
        <div><span>全部事项</span><strong><?= e((string) $stats['total']) ?></strong></div>
        <div><span>文章</span><strong><?= e((string) $stats['article']) ?></strong></div>
        <div><span>早报</span><strong><?= e((string) $stats['newsletter']) ?></strong></div>
    </div>

    <form class="admin-filter-bar calendar-filter-bar" method="get" action="<?= e(url('admin/calendar')) ?>">
        <select name="view" aria-label="View">
            <?php foreach ($viewModes as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['view'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?= e($filters['date']) ?>" aria-label="Date">
        <select name="status" aria-label="Status">
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="category" aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($adminCategories as $category): ?>
                <option value="<?= e((string) $category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="editor_id" aria-label="Editor">
            <option value="0">All editors</option>
            <?php foreach ($editors as $editor): ?>
                <?php $label = trim((string) ($editor['display_name'] ?? '')) ?: (string) $editor['email']; ?>
                <option value="<?= e((string) $editor['id']) ?>" <?= (int) $filters['editor_id'] === (int) $editor['id'] ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="calendar-nav">
        <a class="ghost-link" href="<?= e($calendarUrl(['date' => $range['previous_date']])) ?>">← 上一段</a>
        <strong><?= e($range['label']) ?></strong>
        <a class="ghost-link" href="<?= e($calendarUrl(['date' => date('Y-m-d')])) ?>">今天</a>
        <a class="ghost-link" href="<?= e($calendarUrl(['date' => $range['next_date']])) ?>">下一段 →</a>
    </div>

    <?php if ($filters['view'] === 'week'): ?>
        <div class="calendar-week-grid">
            <?php foreach ($weekDays as $day): ?>
                <?php $dayEvents = $events[$day['date']] ?? []; ?>
                <section class="calendar-week-day <?= $day['is_today'] ? 'is-today' : '' ?>">
                    <h2><?= e($day['label']) ?></h2>
                    <?php foreach ($dayEvents as $event): ?>
                        <?php $renderEvent($event); ?>
                    <?php endforeach; ?>
                    <?php if (!$dayEvents): ?>
                        <p class="calendar-empty">No scheduled items.</p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="calendar-weekdays" aria-hidden="true">
            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
        </div>
        <div class="calendar-month-grid">
            <?php foreach ($monthDays as $day): ?>
                <?php $dayEvents = $events[$day['date']] ?? []; ?>
                <section class="calendar-day <?= !$day['is_current_month'] ? 'is-muted' : '' ?> <?= $day['is_today'] ? 'is-today' : '' ?>">
                    <div class="calendar-day-number">
                        <strong><?= e((string) $day['day']) ?></strong>
                        <?php if ($dayEvents): ?><small><?= e((string) count($dayEvents)) ?></small><?php endif; ?>
                    </div>
                    <div class="calendar-day-events">
                        <?php foreach ($dayEvents as $event): ?>
                            <?php $renderEvent($event); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($stats['total'] === 0): ?>
        <div class="empty-state calendar-empty-state">
            <strong>这个时间范围还没有排期。</strong>
            <p>切换月份/周视图，或调整状态、栏目、编辑筛选。没有发布时间的草稿不会出现在日历上。</p>
        </div>
    <?php endif; ?>
</section>
