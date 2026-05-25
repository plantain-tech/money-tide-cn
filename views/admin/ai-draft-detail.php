<?php
$pageTitle = 'AI 草稿详情 - 钱潮 Money Tide';
$payload = $draft['draft_payload'];
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow"><?= e($templates[$draft['section_slug']]['name'] ?? $draft['section_slug']) ?></p>
            <h1><?= e((string) ($payload['title'] ?? 'AI 草稿')) ?></h1>
            <p><?= e((string) ($payload['dek'] ?? '')) ?></p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">草稿列表</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="form-message form-message-error"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="status-banner is-ready">
        <strong>状态：<?= e($draft['status']) ?></strong>
        <span>AI 草稿不能直接发布，必须先转成 CMS 草稿。</span>
    </div>

    <div class="ai-draft-layout">
        <article class="ai-draft-preview">
            <h2>一句话看懂</h2>
            <p><?= e((string) ($payload['brief'] ?? '')) ?></p>
            <h2>为什么重要</h2>
            <p><?= e((string) ($payload['why_it_matters'] ?? '')) ?></p>
            <h2>正文草稿</h2>
            <?php foreach (($payload['body'] ?? []) as $paragraph): ?>
                <p><?= e((string) $paragraph) ?></p>
            <?php endforeach; ?>
            <h2>Newsletter blurb</h2>
            <p><?= e((string) ($payload['newsletter_blurb'] ?? '')) ?></p>
            <h2>社交标题</h2>
            <p><?= e((string) ($payload['social_headline'] ?? '')) ?></p>
        </article>

        <aside class="ai-draft-sidebar">
            <h2>来源</h2>
            <?php foreach ($draft['source_links'] as $link): ?>
                <a href="<?= e((string) $link) ?>" target="_blank" rel="noopener"><?= e((string) $link) ?></a>
            <?php endforeach; ?>

            <h2>核查提醒</h2>
            <?php foreach (($payload['source_notes'] ?? []) as $note): ?>
                <p><?= e((string) $note) ?></p>
            <?php endforeach; ?>
            <?php foreach (($payload['risk_notes'] ?? []) as $note): ?>
                <p><?= e((string) $note) ?></p>
            <?php endforeach; ?>
            <p><?= e((string) ($payload['disclaimer'] ?? '')) ?></p>

            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/status')) ?>">
                <input type="hidden" name="status" value="reviewed">
                <button class="button full-width" type="submit">标记已审核</button>
            </form>
            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/convert')) ?>">
                <button class="button full-width" type="submit">转为文章草稿</button>
            </form>
            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/status')) ?>">
                <input type="hidden" name="status" value="rejected">
                <button class="button full-width button-danger" type="submit">拒绝草稿</button>
            </form>
        </aside>
    </div>
</section>
