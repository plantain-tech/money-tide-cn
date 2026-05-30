<?php
$pageTitle = '第 5 周检查 - 钱潮 Money Tide';
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
            <p class="eyebrow">第 5 周 · AI 编辑部</p>
            <h1>第 5 周完成签收</h1>
            <p>覆盖 AI 编辑机器人、选题录入、草稿队列、事实核查、改写工具、早报助理和进度提示。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <div class="status-banner <?= $pass === $total ? 'is-ready' : 'is-warning' ?>">
        <strong>系统自检：<?= e((string) $pass) ?>/<?= e((string) $total) ?></strong>
        <span><?= $pass === $total ? '所有核心模块运转正常。' : '有未通过项，请去 /admin/smoke 查看详情。' ?></span>
    </div>

    <section class="newsletter-block">
        <h2>第 5 周完成定义</h2>
        <ol class="qa-list">
            <li><strong>管理员可管理 AI 编辑机器人。</strong><small>/admin/ai-bots — 名称 / 语气 / 目标读者 / 来源规则 / 风险规则 / 提示词模板 / 启用状态</small></li>
            <li><strong>管理员可提交选题与来源。</strong><small>/admin/ai-intake — 选择 bot、紧急程度、目标读者、来源链接</small></li>
            <li><strong>AI 可生成结构化简报。</strong><small>简报包含 headline / 一句话看懂 / 为什么重要 / key numbers / 标签 / 风险 / 来源问题 / next steps</small></li>
            <li><strong>AI 草稿进入审核队列。</strong><small>/admin/ai-drafts — 9 个状态 tab + 质量评分 + 警告 pill</small></li>
            <li><strong>编辑可以用 AI 重写各个区块。</strong><small>/admin/ai-drafts/{id} — 10 个 rewrite target + 3 个 tone preset</small></li>
            <li><strong>事实/风险检查在发布前可见。</strong><small>/admin/articles/{id}/edit — 风险面板 + Claims 区块</small></li>
            <li><strong>Newsletter 助理可生成草稿期。</strong><small>/admin/newsletter/{id}/edit — 开场白 + 推荐语 + 6 个主题段落</small></li>
            <li><strong>不会自动发布。</strong><small>所有发布/广播仍需编辑手动点击。AI 工具仅产出草稿。</small></li>
            <li><strong>生产 smoke 通过。</strong><small>/admin/smoke?format=json — 当前 <?= e((string) $pass) ?>/<?= e((string) $total) ?> 通过</small></li>
        </ol>
    </section>

    <section class="newsletter-block">
        <h2>第 5 周 QA 自查清单</h2>
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
        <h2>第 6 周储备清单</h2>
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
