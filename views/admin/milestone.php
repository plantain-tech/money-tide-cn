<?php
$pageTitle = '里程碑 · 8 周回顾 - 钱潮 Money Tide';
?>
<section class="admin-shell milestone-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">里程碑</p>
            <h1>8 周建设回顾与上线就绪</h1>
            <p>从空白到可上线的财经内容平台 —— 这是 8 周走过的路，以及下一步去哪里。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/week8-checklist')) ?>">第 8 周检查</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
        </div>
    </div>

    <!-- ── Launch readiness hero ──────────────────────────────────────────── -->
    <div class="milestone-hero <?= $readiness['ready'] ? 'is-ready' : 'is-warning' ?>">
        <div class="milestone-hero-status">
            <span class="milestone-hero-icon"><?= $readiness['ready'] ? '🚀' : '⚠️' ?></span>
            <div>
                <strong><?= $readiness['ready'] ? '已具备上线条件' : '上线前还有未通过项' ?></strong>
                <p>生产自检 <?= e((string) $readiness['passed']) ?>/<?= e((string) $readiness['total']) ?> 通过（<?= e((string) $readiness['pass_rate']) ?>%）<?= $readiness['failed'] > 0 ? ' · ' . e((string) $readiness['failed']) . ' 项需处理' : '' ?></p>
            </div>
        </div>
        <div class="milestone-hero-stats">
            <div><strong><?= e((string) $stats['weeks']) ?></strong><span>周建设</span></div>
            <div><strong><?= e((string) $stats['articles']) ?></strong><span>已发布文章</span></div>
            <div><strong><?= e((string) $stats['subscribers']) ?></strong><span>活跃订阅</span></div>
            <div><strong><?= e((string) $stats['categories']) ?></strong><span>栏目</span></div>
            <div><strong><?= e((string) $stats['tables']) ?></strong><span>数据表</span></div>
        </div>
    </div>

    <?php if (!empty($readiness['failures'])): ?>
        <div class="form-message form-message-error">
            <strong>需处理的检查项：</strong>
            <?php foreach ($readiness['failures'] as $f): ?>
                <div>· <?= e($f['name']) ?> — <?= e($f['detail']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── Launch readiness pillars ───────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>✅ 上线就绪保障</h2>
        <div class="milestone-pillars">
            <?php foreach ($pillars as $p): ?>
                <div class="milestone-pillar">
                    <span class="milestone-pillar-icon"><?= e($p['icon']) ?></span>
                    <strong><?= e($p['title']) ?></strong>
                    <p><?= e($p['detail']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── 8-week journey ─────────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>🗺️ 8 周建设之路</h2>
        <div class="milestone-journey">
            <?php foreach ($journey as $i => $week): ?>
                <article class="milestone-week" style="--i: <?= e((string) $i) ?>">
                    <div class="milestone-week-head">
                        <span class="milestone-week-icon"><?= e($week['icon']) ?></span>
                        <div>
                            <strong><?= e($week['week']) ?></strong>
                            <span class="milestone-week-theme"><?= e($week['theme']) ?></span>
                        </div>
                    </div>
                    <ul class="milestone-week-items">
                        <?php foreach ($week['shipped'] as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── Week 8 wrap-up note ────────────────────────────────────────────── -->
    <section class="newsletter-block milestone-wrapup">
        <h2>📝 第 8 周总结</h2>
        <p>第 8 周把"能用"变成了"能放心上线"。邮件从模拟发送切换到 Brevo 真实投递并通过域名认证；Google 登录正式公开；全站加了缓存与性能优化；建立了分级备份、安全审计与监控接口；最后完成了全站中文化、移动端打磨和一套内容运营手册。</p>
        <p>核心安全原则贯穿始终 —— <strong>系统永远不会自动发布、自动群发或自动发帖</strong>。每一个对外动作都由人来确认。这让平台既高效又可控。</p>
        <p>8 周下来，钱潮 Money Tide 已经是一个具备完整编辑流程、AI 辅助、多渠道分发、读者账号、数据分析和运维保障的财经内容平台，可以开始稳定的日常发布。</p>
    </section>

    <!-- ── Post-milestone roadmap (6 pillars) ─────────────────────────────── -->
    <section class="newsletter-block">
        <h2>🧭 8 周之后的路线图</h2>
        <p>基础已经牢固。接下来按这六个方向把平台推向增长 —— 每一项都仍然遵守"不自动对外"的底线。</p>
        <div class="milestone-roadmap">
            <?php foreach ($roadmap as $r): ?>
                <article class="milestone-roadmap-card">
                    <div class="milestone-roadmap-head">
                        <span class="milestone-roadmap-icon"><?= e($r['icon']) ?></span>
                        <div>
                            <span class="milestone-roadmap-pillar"><?= e($r['pillar']) ?></span>
                            <strong><?= e($r['title']) ?></strong>
                        </div>
                    </div>
                    <p><?= e($r['detail']) ?></p>
                    <div class="milestone-roadmap-meta">
                        <span class="milestone-chip"><?= e($r['phase']) ?></span>
                        <span class="milestone-chip milestone-chip-effort">工作量：<?= e($r['effort']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="milestone-cta">
        <p>下一步建议：选定 1–2 个路线图方向作为第 9 周重点，其余进入储备清单。</p>
        <a class="button" href="<?= e(url('admin/week8-checklist')) ?>">查看第 8 周签收清单 →</a>
    </div>
</section>
