<?php
$pageTitle = '第 6 周检查 - 钱潮 Money Tide';
$pass = 0;
$total = count($smokeChecks);
foreach ($smokeChecks as $c) {
    if ($c['ok']) {
        $pass++;
    }
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 6 周 · 社交分发</p>
            <h1>第 6 周完成签收</h1>
            <p>覆盖社交文案、AI 文案生成、微信导出、分享卡片、60秒看懂、UTM 追踪和传播分析。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/social')) ?>">社交分发</a>
            <a class="ghost-link" href="<?= e(url('admin/social-analytics')) ?>">传播分析</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <div class="status-banner <?= $pass === $total ? 'is-ready' : 'is-warning' ?>">
        <strong>系统自检：<?= e((string) $pass) ?>/<?= e((string) $total) ?></strong>
        <span><?= $pass === $total ? '所有核心模块运转正常。' : '有未通过项，请去 /admin/smoke 查看详情。' ?></span>
    </div>

    <section class="newsletter-block">
        <h2>第 6 周完成定义</h2>
        <ol class="qa-list">
            <li><strong>每篇文章有一个社交打包工作台。</strong><small>/admin/articles/{id}/social — 5 个渠道，独立内容、状态、备注</small></li>
            <li><strong>AI 能为每个渠道生成风格化文案。</strong><small>微信导语 / 小红书钩子 / LinkedIn 专业 / X 短钩子 / Newsletter 短推荐</small></li>
            <li><strong>微信版面一键导出。</strong><small>/admin/articles/{id}/wechat-export — 复制 HTML / 复制纯文本</small></li>
            <li><strong>每篇文章自动生成分享卡 / OG 图。</strong><small>/share-card/{slug}/{type}.svg + 社交图覆盖</small></li>
            <li><strong>60秒看懂速读卡可生成、可编辑、前台可见可分享。</strong><small>/admin/articles/{id}/short-format + 文章页 #short-format</small></li>
            <li><strong>分享链接带 UTM，分享事件按渠道记录。</strong><small>utm_source / utm_medium=social</small></li>
            <li><strong>传播分析可见。</strong><small>/admin/social-analytics — 分享数、渠道、回流、推荐订阅</small></li>
            <li><strong>不会自动发布。</strong><small>所有平台仍由编辑手动复制粘贴发布</small></li>
            <li><strong>生产 smoke 通过。</strong><small>当前 <?= e((string) $pass) ?>/<?= e((string) $total) ?> 通过</small></li>
        </ol>
    </section>

    <section class="newsletter-block">
        <h2>第 6 周 QA 自查清单</h2>
        <ol class="qa-list">
            <?php foreach ($items as $item): ?>
                <li>
                    <strong><?= e($item['label']) ?></strong>
                    <small><?= e($item['tip']) ?></small>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="newsletter-block">
        <h2>第 7 周储备清单</h2>
        <div class="story-grid">
            <?php foreach ($backlog as $item): ?>
                <article class="story-card interactive-card">
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e($item['detail']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
