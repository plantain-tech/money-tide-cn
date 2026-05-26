<?php
$pageTitle = '数据导出 - 钱潮 Money Tide';
$tables = [
    'articles' => '所有文章（含元数据）',
    'subscribers' => '订阅用户',
    'newsletter_issues' => 'Newsletter 期号',
    'newsletter_sends' => 'Newsletter 发送记录',
    'source_profiles' => '来源库',
    'source_templates' => '输入模板',
    'research_briefs' => '研究简报',
    'analytics_events' => '站内事件（最近 5000 条）',
    'article_audit_logs' => '文章审计日志',
    'ai_usage_logs' => 'AI 调用日志',
];
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维</p>
            <h1>数据导出</h1>
            <p>每张表导出为 CSV，前 5000 行。可作备份或外部分析。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
        </div>
    </div>

    <div class="admin-module-grid">
        <?php foreach ($tables as $name => $label): ?>
            <a class="admin-module" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>
                <strong><?= e($label) ?></strong>
                <span><code><?= e($name) ?></code>.csv</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
