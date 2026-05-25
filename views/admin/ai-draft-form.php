<?php $pageTitle = '生成 AI 草稿 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">AI 编辑部</p>
            <h1>生成 AI 草稿</h1>
            <p>至少提供一个来源链接。AI 只负责起草，事实核查和发布仍由编辑完成。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">草稿列表</a>
        </div>
    </div>

    <?php if (!$aiReady): ?>
        <div class="status-banner is-warning"><strong>AI 未配置</strong><span>请先添加 GitHub Secret：OPENAI_API_KEY。</span></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="cms-form" method="post" action="<?= e(url('admin/ai-drafts/new')) ?>">
        <div class="cms-form-grid">
            <label>
                栏目机器人
                <select name="section_slug">
                    <?php foreach ($templates as $slug => $template): ?>
                        <option value="<?= e($slug) ?>" <?= $form['section_slug'] === $slug ? 'selected' : '' ?>><?= e($template['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                紧急程度
                <select name="urgency">
                    <?php foreach (['low' => '低', 'normal' => '普通', 'high' => '高'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $form['urgency'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                目标读者
                <input type="text" name="target_reader" value="<?= e((string) $form['target_reader']) ?>">
            </label>
        </div>
        <label>
            选题角度
            <textarea name="topic_angle" rows="3" required><?= e((string) $form['topic_angle']) ?></textarea>
        </label>
        <label>
            来源链接，每行一个
            <textarea name="source_links" rows="6" required><?= e((string) $form['source_links']) ?></textarea>
        </label>
        <div class="status-banner is-warning">
            <strong>编辑守门</strong>
            <span>生成后必须人工核查来源、数字和金融风险表述，再转成文章草稿。</span>
        </div>
        <div class="cms-form-actions">
            <button class="button" type="submit">生成草稿</button>
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">取消</a>
        </div>
    </form>
</section>
