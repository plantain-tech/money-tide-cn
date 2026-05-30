<?php
$pageTitle = 'AI 草稿队列 - 钱潮 Money Tide';
$statusCounts = $statusCounts ?? [];
$totalCount = (int) ($totalCount ?? array_sum($statusCounts));
$totalWarnings = 0;
foreach ($drafts as $d) {
    $totalWarnings += count($d['_warnings'] ?? []);
}
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">AI 编辑部 · 队列</p>
            <h1>AI 草稿队列</h1>
            <p>每条草稿带有局部质量评分和风险提示。可按栏目机器人和编辑阶段过滤。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-bots')) ?>">机器人</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-intake')) ?>">选题录入</a>
            <a class="button button-small" href="<?= e(url('admin/ai-drafts/new')) ?>">生成草稿</a>
        </div>
    </div>

    <div class="status-banner <?= $aiProvider['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong><?= e($aiProvider['label']) ?> · <?= e($aiProvider['model']) ?></strong>
        <span><?= e($aiProvider['message']) ?> 今日额度：<?= e((string) $aiUsage['used_today']) ?>/<?= e((string) $aiUsage['daily_limit']) ?> · 全队列警告：<?= e((string) $totalWarnings) ?></span>
    </div>

    <nav class="admin-tabs" aria-label="状态">
        <?php
            $baseQuery = $filters;
            unset($baseQuery['status']);
            $tabs = ['' => ['全部', $totalCount]];
            foreach ($statusOptions as $key => $label) {
                $tabs[$key] = [$label, $statusCounts[$key] ?? 0];
            }
            foreach ($tabs as $value => [$label, $count]):
                $params = array_filter(array_merge($baseQuery, ['status' => $value]), static fn ($v) => $v !== '' && $v !== null);
                $href = url('admin/ai-drafts') . ($params ? '?' . http_build_query($params) : '');
        ?>
            <a class="admin-tab <?= $filters['status'] === $value ? 'is-active' : '' ?>" href="<?= e($href) ?>">
                <span><?= e($label) ?></span>
                <small><?= e((string) $count) ?></small>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="admin-filter-bar" method="get" action="<?= e(url('admin/ai-drafts')) ?>">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <select name="section_slug">
            <option value="">全部栏目机器人</option>
            <?php foreach ($templates as $slug => $template): ?>
                <option value="<?= e($slug) ?>" <?= $filters['section_slug'] === $slug ? 'selected' : '' ?>><?= e($template['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-small" type="submit">筛选</button>
    </form>

    <div class="ai-draft-queue">
        <?php foreach ($drafts as $draft): ?>
            <?php
                $payload = $draft['draft_payload'];
                $quality = (int) ($draft['_quality'] ?? 0);
                $warnings = $draft['_warnings'] ?? [];
                $qualityClass = $quality >= 80 ? 'is-high' : ($quality >= 50 ? 'is-mid' : 'is-low');
                $status = ai_draft_normalize_status((string) ($draft['status'] ?? 'generated'));
            ?>
            <a class="ai-draft-row" href="<?= e(url('admin/ai-drafts/' . $draft['id'])) ?>">
                <div class="ai-draft-row-main">
                    <div class="ai-draft-row-title">
                        <strong><?= e((string) ($payload['title'] ?? '未命名草稿')) ?></strong>
                        <small><?= e((string) ($payload['brief'] ?? '')) ?></small>
                    </div>
                    <div class="ai-draft-row-meta">
                        <span class="pill"><?= e($templates[$draft['section_slug']]['name'] ?? $draft['section_slug']) ?></span>
                        <mark class="draft-status-pill" data-status="<?= e($status) ?>"><?= e(ai_draft_status_label($status)) ?></mark>
                        <span class="quality-badge <?= e($qualityClass) ?>" title="局部质量评分（0-100）"><?= e((string) $quality) ?> / 100</span>
                    </div>
                </div>
                <?php if ($warnings): ?>
                    <ul class="ai-draft-row-warnings">
                        <?php foreach (array_slice($warnings, 0, 3) as $w): ?>
                            <li class="warning-pill is-<?= e((string) $w['severity']) ?>" title="<?= e((string) $w['type']) ?>">
                                <?= e((string) $w['message']) ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (count($warnings) > 3): ?>
                            <li class="warning-pill is-more">+<?= e((string) (count($warnings) - 3)) ?> 更多</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
                <div class="ai-draft-row-foot">
                    <span><?= e(date('Y-m-d H:i', strtotime((string) $draft['created_at']))) ?></span>
                    <span><?= e((string) count($draft['source_links'] ?? [])) ?> 个来源</span>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (!$drafts): ?>
            <div class="empty-state">
                <strong>还没有 AI 草稿。</strong>
                <p>添加来源链接后，让栏目机器人先生成一版。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
