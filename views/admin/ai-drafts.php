<?php $pageTitle = 'AI 草稿 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">AI 编辑部</p>
            <h1>AI 草稿</h1>
            <p>按栏目机器人查看生成、审核、接受或拒绝的草稿。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="button button-small" href="<?= e(url('admin/ai-drafts/new')) ?>">生成草稿</a>
        </div>
    </div>

    <?php if (!$aiReady): ?>
        <div class="status-banner is-warning"><strong>AI 未配置</strong><span>添加 GitHub Secret：OPENAI_API_KEY 后即可生成。</span></div>
    <?php endif; ?>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/ai-drafts')) ?>">
        <select name="section_slug">
            <option value="">全部栏目</option>
            <?php foreach ($templates as $slug => $template): ?>
                <option value="<?= e($slug) ?>" <?= $filters['section_slug'] === $slug ? 'selected' : '' ?>><?= e($template['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">全部状态</option>
            <?php foreach (['generated' => '已生成', 'reviewed' => '已审核', 'accepted' => '已接受', 'rejected' => '已拒绝'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="admin-table">
        <div class="admin-table-row ai-table-head">
            <span>草稿</span><span>栏目</span><span>状态</span><span>时间</span><span>操作</span>
        </div>
        <?php foreach ($drafts as $draft): ?>
            <?php $payload = $draft['draft_payload']; ?>
            <div class="admin-table-row ai-table-row">
                <div>
                    <strong><?= e((string) ($payload['title'] ?? '未命名草稿')) ?></strong>
                    <small><?= e((string) ($payload['brief'] ?? '')) ?></small>
                </div>
                <span><?= e($templates[$draft['section_slug']]['name'] ?? $draft['section_slug']) ?></span>
                <span><mark><?= e($draft['status']) ?></mark></span>
                <span><?= e(date('Y-m-d H:i', strtotime((string) $draft['created_at']))) ?></span>
                <div class="admin-row-actions"><a href="<?= e(url('admin/ai-drafts/' . $draft['id'])) ?>">查看</a></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$drafts): ?>
            <div class="empty-state">
                <strong>还没有 AI 草稿。</strong>
                <p>添加来源链接后，让栏目机器人先生成一版。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
