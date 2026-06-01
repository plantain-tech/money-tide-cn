<?php $pageTitle = '会员与变现 - 钱潮 Money Tide'; $settings = $summary['settings'] ?? []; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">会员变现</p>
            <h1>会员与变现准备</h1>
            <p>当前只做 premium 标记和软付费墙结构，不阻止任何读者阅读。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">文章管理</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="admin-stat-grid">
        <div><span>Premium 文章</span><strong><?= e((string) ($summary['premium_articles'] ?? 0)) ?></strong></div>
        <?php foreach (($summary['tiers'] ?? []) as $tier => $total): ?>
            <div><span><?= e((string) $tier) ?> readers</span><strong><?= e((string) $total) ?></strong></div>
        <?php endforeach; ?>
    </div>

    <form class="cms-form" method="post" action="<?= e(url('admin/monetization')) ?>">
        <input type="hidden" name="section" value="premium">
        <label>
            付费墙模式
            <select name="paywall_mode">
                <option value="soft_preview" <?= ($settings['paywall_mode'] ?? '') === 'soft_preview' ? 'selected' : '' ?>>Soft preview：显示提示但不拦截阅读</option>
                <option value="off" <?= ($settings['paywall_mode'] ?? '') === 'off' ? 'selected' : '' ?>>Off：不显示提示</option>
            </select>
        </label>
        <label>
            Premium 标签
            <input type="text" name="premium_label" value="<?= e((string) ($settings['premium_label'] ?? '会员内容')) ?>">
        </label>
        <label>
            会员价格/说明占位
            <textarea name="member_price_note" rows="3"><?= e((string) ($settings['member_price_note'] ?? '')) ?></textarea>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit">保存设置</button>
        </div>
    </form>

    <?php $m = $monetize ?? []; ?>
    <!-- ── Day 10·5: affiliate links + sponsor slot ─────────────────────── -->
    <section class="newsletter-block monetize-block">
        <div class="ops-section-head">
            <h2>💰 联盟链接与赞助位</h2>
            <span class="monetize-flags">
                <span class="monetize-flag <?= !empty($m['affiliate_enabled']) ? 'is-on' : '' ?>">联盟 <?= !empty($m['affiliate_enabled']) ? '开' : '关' ?></span>
                <span class="monetize-flag <?= !empty($m['sponsor_enabled']) ? 'is-on' : '' ?>">赞助 <?= !empty($m['sponsor_enabled']) ? '开' : '关' ?></span>
            </span>
        </div>
        <p>在文章正文里按关键词自动插入<strong>带披露</strong>的联盟链接（每个关键词每篇最多一次），并可在文末渲染一个清晰标注的<strong>原生赞助位</strong>。合规优先：链接带 <code>rel="sponsored nofollow"</code> 与 ↗ 标记，赞助位带「赞助」标签。</p>

        <form class="cms-form" method="post" action="<?= e(url('admin/monetization')) ?>">
            <input type="hidden" name="section" value="monetize">

            <h3 class="monetize-sub">🔗 联盟链接</h3>
            <label class="pa-switch-row"><input type="checkbox" name="affiliate_enabled" value="1" <?= !empty($m['affiliate_enabled']) ? 'checked' : '' ?>> <span>开启联盟链接自动插入</span></label>
            <label>规则（每行一条：<code>关键词 | https://联盟链接 | 可选标签</code>）
                <textarea name="affiliate_rules" rows="5" placeholder="英伟达 | https://aff.example.com/nvidia | 合作券商&#10;比特币 ETF | https://aff.example.com/btc-etf"><?= e((string) ($rulesText ?? '')) ?></textarea>
            </label>
            <div class="cms-form-grid">
                <label>每篇最多链接数<input type="number" name="affiliate_max" min="1" max="10" value="<?= e((string) ($m['affiliate_max'] ?? 3)) ?>"></label>
            </div>
            <label>披露声明（显示在含联盟链接文章的正文顶部）
                <textarea name="affiliate_disclosure" rows="2"><?= e((string) ($m['affiliate_disclosure'] ?? '')) ?></textarea>
            </label>

            <h3 class="monetize-sub">🎯 原生赞助位</h3>
            <label class="pa-switch-row"><input type="checkbox" name="sponsor_enabled" value="1" <?= !empty($m['sponsor_enabled']) ? 'checked' : '' ?>> <span>在文章/早报渲染赞助位</span></label>
            <div class="cms-form-grid">
                <label>赞助标签<input type="text" name="sponsor_label" value="<?= e((string) ($m['sponsor_label'] ?? '赞助 · Sponsored')) ?>"></label>
                <label>赞助商名称<input type="text" name="sponsor_name" value="<?= e((string) ($m['sponsor_name'] ?? '')) ?>" placeholder="例：某券商 / 某交易所"></label>
                <label>按钮文案<input type="text" name="sponsor_cta" value="<?= e((string) ($m['sponsor_cta'] ?? '了解更多')) ?>"></label>
                <label>落地链接<input type="url" name="sponsor_url" value="<?= e((string) ($m['sponsor_url'] ?? '')) ?>" placeholder="https://..."></label>
            </div>
            <label>赞助文案<textarea name="sponsor_blurb" rows="2" placeholder="一句话介绍赞助商，清晰、不夸大。"><?= e((string) ($m['sponsor_blurb'] ?? '')) ?></textarea></label>

            <h3 class="monetize-sub">📢 Google AdSense（仅在批准的版位渲染）</h3>
            <label class="pa-switch-row"><input type="checkbox" name="adsense_enabled" value="1" <?= !empty($m['adsense_enabled']) ? 'checked' : '' ?>> <span>开启 AdSense（仅公开文章页正文内的版位，绝不出现在后台）</span></label>
            <div class="cms-form-grid">
                <label>Publisher ID<input type="text" name="adsense_client" value="<?= e((string) ($m['adsense_client'] ?? '')) ?>" placeholder="ca-pub-xxxxxxxxxxxxxxxx"></label>
                <label>文章版位 Slot ID<input type="text" name="adsense_slot_article" value="<?= e((string) ($m['adsense_slot_article'] ?? '')) ?>" placeholder="如 1234567890"></label>
                <label>目标 RPM（USD/每千次浏览，用于收入估算）<input type="number" step="0.1" min="0" name="target_rpm" value="<?= e((string) ($m['target_rpm'] ?? 0)) ?>"></label>
            </div>

            <div class="cms-form-actions"><button class="button" type="submit">保存广告与联盟设置</button></div>
            <p class="news-action-hint">提示：联盟链接只在正文里命中关键词时插入，并自动加披露；赞助位为空（名称/文案缺失）时不渲染。AdSense 仅在公开文章页的批准版位加载（后台与缩略页绝不出现），并带「广告」标注。三者都清晰披露，符合 AdSense / 广告法要求。</p>
        </form>

        <?php if (function_exists('monetize_sponsor_html') && monetize_sponsor_enabled()): ?>
            <h3 class="monetize-sub">预览</h3>
            <?= monetize_sponsor_html('article') ?>
        <?php endif; ?>
    </section>
</section>
