<?php
$pageTitle = '60秒看懂 — ' . ($article['title'] ?? '') . ' - 钱潮 Money Tide';
$articleId = (int) ($article['id'] ?? 0);
$bullets = $form['bullets'] ?? ['', '', ''];
while (count($bullets) < 3) {
    $bullets[] = '';
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 6 周 · 速读</p>
            <h1>60 秒看懂</h1>
            <p>给这篇文章配一个可跳读、可分享的速读卡：一句话总结、3 个要点、关键数字、为什么重要、风险提示。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/edit')) ?>">回到文章</a>
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/share-cards')) ?>">分享卡片</a>
            <?php if ($article['status'] === 'published'): ?>
                <a class="ghost-link" href="<?= e(url('article/' . $article['slug'] . '#short-format')) ?>" target="_blank" rel="noopener">查看前台</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="status-banner is-ready">
        <strong><?= e((string) $article['title']) ?></strong>
        <span>今日 AI: <?= e((string) $aiUsage['used_today']) ?>/<?= e((string) $aiUsage['daily_limit']) ?></span>
    </div>

    <div class="short-format-admin-actions">
        <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/short-format/generate')) ?>"
              data-ai-progress
              data-ai-progress-title="正在生成 60 秒看懂"
              data-ai-progress-phases='["正在读取文章正文","正在压缩为速读结构","正在调用编辑模型","模型正在产出要点","正在保存速读卡","即将刷新页面"]'>
            <button type="submit" class="button button-small"
                    <?= $shortFormat ? 'data-confirm="重新用 AI 生成会覆盖当前内容。继续？" data-confirm-sub="如已手动编辑，请先复制。" data-confirm-title="重新生成" data-confirm-confirm="重新生成"' : '' ?>>
                <?= $shortFormat ? 'AI 重新生成' : 'AI 生成 60 秒看懂' ?>
            </button>
        </form>
        <?php if ($shortFormat): ?>
            <button type="button" class="button button-small button-ghost" data-export-copy>复制速读文本</button>
            <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/short-format/delete')) ?>" class="inline-action">
                <button type="submit" class="link-button is-danger"
                        data-confirm="删除这篇文章的 60 秒看懂？"
                        data-confirm-variant="danger"
                        data-confirm-title="删除速读卡"
                        data-confirm-confirm="删除">删除</button>
            </form>
        <?php endif; ?>
    </div>

    <form class="cms-form" method="post" action="<?= e(url('admin/articles/' . $articleId . '/short-format')) ?>">
        <label>
            一句话总结
            <input type="text" name="summary" maxlength="280" value="<?= e((string) ($form['summary'] ?? '')) ?>" placeholder="不超过 40 字的核心结论">
        </label>
        <fieldset class="short-format-bullets-fieldset">
            <legend>3 个要点</legend>
            <?php foreach (array_slice($bullets, 0, 3) as $i => $b): ?>
                <input type="text" name="bullets[]" value="<?= e((string) $b) ?>" placeholder="要点 <?= e((string) ($i + 1)) ?>">
            <?php endforeach; ?>
        </fieldset>
        <div class="cms-form-grid">
            <label>
                关键数字
                <input type="text" name="key_number" maxlength="120" value="<?= e((string) ($form['key_number'] ?? '')) ?>" placeholder="例如：5.25% 或 3 倍">
            </label>
        </div>
        <label>
            为什么重要
            <textarea name="why_it_matters" rows="2" maxlength="500"><?= e((string) ($form['why_it_matters'] ?? '')) ?></textarea>
        </label>
        <label>
            风险 / 注意
            <textarea name="risk_note" rows="2" maxlength="500"><?= e((string) ($form['risk_note'] ?? '')) ?></textarea>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit">保存</button>
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/edit')) ?>">取消</a>
        </div>
    </form>

    <?php if ($shortFormat): ?>
        <textarea hidden data-export-text><?= e($exportText) ?></textarea>
    <?php endif; ?>
</section>

<script>
(function () {
    var btn = document.querySelector('[data-export-copy]');
    var src = document.querySelector('[data-export-text]');
    if (!btn || !src) return;
    btn.addEventListener('click', function () {
        var text = src.value;
        var done = function () {
            var o = btn.textContent; btn.textContent = '已复制 ✓';
            setTimeout(function () { btn.textContent = o; }, 1400);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta); done();
        }
    });
})();
</script>
