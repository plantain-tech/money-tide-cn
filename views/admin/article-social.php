<?php
$pageTitle = '社交分发 — ' . ($article['title'] ?? '') . ' - 钱潮 Money Tide';
$articleId = (int) ($article['id'] ?? 0);
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">社交分发 · Week 6</p>
            <h1>社交文案工作台</h1>
            <p>把这篇文章打包成微信、小红书、LinkedIn、X 和 newsletter 短推荐。AI 可一键生成每个渠道的版本，编辑做最后润色，然后手动发布。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/edit')) ?>">回到文章</a>
            <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/wechat-export')) ?>">微信版面</a>
            <a class="ghost-link" href="<?= e(url('admin/social')) ?>">全部社交</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="status-banner is-ready">
        <strong><?= e((string) $article['title']) ?></strong>
        <span><?= e((string) ($article['dek'] ?? '')) ?> · 今日 AI: <?= e((string) $aiUsage['used_today']) ?>/<?= e((string) $aiUsage['daily_limit']) ?></span>
    </div>

    <div class="social-channel-grid">
        <?php foreach ($channels as $channelKey => $meta): ?>
            <?php
                $post = $posts[$channelKey] ?? null;
                $status = $post ? (string) $post['status'] : 'draft';
                $content = (string) ($post['content'] ?? '');
                $hashtags = (string) ($post['hashtags'] ?? '');
                $note = (string) ($post['note'] ?? '');
                $scheduledAt = !empty($post['scheduled_at']) ? date('Y-m-d\TH:i', strtotime((string) $post['scheduled_at'])) : '';
                $scheduleLabel = !empty($post['scheduled_at']) ? date('Y-m-d H:i', strtotime((string) $post['scheduled_at'])) : '未排期';
                $generatedBy = (string) ($post['generated_by'] ?? '');
                $hasContent = trim($content) !== '';
                $contentLen = mb_strlen($content, 'UTF-8');
                $overLimit = $contentLen > (int) $meta['limit'];
            ?>
            <article id="ch-<?= e($channelKey) ?>" class="social-channel-card <?= $hasContent ? 'has-content' : 'is-empty' ?>" data-channel="<?= e($channelKey) ?>">
                <header class="social-card-head">
                    <div>
                        <p class="eyebrow"><?= e($channelKey) ?></p>
                        <h2><?= e((string) $meta['label']) ?></h2>
                        <small><?= e((string) $meta['hint']) ?></small>
                    </div>
                    <span class="social-status-chip is-<?= e($status) ?>"><?= e($statusOptions[$status] ?? $status) ?></span>
                </header>
                <div class="social-schedule-strip">
                    <span><?= e($scheduleLabel) ?></span>
                    <small>仅作人工提醒，不会自动发布。</small>
                </div>

                <div class="social-card-body">
                    <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/social/' . $channelKey . '/save')) ?>" class="social-content-form" data-social-form>
                        <label class="social-field">
                            <span class="social-field-label">
                                文案
                                <small class="social-counter <?= $overLimit ? 'is-over' : '' ?>">
                                    <span data-char-count><?= e((string) $contentLen) ?></span> / <?= e((string) $meta['limit']) ?>
                                </small>
                            </span>
                            <textarea name="content" rows="6" data-char-input data-char-limit="<?= e((string) $meta['limit']) ?>" placeholder="<?= e('生成后或手动填入 ' . $meta['label'] . ' 文案') ?>"><?= e($content) ?></textarea>
                        </label>

                        <?php if (in_array($channelKey, ['xiaohongshu', 'twitter', 'linkedin'], true)): ?>
                            <label class="social-field">
                                <span class="social-field-label">Hashtags（空格分隔）</span>
                                <input type="text" name="hashtags" value="<?= e($hashtags) ?>" placeholder="例如：#美联储 #市场 #宏观">
                            </label>
                        <?php endif; ?>

                        <label class="social-field">
                            <span class="social-field-label">内部备注（可选）</span>
                            <input type="text" name="note" value="<?= e($note) ?>" placeholder="发布平台账号、计划时间、责任编辑…">
                        </label>

                        <label class="social-field">
                            <span class="social-field-label">计划发布时间（人工发布）</span>
                            <input type="datetime-local" name="scheduled_at" value="<?= e($scheduledAt) ?>">
                        </label>

                        <div class="social-card-actions">
                            <button class="button button-small" type="submit">保存</button>
                            <button class="link-button" type="button" data-copy-target="content" data-copy-channel="<?= e($channelKey) ?>">复制文案</button>
                            <?php if ($hasContent && in_array($channelKey, ['xiaohongshu', 'twitter', 'linkedin'], true)): ?>
                                <button class="link-button" type="button" data-copy-target="full" data-copy-channel="<?= e($channelKey) ?>">复制文案+hashtags</button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="social-card-foot">
                        <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/social/' . $channelKey . '/generate')) ?>"
                              data-ai-progress
                              data-ai-progress-title="正在为「<?= e((string) $meta['label']) ?>」生成文案"
                              data-ai-progress-phases='["正在读取文章内容","正在拼装渠道风格指令","正在调用社交编辑模型","模型正在产出文案","正在保存到 social_posts","即将刷新页面"]'>
                            <button type="submit" class="button button-small button-ghost"
                                    <?= $hasContent ? 'data-confirm="重新生成会覆盖当前文案。继续？" data-confirm-sub="如果你已经手动改过，可以先复制下来。" data-confirm-title="重新生成" data-confirm-confirm="重新生成"' : '' ?>>
                                <?= $hasContent ? 'AI 重新生成' : 'AI 生成文案' ?>
                            </button>
                        </form>

                        <?php if ($hasContent): ?>
                            <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/social/' . $channelKey . '/status')) ?>" class="inline-action">
                                <select name="status" onchange="this.form.submit()">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/social/' . $channelKey . '/delete')) ?>" class="inline-action">
                                <button type="submit" class="link-button is-danger"
                                        data-confirm="删除「<?= e((string) $meta['label']) ?>」渠道的文案？"
                                        data-confirm-sub="只删除该渠道的文案，不会动文章本身。"
                                        data-confirm-variant="danger"
                                        data-confirm-title="删除社交文案"
                                        data-confirm-confirm="删除">删除</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($generatedBy === 'ai' && !empty($post['generated_at'])): ?>
                        <small class="social-ai-stamp">AI 生成于 <?= e(date('Y-m-d H:i', strtotime((string) $post['generated_at']))) ?></small>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function () {
    // Live character counters
    document.querySelectorAll('textarea[data-char-input]').forEach(function (el) {
        var limit = parseInt(el.getAttribute('data-char-limit') || '0', 10);
        var counter = el.closest('.social-field').querySelector('[data-char-count]');
        var box = el.closest('.social-field').querySelector('.social-counter');
        el.addEventListener('input', function () {
            var n = el.value.length;
            if (counter) counter.textContent = String(n);
            if (box) box.classList.toggle('is-over', limit > 0 && n > limit);
        });
    });

    // Copy-to-clipboard
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.social-channel-card');
            if (!card) return;
            var content = card.querySelector('textarea[name="content"]');
            var hashtags = card.querySelector('input[name="hashtags"]');
            var target = btn.getAttribute('data-copy-target');
            var text = content ? content.value : '';
            if (target === 'full' && hashtags && hashtags.value.trim() !== '') {
                text = text.trim() + '\n\n' + hashtags.value.trim();
            }
            if (!text) return;
            var done = function () {
                var original = btn.textContent;
                btn.textContent = '已复制 ✓';
                setTimeout(function () { btn.textContent = original; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () {
                    fallbackCopy(text);
                    done();
                });
            } else {
                fallbackCopy(text);
                done();
            }
        });
    });

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
})();
</script>
