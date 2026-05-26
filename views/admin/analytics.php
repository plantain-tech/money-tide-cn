<?php
$pageTitle = '站内分析 - 钱潮 Money Tide';
$max = 0;
foreach ($analytics['views_by_day'] as $row) {
    if ((int) $row['total'] > $max) {
        $max = (int) $row['total'];
    }
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Analytics</p>
            <h1>站内分析</h1>
            <p>轻量内部统计，仅记录页面路径、栏目和来源，不存储个人信息。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <div class="admin-stat-grid">
        <div><span>今日浏览</span><strong><?= e((string) $analytics['views']['today']) ?></strong></div>
        <div><span>7 日浏览</span><strong><?= e((string) $analytics['views']['last_7d']) ?></strong></div>
        <div><span>30 日浏览</span><strong><?= e((string) $analytics['views']['last_30d']) ?></strong></div>
        <div><span>累计浏览</span><strong><?= e((string) $analytics['views']['total']) ?></strong></div>
        <div><span>今日订阅</span><strong><?= e((string) $analytics['subscribes']['today']) ?></strong></div>
        <div><span>7 日订阅</span><strong><?= e((string) $analytics['subscribes']['last_7d']) ?></strong></div>
        <div><span>30 日订阅</span><strong><?= e((string) $analytics['subscribes']['last_30d']) ?></strong></div>
        <div><span>累计订阅事件</span><strong><?= e((string) $analytics['subscribes']['total']) ?></strong></div>
    </div>

    <div class="status-banner <?= ($externalAnalytics['ga_id'] !== '' || $externalAnalytics['plausible_domain'] !== '') ? 'is-ready' : 'is-warning' ?>">
        <strong>外部分析</strong>
        <span>
            GA: <?= $externalAnalytics['ga_id'] !== '' ? '已配置 (' . e($externalAnalytics['ga_id']) . ')' : '未配置（在部署 Secrets 设置 GA_MEASUREMENT_ID）' ?> ·
            Plausible: <?= $externalAnalytics['plausible_domain'] !== '' ? '已配置 (' . e($externalAnalytics['plausible_domain']) . ')' : '未配置（PLAUSIBLE_DOMAIN）' ?>
        </span>
    </div>

    <section class="analytics-panel">
        <h2>14 日浏览趋势</h2>
        <?php if ($analytics['views_by_day']): ?>
            <div class="analytics-bars">
                <?php foreach ($analytics['views_by_day'] as $row): ?>
                    <?php $h = $max > 0 ? max(4, (int) round((int) $row['total'] * 100 / $max)) : 4; ?>
                    <div class="analytics-bar" title="<?= e((string) $row['day']) ?>: <?= e((string) $row['total']) ?>">
                        <span style="height: <?= e((string) $h) ?>%"></span>
                        <small><?= e(substr((string) $row['day'], 5)) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p><small>还没有浏览数据。</small></p>
        <?php endif; ?>
    </section>

    <div class="analytics-panels">
        <section class="analytics-panel">
            <h2>7 日热门文章</h2>
            <?php if ($analytics['top_articles_7d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_articles_7d'] as $row): ?>
                        <li>
                            <a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) ($row['title'] ?: $row['slug'])) ?></a>
                            <span><?= e((string) $row['views']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><small>无数据。</small></p>
            <?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>30 日订阅来源</h2>
            <?php if ($analytics['top_sources_30d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_sources_30d'] as $row): ?>
                        <li>
                            <span><?= e((string) $row['source']) ?></span>
                            <span><?= e((string) $row['total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><small>无数据。</small></p>
            <?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>7 日外部来源</h2>
            <?php if ($analytics['top_referrers_7d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_referrers_7d'] as $row): ?>
                        <li>
                            <span><?= e((string) $row['referrer']) ?></span>
                            <span><?= e((string) $row['total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><small>无数据。</small></p>
            <?php endif; ?>
        </section>
    </div>
</section>
