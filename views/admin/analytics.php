<?php
$pageTitle = '站内分析 - 钱潮 Money Tide';
$max = 0;
foreach ($analytics['views_by_day'] as $row) {
    $max = max($max, (int) $row['total']);
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">数据分析</p>
            <h1>站内分析</h1>
            <p>覆盖浏览、订阅、收藏、分享、读完率和回访读者。</p>
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
        <div><span>收藏总数</span><strong><?= e((string) ($analytics['retention']['saved_total'] ?? 0)) ?></strong></div>
        <div><span>30 日回访读者</span><strong><?= e((string) ($analytics['retention']['returning_readers_30d'] ?? 0)) ?></strong></div>
        <div><span>30 日读完</span><strong><?= e((string) ($analytics['retention']['completion_events_30d'] ?? 0)) ?></strong></div>
        <div><span>30 日分享</span><strong><?= e((string) ($analytics['retention']['share_events_30d'] ?? 0)) ?></strong></div>
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
                        <li><a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) ($row['title'] ?: $row['slug'])) ?></a><span><?= e((string) $row['views']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>无数据。</small></p><?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>最多收藏</h2>
            <?php if (!empty($analytics['retention']['top_saved'])): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['retention']['top_saved'] as $row): ?>
                        <li><a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) $row['title']) ?></a><span><?= e((string) $row['saves']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>暂无收藏数据。</small></p><?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>30 日订阅来源</h2>
            <?php if ($analytics['top_sources_30d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_sources_30d'] as $row): ?>
                        <li><span><?= e((string) $row['source']) ?></span><span><?= e((string) $row['total']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>无数据。</small></p><?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>7 日外部来源</h2>
            <?php if ($analytics['top_referrers_7d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_referrers_7d'] as $row): ?>
                        <li><span><?= e((string) $row['referrer']) ?></span><span><?= e((string) $row['total']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?><p><small>无数据。</small></p><?php endif; ?>
        </section>
    </div>

    <?php
        $reactions = $reactions ?? ['totals' => [], 'total_all' => 0, 'recent' => [], 'top_helpful' => [], 'top_more' => [], 'top_complex' => [], 'most_engaged' => [], 'days' => 30];
        $reactionLabels = function_exists('reaction_types') ? reaction_types() : ['helpful' => '有帮助', 'more' => '想看更多', 'complex' => '太复杂'];
        $reactionHints = [
            'helpful' => '读者觉得有价值，可以多写这类选题。',
            'more' => '读者希望看到更多延伸，适合做系列或深度跟进。',
            'complex' => '读者觉得偏难，可考虑加“一句话看懂”或拆解。',
        ];
    ?>
    <section class="analytics-panel">
        <h2>读者反馈</h2>
        <p><small>读者在文章页给出的一秒反馈，用来指导选题方向。共 <?= e((string) ($reactions['total_all'] ?? 0)) ?> 次反馈。</small></p>
        <div class="admin-stat-grid">
            <?php foreach ($reactionLabels as $key => $label): ?>
                <div>
                    <span><?= e($label) ?></span>
                    <strong><?= e((string) (int) ($reactions['totals'][$key] ?? 0)) ?></strong>
                    <small>近 <?= e((string) ($reactions['days'] ?? 30)) ?> 日 <?= e((string) (int) ($reactions['recent'][$key] ?? 0)) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="reaction-hint-list">
            <?php foreach ($reactionLabels as $key => $label): ?>
                <p><strong><?= e($label) ?></strong>：<?= e($reactionHints[$key] ?? '') ?></p>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="analytics-panels">
        <?php
            $reactionBuckets = [
                'top_helpful' => '“有帮助”最多',
                'top_more' => '“想看更多”最多',
                'top_complex' => '“太复杂”最多',
            ];
        ?>
        <?php foreach ($reactionBuckets as $bucket => $heading): ?>
            <section class="analytics-panel">
                <h2><?= e($heading) ?></h2>
                <?php if (!empty($reactions[$bucket])): ?>
                    <ol class="analytics-list">
                        <?php foreach ($reactions[$bucket] as $row): ?>
                            <li><a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) ($row['title'] ?: $row['slug'])) ?></a><span><?= e((string) $row['n']) ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?><p><small>暂无数据。</small></p><?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($reactions['most_engaged'])): ?>
        <section class="analytics-panel">
            <h2>互动最多的文章</h2>
            <table class="admin-table reaction-table">
                <thead>
                    <tr><th>文章</th><th>有帮助</th><th>想看更多</th><th>太复杂</th><th>合计</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reactions['most_engaged'] as $row): ?>
                        <tr>
                            <td><a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) ($row['title'] ?: $row['slug'])) ?></a></td>
                            <td><?= e((string) (int) $row['helpful']) ?></td>
                            <td><?= e((string) (int) $row['more']) ?></td>
                            <td><?= e((string) (int) $row['complex']) ?></td>
                            <td><strong><?= e((string) (int) $row['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</section>
