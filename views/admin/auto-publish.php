<?php
$pageTitle = '发布与早报组装 - 钱潮 Money Tide';
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">自动新闻 · 发布与组装</p>
            <h1>一键发布 + 早报组装</h1>
            <p>把「已批准」的草稿一键发布为正式文章，再按栏目把今天发布的文章自动组装成每个栏目一份早报（状态「可发送」）。组装不等于发送——广播仍是单独的人工步骤。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/review-queue')) ?>">AI 审核台</a>
            <a class="ghost-link" href="<?= e(url('admin/newsletter')) ?>">早报期号</a>
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">文章管理</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <!-- Stats hero -->
    <div class="news-stat-grid">
        <div class="news-stat news-stat-accent"><span>待发布</span><strong><?= e((string) $summary['approved_pending']) ?></strong><small>已批准草稿</small></div>
        <div class="news-stat"><span>今日已发布</span><strong><?= e((string) $summary['published_today']) ?></strong><small>正式文章</small></div>
        <div class="news-stat"><span>今日早报</span><strong><?= e((string) $summary['issues_today']) ?></strong><small>自动组装</small></div>
        <div class="news-stat"><span>累计文章</span><strong><?= e((string) $summary['total_published']) ?></strong><small>全站已发布</small></div>
    </div>

    <!-- Run action -->
    <div class="news-action-bar">
        <form method="post" action="<?= e(url('admin/auto-publish')) ?>" class="news-fetch-form" data-news-fetch>
            <input type="hidden" name="action" value="run">
            <button class="button" type="submit" data-news-fetch-btn>
                <span class="news-fetch-label">🚀 一键发布 + 组装早报</span>
                <span class="news-fetch-spinner" hidden></span>
            </button>
        </form>
        <form method="post" action="<?= e(url('admin/auto-publish')) ?>" class="inline-action">
            <input type="hidden" name="action" value="publish_only">
            <button class="button button-ghost button-small" type="submit">仅发布文章</button>
        </form>
        <form method="post" action="<?= e(url('admin/auto-publish')) ?>" class="inline-action">
            <input type="hidden" name="action" value="assemble_only">
            <button class="button button-ghost button-small" type="submit">仅组装早报</button>
        </form>
        <small class="news-action-hint">「一键」会先发布全部已批准草稿，再为每个栏目组装今日早报。</small>
    </div>

    <!-- Run result -->
    <?php if (!empty($runResult)): ?>
        <?php if (!empty($runResult['publish'])): ?>
            <section class="news-fetch-result">
                <h2>📄 发布结果（<?= e((string) $runResult['publish']['ok']) ?> 篇）</h2>
                <div class="admin-table">
                    <div class="admin-table-row admin-table-head"><span>草稿</span><span>结果</span></div>
                    <?php foreach ($runResult['publish']['details'] as $d): ?>
                        <div class="admin-table-row <?= $d['ok'] ? '' : 'smoke-row-fail' ?>">
                            <strong>草稿 #<?= e((string) $d['draft_id']) ?></strong>
                            <span><mark class="<?= $d['ok'] ? 'status-ok' : 'status-warn' ?>"><?= e($d['message']) ?></mark></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($runResult['publish']['details'])): ?>
                        <div class="admin-table-row"><span>没有待发布的已批准草稿。</span><span></span></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($runResult['publish']['articles'])): ?>
                    <p class="news-action-hint">新文章：
                        <?php foreach ($runResult['publish']['articles'] as $aid): ?>
                            <a class="link-button" href="<?= e(url('admin/articles/' . (int) $aid . '/edit')) ?>">#<?= e((string) (int) $aid) ?></a>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($runResult['assemble'])): ?>
            <section class="news-fetch-result">
                <h2>📰 早报组装结果（<?= e((string) $runResult['assemble']['issues']) ?> 份）</h2>
                <div class="admin-table">
                    <div class="admin-table-row admin-table-head"><span>栏目</span><span>结果</span></div>
                    <?php foreach ($runResult['assemble']['details'] as $d): ?>
                        <div class="admin-table-row <?= $d['ok'] ? '' : '' ?>">
                            <strong><?= e($d['category']) ?></strong>
                            <span>
                                <mark class="<?= $d['ok'] ? 'status-ok' : '' ?>"><?= e($d['message']) ?></mark>
                                <?php if ($d['ok'] && !empty($d['issue_id'])): ?>
                                    <a class="link-button" href="<?= e(url('admin/newsletter/' . (int) $d['issue_id'] . '/edit')) ?>">编辑</a>
                                    <a class="link-button" href="<?= e(url('admin/newsletter/' . (int) $d['issue_id'] . '/preview')) ?>" target="_blank" rel="noopener">预览</a>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Flow note -->
    <section class="news-cron-guide">
        <h2>🔄 这一步在流水线里的位置</h2>
        <p>聚类 → 生成草稿 → <strong>AI 审核（已批准）</strong> → <strong>本页：发布 + 组装早报</strong> → 人工广播（Sprint 2）。</p>
        <p><small>组装好的早报状态为「可发送」，会出现在 <a href="<?= e(url('admin/newsletter/schedule')) ?>">早报排期</a> 里，等待你或后续自动发送流程处理。发送前可在期号编辑页走一遍发送前检查。</small></p>
    </section>
</section>
