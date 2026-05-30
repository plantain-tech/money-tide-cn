<?php
$pageTitle = '数据导出 - 钱潮 Money Tide';
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维</p>
            <h1>数据导出</h1>
            <p>每张表导出为 UTF-8 CSV，最多 5000 行，可用于备份或外部分析。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/backup')) ?>">备份指南</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
        </div>
    </div>

    <div class="status-banner is-ready">
        <strong>完整备份建议</strong>
        <span>在 <a href="<?= e(url('admin/backup')) ?>" style="border-bottom:1px solid">备份与安全</a> 页面查看推荐导出频率、主机快照指南和密钥保管说明。</span>
    </div>

    <h2 style="margin:28px 0 12px">核心数据</h2>
    <div class="admin-module-grid">
        <?php foreach ([
            'articles'          => '所有文章（含元数据）',
            'subscribers'       => '订阅用户',
            'newsletter_issues' => 'Newsletter 期号',
            'newsletter_sends'  => 'Newsletter 发送记录',
            'article_reactions' => '读者反馈',
        ] as $name => $label): ?>
            <a class="admin-module" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>
                <strong><?= e($label) ?></strong>
                <span><code><?= e($name) ?></code>.csv</span>
            </a>
        <?php endforeach; ?>
    </div>

    <h2 style="margin:28px 0 12px">分析与日志</h2>
    <div class="admin-module-grid">
        <?php foreach ([
            'analytics_events'  => '站内事件（最近 5000 条）',
            'article_audit_logs'=> '文章审计日志',
            'ai_usage_logs'     => 'AI 调用日志',
        ] as $name => $label): ?>
            <a class="admin-module" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>
                <strong><?= e($label) ?></strong>
                <span><code><?= e($name) ?></code>.csv</span>
            </a>
        <?php endforeach; ?>
    </div>

    <h2 style="margin:28px 0 12px">配置与模板</h2>
    <div class="admin-module-grid">
        <?php foreach ([
            'source_profiles'    => '来源库',
            'source_templates'   => '输入模板',
            'research_briefs'    => '研究简报',
            'ai_bots'            => 'AI 栏目机器人',
            'ai_task_templates'  => 'AI 任务模板',
            'ai_story_intakes'   => 'AI 故事选题',
            'social_posts'       => '社交文案',
            'article_claims'     => '文章声明检查',
            'article_short_format' => '60秒看懂卡片',
            'reader_saved_articles' => '读者收藏',
            'reader_recent_reads'   => '读者阅读历史',
        ] as $name => $label): ?>
            <a class="admin-module" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>
                <strong><?= e($label) ?></strong>
                <span><code><?= e($name) ?></code>.csv</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
