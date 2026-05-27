<?php $pageTitle = '新研究简报 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">研究台</p>
            <h1>新研究简报</h1>
            <p>提交选题和来源，AI 会生成研究素材；不会写文章，写文章仍然在 AI 草稿那一步。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/research-desk')) ?>">返回列表</a>
        </div>
    </div>

    <div class="status-banner <?= $aiProvider['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong><?= e($aiProvider['label']) ?> · <?= e($aiProvider['model']) ?></strong>
        <span><?= e($aiProvider['message']) ?> 今日剩余：<?= e((string) $aiUsage['remaining_today']) ?></span>
    </div>

    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="cms-form" method="post" action="<?= e(url('admin/research-desk/new')) ?>"
          data-ai-progress
          data-ai-progress-title="正在生成研究简报"
          data-ai-progress-phases='["正在解析来源链接","正在拼装研究指令","正在调用研究台模型","模型正在整理事实与角度","正在保存研究简报","即将打开简报详情"]'
          data-ai-progress-foot="研究简报通常需要 60–90 秒。请勿关闭页面。">
        <div class="cms-form-grid">
            <label>
                栏目
                <select name="section_slug" required>
                    <?php foreach ($botSections as $slug => $bot): ?>
                        <option value="<?= e($slug) ?>" <?= ($form['section_slug'] ?? '') === $slug ? 'selected' : '' ?>><?= e($bot['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>
            选题角度
            <textarea name="topic_angle" rows="3" required><?= e((string) ($form['topic_angle'] ?? '')) ?></textarea>
        </label>
        <label>
            来源链接，每行一个
            <textarea name="source_links" rows="6" required><?= e((string) ($form['source_links'] ?? '')) ?></textarea>
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit">生成研究简报</button>
            <a class="ghost-link" href="<?= e(url('admin/research-desk')) ?>">取消</a>
        </div>
    </form>

    <?php if ($templates): ?>
        <section class="newsletter-block">
            <h2>从模板快速填充</h2>
            <ul class="analytics-list">
                <?php foreach ($templates as $tpl): ?>
                    <li>
                        <a href="<?= e(url('admin/research-desk/new?template_id=' . $tpl['id'])) ?>"><?= e((string) $tpl['name']) ?></a>
                        <span><?= e((string) ($botSections[$tpl['section_slug']]['name'] ?? $tpl['section_slug'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
