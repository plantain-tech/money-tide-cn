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

    <div class="status-banner <?= $aiProvider['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong><?= e($aiProvider['label']) ?> · <?= e($aiProvider['model']) ?></strong>
        <span><?= e($aiProvider['message']) ?> 今日剩余：<?= e((string) $aiUsage['remaining_today']) ?></span>
    </div>

    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="cms-form" method="post" action="<?= e(url('admin/ai-drafts/new')) ?>" data-ai-draft-form
          data-ai-progress
          data-ai-progress-title="正在生成 AI 草稿"
          data-ai-progress-phases='["正在加载栏目机器人配置","正在抓取来源链接元信息","正在拼装编辑指令","模型正在起草文章","正在结构化返回的 JSON","正在保存草稿到数据库"]'
          data-ai-progress-foot="正在生成完整草稿，通常需要 60–90 秒。请勿关闭页面或后退。">
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
        <div class="ai-generation-panel" data-ai-generation-panel hidden aria-live="polite">
            <div class="ai-generation-head">
                <div>
                    <span class="ai-generation-kicker">AI 生成中</span>
                    <strong data-ai-generation-title>编辑机器人正在准备草稿</strong>
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
            <button class="button" type="submit" data-ai-generate-button>生成草稿</button>
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">取消</a>
        </div>
    </form>
</section>
