<?php
$pageTitle = '今日分发 - 钱潮 Money Tide';
$distItems = $distItems ?? [];
$digestText = $digestText ?? '';
$platform = $platform ?? 'toutiao';
$today = $today ?? date('Y-m-d');

// Platform meta: label, icon, and the publish editor to open in a new tab.
// Ordered easy→hard for a phone-only (no Chinese ID) account: 知乎 leads, the
// ID-gated 百家号 is last and flagged.
$platforms = [
    'x'           => ['label' => 'X / Twitter', 'icon' => '𝕏', 'editor' => 'https://x.com/compose/post', 'tip' => '手动发推（免费）：复制 → 到 X 粘贴发布。中文约 140 字内，配图更佳。简介放 Telegram 链接引流。'],
    'reddit'      => ['label' => 'Reddit', 'icon' => '👽', 'editor' => 'https://www.reddit.com/submit', 'tip' => '正文不放链接，发完后把「首条评论」里的链接贴到评论区（9:1 原则）。最适合 r/China_irl；先攒 karma 再发帖。'],
    'zhihu'       => ['label' => '知乎',     'icon' => '🔵', 'editor' => 'https://zhuanlan.zhihu.com/write', 'tip' => '手机号即可发，无需实名。可发为专栏文章，更要去热门财经问题下贴「回答」——借现成流量最快涨粉。'],
    'xiaohongshu' => ['label' => '小红书',   'icon' => '📕', 'editor' => 'https://creator.xiaohongshu.com/publish/publish', 'tip' => '手机号即可发。配 9 宫格图或封面卡；标题短、多 emoji。'],
    'xueqiu'      => ['label' => '雪球',     'icon' => '❄️', 'editor' => 'https://xueqiu.com/', 'tip' => '手机号即可发。当长帖发；带 $股票$ 现金标签进个股讨论页（新号先养几天再放链接）。'],
    'toutiao'     => ['label' => '今日头条', 'icon' => '🟠', 'editor' => 'https://mp.toutiao.com/profile_v4/graphic/publish', 'tip' => '无实名时先发「微头条」（短帖可发）；完整图文文章一般需实名认证。配一张图反响更好。'],
    'baijiahao'   => ['label' => '百家号',   'icon' => '🐾', 'editor' => 'https://baijiahao.baidu.com/builder/rc/edit?type=news', 'tip' => '⚠️ 需中国身份证实名认证，无 ID 暂时跳过。'],
];
if (!isset($platforms[$platform])) {
    $platform = 'x';
}

