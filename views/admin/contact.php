<?php
$pageTitle = '联系留言 - 钱潮 Money Tide';
$counts = $counts ?? ['new' => 0, 'replied' => 0, 'archived' => 0, 'total' => 0];
$messages = $messages ?? [];
$activeStatus = $activeStatus ?? '';
$flash = $flash ?? '';
$openId = (int) ($openId ?? 0);
$tabs = ['' => '全部', 'new' => '未回复', 'replied' => '已回复', 'archived' => '已归档'];
?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">联系我们 · 收件箱</p>
            <h1>联系留言</h1>
            <p>访客在 <a href="<?= e(url('contact')) ?>" target="_blank" rel="noopener">联系页</a> 提交的留言都会落在这里。直接在后台回复——邮件由站点通过 Brevo 以 <strong>钱潮 Money Tide &lt;newsletter@…&gt;</strong> 的名义发出，访客看不到你的个人邮箱。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>" style="white-space:nowrap">工作台</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="status-banner is-ready ap-flash"><strong>✅</strong> <span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <div class="growth-analytics-grid">
        <div><span>未回复</span><strong><?= e((string) $counts['new']) ?></strong></div>
        <div><span>已回复</span><strong><?= e((string) $counts['replied']) ?></strong></div>
        <div><span>已归档</span><strong><?= e((string) $counts['archived']) ?></strong></div>
        <div><span>总计</span><strong><?= e((string) $counts['total']) ?></strong></div>
    </div>

    <nav class="filter-tabs" aria-label="状态筛选" style="display:flex;gap:8px;flex-wrap:wrap;margin:18px 0">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="button button-small <?= $activeStatus === $key ? '' : 'ghost-link' ?>"
               href="<?= e(url('admin/contact' . ($key !== '' ? ('?status=' . $key) : ''))) ?>"><?= e($label) ?><?= $key === 'new' && $counts['new'] > 0 ? ' (' . (int) $counts['new'] . ')' : '' ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($messages)): ?>
        <div class="status-banner"><span>暂无留言。</span></div>
    <?php else: ?>
        <div class="contact-list" style="display:flex;flex-direction:column;gap:14px">
            <?php foreach ($messages as $m): ?>
                <?php
                    $mid = (int) $m['id'];
                    $st = (string) $m['status'];
                    $isOpen = $openId === $mid;
                    $badge = ['new' => ['未回复', '#dcff00'], 'replied' => ['已回复', '#bdf3c9'], 'archived' => ['已归档', '#e6e6e6']][$st] ?? ['—', '#eee'];
                ?>
                <article class="contact-card" id="m<?= $mid ?>" style="border:1px solid #e8e6df;border-radius:14px;padding:16px 18px;background:#fff">
                    <header style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                        <div>
                            <strong style="font-size:1.05rem"><?= e((string) ($m['subject'] ?? '') ?: '（无主题）') ?></strong>
                            <div style="color:#666;font-size:.9rem;margin-top:4px">
                                <?= e((string) ($m['name'] ?? '') ?: '匿名') ?>
                                · <a href="mailto:<?= e((string) $m['email']) ?>"><?= e((string) $m['email']) ?></a>
                                · <?= e((string) $m['created_at']) ?>
                            </div>
                        </div>
                        <span style="background:<?= $badge[1] ?>;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:600;white-space:nowrap"><?= e($badge[0]) ?></span>
                    </header>

                    <div style="white-space:pre-wrap;line-height:1.6;margin:12px 0;padding:10px 14px;border-left:3px solid #dcff00;background:#faf7ef;border-radius:0 8px 8px 0"><?= nl2br(e((string) $m['message'])) ?></div>

                    <?php if (!empty($m['reply_body'])): ?>
                        <details style="margin:10px 0" <?= $isOpen ? '' : '' ?>>
                            <summary style="cursor:pointer;color:#444;font-size:.9rem">📤 已回复 · <?= e((string) ($m['replied_at'] ?? '')) ?></summary>
                            <div style="white-space:pre-wrap;line-height:1.6;margin-top:8px;padding:10px 14px;background:#f3f6f9;border-radius:8px;color:#333"><?= nl2br(e((string) $m['reply_body'])) ?></div>
                        </details>
                    <?php endif; ?>

                    <div class="contact-card-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px">
                        <?php if (!$isOpen): ?>
                            <a class="button button-small" href="<?= e(url('admin/contact' . ($activeStatus !== '' ? ('?status=' . $activeStatus . '&') : '?') . 'reply=' . $mid)) ?>#reply<?= $mid ?>"><?= !empty($m['reply_body']) ? '再次回复' : '✍️ 回复' ?></a>
                        <?php endif; ?>
                        <?php if ($st !== 'archived'): ?>
                            <form method="post" action="<?= e(url('admin/contact')) ?>" style="display:inline">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="id" value="<?= $mid ?>">
                                <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
                                <button class="ghost-link" type="submit">归档</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('admin/contact')) ?>" style="display:inline">
                                <input type="hidden" name="action" value="unarchive">
                                <input type="hidden" name="id" value="<?= $mid ?>">
                                <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
                                <button class="ghost-link" type="submit">恢复</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($isOpen): ?>
                        <form id="reply<?= $mid ?>" method="post" action="<?= e(url('admin/contact')) ?>" class="cms-form" style="margin-top:14px;border-top:1px dashed #e0ddd4;padding-top:14px">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="id" value="<?= $mid ?>">
                            <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
                            <label>回复 <?= e((string) ($m['name'] ?? '') ?: (string) $m['email']) ?>
                                <textarea name="reply_body" rows="5" required minlength="2" autofocus placeholder="写下你的回复…（将以 钱潮 Money Tide 的名义发送）"></textarea>
                            </label>
                            <div class="cms-form-actions" style="display:flex;gap:8px">
                                <button class="button" type="submit">📨 发送回复</button>
                                <a class="ghost-link" href="<?= e(url('admin/contact' . ($activeStatus !== '' ? ('?status=' . $activeStatus) : ''))) ?>#m<?= $mid ?>">取消</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
