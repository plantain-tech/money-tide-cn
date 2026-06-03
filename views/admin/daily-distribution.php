<?php
$pageTitle = '今日分发 - 钱潮 Money Tide';
$distItems = $distItems ?? [];
$digestText = $digestText ?? '';
$platform = $platform ?? 'toutiao';
$today = $today ?? date('Y-m-d');

// Platform meta: label, icon, and the publish editor to open in a new tab.
$platforms = [
    'toutiao'     => ['label' => '今日头条', 'icon' => '🟠', 'editor' => 'https://mp.toutiao.com/profile_v4/graphic/publish', 'tip' => '粘贴到头条号图文编辑器，配一张图，发布。'],
    'baijiahao'   => ['label' => '百家号',   'icon' => '🐾', 'editor' => 'https://baijiahao.baidu.com/builder/rc/edit?type=news', 'tip' => '需先实名认证；正式新闻风，注明来源。'],
    'zhihu'       => ['label' => '知乎',     'icon' => '🔵', 'editor' => 'https://zhuanlan.zhihu.com/write', 'tip' => '可发为专栏文章，或当作回答贴到热门问题下。'],
    'xiaohongshu' => ['label' => '小红书',   'icon' => '📕', 'editor' => 'https://creator.xiaohongshu.com/publish/publish', 'tip' => '配 9 宫格图或封面卡；标题短、多 emoji。'],
    'xueqiu'      => ['label' => '雪球',     'icon' => '❄️', 'editor' => 'https://xueqiu.com/', 'tip' => '当长帖发；带 $股票$ 现金标签进个股讨论页。'],
];
if (!isset($platforms[$platform])) {
    $platform = 'toutiao';
}

// Extract a single copy-ready string for a given platform from a package.
$pkgText = static function (array $packages, string $key): string {
    $p = $packages[$key] ?? null;
    if (!is_array($p)) {
        return '';
    }
    if (!empty($p['text'])) {
        return (string) $p['text'];
    }
    if (!empty($p['blocks'][0]['text'])) {
        return (string) $p['blocks'][0]['text']; // e.g. 雪球 长文
    }
    return '';
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
                        <?php $txt = $pkgText($item['packages'], $key); ?>
                        <div class="dist-block" data-dist-block="<?= e($key) ?>" <?= $key === $platform ? '' : 'hidden' ?>>
                            <div class="dist-block-bar"><span class="dist-charcount"><?= (int) mb_strlen($txt, 'UTF-8') ?> 字</span></div>
                            <textarea class="dist-text" rows="8" readonly data-dist-text><?= e($txt) ?></textarea>
                            <button class="button button-small dist-copy" type="button" data-dist-copy><span aria-hidden="true">📋</span> 复制</button>
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
            var block = btn.closest('[data-dist-block]');
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
