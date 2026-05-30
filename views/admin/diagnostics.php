<?php $pageTitle = '系统诊断 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维</p>
            <h1>系统诊断</h1>
            <p>数据库连接、表行数、AI 额度、错误日志一站式查看。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">自检</a>
            <a class="ghost-link" href="<?= e(url('admin/backup')) ?>">备份</a>
            <a class="ghost-link" href="<?= e(url('admin/launch-cleanup')) ?>">上线清理</a>
            <a class="ghost-link" href="<?= e(url('admin/exports')) ?>">导出</a>
            <a class="ghost-link" href="<?= e(url('admin/audit')) ?>">审计日志</a>
        </div>
    </div>

    <?php
    $tableOk = count(array_filter($diagnostics['tables'], static fn (array $t): bool => $t['error'] === null));
    $tableTotal = count($diagnostics['tables']);
    $tableErrors = $tableTotal - $tableOk;
    ?>
    <div class="diagnostics-health-bar">
        <div class="diag-stat <?= $diagnostics['ready'] ? 'diag-stat-ok' : 'diag-stat-fail' ?>">
            <span>数据库</span>
            <strong><?= $diagnostics['ready'] ? '已连接' : '未连接' ?></strong>
        </div>
        <div class="diag-stat diag-stat-ok">
            <span>MySQL</span>
            <strong><?= e($diagnostics['version'] ? explode('-', $diagnostics['version'])[0] : '—') ?></strong>
        </div>
        <div class="diag-stat <?= $tableErrors === 0 ? 'diag-stat-ok' : 'diag-stat-warn' ?>">
            <span>表状态</span>
            <strong><?= e((string) $tableOk) ?>/<?= e((string) $tableTotal) ?> 正常</strong>
        </div>
        <div class="diag-stat <?= $aiUsage['used_today'] < $aiUsage['daily_limit'] ? 'diag-stat-ok' : 'diag-stat-warn' ?>">
            <span>今日 AI</span>
            <strong><?= e((string) $aiUsage['used_today']) ?>/<?= e((string) $aiUsage['daily_limit']) ?></strong>
        </div>
        <?php if (!empty($errorLog['lines'])): ?>
            <div class="diag-stat diag-stat-warn">
                <span>错误日志</span>
                <strong><?= e((string) count($errorLog['lines'])) ?> 行</strong>
            </div>
        <?php else: ?>
            <div class="diag-stat diag-stat-ok">
                <span>错误日志</span>
                <strong>清空</strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($diagnostics['errors'])): ?>
        <div class="form-message form-message-error">
            <?php foreach ($diagnostics['errors'] as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="newsletter-block">
        <h2>表行数</h2>
        <div class="admin-table">
            <div class="admin-table-row admin-table-head"><span>表</span><span>行数</span><span>状态</span></div>
            <?php foreach ($diagnostics['tables'] as $t): ?>
                <div class="admin-table-row">
                    <span><code><?= e($t['table']) ?></code></span>
                    <span><?= $t['rows'] === null ? '—' : e((string) $t['rows']) ?></span>
                    <span><?= $t['error'] ? '<mark class="status-warn">' . e($t['error']) . '</mark>' : '<mark class="status-ok">OK</mark>' ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="newsletter-block">
        <h2>PHP 错误日志（最近 <?= e((string) count($errorLog['lines'])) ?> 行）</h2>
        <?php if ($errorLog['path'] !== ''): ?>
            <small>路径：<code><?= e($errorLog['path']) ?></code></small>
            <?php if ($errorLog['lines']): ?>
                <pre class="error-log-pre"><?php foreach ($errorLog['lines'] as $line) { echo e($line) . "\n"; } ?></pre>
            <?php else: ?>
                <p><small>日志文件为空。</small></p>
            <?php endif; ?>
        <?php else: ?>
            <p><small>找不到 PHP 错误日志文件路径（已尝试常见位置）。</small></p>
        <?php endif; ?>
    </section>
</section>
