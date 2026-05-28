<?php $pageTitle = '社交与传播分析 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 6 · 传播</p>
            <h1>社交与传播分析</h1>
            <p>哪些文章被分享、走了哪些渠道、社交带来多少回流、推荐链接带来多少订阅。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/social')) ?>">社交文案</a>
            <a class="ghost-link" href="<?= e(url('admin/analytics')) ?>">站内分析</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <div class="admin-stat-grid">
        <div><span>7 日分享</span><strong><?= e((string) $stats['share_events_7d']) ?></strong></div>
        <div><span>30 日分享</span><strong><?= e((string) $stats['share_events_30d']) ?></strong></div>
        <div><span>30 日复制链接</span><strong><?= e((string) $stats['copy_events_30d']) ?></strong></div>
        <div><span>7 日社交回流</span><strong><?= e((string) $stats['social_referred_views_7d']) ?></strong></div>
        <div><span>30 日推荐订阅</span><strong><?= e((string) $stats['referral_signups_30d']) ?></strong></div>
    </div>

    <div class="analytics-panels">
        <section class="analytics-panel">
            <h2>7 日最常被分享</h2>
            <?php if ($stats['top_shared_7d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($stats['top_shared_7d'] as $row): ?>
                        <li>
                            <a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) ($row['title'] ?: $row['slug'])) ?></a>
                            <span><?= e((string) $row['shares']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>还没有分享数据。</small></p><?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>30 日分享渠道</h2>
            <?php if ($stats['channels_30d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($stats['channels_30d'] as $row): ?>
                        <li><span><?= e((string) $row['channel']) ?></span><span><?= e((string) $row['total']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>无数据。</small></p><?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>30 日订阅来源</h2>
            <?php if ($stats['top_referral_sources_30d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($stats['top_referral_sources_30d'] as $row): ?>
                        <li><span><?= e((string) $row['source']) ?></span><span><?= e((string) $row['total']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>无数据。</small></p><?php endif; ?>
        </section>
    </div>

    <div class="status-banner is-ready">
        <strong>追踪说明</strong>
        <span>所有前台分享按钮已带 UTM 参数（utm_source=渠道, utm_medium=social）。复制链接、X、LinkedIn 都会记录 share 事件并标注渠道。回流统计基于 referrer 匹配 UTM 或主流社交域名。</span>
    </div>
</section>
