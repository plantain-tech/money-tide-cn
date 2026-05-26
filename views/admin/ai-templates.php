<?php $pageTitle = '提示词模板 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">AI 编辑部</p>
            <h1>栏目机器人提示词</h1>
            <p>每个栏目都有自己的提示词，决定生成草稿的风格与重点。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">AI 草稿</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="ai-template-grid">
        <?php foreach ($templates as $slug => $tpl): ?>
            <?php
                $default = $defaults[$slug] ?? null;
                $isCustom = $default !== null && ($default['name'] !== $tpl['name'] || $default['prompt'] !== $tpl['prompt']);
            ?>
            <form class="ai-template-card" method="post" action="<?= e(url('admin/ai-templates')) ?>">
                <header>
                    <div>
                        <p class="eyebrow"><?= e($slug) ?></p>
                        <strong><?= e($tpl['name']) ?></strong>
                    </div>
                    <?php if ($isCustom): ?>
                        <span class="pill">已自定义</span>
                    <?php endif; ?>
                </header>
                <input type="hidden" name="section_slug" value="<?= e($slug) ?>">
                <label>
                    机器人名称
                    <input type="text" name="name" value="<?= e($tpl['name']) ?>" required>
                </label>
                <label>
                    提示词
                    <textarea name="prompt" rows="6" required><?= e($tpl['prompt']) ?></textarea>
                </label>
                <div class="ai-template-actions">
                    <button class="button button-small" type="submit">保存</button>
                    <?php if ($isCustom): ?>
                        <button class="link-button" type="submit" name="action" value="reset" onclick="return confirm('恢复为默认提示词？')">恢复默认</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</section>
