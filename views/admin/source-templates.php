<?php $pageTitle = '输入模板 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">研究台</p>
            <h1>来源输入模板</h1>
            <p>预设栏目 + 选题角度 + 常用来源，加速 AI 草稿生成和研究简报。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/sources')) ?>">来源库</a>
            <a class="ghost-link" href="<?= e(url('admin/research-desk')) ?>">研究简报</a>
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

    <form class="cms-form" method="post" action="<?= e(url('admin/source-templates')) ?>">
        <input type="hidden" name="id" value="<?= e((string) ($form['id'] ?? '')) ?>">
        <div class="cms-form-grid">
            <label>
                模板名称
                <input type="text" name="name" value="<?= e((string) $form['name']) ?>" required>
            </label>
            <label>
                栏目
                <select name="section_slug" required>
                    <option value="">请选择</option>
                    <?php foreach ($botSections as $slug => $bot): ?>
                        <option value="<?= e($slug) ?>" <?= ($form['section_slug'] ?? '') === $slug ? 'selected' : '' ?>><?= e($bot['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>
            选题角度
            <textarea name="topic_angle" rows="3"><?= e((string) $form['topic_angle']) ?></textarea>
        </label>
        <label>
            常用来源链接（每行一个）
            <textarea name="source_links" rows="5"><?= e((string) $form['source_links']) ?></textarea>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit"><?= ($form['id'] ?? '') !== '' ? '更新模板' : '保存模板' ?></button>
        </div>
    </form>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head">
            <span>名称</span><span>栏目</span><span>选题角度</span><span>来源数</span><span>操作</span>
        </div>
        <?php foreach ($templates as $tpl): ?>
            <?php
                $links = trim((string) ($tpl['source_links'] ?? ''));
                $linkCount = $links === '' ? 0 : count(array_filter(preg_split('/\R+/', $links)));
            ?>
            <div class="admin-table-row">
                <div>
                    <strong><?= e((string) $tpl['name']) ?></strong>
                </div>
                <span><?= e((string) ($botSections[$tpl['section_slug']]['name'] ?? $tpl['section_slug'])) ?></span>
                <span><?= e(mb_substr((string) ($tpl['topic_angle'] ?? ''), 0, 60, 'UTF-8')) ?></span>
                <span><?= e((string) $linkCount) ?> 条</span>
                <div class="admin-row-actions">
                    <a href="<?= e(url('admin/research-desk/new?template_id=' . $tpl['id'])) ?>">用于研究</a>
                    <form method="post" action="<?= e(url('admin/source-templates')) ?>" class="inline-action">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string) $tpl['id']) ?>">
                        <button type="submit" class="link-button is-danger" onclick="return confirm('删除这个模板？')">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$templates): ?>
            <div class="empty-state">
                <strong>还没有模板。</strong>
                <p>给每个栏目准备一两个常用模板，研究台会快很多。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
