<?php
$pageTitle = '系统自检 - 钱潮 Money Tide';
$pass = 0;
$total = count($checks);
foreach ($checks as $c) {
    if ($c['ok']) {
        $pass++;
    }
}
$fail = $total - $pass;
// Group checks by optional 'group' key; ungrouped → 'core'
$grouped = [];
foreach ($checks as $check) {
    $g = (string) ($check['group'] ?? 'core');
    $grouped[$g][] = $check;
}
$groupLabels = [
    'core'     => '核心系统',
    'content'  => '内容与发布',
    'ai'       => 'AI 编辑部',
    'social'   => '社交与分发',
    'email'    => '邮件',
    'reader'   => '读者功能',
    'media'    => '媒体质量',
    'safety'   => '安全保障',
];
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维</p>
            <h1>系统自检</h1>
            <p>关键模块快速自检；通过 <strong><?= e((string) $pass) ?>/<?= e((string) $total) ?></strong> 项<?= $fail > 0 ? '，<strong class="text-warn">' . e((string) $fail) . ' 项失败</strong>' : '' ?>。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
            <a class="ghost-link" href="<?= e(url('admin/backup')) ?>">备份</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke?format=json')) ?>" target="_blank" rel="noopener">JSON ↗</a>
        </div>
    </div>

    <div class="status-banner <?= $pass === $total ? 'is-ready' : 'is-warning' ?>">
        <strong><?= $pass === $total ? '✓ 全部通过' : '⚠ 部分检查未通过' ?></strong>
        <span>通过 <?= e((string) $pass) ?>/<?= e((string) $total) ?><?= $fail > 0 ? ' · 失败 ' . e((string) $fail) . ' 项，请查看下方详情' : ' · 系统运行正常' ?></span>
    </div>

    <?php if ($fail > 0): ?>
        <section class="smoke-failures-panel newsletter-block">
            <h2>⚠ 失败项目</h2>
            <div class="admin-table">
                <div class="admin-table-row admin-table-head"><span>检查</span><span>详情</span></div>
                <?php foreach ($checks as $check): ?>
                    <?php if (!$check['ok']): ?>
                        <div class="admin-table-row smoke-row-fail">
                            <strong><?= e($check['name']) ?></strong>
                            <span><?= e((string) $check['detail']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($grouped as $groupKey => $groupChecks): ?>
        <section class="smoke-group newsletter-block">
            <h2><?= e($groupLabels[$groupKey] ?? ucfirst($groupKey)) ?></h2>
            <div class="admin-table">
                <div class="admin-table-row admin-table-head"><span>检查</span><span>状态</span><span>详情</span></div>
                <?php foreach ($groupChecks as $check): ?>
                    <div class="admin-table-row <?= $check['ok'] ? '' : 'smoke-row-fail' ?>">
                        <strong><?= e($check['name']) ?></strong>
                        <span><mark class="<?= $check['ok'] ? 'status-ok' : 'status-warn' ?>"><?= $check['ok'] ? 'PASS' : 'FAIL' ?></mark></span>
                        <span><?= e((string) $check['detail']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="newsletter-block">
        <h2>📡 监控集成</h2>
        <p>JSON 端点已升级为监控友好格式，可被外部告警工具轮询：</p>
        <div class="monitoring-endpoint-card">
            <div class="monitoring-endpoint-head">
                <strong>鉴权端点（完整检查）</strong>
                <a class="ghost-link" href="<?= e(url('admin/smoke?format=json')) ?>" target="_blank" rel="noopener">查看 JSON →</a>
            </div>
            <code class="monitoring-url"><?= e(canonical_url('admin/smoke?format=json')) ?></code>
            <p><small>返回：<code>{"status":"ok|degraded|critical","summary":{...},"failures":[...],"checks":[...]}</code></small></p>
        </div>
        <div class="monitoring-endpoint-card" style="margin-top:12px">
            <div class="monitoring-endpoint-head">
                <strong>无需鉴权探针</strong>
            </div>
            <code class="monitoring-url"><?= e(canonical_url('health.php')) ?></code>
            <p><small>适合 UptimeRobot / Better Uptime — 监控关键词 <code>status":"ok"</code>，5 分钟间隔即可。</small></p>
        </div>
    </section>
</section>
