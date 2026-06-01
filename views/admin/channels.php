<?php
$pageTitle = '分发渠道 - 钱潮 Money Tide';
$tg = $telegram;
$tgConfigured = trim((string) ($tg['bot_token'] ?? '')) !== '' && trim((string) ($tg['channel_id'] ?? '')) !== '';
$tgReady = !empty($tg['enabled']) && $tgConfigured;
$kindLabels = ['article' => '文章', 'digest' => '早报'];
?>
<section class="admin-shell" data-channels>
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Sprint 2 · 自动分发</p>
            <h1>分发渠道 <span class="ch-dot <?= $tgReady ? 'is-on' : 'is-off' ?>" title="<?= $tgReady ? '已开启自动分发' : '未开启' ?>"></span></h1>
            <p>流水线每发布一篇文章，自动把<strong>标题 + 摘要 + 链接</strong>推送到你的 Telegram 频道；每天再推一条带链接的早报。只发自己站点的链接，不外发正文，消息 ID 会记录在下方。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a>
            <a class="ghost-link" href="<?= e(url('admin/pipeline-analytics')) ?>">流水线分析</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner <?= strpos($flash, '⚠️') === 0 ? 'is-warning' : 'is-ready' ?> ap-flash"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="pa-kpi-grid">
        <article class="pa-kpi <?= $tgReady ? 'pa-kpi-accent' : 'pa-kpi-warn' ?>">
            <span class="pa-kpi-icon">✈️</span>
            <strong style="font-size:1.2rem"><?= $tgReady ? '已开启' : ($tgConfigured ? '已配置·未开' : '未配置') ?></strong>
            <small>Telegram 自动分发</small>
        </article>
        <article class="pa-kpi"><span class="pa-kpi-icon">📤</span><strong data-countup="<?= e((string) $summary['ok']) ?>">0</strong><small>成功分发（累计）</small></article>
        <article class="pa-kpi"><span class="pa-kpi-icon">📅</span><strong data-countup="<?= e((string) $summary['today']) ?>">0</strong><small>今日已分发</small></article>
        <article class="pa-kpi <?= $summary['failed'] > 0 ? 'pa-kpi-warn' : '' ?>"><span class="pa-kpi-icon">⚠️</span><strong data-countup="<?= e((string) $summary['failed']) ?>">0</strong><small>失败次数</small></article>
    </div>

    <div class="autopilot-cols">
        <!-- Telegram settings -->
        <section class="newsletter-block">
            <h2>✈️ Telegram 频道设置</h2>
            <form method="post" action="<?= e(url('admin/channels')) ?>" class="cms-form">
                <input type="hidden" name="action" value="save_settings">
                <label class="pa-switch-row">
                    <input type="checkbox" name="telegram_enabled" value="1" <?= !empty($tg['enabled']) ? 'checked' : '' ?>>
                    <span>开启自动分发到 Telegram</span>
                </label>
                <label>Bot Token
                    <input type="password" name="telegram_bot_token" autocomplete="off"
                        placeholder="<?= $tgConfigured && trim((string) $tg['bot_token']) !== '' ? '已配置 ••••（留空则不修改）' : '从 @BotFather 获取，例如 1234567:AAE…' ?>">
                </label>
                <label>频道 ID / 用户名
                    <input type="text" name="telegram_channel_id" value="<?= e((string) ($tg['channel_id'] ?? '')) ?>" placeholder="@your_channel 或 -1001234567890">
                </label>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存设置</button></div>
            </form>
            <form method="post" action="<?= e(url('admin/channels')) ?>" class="news-fetch-form" data-news-fetch style="margin-top:0.6rem">
                <input type="hidden" name="action" value="test">
                <button class="button button-small button-ghost" type="submit" data-news-fetch-btn data-loading-label="发送中…" <?= $tgConfigured ? '' : 'disabled' ?>>
                    <span class="news-fetch-label">📨 发送测试消息</span>
                    <span class="news-fetch-spinner" hidden></span>
                </button>
            </form>
        </section>

        <!-- Setup guide -->
        <section class="newsletter-block">
            <h2>🛠 三步接好 Telegram</h2>
            <ol class="qa-list">
                <li><strong>建机器人。</strong><small>在 Telegram 搜 <code>@BotFather</code> → 发送 <code>/newbot</code> → 拿到 Bot Token 填到左边。</small></li>
                <li><strong>把机器人设为频道管理员。</strong><small>打开你的频道 → 管理员 → 添加你的机器人，勾选「发布消息」权限。</small></li>
                <li><strong>填频道 ID。</strong><small>公开频道用 <code>@频道用户名</code>；私有频道用数字 ID（形如 <code>-100…</code>）。保存后点「发送测试消息」确认。</small></li>
            </ol>
            <p class="news-action-hint">提示：Bot Token 和频道 ID 也可在部署 Secrets 配置（TELEGRAM_BOT_TOKEN / TELEGRAM_CHANNEL_ID），但在此页填写更方便、无需重新部署。</p>
        </section>
    </div>

    <!-- Dispatch log -->
    <section class="newsletter-block">
        <h2>🕑 分发记录</h2>
        <div class="admin-table">
            <div class="admin-table-row admin-table-head"><span>时间</span><span>渠道</span><span>类型</span><span>状态</span><span>消息 ID / 备注</span></div>
            <?php foreach ($dispatches as $d): ?>
                <div class="admin-table-row">
                    <span><?= e(date('m-d H:i', strtotime((string) $d['created_at']))) ?></span>
                    <span>✈️ <?= e((string) $d['channel']) ?></span>
                    <span><?= e($kindLabels[$d['kind']] ?? (string) $d['kind']) ?></span>
                    <span><mark class="<?= $d['status'] === 'ok' ? 'status-ok' : 'status-warn' ?>"><?= e((string) $d['status']) ?></mark></span>
                    <span><?= $d['status'] === 'ok' ? 'msg #' . e((string) $d['external_id']) : e((string) ($d['message'] ?? '')) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$dispatches): ?>
                <div class="empty-state"><strong>还没有分发记录。</strong><p>开启并配置后，下一次发布文章会自动推送到频道，这里显示每条消息的 ID。</p></div>
            <?php endif; ?>
        </div>
    </section>
</section>
