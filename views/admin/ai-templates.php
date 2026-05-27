<?php $pageTitle = 'AI 任务模板 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">AI 工作流</p>
            <h1>AI 任务模板</h1>
            <p>这里管理“做什么任务”的提示词。栏目机器人的身份、语气、读者和风险规则请到 AI Bots 管理。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-bots')) ?>">AI Bots</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-intake')) ?>">Story Intake</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <div class="status-banner is-ready">
        <strong>定位已拆分</strong>
        <span>AI Bots = 谁来写；AI 任务模板 = 执行什么工作，例如起草、改写、SEO、newsletter、社交文案和事实核查。</span>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="ai-template-grid">
        <?php foreach ($templates as $key => $tpl): ?>
            <?php
                $default = $defaults[$key] ?? null;
                $isCustom = $default !== null && ($default['name'] !== $tpl['name'] || $default['workflow'] !== $tpl['workflow'] || $default['prompt'] !== $tpl['prompt']);
            ?>
            <form class="ai-template-card" method="post" action="<?= e(url('admin/ai-templates')) ?>">
                <header>
                    <div>
                        <p class="eyebrow"><?= e($key) ?></p>
                        <strong><?= e((string) $tpl['name']) ?></strong>
                    </div>
                    <span class="pill"><?= $isCustom ? '已自定义' : '默认' ?></span>
                </header>
                <input type="hidden" name="task_key" value="<?= e($key) ?>">
                <label>
                    任务名称
                    <input type="text" name="name" value="<?= e((string) $tpl['name']) ?>" required>
                </label>
                <label>
                    使用场景
                    <textarea name="workflow" rows="2" required><?= e((string) $tpl['workflow']) ?></textarea>
                </label>
                <label>
                    任务提示词
                    <textarea name="prompt" rows="6" required><?= e((string) $tpl['prompt']) ?></textarea>
                </label>
                <div class="ai-template-actions">
                    <button class="button button-small" type="submit">保存任务模板</button>
                    <?php if ($isCustom): ?>
                        <button class="link-button" type="submit" name="action" value="reset" onclick="return confirm('恢复这个任务模板的默认提示词？')">恢复默认</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</section>
