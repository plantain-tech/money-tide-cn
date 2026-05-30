<?php
$pageTitle = '备份与安全 - 钱潮 Money Tide';
$essential = array_filter($manifest, static fn (array $t): bool => $t['priority'] === 'essential');
$important = array_filter($manifest, static fn (array $t): bool => $t['priority'] === 'important');
$optional  = array_filter($manifest, static fn (array $t): bool => $t['priority'] === 'optional');
$safetyPass = array_filter($safetyAudit, static fn (array $a): bool => $a['ok']);
$safetyTotal = count($safetyAudit);
$safetyPassCount = count($safetyPass);
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">运维 · 安全</p>
            <h1>备份、导出与安全审计</h1>
            <p>数据导出备份、系统安全保障确认、角色权限矩阵一站式查看。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
            <a class="ghost-link" href="<?= e(url('admin/audit')) ?>">审计日志</a>
        </div>
    </div>

    <!-- ── Backup schedule banner ────────────────────────────────────────── -->
    <div class="status-banner is-ready">
        <strong>建议备份频率</strong>
        <span>核心数据（文章、订阅用户、早报）—— 每周导出一次；配置数据 —— 每月；Hostinger 主机快照 —— 每月</span>
    </div>

    <!-- ── Hostinger built-in backup note ────────────────────────────────── -->
    <div class="backup-platform-note">
        <div class="backup-platform-icon">🏠</div>
        <div>
            <strong>Hostinger 主机快照备份</strong>
            <p>进入 Hostinger hPanel → 备份 → 创建备份，可快照整个主机环境（文件 + 数据库）。建议每月操作一次，发布重大功能前额外备份一次。</p>
            <p><small>CSV 导出是数据层面的增量备份，与主机快照互补，不能互相替代。</small></p>
        </div>
    </div>

    <!-- ── Essential exports ──────────────────────────────────────────────── -->
    <section class="newsletter-block backup-section">
        <div class="backup-section-head">
            <h2>📦 核心数据导出</h2>
            <span class="backup-freq-badge">建议每周</span>
        </div>
        <p>以下数据是运营核心，丢失后最难恢复，建议每周至少下载一次。</p>
        <div class="backup-export-grid">
            <?php foreach ($essential as $name => $meta): ?>
                <div class="backup-export-card">
                    <div class="backup-export-info">
                        <strong><?= e($meta['label']) ?></strong>
                        <small><?= e($meta['desc']) ?></small>
                    </div>
                    <a class="button button-small" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>下载 CSV</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Important exports ──────────────────────────────────────────────── -->
    <section class="newsletter-block backup-section">
        <div class="backup-section-head">
            <h2>📋 重要数据导出</h2>
            <span class="backup-freq-badge backup-freq-monthly">建议每月</span>
        </div>
        <p>配置、模板和日志类数据，月度导出即可，或在大版本上线前备份一次。</p>
        <div class="backup-export-grid">
            <?php foreach ($important as $name => $meta): ?>
                <div class="backup-export-card">
                    <div class="backup-export-info">
                        <strong><?= e($meta['label']) ?></strong>
                        <small><?= e($meta['desc']) ?></small>
                    </div>
                    <a class="button button-small button-ghost" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>下载 CSV</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Optional exports ───────────────────────────────────────────────── -->
    <section class="newsletter-block backup-section">
        <div class="backup-section-head">
            <h2>🗂 其他数据导出</h2>
            <span class="backup-freq-badge backup-freq-optional">按需</span>
        </div>
        <div class="backup-export-grid">
            <?php foreach ($optional as $name => $meta): ?>
                <div class="backup-export-card">
                    <div class="backup-export-info">
                        <strong><?= e($meta['label']) ?></strong>
                        <small><?= e($meta['desc']) ?></small>
                    </div>
                    <a class="button button-small button-ghost" href="<?= e(url('admin/exports/' . $name . '.csv')) ?>" download>下载 CSV</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Config / secrets note ──────────────────────────────────────────── -->
    <div class="backup-config-note">
        <strong>⚙️ 密钥与配置备份</strong>
        <ul>
            <li>数据库凭证、邮件 API 密钥、OAuth 密钥存储在 <strong>GitHub Secrets</strong> 中，不在代码库，不在 CSV 导出里。</li>
            <li>确认 GitHub → Settings → Secrets and variables 中以下密钥已正确填写：<code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>EMAIL_API_KEY</code>, <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code>, <code>UNSUBSCRIBE_SECRET</code>。</li>
            <li>将这些密钥同步保存到加密密码管理器（如 1Password / Bitwarden）作为离线副本。</li>
        </ul>
    </div>

    <!-- ── Safety audit ───────────────────────────────────────────────────── -->
    <section class="newsletter-block backup-section">
        <div class="backup-section-head">
            <h2>🛡 安全保障确认</h2>
            <span class="backup-safety-badge <?= $safetyPassCount === $safetyTotal ? 'is-ok' : 'is-warn' ?>">
                <?= e((string) $safetyPassCount) ?>/<?= e((string) $safetyTotal) ?> PASS
            </span>
        </div>
        <p>以下项目为代码架构层面的安全保障，确认系统不存在任何自动发布、自动广播或绕过角色权限的路径。</p>
        <div class="admin-table">
            <div class="admin-table-row admin-table-head">
                <span>保障项目</span><span>状态</span><span>说明</span>
            </div>
            <?php foreach ($safetyAudit as $audit): ?>
                <div class="admin-table-row">
                    <div>
                        <strong><?= e($audit['gate']) ?></strong>
                        <?php if (!empty($audit['guarantee']) && $audit['guarantee'] !== '无' && $audit['guarantee'] !== '已实现'): ?>
                            <small>限制：<?= e($audit['guarantee']) ?></small>
                        <?php endif; ?>
                    </div>
                    <span><mark class="<?= $audit['ok'] ? 'status-ok' : 'status-warn' ?>"><?= $audit['ok'] ? 'PASS' : 'FAIL' ?></mark></span>
                    <div>
                        <span><?= e($audit['detail']) ?></span>
                        <small><?= e($audit['verified_in']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Permission matrix ──────────────────────────────────────────────── -->
    <section class="newsletter-block backup-section">
        <div class="backup-section-head">
            <h2>👤 角色权限矩阵</h2>
        </div>
        <p>系统支持 writer / editor / admin 三个角色。以下为各角色的操作权限。</p>
        <div class="permission-matrix-wrap">
            <table class="admin-table permission-matrix">
                <thead>
                    <tr>
                        <th>操作</th>
                        <?php foreach ($permissionMatrix['columns'] as $col): ?>
                            <th><?= e($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissionMatrix['rows'] as $row): ?>
                        <tr>
                            <td><?= e($row['label']) ?></td>
                            <?php foreach ($permissionMatrix['columns'] as $col): ?>
                                <td class="permission-cell <?= $row[$col] ? 'permission-yes' : 'permission-no' ?>">
                                    <?= $row[$col] ? '✓' : '✗' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ── Monitoring integration ─────────────────────────────────────────── -->
    <section class="newsletter-block backup-section">
        <div class="backup-section-head">
            <h2>📡 监控集成</h2>
        </div>
        <p>Smoke 自检接口已升级为监控友好格式，可接入 UptimeRobot、Better Uptime、Healthchecks.io 或自定义脚本：</p>
        <div class="monitoring-endpoint-card">
            <div class="monitoring-endpoint-head">
                <strong>JSON 自检端点</strong>
                <a class="ghost-link" href="<?= e(url('admin/smoke?format=json')) ?>" target="_blank" rel="noopener">在新窗口打开 →</a>
            </div>
            <code class="monitoring-url"><?= e(canonical_url('admin/smoke?format=json')) ?></code>
            <p><small>返回格式：<code>{"status":"ok","summary":{"total":N,"passed":N,"failed":0},"failures":[],"checks":[...]}</code></small></p>
            <ul class="monitoring-tips">
                <li>监控脚本可检查 <code>"status"</code> 字段是否为 <code>"ok"</code>。</li>
                <li><code>"degraded"</code> 表示 1-2 项失败，<code>"critical"</code> 表示 3 项以上失败。</li>
                <li><code>"failures"</code> 数组只包含失败项，为空时表示全部通过。</li>
                <li>端点需要管理员 session，建议通过 IP 白名单或专用监控账号访问。</li>
            </ul>
        </div>

        <p class="mt-16"><strong>健康探针（无需鉴权）：</strong></p>
        <code class="monitoring-url"><?= e(canonical_url('health.php')) ?></code>
        <p><small>返回 <code>{"status":"ok","release":"..."}</code>，可用于 UptimeRobot HTTP 关键词检查（关键词：<code>status":"ok"</code>）。</small></p>
    </section>
</section>
