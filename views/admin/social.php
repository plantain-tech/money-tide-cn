<?php $pageTitle = '社交分发 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 6 · 社交分发</p>
            <h1>社交文案中心</h1>
            <p>跨文章查看所有社交文案。每条都对应一篇文章 × 一个渠道。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/social/schedule')) ?>">发布队列</a>
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">文章列表</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <nav class="admin-tabs" aria-label="状态">
        <?php
            $baseQuery = $filters;
            unset($baseQuery['status']);
            $statusTabs = ['' => ['全部', $statusCounts['all'] ?? 0]];
            foreach ($statusOptions as $k => $l) {
                $statusTabs[$k] = [$l, $statusCounts[$k] ?? 0];
            }
            foreach ($statusTabs as $value => [$label, $count]):
                $qs = array_filter(array_merge($baseQuery, ['status' => $value]), static fn ($v) => $v !== '' && $v !== null);
                $href = url('admin/social') . ($qs ? '?' . http_build_query($qs) : '');
        ?>
            <a class="admin-tab <?= ($filters['status'] ?? '') === $value ? 'is-active' : '' ?>" href="<?= e($href) ?>">
                <span><?= e($label) ?></span>
                <small><?= e((string) $count) ?></small>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/social')) ?>">
        <input type="search" name="q" placeholder="搜索标题或文案" value="<?= e($filters['q']) ?>">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <select name="channel">
            <option value="">全部渠道</option>
            <?php foreach ($channels as $key => $meta): ?>
                <option value="<?= e($key) ?>" <?= $filters['channel'] === $key ? 'selected' : '' ?>><?= e((string) $meta['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head">
            <span>文章</span><span>渠道</span><span>文案预览</span><span>Schedule</span><span>状态</span><span>操作</span>
        </div>
        <?php foreach ($posts as $row): ?>
            <?php
                $ch = (string) $row['channel'];
                $meta = $channels[$ch] ?? ['label' => $ch];
                $preview = trim((string) $row['content']);
                $preview = mb_substr($preview, 0, 100, 'UTF-8') . (mb_strlen((string) $row['content'], 'UTF-8') > 100 ? '…' : '');
            ?>
            <div class="admin-table-row">
                <div>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <small><?= e((string) $row['category_name']) ?> · /<?= e((string) $row['slug']) ?></small>
                </div>
                <span><mark><?= e((string) $meta['label']) ?></mark></span>
                <small><?= e($preview) ?></small>
                <span><?= !empty($row['scheduled_at']) ? e(date('Y-m-d H:i', strtotime((string) $row['scheduled_at']))) : '<small>未排期</small>' ?></span>
                <span><span class="social-status-chip is-<?= e((string) $row['status']) ?>"><?= e($statusOptions[$row['status']] ?? $row['status']) ?></span></span>
                <div class="admin-row-actions">
                    <a href="<?= e(url('admin/articles/' . $row['article_id'] . '/social#ch-' . $ch)) ?>">编辑</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$posts): ?>
            <div class="empty-state">
                <strong>还没有社交文案。</strong>
                <p>到任意文章的编辑页面，点击"社交分发"或在文章列表上点击 social 按钮，开始生成第一条文案。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
