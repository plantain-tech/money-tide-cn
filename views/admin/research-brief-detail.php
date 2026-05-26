<?php
$pageTitle = '研究简报详情 - 钱潮 Money Tide';
$payload = $brief['brief_payload'];
$listGroups = [
    'key_facts' => '关键事实',
    'numbers' => '关键数字',
    'quotes' => '引语',
    'risks' => '风险与不确定性',
    'angles' => '可选角度',
    'questions' => '编辑应继续追问',
];
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">研究简报 #<?= e((string) $brief['id']) ?> · <?= e((string) ($botSections[$brief['section_slug']]['name'] ?? $brief['section_slug'])) ?></p>
            <h1><?= e((string) ($payload['recommended_title'] ?: $brief['topic_angle'])) ?></h1>
            <?php if (!empty($payload['recommended_dek'])): ?>
                <p><?= e((string) $payload['recommended_dek']) ?></p>
            <?php endif; ?>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/research-desk')) ?>">返回列表</a>
            <form method="post" action="<?= e(url('admin/research-desk/' . $brief['id'] . '/use')) ?>">
                <button type="submit" class="button button-small is-primary">生成 AI 草稿</button>
            </form>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <?php if (!empty($payload['summary'])): ?>
        <section class="newsletter-block">
            <h2>摘要</h2>
            <p><?= nl2br(e((string) $payload['summary'])) ?></p>
        </section>
    <?php endif; ?>

    <div class="analytics-panels">
        <?php foreach ($listGroups as $key => $label): ?>
            <section class="analytics-panel">
                <h2><?= e($label) ?></h2>
                <?php if (!empty($payload[$key])): ?>
                    <ul class="analytics-list">
                        <?php foreach ((array) $payload[$key] as $item): ?>
                            <li><span><?= e((string) $item) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><small>—</small></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="newsletter-block">
        <h2>来源</h2>
        <?php if (!empty($brief['source_links'])): ?>
            <ul class="analytics-list">
                <?php foreach ((array) $brief['source_links'] as $link): ?>
                    <li><a href="<?= e((string) $link) ?>" target="_blank" rel="noopener"><?= e((string) $link) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="newsletter-block">
        <h2>原始选题</h2>
        <p><?= nl2br(e((string) $brief['topic_angle'])) ?></p>
    </section>
</section>
