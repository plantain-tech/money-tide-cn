<?php $pageTitle = 'AI 选题录入 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 5 周 · AI 编辑部</p>
            <h1>AI 选题 Intake</h1>
            <p>输入选题角度、来源和目标栏目，让对应机器人先生成编辑简报，再进入草稿流程。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-bots')) ?>">AI 机器人</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">AI 草稿</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <div class="status-banner <?= $aiProvider['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong><?= e($aiProvider['label']) ?> · <?= e($aiProvider['model']) ?></strong>
        <span><?= e($aiProvider['message']) ?> 今日剩余：<?= e((string) $aiUsage['remaining_today']) ?></span>
    </div>

    <?php if ($message !== ''): ?>
        <div class="status-banner is-warning"><strong>提示</strong><span><?= e($message) ?></span></div>
    <?php endif; ?>

    <div class="admin-two-column">
        <form class="cms-form" method="post" action="<?= e(url('admin/ai-intake')) ?>" data-ai-draft-form
              data-ai-progress
              data-ai-progress-title="正在生成编辑简报"
              data-ai-progress-phases='["正在分析选题与来源","正在选择目标栏目机器人","正在评估紧急程度与读者画像","模型正在产出简报结构","正在保存到 story intake","即将打开简报"]'
              data-ai-progress-foot="生成编辑简报通常需要 45–90 秒。完成后会自动跳转到简报详情页。">
            <div class="cms-form-grid">
                <label>
                    目标机器人
                    <select name="bot_slug">
                        <?php foreach ($bots as $slug => $bot): ?>
                            <option value="<?= e($slug) ?>" <?= $form['bot_slug'] === $slug ? 'selected' : '' ?>><?= e((string) $bot['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    紧急程度
                    <select name="urgency">
                        <?php foreach (['low' => '低', 'normal' => '普通', 'high' => '高', 'breaking' => '突发'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $form['urgency'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>
                目标读者
                <input type="text" name="target_reader" value="<?= e((string) $form['target_reader']) ?>" placeholder="留空则使用机器人默认读者">
            </label>
            <label>
                选题角度
                <textarea name="topic_angle" rows="4" required><?= e((string) $form['topic_angle']) ?></textarea>
            </label>
            <label>
                来源链接，每行一个
                <textarea name="source_links" rows="6" required><?= e((string) $form['source_links']) ?></textarea>
            </label>
            <div class="status-banner is-warning">
                <strong>编辑守门</strong>
                <span>AI 简报只负责结构化选题。数字、事实、来源可靠性和金融风险仍由编辑核查。</span>
            </div>
            <div class="ai-generation-panel" data-ai-generation-panel hidden aria-live="polite">
                <div class="ai-generation-head">
                    <div>
                        <span class="ai-generation-kicker">AI 选题录入</span>
                        <strong data-ai-generation-title>编辑机器人正在准备简报</strong>
                    </div>
                    <span class="ai-generation-percent" data-ai-generation-percent>0%</span>
                </div>
                <div class="ai-generation-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-ai-generation-progressbar>
                    <span data-ai-generation-bar></span>
                </div>
                <div class="ai-generation-meta">
                    <span data-ai-generation-step>正在读取来源链接并整理编辑简报。</span>
                    <span data-ai-generation-time>已用时 0 秒</span>
                </div>
            </div>
            <div class="cms-form-actions">
                <button class="button" type="submit" data-ai-generate-button>生成编辑简报</button>
            </div>
        </form>

        <aside class="admin-side-panel">
            <h2>最近简报</h2>
            <?php foreach ($briefs as $brief): ?>
                <?php $payload = $brief['brief_payload']; ?>
                <a class="admin-list-item" href="<?= e(url('admin/ai-intake?brief=' . $brief['id'])) ?>">
                    <strong><?= e((string) ($payload['suggested_headline'] ?? $brief['topic_angle'])) ?></strong>
                    <span><?= e((string) $brief['bot_slug']) ?> · <?= e(date('Y-m-d H:i', strtotime((string) $brief['created_at']))) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$briefs): ?>
                <div class="empty-state"><strong>还没有 Intake 简报</strong><p>提交第一个选题后会显示在这里。</p></div>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($selectedBrief): ?>
        <?php $payload = $selectedBrief['brief_payload']; ?>
        <section class="admin-detail-panel">
            <div class="admin-topbar">
                <div>
                    <p class="eyebrow"><?= e((string) $selectedBrief['bot_slug']) ?> · <?= e((string) $selectedBrief['urgency']) ?></p>
                    <h2><?= e((string) ($payload['suggested_headline'] ?? '未命名简报')) ?></h2>
                    <p><?= e((string) ($payload['brief'] ?? '')) ?></p>
                </div>
                <a class="button button-small" href="<?= e(url('admin/ai-drafts/new?' . ai_story_brief_to_draft_query($selectedBrief))) ?>">继续生成草稿</a>
            </div>
            <div class="ai-brief-grid">
                <article>
                    <h3>一句话看懂</h3>
                    <p><?= e((string) ($payload['brief'] ?? '')) ?></p>
                </article>
                <article>
                    <h3>为什么重要</h3>
                    <p><?= e((string) ($payload['why_it_matters'] ?? '')) ?></p>
                </article>
                <article>
                    <h3>关键数字</h3>
                    <ul><?php foreach ((array) ($payload['key_numbers'] ?? []) as $item): ?><li><?= e((string) $item) ?></li><?php endforeach; ?></ul>
                </article>
                <article>
                    <h3>建议标签</h3>
                    <p><?= e(implode(' · ', (array) ($payload['suggested_tags'] ?? []))) ?></p>
                </article>
                <article>
                    <h3>风险提醒</h3>
                    <ul><?php foreach ((array) ($payload['risk_notes'] ?? []) as $item): ?><li><?= e((string) $item) ?></li><?php endforeach; ?></ul>
                </article>
                <article>
                    <h3>来源问题</h3>
                    <ul><?php foreach ((array) ($payload['source_questions'] ?? []) as $item): ?><li><?= e((string) $item) ?></li><?php endforeach; ?></ul>
                </article>
                <article>
                    <h3>下一步</h3>
                    <ul><?php foreach ((array) ($payload['next_steps'] ?? []) as $item): ?><li><?= e((string) $item) ?></li><?php endforeach; ?></ul>
                </article>
            </div>
        </section>
    <?php endif; ?>
</section>
