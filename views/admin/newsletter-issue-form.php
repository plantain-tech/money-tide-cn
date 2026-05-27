<?php
$pageTitle = ($mode === 'edit' ? '编辑期号' : '新建期号') . ' - 钱潮 Money Tide';
$issueId = $issue['id'] ?? 0;
$status = $issue ? (string) $issue['status'] : 'draft';
$articles = $issue['articles'] ?? [];
$flash = $flash ?? '';
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">钱潮早报</p>
            <h1><?= $mode === 'edit' ? '编辑期号' : '新建期号' ?></h1>
            <?php if ($issue): ?><p>状态：<strong><?= e($status) ?></strong></p><?php endif; ?>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/newsletter')) ?>">返回列表</a>
            <?php if ($mode === 'edit'): ?>
                <a class="ghost-link" href="<?= e(url('admin/newsletter/' . $issueId . '/preview')) ?>" target="_blank" rel="noopener">预览邮件</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="form-message form-message-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="cms-form" method="post" action="<?= e($action) ?>">
        <label>
            邮件主题
            <input type="text" name="subject" value="<?= e((string) $form['subject']) ?>" required>
        </label>
        <label>
            开篇导读
            <textarea name="intro" rows="3"><?= e((string) $form['intro']) ?></textarea>
        </label>
        <label>
            结尾说明
            <textarea name="outro" rows="2"><?= e((string) $form['outro']) ?></textarea>
        </label>
        <label>
            计划发送时间（可选）
            <input type="datetime-local" name="scheduled_at" value="<?= e((string) $form['scheduled_at']) ?>">
        </label>
        <div class="cms-form-actions">
            <button class="button" type="submit">保存基本信息</button>
        </div>
    </form>

    <?php if ($mode === 'edit'): ?>
        <section class="newsletter-block">
            <h2>本期文章 (<?= count($articles) ?>)</h2>
            <?php if ($articles): ?>
                <ol class="newsletter-article-list">
                    <?php foreach ($articles as $art): ?>
                        <li>
                            <div>
                                <strong><?= e((string) $art['title']) ?></strong>
                                <small><?= e((string) ($art['category_name'] ?? '')) ?> · /<?= e((string) $art['slug']) ?></small>
                                <p><?= e((string) ($art['blurb'] ?: $art['brief'])) ?></p>
                            </div>
                            <form method="post" action="<?= e(url('admin/newsletter/' . $issueId . '/articles/' . $art['id'] . '/remove')) ?>">
                                <button type="submit" class="link-button is-danger" data-confirm="从本期移除这篇文章？" data-confirm-sub="文章本身不会被删除，只是从这一期 newsletter 中移除。" data-confirm-variant="danger" data-confirm-title="移除文章" data-confirm-confirm="移除">移除</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><small>还没有添加文章。先从下面挑选要收录的已发布文章。</small></p>
            <?php endif; ?>

            <h3>添加已发布文章</h3>
            <?php if ($availableArticles): ?>
                <div class="newsletter-article-picker">
                    <?php foreach ($availableArticles as $candidate): ?>
                        <form method="post" action="<?= e(url('admin/newsletter/' . $issueId . '/articles/add')) ?>">
                            <input type="hidden" name="article_id" value="<?= e((string) $candidate['id']) ?>">
                            <div>
                                <strong><?= e((string) $candidate['title']) ?></strong>
                                <small><?= e((string) $candidate['category_name']) ?> · /<?= e((string) $candidate['slug']) ?></small>
                            </div>
                            <input type="text" name="blurb" placeholder="本期编辑荐语（可选）" maxlength="280">
                            <button type="submit" class="button button-small">加入本期</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><small>所有已发布文章都已加入本期，或没有更多可选项。</small></p>
            <?php endif; ?>
        </section>

        <section class="newsletter-block">
            <h2>状态流转</h2>
            <p>当前状态：<strong><?= e($status) ?></strong></p>
            <div class="workflow-actions">
                <?php
                $transitions = [
                    'draft' => [['ready', '标记为 Ready']],
                    'ready' => [['draft', '回到草稿'], ['sent', '直接标记为已发送']],
                    'sent' => [['archived', '归档']],
                    'archived' => [['draft', '恢复为草稿']],
                ];
                foreach ($transitions[$status] ?? [] as $step):
                    [$nextStatus, $label] = $step;
                ?>
                    <form method="post" action="<?= e(url('admin/newsletter/' . $issueId . '/status')) ?>">
                        <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                        <button class="button button-small" type="submit"><?= e($label) ?></button>
                    </form>
                <?php endforeach; ?>
                <?php if (in_array($status, ['sent', 'archived'], true) && !empty($issue['slug'])): ?>
                    <a class="ghost-link" href="<?= e(url('newsletter/' . $issue['slug'])) ?>" target="_blank" rel="noopener">查看公开页面</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="newsletter-block">
            <h2>测试 & 广播</h2>
            <div class="status-banner <?= $providerStatus['ready'] ? 'is-ready' : 'is-warning' ?>">
                <strong>邮件服务：<?= e($providerStatus['provider']) ?></strong>
                <span><?= e($providerStatus['message']) ?></span>
            </div>
            <form method="post" action="<?= e(url('admin/newsletter/' . $issueId . '/test')) ?>" class="newsletter-test-form">
                <input type="email" name="test_email" placeholder="测试邮箱地址" required>
                <button type="submit" class="button button-small">发送测试</button>
            </form>
            <form method="post" action="<?= e(url('admin/newsletter/' . $issueId . '/send')) ?>" class="newsletter-broadcast-form">
                <button type="submit" class="button is-primary" data-confirm="向所有 active 订阅者广播本期 newsletter？" data-confirm-sub="一旦发送将无法撤回。请先用测试邮箱预览过。" data-confirm-variant="broadcast" data-confirm-title="广播本期" data-confirm-confirm="立即广播" <?= ($status === 'sent' || !$articles) ? 'disabled' : '' ?>>广播给所有订阅者</button>
                <?php if ($status === 'sent'): ?>
                    <small>本期已发送 (<?= e((string) $issue['sent_count']) ?>/<?= e((string) $issue['recipients_count']) ?>)。</small>
                <?php endif; ?>
            </form>
            <?php if ($sends): ?>
                <h3>最近发送记录</h3>
                <ul class="newsletter-sends-log">
                    <?php foreach (array_slice($sends, 0, 30) as $row): ?>
                        <li class="<?= $row['status'] === 'sent' ? 'is-pass' : ($row['status'] === 'failed' ? 'is-fail' : '') ?>">
                            <span><?= e((string) $row['email']) ?></span>
                            <span><?= e((string) $row['status']) ?></span>
                            <small><?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></small>
                            <?php if (!empty($row['error_message'])): ?><small><?= e((string) $row['error_message']) ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (can_delete_article()): ?>
                <form method="post" action="<?= e(url('admin/newsletter/' . $issueId . '/delete')) ?>" class="newsletter-delete-form">
                    <button type="submit" class="button button-small button-danger" data-confirm="永久删除这一期 newsletter？" data-confirm-sub="发送记录也会一并删除，无法恢复。" data-confirm-variant="danger" data-confirm-title="删除本期" data-confirm-confirm="永久删除">删除本期</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
