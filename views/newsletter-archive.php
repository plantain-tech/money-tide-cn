<?php
$pageTitle = '钱潮早报 - Newsletter Archive';
$pageDescription = '历期钱潮早报 / Money Tide Newsletter。';
$canonicalPath = 'newsletter';
?>
<section class="tag-shell">
    <div class="tag-header">
        <p class="eyebrow">钱潮早报</p>
        <h1>历期 Newsletter</h1>
        <p>每一期都把全球财经、科技、加密、出海的关键信号浓缩到 5 分钟。</p>
    </div>

    <?php if ($issues): ?>
        <ol class="newsletter-archive-list">
            <?php foreach ($issues as $issue): ?>
                <li>
                    <div>
                        <small><?= e(date('Y-m-d', strtotime((string) ($issue['sent_at'] ?: $issue['created_at'])))) ?></small>
                        <h3><a href="<?= e(url('newsletter/' . ($issue['slug'] ?: ('issue-' . $issue['id'])))) ?>"><?= e((string) $issue['subject']) ?></a></h3>
                        <?php if (!empty($issue['intro'])): ?>
                            <p><?= e(mb_substr((string) $issue['intro'], 0, 160, 'UTF-8')) ?><?= mb_strlen((string) $issue['intro'], 'UTF-8') > 160 ? '…' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <a class="button button-small" href="<?= e(url('newsletter/' . ($issue['slug'] ?: ('issue-' . $issue['id'])))) ?>">阅读这一期</a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>还没有发布过 Newsletter。</strong>
            <p>第一期上线后会自动显示在这里。</p>
        </div>
    <?php endif; ?>

    <p><a class="ghost-link" href="<?= e(url('subscribe')) ?>">订阅 →</a></p>
</section>
