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
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">Smoke</a>
            <a class="ghost-link" href="<?= e(url('admin/exports')) ?>">导出</a>
            <a class="ghost-link" href="<?= e(url('admin/audit')) ?>">审计日志</a>
            <a class="ghost-link" href="<?= e(url('admin/qa-checklist')) ?>">QA</a>
        </div>
    </div>

    <div class="status-banner <?= $diagnostics['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong>数据库：<?= $diagnostics['ready'] ? '已连接' : '未连接' ?></strong>
        <span>MySQL 版本：<?= e($diagnostics['version'] ?: '—') ?> · 今日 AI：<?= e((string) $aiUsage['used_today']) ?>/<?= e((string) $aiUsage['daily_limit']) ?></span>
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
