<?php $pageTitle = 'AI Bots - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 5 AI Newsroom</p>
            <h1>AI 编辑机器人</h1>
            <p>每个核心栏目都有独立的语气、目标读者、来源要求、风险规则和提示词模板。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-intake')) ?>">选题 Intake</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">AI 草稿</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="ai-template-grid">
        <?php foreach ($bots as $slug => $bot): ?>
            <?php
                $default = $defaults[$slug] ?? [];
                $isCustom = $default && json_encode($default, JSON_UNESCAPED_UNICODE) !== json_encode(array_intersect_key($bot, $default), JSON_UNESCAPED_UNICODE);
            ?>
            <form class="ai-template-card" method="post" action="<?= e(url('admin/ai-bots')) ?>">
                <header>
                    <div>
                        <p class="eyebrow"><?= e($slug) ?></p>
                        <strong><?= e((string) $bot['name']) ?></strong>
                    </div>
                    <span class="pill"><?= e((string) $bot['status']) ?></span>
                </header>
                <input type="hidden" name="section_slug" value="<?= e($slug) ?>">
                <div class="cms-form-grid">
                    <label>
                        Bot name
                        <input type="text" name="name" value="<?= e((string) $bot['name']) ?>" required>
                    </label>
                    <label>
                        Status
                        <select name="status">
                            <option value="active" <?= $bot['status'] === 'active' ? 'selected' : '' ?>>active</option>
                            <option value="inactive" <?= $bot['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option>
                        </select>
                    </label>
                </div>
                <label>
                    Tone
                    <textarea name="tone" rows="2"><?= e((string) $bot['tone']) ?></textarea>
                </label>
                <label>
                    Target reader
                    <textarea name="target_reader" rows="2"><?= e((string) $bot['target_reader']) ?></textarea>
                </label>
                <label>
                    Source requirements
                    <textarea name="source_requirements" rows="3"><?= e((string) $bot['source_requirements']) ?></textarea>
                </label>
                <label>
                    Risk rules
                    <textarea name="risk_rules" rows="3"><?= e((string) $bot['risk_rules']) ?></textarea>
                </label>
                <label>
                    Prompt template
                    <textarea name="prompt_template" rows="5" required><?= e((string) $bot['prompt_template']) ?></textarea>
                </label>
                <div class="ai-template-actions">
                    <button class="button button-small" type="submit">保存 Bot</button>
                    <?php if ($isCustom): ?>
                        <button class="link-button" type="submit" name="action" value="reset" onclick="return confirm('恢复这个 Bot 的默认配置？')">恢复默认</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</section>
