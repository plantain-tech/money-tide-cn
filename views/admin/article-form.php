<?php
$pageTitle = ($mode === 'edit' ? '编辑文章' : '新建文章') . ' - 钱潮 Money Tide';
$articleId = (int) ($articleId ?? 0);
$currentStatus = (string) ($currentStatus ?? 'draft');
$checklist = $checklist ?? [];
$flash = (string) ($flash ?? '');
$authors = $authors ?? [];
$editors = $editors ?? [];
$auditLogs = $auditLogs ?? [];
$seoChecklist = $seoChecklist ?? [];
$statusLabels = ['draft' => '草稿', 'review' => '审核', 'published' => '已发布', 'archived' => '已归档'];
$transitions = [
    'draft' => [['review', '提交审核']],
    'review' => [['draft', '退回草稿'], ['published', '发布上线']],
    'published' => [['archived', '归档下线']],
    'archived' => [['draft', '恢复为草稿']],
];
$permissionArticle = [
    'id' => $articleId,
    'status' => $currentStatus,
    'created_by_user_id' => $form['created_by_user_id'] ?? null,
];
$nextSteps = array_values(array_filter($transitions[$currentStatus] ?? [], static fn (array $step): bool => can_transition_article($permissionArticle, (string) $step[0])));
$checklistPassed = !empty($checklist) ? array_reduce($checklist, static fn (bool $carry, array $item): bool => $carry && $item['passed'], true) : false;
$canAssignEditor = can_assign_editor();
$canDeleteCurrentArticle = $mode === 'edit' && $articleId > 0 && can_delete_article() && in_array($currentStatus, ['draft', 'archived'], true);
$heroPreview = trim((string) ($form['hero_image_path'] ?? ''));
if ($heroPreview !== '' && !preg_match('#^https?://#i', $heroPreview)) {
    $heroPreview = canonical_url(ltrim($heroPreview, '/'));
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">CMS</p>
            <h1><?= $mode === 'edit' ? '编辑文章' : '新建文章' ?></h1>
            <p>每篇文章都应包含清晰的作者、编辑、配图、摘要和发布状态。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">返回列表</a>
            <?php if ($mode === 'edit' && $articleId > 0): ?>
                <a class="ghost-link" href="<?= e(url('admin/articles/' . $articleId . '/preview')) ?>" target="_blank" rel="noopener">预览</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'edit'): ?>
        <div class="workflow-panel">
            <div>
                <p class="eyebrow">当前状态</p>
                <strong><?= e($statusLabels[$currentStatus] ?? $currentStatus) ?></strong>
            </div>
            <div class="workflow-actions">
                <?php foreach ($nextSteps as $step): [$nextStatus, $label] = $step; ?>
                    <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/status')) ?>">
                        <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                        <input type="hidden" name="return" value="edit">
                        <button class="button button-small <?= $nextStatus === 'published' ? 'is-primary' : '' ?>" type="submit"
                            <?= ($nextStatus === 'published' && !$checklistPassed) ? 'disabled title="发布前检查未全部通过"' : '' ?>>
                            <?= e($label) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/duplicate')) ?>">
                    <button class="button button-small button-ghost" type="submit" data-confirm="从这篇文章创建新草稿？" data-confirm-sub="副本会以草稿状态保存，便于在不动原文的前提下二次编辑。" data-confirm-title="创建副本" data-confirm-confirm="创建副本">复制为新稿</button>
                </form>
                <?php if ($canDeleteCurrentArticle): ?>
                    <form method="post" action="<?= e(url('admin/articles/' . $articleId . '/delete')) ?>">
                        <button class="button button-small button-danger" type="submit" data-confirm="永久删除这篇文章？" data-confirm-sub="这一操作无法撤销。仅限草稿和已归档文章。" data-confirm-variant="danger" data-confirm-title="删除文章" data-confirm-confirm="永久删除">删除</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($checklist): ?>
            <div class="publish-checklist">
                <p class="eyebrow">发布前检查</p>
                <ul>
                    <?php foreach ($checklist as $item): ?>
                        <li class="<?= $item['passed'] ? 'is-pass' : 'is-fail' ?>">
                            <span aria-hidden="true"><?= $item['passed'] ? '✓' : '○' ?></span>
                            <?= e($item['label']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$checklistPassed): ?>
                    <small>请补齐未通过的项目，再切换为“已发布”。</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($mode === 'edit' && $seoChecklist): ?>
        <div class="publish-checklist">
            <p class="eyebrow">SEO / 分享检查</p>
            <ul>
                <?php foreach ($seoChecklist as $item): ?>
                    <li class="<?= $item['passed'] ? 'is-pass' : 'is-fail' ?>">
                        <span aria-hidden="true"><?= $item['passed'] ? '✓' : '○' ?></span>
                        <?= e($item['label']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="cms-form" method="post" action="<?= e($action) ?>">
        <div class="cms-form-grid">
            <label>
                栏目
                <select name="category_id" required>
                    <option value="">请选择</option>
                    <?php foreach ($adminCategories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= (string) $form['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                状态
                <select name="status">
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <?php $allowedStatus = $mode === 'create' || $value === $form['status'] || can_transition_article($permissionArticle, $value); ?>
                        <option value="<?= e($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?> <?= $allowedStatus ? '' : 'disabled' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                作者
                <select name="author_id">
                    <?php foreach ($authors as $author): ?>
                        <option value="<?= e((string) $author['id']) ?>" <?= (string) ($form['author_id'] ?? '') === (string) $author['id'] ? 'selected' : '' ?>><?= e($author['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                责任编辑
                <select name="editor_id" <?= $canAssignEditor ? '' : 'disabled' ?>>
                    <option value="">未分配</option>
                    <?php foreach ($editors as $editor): ?>
                        <?php $editorName = (string) ($editor['display_name'] ?: $editor['email']); ?>
                        <option value="<?= e((string) $editor['id']) ?>" <?= (string) ($form['editor_id'] ?? '') === (string) $editor['id'] ? 'selected' : '' ?>><?= e($editorName) ?> · <?= e($editor['role']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$canAssignEditor): ?>
                    <input type="hidden" name="editor_id" value="<?= e((string) ($form['editor_id'] ?? '')) ?>">
                <?php endif; ?>
            </label>
            <label>
                阅读时间
                <input type="number" name="read_time_minutes" min="1" max="30" value="<?= e((string) $form['read_time_minutes']) ?>">
            </label>
            <label>
                发布时间
                <input type="datetime-local" name="published_at" value="<?= e((string) $form['published_at']) ?>">
            </label>
            <label>
                Premium
                <span class="checkbox-row"><input type="checkbox" name="is_premium" value="1" <?= !empty($form['is_premium']) ? 'checked' : '' ?>> 会员内容标记</span>
            </label>
        </div>

        <label>
            标题
            <input type="text" name="title" value="<?= e((string) $form['title']) ?>" required>
        </label>
        <label>
            URL slug
            <input type="text" name="slug" value="<?= e((string) $form['slug']) ?>" placeholder="留空则自动生成">
        </label>
        <label>
            副标题 / Dek
            <textarea name="dek" rows="2" required><?= e((string) $form['dek']) ?></textarea>
        </label>

        <section class="media-editor-panel">
            <div>
                <p class="eyebrow">Media / SEO</p>
                <h2>文章头图与分享卡片</h2>
                <p>可填写外部图片 URL，或站内路径如 /assets/img/og-money-tide.svg。留空时系统会按栏目使用默认图。</p>
            </div>
            <div class="media-preview">
                <?php if ($heroPreview !== ''): ?>
                    <img src="<?= e($heroPreview) ?>" alt="<?= e((string) ($form['hero_image_alt'] ?? $form['title'] ?? '')) ?>">
                <?php else: ?>
                    <span>使用栏目默认图</span>
                <?php endif; ?>
            </div>
            <label>
                Hero image URL / path
                <input type="text" name="hero_image_path" value="<?= e((string) ($form['hero_image_path'] ?? '')) ?>" placeholder="https://... 或 /assets/img/...">
            </label>
            <label>
                图片替代文字
                <input type="text" name="hero_image_alt" value="<?= e((string) ($form['hero_image_alt'] ?? '')) ?>" placeholder="用于无障碍和图片加载失败时显示">
            </label>
            <label>
                SEO 标题
                <input type="text" name="seo_title" value="<?= e((string) ($form['seo_title'] ?? '')) ?>" placeholder="留空则使用文章标题">
            </label>
            <label>
                SEO 描述
                <textarea name="seo_description" rows="2" placeholder="留空则使用副标题"><?= e((string) ($form['seo_description'] ?? '')) ?></textarea>
            </label>
            <label>
                Premium 摘要 / 软付费墙提示
                <textarea name="premium_excerpt" rows="2" placeholder="仅在 Premium 标记开启时显示；当前不会阻止阅读"><?= e((string) ($form['premium_excerpt'] ?? '')) ?></textarea>
            </label>
        </section>

        <label>
            一句话看懂
            <textarea name="brief" rows="2" required><?= e((string) $form['brief']) ?></textarea>
        </label>
        <label>
            为什么重要
            <textarea name="why_it_matters" rows="3" required><?= e((string) $form['why_it_matters']) ?></textarea>
        </label>
        <label>
            正文
            <textarea name="body" rows="14" required><?= e((string) $form['body']) ?></textarea>
        </label>

        <label>
            标签
            <input type="text" name="tags" value="<?= e((string) ($form['tags'] ?? '')) ?>" placeholder="用逗号或换行分隔，例如：Fed, AI, 出海">
        </label>

        <div class="cms-form-actions">
            <button class="button" type="submit">保存文章</button>
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">取消</a>
        </div>
    </form>

    <?php if ($mode === 'edit' && $auditLogs): ?>
        <section class="audit-panel">
            <p class="eyebrow">Audit Trail</p>
            <h2>状态变更记录</h2>
            <div class="audit-list">
                <?php foreach ($auditLogs as $log): ?>
                    <div class="audit-item">
                        <strong><?= e((string) $log['action']) ?></strong>
                        <span><?= e((string) ($log['from_status'] ?: '-')) ?> → <?= e((string) ($log['to_status'] ?: '-')) ?></span>
                        <small><?= e((string) $log['actor_email']) ?> · <?= e(date('Y-m-d H:i', strtotime((string) $log['created_at']))) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
