<?php
$pageTitle = '分发渠道 - 钱潮 Money Tide';
$tg = $telegram;
$tgConfigured = trim((string) ($tg['bot_token'] ?? '')) !== '' && trim((string) ($tg['channel_id'] ?? '')) !== '';
$tgReady = !empty($tg['enabled']) && $tgConfigured;
$xc = $x ?? [];
$xConfigured = trim((string) ($xc['api_key'] ?? '')) !== '' && trim((string) ($xc['access_token_secret'] ?? '')) !== '';
$xReady = !empty($xc['enabled']) && $xConfigured;
$xBudget = (int) ($xc['monthly_budget'] ?? 450);
$xUsed = (int) ($xUsage ?? 0);
$kindLabels = ['article' => '文章', 'digest' => '早报', 'test' => '测试'];
$channelLabels = ['telegram' => '✈️ Telegram', 'x' => '𝕏 X'];
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
        <article class="pa-kpi <?= $xReady ? 'pa-kpi-accent' : '' ?>">
            <span class="pa-kpi-icon">𝕏</span>
            <strong style="font-size:1.2rem"><?= e((string) $xUsed) ?>/<?= e((string) $xBudget) ?></strong>
            <small>X 本月用量 · <?= $xReady ? '已开启' : ($xConfigured ? '未开' : '未配置') ?></small>
        </article>
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

    <!-- X (Twitter) -->
    <div class="autopilot-cols">
        <section class="newsletter-block">
            <h2>𝕏 X (Twitter) 设置 <span class="ch-dot <?= $xReady ? 'is-on' : 'is-off' ?>"></span></h2>
            <p>发布即自动发一条推文（AI 社交标题 + 标签 + 带 UTM 的链接）。免费额度有限，已内置<strong>月度预算保护</strong>：用满自动停发，下月恢复。</p>
            <form method="post" action="<?= e(url('admin/channels')) ?>" class="cms-form">
                <input type="hidden" name="action" value="save_x">
                <label class="pa-switch-row">
                    <input type="checkbox" name="x_enabled" value="1" <?= !empty($xc['enabled']) ? 'checked' : '' ?>>
                    <span>开启自动分发到 X</span>
                </label>
                <div class="cms-form-grid">
                    <label>API Key<input type="password" name="x_api_key" autocomplete="off" placeholder="<?= trim((string) ($xc['api_key'] ?? '')) !== '' ? '已配置 ••••（留空不改）' : 'Consumer API Key' ?>"></label>
                    <label>API Secret<input type="password" name="x_api_secret" autocomplete="off" placeholder="<?= trim((string) ($xc['api_secret'] ?? '')) !== '' ? '已配置 ••••（留空不改）' : 'Consumer API Secret' ?>"></label>
                    <label>Access Token<input type="password" name="x_access_token" autocomplete="off" placeholder="<?= trim((string) ($xc['access_token'] ?? '')) !== '' ? '已配置 ••••（留空不改）' : 'Access Token' ?>"></label>
                    <label>Access Token Secret<input type="password" name="x_access_token_secret" autocomplete="off" placeholder="<?= trim((string) ($xc['access_token_secret'] ?? '')) !== '' ? '已配置 ••••（留空不改）' : 'Access Token Secret' ?>"></label>
                    <label>每月发推预算<input type="number" name="x_monthly_budget" min="1" max="10000" value="<?= e((string) $xBudget) ?>"></label>
                </div>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存设置</button></div>
                <p class="news-action-hint">本月已用 <strong><?= e((string) $xUsed) ?></strong> / <?= e((string) $xBudget) ?> 条。X 免费版约每月 500 条写入，建议预算留 50 条缓冲。</p>
            </form>
            <form method="post" action="<?= e(url('admin/channels')) ?>" class="news-fetch-form" data-news-fetch style="margin-top:0.6rem">
                <input type="hidden" name="action" value="test_x">
                <button class="button button-small button-ghost" type="submit" data-news-fetch-btn data-loading-label="发推中…" <?= $xConfigured ? '' : 'disabled' ?>>
                    <span class="news-fetch-label">🐦 发送测试推文</span>
                    <span class="news-fetch-spinner" hidden></span>
                </button>
            </form>
        </section>

        <section class="newsletter-block">
            <h2>🛠 接好 X API（v2 免费版）</h2>
            <ol class="qa-list">
                <li><strong>申请开发者账号。</strong><small><a href="https://developer.x.com" target="_blank" rel="noopener">developer.x.com</a> → 创建 App（Free 套餐即可发推）。</small></li>
                <li><strong>把 App 权限设为 Read and write。</strong><small>User authentication settings → App permissions 选 <code>Read and write</code>（改完要重新生成 Access Token）。</small></li>
                <li><strong>拿 4 项凭证填到左边。</strong><small>Keys and tokens 页：API Key/Secret + Access Token/Secret。保存后点「发送测试推文」确认（会真的发一条到你时间线）。</small></li>
            </ol>
            <p class="news-action-hint">提示：凭证也可用部署 Secrets（X_API_KEY 等），但此页填写无需重新部署。预算保护让你不会超出免费额度被封。</p>
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
                    <span><?= e($channelLabels[$d['channel']] ?? (string) $d['channel']) ?></span>
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
