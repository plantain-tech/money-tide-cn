<?php $pageTitle = '研究简报 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">研究台</p>
            <h1>研究简报</h1>
            <p>在写文章之前，先让 AI 帮你整理事实、数字、争议点和角度。研究简报不是文章，是给编辑的素材。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/sources')) ?>">来源库</a>
            <a class="ghost-link" href="<?= e(url('admin/source-templates')) ?>">输入模板</a>
            <a class="button button-small" href="<?= e(url('admin/research-desk/new')) ?>">新研究简报</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/research-desk')) ?>">
        <select name="section_slug">
            <option value="">全部栏目</option>
            <?php foreach ($botSections as $slug => $bot): ?>
                <option value="<?= e($slug) ?>" <?= $filters['section_slug'] === $slug ? 'selected' : '' ?>><?= e($bot['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach (['draft' => '草稿', 'used' => '已使用', 'archived' => '已归档'] as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button button-small">筛选</button>
    </form>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head">
            <span>选题角度</span><span>栏目</span><span>状态</span><span>创建时间</span><span>操作</span>
        </div>
        <?php foreach ($briefs as $brief): ?>
            <div class="admin-table-row">
                <div>
                    <strong><?= e(mb_substr((string) $brief['topic_angle'], 0, 80, 'UTF-8')) ?></strong>
                </div>
                <span><?= e((string) ($botSections[$brief['section_slug']]['name'] ?? $brief['section_slug'])) ?></span>
                <span><mark><?= e((string) $brief['status']) ?></mark></span>
                <span><?= e(date('Y-m-d H:i', strtotime((string) $brief['created_at']))) ?></span>
                <div class="admin-row-actions">
                    <a href="<?= e(url('admin/research-desk/' . $brief['id'])) ?>">查看</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$briefs): ?>
            <div class="empty-state">
                <strong>还没有研究简报。</strong>
                <p>点击"新研究简报"，让 AI 先把素材整理好，再写文章。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
