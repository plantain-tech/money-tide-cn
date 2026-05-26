<?php $pageTitle = '来源库 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">研究台</p>
            <h1>来源库</h1>
            <p>保存常用来源，并标记可信度。AI 草稿和研究简报会按栏目挑选来源。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/source-templates')) ?>">输入模板</a>
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

    <form class="cms-form" method="post" action="<?= e(url('admin/sources')) ?>">
        <input type="hidden" name="id" value="<?= e((string) ($form['id'] ?? '')) ?>">
        <div class="cms-form-grid">
            <label>
                名称
                <input type="text" name="name" value="<?= e((string) $form['name']) ?>" required>
            </label>
            <label>
                栏目
                <select name="section_slug">
                    <option value="">通用</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat['slug']) ?>" <?= ($form['section_slug'] ?? '') === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
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
            URL
            <input type="url" name="url" value="<?= e((string) $form['url']) ?>" placeholder="https://..." required>
        </label>
        <label>
            备注
            <textarea name="notes" rows="2"><?= e((string) ($form['notes'] ?? '')) ?></textarea>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit"><?= ($form['id'] ?? '') !== '' ? '更新来源' : '保存来源' ?></button>
        </div>
    </form>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/sources')) ?>">
        <input type="search" name="q" placeholder="名称或 URL" value="<?= e($filters['q']) ?>">
        <select name="section_slug">
            <option value="">全部栏目</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat['slug']) ?>" <?= $filters['section_slug'] === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="credibility">
            <option value="">全部可信度</option>
            <?php foreach ($credibilityOptions as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $filters['credibility'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button button-small">筛选</button>
    </form>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head">
            <span>名称</span><span>栏目</span><span>可信度</span><span>更新</span><span>操作</span>
        </div>
        <?php foreach ($sources as $src): ?>
            <div class="admin-table-row">
                <div>
                    <strong><?= e((string) $src['name']) ?></strong>
                    <small><a href="<?= e((string) $src['url']) ?>" target="_blank" rel="noopener"><?= e((string) $src['url']) ?></a></small>
                    <?php if (!empty($src['notes'])): ?><small><?= e((string) $src['notes']) ?></small><?php endif; ?>
                </div>
                <span><?= e((string) ($src['section_slug'] ?: '通用')) ?></span>
                <span><mark><?= e($credibilityOptions[$src['credibility']] ?? $src['credibility']) ?></mark></span>
                <span><?= e(date('Y-m-d', strtotime((string) $src['updated_at']))) ?></span>
                <div class="admin-row-actions">
                    <form method="post" action="<?= e(url('admin/sources')) ?>" class="inline-action">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string) $src['id']) ?>">
                        <button type="submit" class="link-button is-danger" onclick="return confirm('删除这条来源？')">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$sources): ?>
            <div class="empty-state">
                <strong>来源库还是空的。</strong>
                <p>使用上面的表单添加第一条来源。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
