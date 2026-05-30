<?php
$pageTitle = '微信版面 — ' . ($article['title'] ?? '') . ' - 钱潮 Money Tide';
$articleId = (int) ($article['id'] ?? 0);
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 6 周 · 微信导出</p>
            <h1>微信版面预览</h1>
            <p>用微信公众号编辑器粘贴前的最后一步。下面是干净 HTML，行内样式，复制粘贴即可。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/edit')) ?>">回到文章</a>
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/social')) ?>">社交分发</a>
        </div>
    </div>

    <div class="status-banner is-ready">
        <strong>微信导出说明</strong>
        <span>"复制 HTML" 适合粘贴到公众号编辑器的源代码模式；"复制文本" 适合粘贴到富文本编辑器（会丢失部分样式）。</span>
    </div>

    <div class="wechat-export-actions">
        <button class="button" type="button" id="wechat-copy-html">复制 HTML</button>
        <button class="button button-ghost" type="button" id="wechat-copy-text">复制纯文本</button>
        <button class="button button-ghost" type="button" id="wechat-open-source">查看源代码</button>
    </div>

    <details class="wechat-source-block" id="wechat-source-block">
        <summary>HTML 源码（点击展开）</summary>
        <textarea readonly id="wechat-source-text" rows="14"><?= e($wechatHtml) ?></textarea>
    </details>

    <div class="wechat-preview-frame">
        <div class="wechat-preview-inner" id="wechat-preview-inner"><?= $wechatHtml ?></div>
    </div>
</section>

<script>
(function () {
    var sourceText = document.getElementById('wechat-source-text');
    var previewInner = document.getElementById('wechat-preview-inner');
    var htmlBtn = document.getElementById('wechat-copy-html');
    var textBtn = document.getElementById('wechat-copy-text');
    var sourceBlock = document.getElementById('wechat-source-block');
    var openSourceBtn = document.getElementById('wechat-open-source');

    openSourceBtn && openSourceBtn.addEventListener('click', function () {
        sourceBlock.open = !sourceBlock.open;
    });

    function flash(btn) {
        var orig = btn.textContent;
        btn.textContent = '已复制 ✓';
        setTimeout(function () { btn.textContent = orig; }, 1400);
    }

    function copyText(value, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(function () { flash(btn); }, function () { fallbackCopy(value); flash(btn); });
        } else {
            fallbackCopy(value);
            flash(btn);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (_) {}
        document.body.removeChild(ta);
    }

    htmlBtn && htmlBtn.addEventListener('click', function () {
        copyText(sourceText ? sourceText.value : '', htmlBtn);
    });

    textBtn && textBtn.addEventListener('click', function () {
        // Plain text from the rendered preview
        var text = previewInner ? (previewInner.innerText || previewInner.textContent || '') : '';
        copyText(text.trim(), textBtn);
    });
})();
</script>
