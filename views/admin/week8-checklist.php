<?php
$pageTitle = '第 8 周检查 - 钱潮 Money Tide';
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
            <p class="eyebrow">第 8 周 · 基础设施完善</p>
            <h1>第 8 周完成签收</h1>
            <p>确认真实邮件投递、Google OAuth、性能缓存、备份导出和安全保障都已稳定上线。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/milestone')) ?>">里程碑回顾</a>
            <a class="ghost-link" href="<?= e(url('admin/backup')) ?>">备份</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
            <a class="ghost-link" href="<?= e(url('admin/content-ops')) ?>">内容运营</a>
        </div>
    </div>

    <!-- Release summary cards -->
    <div class="week-release-grid">
        <article class="week-release-card">
            <span>D1-2</span>
            <strong>真实邮件投递</strong>
            <p>Brevo 接入，DKIM/DMARC 验证，Newsletter 测试发送，pre-send checklist。</p>
        </article>
        <article class="week-release-card">
            <span>D3</span>
            <strong>Google OAuth</strong>
            <p>公开登录/注册，账号安全状态，偏好与收藏流程，OAuth QA 面板。</p>
        </article>
        <article class="week-release-card">
            <span>D4</span>
            <strong>性能与缓存</strong>
            <p>gzip、1年资产缓存、filemtime 版本号、LCP 优化、图片 Alt 检查。</p>
        </article>
        <article class="week-release-card">
            <span>D5</span>
            <strong>备份与安全</strong>
            <p>分级导出清单、安全保障审计、权限矩阵、监控友好 smoke JSON。</p>
        </article>
        <article class="week-release-card">
            <span>D6</span>
            <strong>UX / SEO / 运营</strong>
            <p>移动端打磨、全站中文化、SEO 健康快照、内容运营手册与发布节奏。</p>
        </article>
        <article class="week-release-card">
            <span>D7</span>
            <strong>上线就绪</strong>
            <p>里程碑回顾、8 周之路、上线就绪保障、8 周后路线图、最终签收。</p>
        </article>
    </div>

    <div class="status-banner <?= $ready ? 'is-ready' : 'is-warning' ?>">
        <strong>生产自检：<?= e((string) $pass) ?>/<?= e((string) $total) ?></strong>
        <span><?= $ready ? '所有核心模块通过。第 8 周基础设施完善阶段完成。' : '还有未通过项目，请查看系统自检详情后再签收。' ?></span>
    </div>

    <section class="newsletter-block">
        <h2>第 8 周完成定义</h2>
        <ol class="qa-list">
            <li><strong>邮件可以真实送达。</strong><small>Brevo 配置、DKIM/DMARC 通过验证，测试邮件可以送到 Gmail 收件箱而不进垃圾箱。</small></li>
            <li><strong>读者可以用 Google 账号登录。</strong><small>Google OAuth 公开可用，账号流程完整，profile/偏好/收藏正常。</small></li>
            <li><strong>站点对浏览器和爬虫更快。</strong><small>静态资源有长效缓存，压缩启用，LCP 图片优先加载，RSS/sitemap 有 Cache-Control。</small></li>
            <li><strong>数据随时可以导出备份。</strong><small>/admin/backup 可下载所有核心和配置表的 CSV，秘钥备份有文档指引。</small></li>
            <li><strong>安全保障有明确文档。</strong><small>无自动发布、无自动广播、无自动发帖，权限矩阵与代码逻辑一致，全部通过安全审计。</small></li>
        </ol>
    </section>

    <section class="newsletter-block">
        <h2>第 8 周 QA 清单</h2>
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
        <div class="ops-section-head">
            <h2>🧭 8 周之后的路线图</h2>
            <a class="ghost-link" href="<?= e(url('admin/milestone')) ?>">完整里程碑回顾 →</a>
        </div>
        <p>基础已经牢固，下一步按这六个方向推动增长（仍然遵守"不自动对外"的底线）。</p>
        <div class="milestone-roadmap">
            <?php foreach ($backlog as $item): ?>
                <article class="milestone-roadmap-card">
                    <div class="milestone-roadmap-head">
                        <span class="milestone-roadmap-icon"><?= e($item['icon'] ?? '•') ?></span>
                        <div>
                            <?php if (!empty($item['pillar'])): ?><span class="milestone-roadmap-pillar"><?= e($item['pillar']) ?></span><?php endif; ?>
                            <strong><?= e($item['title']) ?></strong>
                        </div>
                    </div>
                    <p><?= e($item['detail']) ?></p>
                    <?php if (!empty($item['phase'])): ?>
                        <div class="milestone-roadmap-meta">
                            <span class="milestone-chip"><?= e($item['phase']) ?></span>
                            <?php if (!empty($item['effort'])): ?><span class="milestone-chip milestone-chip-effort">工作量：<?= e($item['effort']) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
