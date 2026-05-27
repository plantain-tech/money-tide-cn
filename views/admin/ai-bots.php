<?php
$pageTitle = 'AI 编辑机器人 - 钱潮 Money Tide';
$activeCount = 0;
foreach ($bots as $bot) {
    if (($bot['status'] ?? 'active') === 'active') {
        $activeCount++;
    }
}
$totalCount = count($bots);
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Week 5 · AI Newsroom</p>
            <h1>AI 编辑机器人</h1>
            <p>每个核心栏目都有自己的语气、目标读者、来源要求、风险规则和提示词。这里管理的是"谁在写"，任务模板管理的是"在写什么"。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/ai-intake')) ?>">选题 Intake</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-templates')) ?>">任务模板</a>
            <a class="ghost-link" href="<?= e(url('admin/ai-drafts')) ?>">AI 草稿</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="ai-bot-toolbar" data-ai-bot-toolbar>
        <span class="ai-bot-toolbar-label">过滤</span>
        <input type="search" placeholder="按栏目、名称或语气搜索" data-ai-bot-search aria-label="搜索 Bot">
        <select data-ai-bot-status-filter aria-label="状态">
            <option value="">全部状态</option>
            <option value="active">仅 active</option>
            <option value="inactive">仅 inactive</option>
        </select>
        <span class="ai-bot-toolbar-meta"><?= e((string) $activeCount) ?> / <?= e((string) $totalCount) ?> active</span>
    </div>

    <div class="ai-bot-grid" data-ai-bot-grid>
        <?php foreach ($bots as $slug => $bot): ?>
            <?php
                $status = (string) ($bot['status'] ?? 'active');
                $default = $defaults[$slug] ?? [];
                $isCustom = $default && json_encode($default, JSON_UNESCAPED_UNICODE) !== json_encode(array_intersect_key($bot, $default), JSON_UNESCAPED_UNICODE);
                $haystack = strtolower($slug . ' ' . ($bot['name'] ?? '') . ' ' . ($bot['tone'] ?? '') . ' ' . ($bot['target_reader'] ?? ''));
            ?>
            <form class="ai-bot-card <?= $status === 'inactive' ? 'is-inactive' : '' ?>"
                  method="post"
                  action="<?= e(url('admin/ai-bots')) ?>"
                  data-ai-bot-card
                  data-status="<?= e($status) ?>"
                  data-search="<?= e($haystack) ?>">
                <header class="ai-bot-card-header">
                    <div>
                        <p class="eyebrow"><?= e($slug) ?></p>
                        <h2><?= e((string) $bot['name']) ?></h2>
                    </div>
                    <span class="ai-bot-status-chip <?= $status === 'inactive' ? 'is-inactive' : '' ?>" data-ai-bot-status-chip>
                        <?= e($status) ?>
                    </span>
                </header>

                <input type="hidden" name="section_slug" value="<?= e($slug) ?>">

                <div class="ai-bot-card-body">
                    <section class="ai-bot-section">
                        <h3 class="ai-bot-section-title">身份</h3>
                        <div class="ai-bot-field-row">
                            <div class="ai-bot-field">
                                <label for="bot-<?= e($slug) ?>-name">Bot 名称</label>
                                <input id="bot-<?= e($slug) ?>-name" type="text" name="name" value="<?= e((string) $bot['name']) ?>" required>
                            </div>
                            <div class="ai-bot-field">
                                <label for="bot-<?= e($slug) ?>-status">状态</label>
                                <select id="bot-<?= e($slug) ?>-status" name="status" data-ai-bot-status-select>
                                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>active · 可用</option>
                                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>inactive · 暂停</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="ai-bot-section">
                        <h3 class="ai-bot-section-title">读者画像</h3>
                        <div class="ai-bot-field">
                            <label for="bot-<?= e($slug) ?>-tone">语气 Tone</label>
                            <textarea id="bot-<?= e($slug) ?>-tone" name="tone" rows="3" placeholder="冷静、数据优先、解释市场传导链条。"><?= e((string) $bot['tone']) ?></textarea>
                        </div>
                        <div class="ai-bot-field">
                            <label for="bot-<?= e($slug) ?>-reader">目标读者</label>
                            <textarea id="bot-<?= e($slug) ?>-reader" name="target_reader" rows="3" placeholder="关注全球市场的中文读者，已有基本财经常识。"><?= e((string) $bot['target_reader']) ?></textarea>
                        </div>
                    </section>

                    <section class="ai-bot-section">
                        <h3 class="ai-bot-section-title">编辑规则</h3>
                        <div class="ai-bot-field">
                            <label for="bot-<?= e($slug) ?>-sources">来源要求</label>
                            <textarea id="bot-<?= e($slug) ?>-sources" name="source_requirements" rows="3" placeholder="至少一个可信来源；数字必须核查来源。"><?= e((string) $bot['source_requirements']) ?></textarea>
                        </div>
                        <div class="ai-bot-field">
                            <label for="bot-<?= e($slug) ?>-risk">风险规则</label>
                            <textarea id="bot-<?= e($slug) ?>-risk" name="risk_rules" rows="3" placeholder="不得给出买卖建议；区分事实和分析。"><?= e((string) $bot['risk_rules']) ?></textarea>
                        </div>
                    </section>

                    <section class="ai-bot-section">
                        <h3 class="ai-bot-section-title">提示词使命</h3>
                        <div class="ai-bot-field">
                            <label for="bot-<?= e($slug) ?>-prompt">Prompt mission</label>
                            <textarea id="bot-<?= e($slug) ?>-prompt" name="prompt_template" rows="6" data-large required placeholder="Focus on price action, macro data, rates, USD..."><?= e((string) $bot['prompt_template']) ?></textarea>
                            <small class="ai-bot-field-hint">这段会同步进入 AI 任务模板，作为这个栏目机器人的核心 system prompt。</small>
                        </div>
                    </section>
                </div>

                <footer class="ai-bot-card-footer">
                    <?php if ($isCustom): ?>
                        <span class="ai-bot-saved-hint">已自定义 · 与默认配置不同</span>
                    <?php else: ?>
                        <span class="ai-bot-saved-hint">使用默认配置</span>
                    <?php endif; ?>
                    <div class="ai-bot-card-footer-actions">
                        <?php if ($isCustom): ?>
                            <button class="link-button"
                                    type="submit"
                                    name="action"
                                    value="reset"
                                    data-confirm="恢复 <?= e((string) $bot['name']) ?> 的默认配置？"
                                    data-confirm-sub="你的所有自定义改动会被覆盖，无法撤销。"
                                    data-confirm-variant="danger"
                                    data-confirm-title="恢复默认配置"
                                    data-confirm-confirm="恢复默认">
                                恢复默认
                            </button>
                        <?php endif; ?>
                        <button class="button button-small" type="submit">保存 Bot</button>
                    </div>
                </footer>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function () {
    var grid = document.querySelector('[data-ai-bot-grid]');
    if (!grid) return;
    var search = document.querySelector('[data-ai-bot-search]');
    var statusFilter = document.querySelector('[data-ai-bot-status-filter]');
    var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-ai-bot-card]'));

    function applyFilter() {
        var q = (search.value || '').trim().toLowerCase();
        var st = statusFilter.value || '';
        cards.forEach(function (card) {
            var matchesQ = q === '' || (card.getAttribute('data-search') || '').indexOf(q) >= 0;
            var matchesS = st === '' || card.getAttribute('data-status') === st;
            card.style.display = (matchesQ && matchesS) ? '' : 'none';
        });
    }

    search && search.addEventListener('input', applyFilter);
    statusFilter && statusFilter.addEventListener('change', applyFilter);

    // Live status chip update as the user toggles the select.
    cards.forEach(function (card) {
        var select = card.querySelector('[data-ai-bot-status-select]');
        var chip = card.querySelector('[data-ai-bot-status-chip]');
        if (!select || !chip) return;
        select.addEventListener('change', function () {
            var v = select.value;
            chip.textContent = v;
            chip.classList.toggle('is-inactive', v === 'inactive');
            card.classList.toggle('is-inactive', v === 'inactive');
            card.setAttribute('data-status', v);
        });
    });
})();
</script>
