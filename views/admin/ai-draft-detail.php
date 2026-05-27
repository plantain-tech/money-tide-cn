<?php
$pageTitle = 'AI 草稿详情 - 钱潮 Money Tide';
$payload = $draft['draft_payload'];
$passCount = 0;
foreach ($factChecks as $c) {
    if ($c['passed']) {
        $passCount++;
    }
}
$totalChecks = count($factChecks);
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow"><?= e($templates[$draft['section_slug']]['name'] ?? $draft['section_slug']) ?></p>
            <h1><?= e((string) ($payload['title'] ?? 'AI 草稿')) ?></h1>
            <p><?= e((string) ($payload['dek'] ?? '')) ?></p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">草稿列表</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-templates')) ?>">AI Task Templates</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($message) ?></span></div>
    <?php endif; ?>

    <div class="status-banner is-ready">
        <strong>状态：<?= e($draft['status']) ?> · 核查 <?= e((string) $passCount) ?>/<?= e((string) $totalChecks) ?></strong>
        <span>AI 草稿不能直接发布，必须先转成 CMS 草稿。</span>
    </div>

    <?php if (!empty($tonePresets)): ?>
        <div class="tone-preset-bar">
            <span class="tone-preset-label">一键调整正文语气：</span>
            <?php foreach ($tonePresets as $key => $preset): ?>
                <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/tone')) ?>"
                      data-ai-progress
                      data-ai-progress-title="正在调整正文语气"
                      data-ai-progress-phases='["正在读取当前草稿","正在拼装提示词与上下文","正在调用栏目机器人","模型正在重写正文","正在保存版本快照","即将刷新页面"]'>
                    <input type="hidden" name="preset" value="<?= e($key) ?>">
                    <button type="submit" class="button button-small button-ghost"
                            data-confirm="把正文重写为「<?= e($preset['label']) ?>」？"
                            data-confirm-sub="这会重写整段正文。当前版本会自动备份到版本历史。"
                            data-confirm-title="调整语气"
                            data-confirm-confirm="重写"><?= e($preset['label']) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="ai-draft-layout">
        <article class="ai-draft-preview">
            <?php foreach ($rewriteTargets as $key => $meta): ?>
                <?php
                    $value = $payload[$key] ?? '';
                    $textValue = is_array($value) ? implode("\n\n", array_map('strval', $value)) : (string) $value;
                ?>
                <section class="ai-rewrite-block">
                    <header class="ai-rewrite-head">
                        <h2><?= e($meta['label']) ?></h2>
                        <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/rewrite')) ?>" class="ai-rewrite-form"
                              data-ai-progress
                              data-ai-progress-title="正在重写「<?= e($meta['label']) ?>」"
                              data-ai-progress-phases='["正在读取草稿当前版本","正在拼装提示词与上下文","正在调用栏目机器人","模型正在生成新内容","正在保存版本快照","即将刷新页面"]'>
                            <input type="hidden" name="target" value="<?= e($key) ?>">
                            <input type="text" name="instruction" placeholder="改写说明（可选）" maxlength="200">
                            <button type="submit" class="button button-small">重写</button>
                        </form>
                    </header>
                    <?php if (is_array($value)): ?>
                        <?php foreach ($value as $paragraph): ?>
                            <p><?= e((string) $paragraph) ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?= e($textValue) ?></p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </article>

        <aside class="ai-draft-sidebar">
            <h2>核查清单</h2>
            <ul class="fact-check-list">
                <?php foreach ($factChecks as $check): ?>
                    <li class="<?= $check['passed'] ? 'is-pass' : '' ?>">
                        <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/check')) ?>">
                            <input type="hidden" name="key" value="<?= e($check['key']) ?>">
                            <input type="hidden" name="passed" value="<?= $check['passed'] ? '0' : '1' ?>">
                            <button type="submit" class="link-button">
                                <span aria-hidden="true"><?= $check['passed'] ? '✓' : '○' ?></span>
                                <?= e($check['label']) ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2>来源</h2>
            <?php foreach ($draft['source_links'] as $link): ?>
                <a href="<?= e((string) $link) ?>" target="_blank" rel="noopener"><?= e((string) $link) ?></a>
            <?php endforeach; ?>

            <h2>AI 备注</h2>
            <?php foreach (($payload['source_notes'] ?? []) as $note): ?>
                <p><?= e((string) $note) ?></p>
            <?php endforeach; ?>
            <?php foreach (($payload['risk_notes'] ?? []) as $note): ?>
                <p><?= e((string) $note) ?></p>
            <?php endforeach; ?>

            <h2>版本历史</h2>
            <?php if ($versions): ?>
                <ul class="version-list">
                    <?php foreach ($versions as $version): ?>
                        <li>
                            <div>
                                <strong><?= e(date('Y-m-d H:i', strtotime((string) $version['created_at']))) ?></strong>
                                <small><?= e((string) $version['source']) ?></small>
                            </div>
                            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/restore/' . $version['id'])) ?>">
                                <button type="submit" class="link-button" data-confirm="恢复到此版本？" data-confirm-sub="当前内容会另存为新版本。" data-confirm-title="恢复版本" data-confirm-confirm="恢复">恢复</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><small>还没有改写记录。</small></p>
            <?php endif; ?>

            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/status')) ?>">
                <input type="hidden" name="status" value="reviewed">
                <button class="button full-width" type="submit">标记已审核</button>
            </form>
            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/convert')) ?>">
                <button class="button full-width" type="submit">转为文章草稿</button>
            </form>
            <form method="post" action="<?= e(url('admin/ai-drafts/' . $draft['id'] . '/status')) ?>">
                <input type="hidden" name="status" value="rejected">
                <button class="button full-width button-danger" type="submit">拒绝草稿</button>
            </form>
        </aside>
    </div>
</section>