// Return ALL copy-ready blocks for a platform: [['label'=>.., 'text'=>..], ...].
// Single-text platforms become one unlabeled block; multi-block ones (雪球/Reddit)
// return every block so the whole post is copyable here too.
$pkgBlocks = static function (array $packages, string $key): array {
    $p = $packages[$key] ?? null;
    if (!is_array($p)) {
        return [];
    }
    if (!empty($p['blocks']) && is_array($p['blocks'])) {
        return $p['blocks'];
    }
    if (isset($p['text']) && trim((string) $p['text']) !== '') {
        return [['label' => '', 'text' => (string) $p['text']]];
    }
    return [];
};
$total = count($distItems) + ($digestText !== '' ? 1 : 0);
?>
<section class="admin-shell dist-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">一键分发 · 今日批量</p>
            <h1>📤 今日分发</h1>
            <p>把今天 AI 已发布的文章 + 早报，<strong>在一个屏幕里</strong>按平台排好版、逐条复制粘贴去发。<?= e($today) ?> · 共 <strong><?= (int) $total ?></strong> 条。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="button button-small" id="dist-editor-link" href="<?= e($platforms[$platform]['editor']) ?>" target="_blank" rel="noopener">打开<?= e($platforms[$platform]['label']) ?>编辑器 ↗</a>
        </div>
    </div>

    <?php if ($total === 0): ?>
        <div class="status-banner is-warning ap-flash">
            <strong>今天还没有已发布的内容。</strong>
            <span>等流水线发布后再来，或到 <a href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a> 手动跑一次。</span>
        </div>
    <?php else: ?>
        <!-- Platform switcher -->
        <div class="dist-platforms" role="tablist" data-dist-platforms>
            <?php foreach ($platforms as $key => $meta): ?>
                <button class="dist-platform <?= $key === $platform ? 'is-active' : '' ?>" type="button"
                    role="tab" data-dist-platform="<?= e($key) ?>"
                    data-editor="<?= e($meta['editor']) ?>" data-label="<?= e($meta['label']) ?>">
                    <span class="dist-platform-ic"><?= e($meta['icon']) ?></span>
                    <span><?= e($meta['label']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Progress -->
        <div class="dist-progress-wrap">
            <div class="dist-tip" data-dist-tip>💡 <?= e($platforms[$platform]['tip']) ?></div>
            <div class="dist-progress">
                <div class="dist-progress-bar"><span class="dist-progress-fill" data-dist-fill style="width:0%"></span></div>
                <span class="dist-progress-label" data-dist-count>今日已发 0 / <?= (int) $total ?></span>
            </div>
        </div>

        <div class="dist-grid" data-dist-grid data-today="<?= e($today) ?>">
            <?php $cardIndex = 0; ?>
            <?php if ($digestText !== ''): ?>
                <article class="dist-card dist-card-digest" data-dist-card data-dist-id="digest" style="--i:<?= $cardIndex ?>">
                    <header class="dist-card-head">
                        <span class="dist-card-tag is-digest">📰 今日早报合集</span>
                        <label class="dist-posted-toggle"><input type="checkbox" data-dist-posted> <span>已发</span></label>
                    </header>
                    <h3>今天的 <?= (int) count($distItems) ?> 条要闻，一帖打包</h3>
                    <div class="dist-block" data-dist-block="digest">
                        <div class="dist-block-bar"><span class="dist-charcount"><?= (int) mb_strlen($digestText, 'UTF-8') ?> 字</span></div>
                        <textarea class="dist-text" rows="7" readonly data-dist-text><?= e($digestText) ?></textarea>
                        <button class="button button-small dist-copy" type="button" data-dist-copy><span aria-hidden="true">📋</span> 复制</button>
                    </div>
                </article>
                <?php $cardIndex++; ?>
            <?php endif; ?>

            <?php foreach ($distItems as $item): ?>
                <article class="dist-card" data-dist-card data-dist-id="<?= (int) $item['id'] ?>" style="--i:<?= $cardIndex ?>">
                    <header class="dist-card-head">
                        <span class="dist-card-tag"><?= e($item['category']) ?></span>
                        <label class="dist-posted-toggle"><input type="checkbox" data-dist-posted> <span>已发</span></label>
                    </header>
                    <h3><a href="<?= e(url('article/' . $item['slug'])) ?>" target="_blank" rel="noopener"><?= e($item['title']) ?></a></h3>
                    <?php foreach ($platforms as $key => $meta): ?>
                        <?php $blocks = $pkgBlocks($item['packages'], $key); ?>
                        <div class="dist-block" data-dist-block="<?= e($key) ?>" <?= $key === $platform ? '' : 'hidden' ?>>
                            <?php foreach ($blocks as $blk): ?>
                                <?php $btxt = (string) ($blk['text'] ?? ''); $blabel = (string) ($blk['label'] ?? ''); ?>
                                <div class="dist-subblock" data-dist-subblock>
                                    <div class="dist-block-bar">
                                        <?php if ($blabel !== ''): ?><span class="dist-subblock-label"><?= e($blabel) ?></span><?php endif; ?>
                                        <span class="dist-charcount"><?= (int) mb_strlen($btxt, 'UTF-8') ?> 字</span>
                                    </div>
                                    <textarea class="dist-text" rows="<?= $blabel !== '' && mb_strlen($btxt, 'UTF-8') < 160 ? 4 : 8 ?>" readonly data-dist-text><?= e($btxt) ?></textarea>
                                    <button class="button button-small dist-copy" type="button" data-dist-copy><span aria-hidden="true">📋</span> 复制</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </article>
                <?php $cardIndex++; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    var grid = document.querySelector('[data-dist-grid]');
    if (!grid) return;
    var today = grid.getAttribute('data-today') || '';
    var platform = '<?= e($platform) ?>';
    var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-dist-card]'));
    var fill = document.querySelector('[data-dist-fill]');
    var countEl = document.querySelector('[data-dist-count]');
    var tipEl = document.querySelector('[data-dist-tip]');
    var editorLink = document.getElementById('dist-editor-link');
    var tips = <?= json_encode(array_map(static fn ($m) => $m['tip'], $platforms), JSON_UNESCAPED_UNICODE) ?>;
    var total = cards.length;

    function key(id) { return 'mtDist_' + today + '_' + platform + '_' + id; }
    function isPosted(id) { try { return localStorage.getItem(key(id)) === '1'; } catch (e) { return false; } }
    function setPosted(id, v) { try { v ? localStorage.setItem(key(id), '1') : localStorage.removeItem(key(id)); } catch (e) {} }

    function refreshProgress() {
        var done = 0;
        cards.forEach(function (c) {
            var id = c.getAttribute('data-dist-id');
            var posted = isPosted(id);
            c.classList.toggle('is-posted', posted);
            var cb = c.querySelector('[data-dist-posted]');
            if (cb) cb.checked = posted;
            if (posted) done++;
        });
        var pct = total > 0 ? Math.round(done / total * 100) : 0;
        if (fill) fill.style.width = pct + '%';
        if (countEl) countEl.textContent = '今日已发 ' + done + ' / ' + total;
    }

    // Platform switch
    document.querySelectorAll('[data-dist-platform]').forEach(function (pill) {
        pill.addEventListener('click', function () {
            platform = pill.getAttribute('data-dist-platform');
            document.querySelectorAll('[data-dist-platform]').forEach(function (p) { p.classList.toggle('is-active', p === pill); });
            cards.forEach(function (c) {
                c.querySelectorAll('[data-dist-block]').forEach(function (b) {
                    var k = b.getAttribute('data-dist-block');
                    // digest block has no per-platform variants — always visible
                    if (k === 'digest') { b.hidden = false; return; }
                    b.hidden = (k !== platform);
                });
            });
            if (editorLink) {
                editorLink.href = pill.getAttribute('data-editor');
                editorLink.textContent = '打开' + pill.getAttribute('data-label') + '编辑器 ↗';
            }
            if (tipEl && tips[platform]) tipEl.textContent = '💡 ' + tips[platform];
            refreshProgress();
        });
    });

    // Posted toggles
    cards.forEach(function (c) {
        var cb = c.querySelector('[data-dist-posted]');
        if (cb) cb.addEventListener('change', function () {
            setPosted(c.getAttribute('data-dist-id'), cb.checked);
            refreshProgress();
        });
    });

    // Copy (active block of the card) + auto-mark posted
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
    grid.querySelectorAll('[data-dist-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var block = btn.closest('[data-dist-subblock]') || btn.closest('[data-dist-block]');
            var ta = block ? block.querySelector('[data-dist-text]') : null;
            var text = ta ? ta.value : '';
            if (!text) return;
            var done = function () {
                var orig = btn.innerHTML;
                btn.innerHTML = '✓ 已复制';
                btn.classList.add('is-copied');
                setTimeout(function () { btn.innerHTML = orig; btn.classList.remove('is-copied'); }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
            } else { fallbackCopy(text); done(); }
        });
    });

    refreshProgress();
})();
</script>
