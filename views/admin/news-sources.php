<?php
$pageTitle = '新闻源 - 钱潮 Money Tide';
$catName = [];
foreach ($adminCategories as $c) {
    $catName[(string) $c['slug']] = (string) $c['name'];
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">自动新闻 · 摄取</p>
            <h1>新闻源</h1>
            <p>配置合法 RSS / Atom 源。系统按栏目抓取标题与摘要作为 AI 原创合成的素材 —— 绝不照搬原文。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/news-items')) ?>">已抓取条目</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── Stats hero ─────────────────────────────────────────────────────── -->
    <div class="news-stat-grid">
        <div class="news-stat"><span>新闻源</span><strong><?= e((string) $summary['sources_active']) ?>/<?= e((string) $summary['sources_total']) ?></strong><small>启用 / 总数</small></div>
        <div class="news-stat"><span>已抓取条目</span><strong><?= e((string) $summary['items_total']) ?></strong><small>累计</small></div>
        <div class="news-stat news-stat-accent"><span>待处理</span><strong><?= e((string) $summary['items_new']) ?></strong><small>status=new</small></div>
        <div class="news-stat"><span>今日新增</span><strong><?= e((string) $summary['items_today']) ?></strong><small>今天抓取</small></div>
        <div class="news-stat"><span>上次抓取</span><strong class="news-stat-time"><?= $summary['last_fetched_at'] ? e(date('m-d H:i', strtotime((string) $summary['last_fetched_at']))) : '—' ?></strong><small>最近一次</small></div>
    </div>

    <!-- ── Fetch action bar ───────────────────────────────────────────────── -->
    <div class="news-action-bar">
        <form method="post" action="<?= e(url('admin/news-sources')) ?>" class="news-fetch-form" data-news-fetch>
            <input type="hidden" name="action" value="fetch">
            <select name="category_slug" aria-label="抓取范围">
                <option value="">全部栏目</option>
                <?php foreach ($adminCategories as $c): ?>
                    <option value="<?= e((string) $c['slug']) ?>"><?= e((string) $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button" type="submit" data-news-fetch-btn>
                <span class="news-fetch-label">⚡ 立即抓取</span>
                <span class="news-fetch-spinner" hidden></span>
            </button>
        </form>
        <form method="post" action="<?= e(url('admin/news-sources')) ?>">
            <input type="hidden" name="action" value="seed">
            <button class="button button-ghost" type="submit"><?= $summary['sources_total'] === 0 ? '📥 载入默认新闻源' : '➕ 补充默认新闻源' ?></button>
        </form>
    </div>

    <!-- ── Fetch result detail ────────────────────────────────────────────── -->
    <?php if (!empty($fetchSummary)): ?>
        <section class="news-fetch-result">
            <h2>本次抓取结果</h2>
            <div class="admin-table">
                <div class="admin-table-row admin-table-head"><span>栏目</span><span>来源</span><span>状态</span></div>
                <?php foreach ($fetchSummary['details'] as $d): ?>
                    <div class="admin-table-row <?= $d['ok'] ? '' : 'smoke-row-fail' ?>">
                        <span><?= e($catName[$d['category']] ?? $d['category']) ?></span>
                        <strong><?= e($d['name']) ?></strong>
                        <span><mark class="<?= $d['ok'] ? 'status-ok' : 'status-warn' ?>"><?= e($d['message']) ?></mark></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── Add / edit source ──────────────────────────────────────────────── -->
    <form class="cms-form" method="post" action="<?= e(url('admin/news-sources')) ?>">
        <input type="hidden" name="id" value="<?= e((string) ($form['id'] ?? '')) ?>">
        <input type="hidden" name="action" value="save">
        <div class="cms-form-grid">
            <label>
                名称
                <input type="text" name="name" value="<?= e((string) $form['name']) ?>" placeholder="例如：CNBC · Markets" required>
            </label>
            <label>
                栏目
                <select name="category_slug" required>
                    <option value="">请选择</option>
                    <?php foreach ($adminCategories as $c): ?>
                        <option value="<?= e((string) $c['slug']) ?>" <?= ($form['category_slug'] ?? '') === $c['slug'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                可信度
                <select name="credibility">
                    <?php foreach ($credibilityOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($form['credibility'] ?? 'standard') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>
            Feed URL（RSS / Atom）
            <input type="url" name="feed_url" value="<?= e((string) $form['feed_url']) ?>" placeholder="https://example.com/rss" required>
            <small>必须是公开 RSS/Atom 源；我们只读取标题与摘要用于 AI 合成，不抓取或转载正文。</small>
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>>
            <span>启用（参与每日抓取）</span>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit"><?= ($form['id'] ?? '') !== '' ? '更新新闻源' : '添加新闻源' ?></button>
        </div>
    </form>

    <!-- ── Sources list ───────────────────────────────────────────────────── -->
    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/news-sources')) ?>">
        <select name="category_slug">
            <option value="">全部栏目</option>
            <?php foreach ($adminCategories as $c): ?>
                <option value="<?= e((string) $c['slug']) ?>" <?= (string) ($_GET['category_slug'] ?? '') === (string) $c['slug'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button button-small">筛选</button>
    </form>

    <div class="admin-table news-source-table">
        <div class="admin-table-row admin-table-head">
            <span>新闻源</span><span>栏目</span><span>可信度</span><span>上次抓取</span><span>操作</span>
        </div>
        <?php foreach ($sources as $src): ?>
            <div class="admin-table-row <?= empty($src['is_active']) ? 'news-source-off' : '' ?>">
                <div>
                    <strong><?= e((string) $src['name']) ?> <?php if (empty($src['is_active'])): ?><span class="news-off-pill">已停用</span><?php endif; ?></strong>
                    <small><a href="<?= e((string) $src['feed_url']) ?>" target="_blank" rel="noopener"><?= e(mb_strimwidth((string) $src['feed_url'], 0, 60, '…', 'UTF-8')) ?></a></small>
                    <?php if (!empty($src['last_status'])): ?><small class="news-last-status"><?= e((string) $src['last_status']) ?></small><?php endif; ?>
                </div>
                <span><?= e($catName[(string) $src['category_slug']] ?? (string) $src['category_slug']) ?></span>
                <span><mark class="cred-<?= e((string) $src['credibility']) ?>"><?= e($credibilityOptions[$src['credibility']] ?? $src['credibility']) ?></mark></span>
                <span><?= $src['last_fetched_at'] ? e(date('m-d H:i', strtotime((string) $src['last_fetched_at']))) : '<small>从未</small>' ?></span>
                <div class="admin-row-actions">
                    <form method="post" action="<?= e(url('admin/news-sources')) ?>" class="inline-action">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= e((string) $src['id']) ?>">
                        <button type="submit" class="link-button"><?= empty($src['is_active']) ? '启用' : '停用' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('admin/news-sources')) ?>" class="inline-action">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string) $src['id']) ?>">
                        <button type="submit" class="link-button is-danger" data-confirm="删除这个新闻源？" data-confirm-sub="已抓取的历史条目不受影响，但这个源不会再参与抓取。" data-confirm-variant="danger" data-confirm-title="删除新闻源" data-confirm-confirm="删除">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$sources): ?>
            <div class="empty-state">
                <strong>还没有新闻源。</strong>
                <p>点击上方「📥 载入默认新闻源」一键导入，或用表单手动添加。</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Cron guide ─────────────────────────────────────────────────────── -->
    <section class="news-cron-guide">
        <h2>⏰ 自动定时抓取（Cron）</h2>
        <p>在 Hostinger hPanel → 高级 → Cron Jobs 添加以下任务，让系统每 2 小时自动抓取（无需手动点击）：</p>
        <code class="monitoring-url">0 */2 * * * /usr/bin/php <?= e($cliPath) ?></code>
        <p><small>也可只抓某个栏目：在命令末尾加栏目 slug，例如 <code>… fetch-news.php markets</code>。该脚本仅允许命令行运行，无法通过网页触发。</small></p>
    </section>
</section>
