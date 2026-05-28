<?php
$pageTitle = 'ç¤¾äº¤å‘å¸ƒé˜Ÿåˆ— - é’±æ½® Money Tide';
$segments = social_schedule_segments();
$queueUrl = static function (array $override = []) use ($filters): string {
    $params = array_filter(array_merge($filters, $override), static fn ($value) => $value !== '' && $value !== null);
    return url('admin/social/schedule') . ($params ? '?' . http_build_query($params) : '');
};
?>
<section class="admin-shell schedule-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">ç¬¬ 7 å‘¨ Â· ç¬¬ 4 å¤©</p>
            <h1>ç¤¾äº¤å‘å¸ƒé˜Ÿåˆ—</h1>
            <p>ç»™æ¯æ¡ç¤¾äº¤æ–‡æ¡ˆè®¾ç½®äººå·¥å‘å¸ƒæ—¶é—´ï¼ŒæŒ‰ä»Šå¤©ã€æœªæ¥ã€é€¾æœŸå’ŒæœªæŽ’æœŸæŸ¥çœ‹ã€‚è¿™é‡Œä¸ä¼šè‡ªåŠ¨å‘å¸–ã€‚</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/social')) ?>">ç¤¾äº¤ä¸­å¿ƒ</a>
            <a class="ghost-link" href="<?= e(url('admin/calendar')) ?>">ç¼–è¾‘æ—¥åŽ†</a>
            <a class="button button-small" href="<?= e(url('admin/social/schedule.csv') . '?' . http_build_query($filters)) ?>">å¯¼å‡º CSV</a>
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
        <input type="search" name="q" placeholder="æœç´¢æ ‡é¢˜æˆ–æ–‡æ¡ˆ" value="<?= e($filters['q']) ?>">
        <select name="channel">
            <option value="">å…¨éƒ¨æ¸ é“</option>
            <?php foreach ($channels as $key => $meta): ?>
                <option value="<?= e($key) ?>" <?= $filters['channel'] === $key ? 'selected' : '' ?>><?= e((string) $meta['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">å…¨éƒ¨çŠ¶æ€</option>
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">ç­›é€‰</button>
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
                    <strong><?= $scheduledAt ? e(date('M j', $scheduledAt)) : 'æœªæŽ’æœŸ' ?></strong>
                    <span><?= $scheduledAt ? e(date('H:i', $scheduledAt)) : 'æœªè®¾ç½®æ—¶é—´' ?></span>
                </div>
                <div class="queue-body">
                    <p class="eyebrow"><?= e((string) $meta['label']) ?> Â· <?= e((string) $row['category_name']) ?></p>
                    <h2><?= e((string) $row['title']) ?></h2>
                    <p><?= e(mb_substr(trim((string) $row['content']), 0, 180, 'UTF-8')) ?><?= mb_strlen((string) $row['content'], 'UTF-8') > 180 ? 'â€¦' : '' ?></p>
                </div>
                <div class="queue-actions">
                    <span class="social-status-chip is-<?= e((string) $row['status']) ?>"><?= e($statusOptions[$row['status']] ?? $row['status']) ?></span>
                    <?php if ($isOverdue): ?><mark>éœ€è¦å¤„ç†</mark><?php endif; ?>
                    <a class="ghost-link" href="<?= e(url('admin/articles/' . $row['article_id'] . '/social#ch-' . $channel)) ?>">ç¼–è¾‘ / æ‰‹åŠ¨å‘å¸ƒ</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$posts): ?>
            <div class="empty-state">
                <strong>è¿™ä¸ªé˜Ÿåˆ—é‡Œè¿˜æ²¡æœ‰ç¤¾äº¤æ–‡æ¡ˆã€‚</strong>
                <p>可以在文章的社交工作台设置发布时间，或切换到其他队列。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
