<?php
$pageTitle = '第 7 周检查 - 钱潮 Money Tide';
$pass = 0;
$total = count($smokeChecks);
foreach ($smokeChecks as $check) {
    if (!empty($check['ok'])) {
        $pass++;
    }
}
$ready = $pass === $total;
?>
<section class="admin-shell week-checklist-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 7 周 · QA 与打磨</p>
            <h1>第 7 周完成签收</h1>
            <p>确认搜索、RSS、编辑日历、社交排期、早报排期和读者反馈都已经稳定上线，并且所有发布/发送仍由编辑手动完成。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('search')) ?>">公开搜索</a>
            <a class="ghost-link" href="<?= e(url('feed/all.xml')) ?>">RSS</a>
            <a class="ghost-link" href="<?= e(url('admin/calendar')) ?>">编辑日历</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
        </div>
    </div>

    <div class="week-release-grid">
        <article class="week-release-card">
            <span>内容发现</span>
            <strong>搜索 + RSS</strong>
            <p>读者可以主动找文章，外部工具也可以订阅更新。</p>
        </article>
        <article class="week-release-card">
            <span>编辑计划</span>
            <strong>日历 + 队列</strong>
            <p>编辑能按时间查看文章、早报和社交文案。</p>
        </article>
        <article class="week-release-card">
            <span>人工安全</span>
            <strong>不自动发布</strong>
            <p>排期只提醒，文章发布、早报广播和社交发布都需要人工确认。</p>
        </article>
        <article class="week-release-card">
            <span>读者信号</span>
            <strong>反馈统计</strong>
            <p>用“有帮助 / 想看更多 / 太复杂”指导后续选题。</p>
        </article>
    </div>

    <div class="status-banner <?= $ready ? 'is-ready' : 'is-warning' ?>">
        <strong>生产自检：<?= e((string) $pass) ?>/<?= e((string) $total) ?></strong>
        <span><?= $ready ? '核心模块全部通过。' : '还有未通过项目，请先查看系统自检详情。' ?></span>
    </div>

    <section class="newsletter-block">
        <h2>第 7 周完成定义</h2>
        <ol class="qa-list">
            <li><strong>读者能发现内容。</strong><small>/search、页头搜索、RSS feed 和 sitemap/robots 输出都可用。</small></li>
            <li><strong>编辑能计划内容。</strong><small>/admin/calendar、/admin/social/schedule、/admin/newsletter/schedule 已形成排期工作台。</small></li>
            <li><strong>读者反馈进入数据层。</strong><small>文章 reaction 会进入 /admin/analytics，帮助判断内容方向。</small></li>
            <li><strong>移动端可用。</strong><small>搜索、feed 入口、日历、社交队列和早报队列在窄屏不应横向溢出。</small></li>
            <li><strong>没有误触发自动化。</strong><small>排期不等于自动发布，所有对外发布和发送都由编辑手动确认。</small></li>
        </ol>
    </section>

    <section class="newsletter-block">
        <h2>第 7 周 QA 清单</h2>
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
        <h2>第 8 周 Backlog</h2>
        <div class="story-grid week-backlog-grid">
            <?php foreach ($backlog as $item): ?>
                <article class="story-card interactive-card">
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e($item['detail']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
