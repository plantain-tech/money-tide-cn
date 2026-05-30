<?php
$pageTitle = '内容运营 - 钱潮 Money Tide';
$seoPass = count(array_filter($seoHealth, static fn (array $c): bool => $c['ok']));
$seoTotal = count($seoHealth);
$missingAlt = $missingAlt ?? 0;
$flash = $flash ?? '';
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">内容运营</p>
            <h1>内容运营与每日发布节奏</h1>
            <p>把"每天写什么、什么时候发、发完做什么"固定成一套可持续的流程。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/calendar')) ?>">编辑日历</a>
            <a class="ghost-link" href="<?= e(url('admin/analytics')) ?>">数据分析</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>完成</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <?php if ($missingAlt > 0): ?>
        <div class="alt-backfill-banner">
            <div>
                <strong>⚡ 一键补充图片替代文字</strong>
                <p>当前有 <strong><?= e((string) $missingAlt) ?></strong> 篇已发布文章缺少图片 Alt 文字。点击下方按钮会用文章标题作为默认 Alt 填充（只填空白项，不覆盖已有内容），可立即让 SEO 与 Alt 检查通过；之后仍可在文章编辑页改成更贴切的描述。</p>
            </div>
            <form method="post" action="<?= e(url('admin/content-ops/backfill-alt')) ?>">
                <button class="button" type="submit"
                    data-confirm="确认为 <?= e((string) $missingAlt) ?> 篇文章补充 Alt 文字？"
                    data-confirm-sub="将用各文章标题作为默认 Alt，只填充空白项，不会覆盖已有 Alt。"
                    data-confirm-title="一键补充 Alt 文字"
                    data-confirm-confirm="确认补充">一键补充 Alt（<?= e((string) $missingAlt) ?> 篇）</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ── SEO / 技术健康快照 ─────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <div class="ops-section-head">
            <h2>🔍 SEO 与技术健康</h2>
            <span class="ops-health-badge <?= $seoPass === $seoTotal ? 'is-ok' : 'is-warn' ?>"><?= e((string) $seoPass) ?>/<?= e((string) $seoTotal) ?> 正常</span>
        </div>
        <p>上线前后定期确认这些 SEO 与可发现性要素都到位。</p>
        <div class="ops-seo-grid">
            <?php foreach ($seoHealth as $check): ?>
                <div class="ops-seo-card <?= $check['ok'] ? 'is-ok' : 'is-warn' ?>">
                    <div class="ops-seo-card-head">
                        <strong><?= e($check['name']) ?></strong>
                        <mark class="<?= $check['ok'] ? 'status-ok' : 'status-warn' ?>"><?= $check['ok'] ? '正常' : '需检查' ?></mark>
                    </div>
                    <p><?= e($check['detail']) ?></p>
                    <?php if (!empty($check['url'])): ?>
                        <a class="ghost-link" href="<?= e($check['url']) ?>" target="_blank" rel="noopener">打开查看 ↗</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── 每日发布节奏 ───────────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>🕑 每日发布节奏</h2>
        <p>一套适合个人或小团队的可持续节奏。时间点可按自己情况调整，关键是顺序与每步产出。</p>
        <div class="ops-rhythm-timeline">
            <?php foreach ($rhythm as $i => $block): ?>
                <article class="ops-rhythm-block">
                    <div class="ops-rhythm-marker">
                        <span class="ops-rhythm-icon"><?= e($block['icon']) ?></span>
                        <?php if ($i < count($rhythm) - 1): ?><span class="ops-rhythm-line" aria-hidden="true"></span><?php endif; ?>
                    </div>
                    <div class="ops-rhythm-content">
                        <div class="ops-rhythm-head">
                            <strong><?= e($block['phase']) ?></strong>
                            <span class="ops-rhythm-time"><?= e($block['time']) ?></span>
                        </div>
                        <p class="ops-rhythm-focus"><?= e($block['focus']) ?></p>
                        <ul class="ops-rhythm-steps">
                            <?php foreach ($block['steps'] as $step): ?>
                                <li><?= e($step) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── 每日内容运营清单 ───────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>✅ 每日内容运营清单</h2>
        <p>每篇文章从草稿到发布后跟进的标准动作。可打印或作为发布前自检。</p>
        <div class="ops-checklist-grid">
            <?php foreach ($dailyChecklist as $phase => $items): ?>
                <div class="ops-checklist-col">
                    <h3><?= e($phase) ?></h3>
                    <ul class="ops-checklist">
                        <?php foreach ($items as $item): ?>
                            <li>
                                <label>
                                    <input type="checkbox" class="ops-check">
                                    <span><?= e($item) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ops-checklist-note"><small>勾选状态仅供当前页面自检使用，不会保存到服务器。</small></p>
    </section>

    <!-- ── 每周节奏 ───────────────────────────────────────────────────────── -->
    <section class="newsletter-block">
        <h2>📅 每周内容节奏</h2>
        <p>一个建议的周选题分布，让栏目覆盖更均衡、读者预期更稳定。</p>
        <div class="ops-week-grid">
            <?php foreach ($weeklyRhythm as $day): ?>
                <div class="ops-week-card">
                    <strong><?= e($day['day']) ?></strong>
                    <p><?= e($day['focus']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>
