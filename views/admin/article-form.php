<?php $pageTitle = ($mode === 'edit' ? '编辑文章' : '新建文章') . ' - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">CMS</p>
            <h1><?= $mode === 'edit' ? '编辑文章' : '新建文章' ?></h1>
            <p>每篇文章都应包含一句话看懂、为什么重要和清晰正文。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">返回列表</a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
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
                    <?php foreach (['draft' => '草稿', 'review' => '审核', 'published' => '已发布', 'archived' => '已归档'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                阅读时间
                <input type="number" name="read_time_minutes" min="1" max="30" value="<?= e((string) $form['read_time_minutes']) ?>">
            </label>
            <label>
                发布时间
                <input type="datetime-local" name="published_at" value="<?= e((string) $form['published_at']) ?>">
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

        <div class="cms-form-actions">
            <button class="button" type="submit">保存文章</button>
            <a class="ghost-link" href="<?= e(url('admin/articles')) ?>">取消</a>
        </div>
    </form>
</section>
