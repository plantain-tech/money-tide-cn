<?php
$pageTitle = '流水线告警 - 钱潮 Money Tide';
$sum = $summary;
$st = $settings;
$emailOk = !empty($emailReady['ready']) && !empty($emailReady['real_provider']);
$levelClass = ['critical' => 'is-critical', 'warn' => 'is-warn', 'info' => 'is-info'];
$statusLabels = ['open' => '待处理', 'acknowledged' => '处理中', 'resolved' => '已解决'];
?>
<section class="admin-shell" data-pipeline-alerts>
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Sprint 2 · 韧性</p>
            <h1>流水线告警与自愈 <?php if ($sum['open'] > 0): ?><span class="pa-alert-count"><?= e((string) $sum['open']) ?></span><?php endif; ?></h1>
            <p>引擎会在每次运行后自检：失败、限流、停摆或引擎离线都会在这里报警，并可邮件通知你。写稿被限流时还会自动退避重试一次（自愈）。告警仅供参考，绝不自动对外发布。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/pipeline-analytics')) ?>">流水线分析</a>
            <a class="ghost-link" href="<?= e(url('admin/autopilot')) ?>">自动驾驶</a>
            <a class="ghost-link" href="<?= e(url('admin/email-delivery')) ?>">邮件投递</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="status-banner is-ready ap-flash"><strong>提示</strong><span><?= e($flash) ?></span></div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="pa-kpi-grid">
        <article class="pa-kpi <?= $sum['open'] > 0 ? 'pa-kpi-warn' : 'pa-kpi-accent' ?>">
            <span class="pa-kpi-icon"><?= $sum['open'] > 0 ? '🔔' : '🟢' ?></span>
            <strong data-countup="<?= e((string) $sum['open']) ?>">0</strong>
            <small>待处理告警</small>
        </article>
        <article class="pa-kpi <?= $sum['critical_open'] > 0 ? 'pa-kpi-warn' : '' ?>">
            <span class="pa-kpi-icon">🟥</span>
            <strong data-countup="<?= e((string) $sum['critical_open']) ?>">0</strong>
            <small>严重 · 待处理</small>
        </article>
        <article class="pa-kpi">
            <span class="pa-kpi-icon">🟧</span>
            <strong data-countup="<?= e((string) $sum['warn_open']) ?>">0</strong>
            <small>警告 · 待处理</small>
        </article>
        <article class="pa-kpi">
            <span class="pa-kpi-icon">✅</span>
            <strong data-countup="<?= e((string) $sum['resolved']) ?>">0</strong>
            <small>已解决（累计）</small>
        </article>
    </div>

    <div class="autopilot-cols">
        <!-- Manual check -->
        <section class="newsletter-block">
            <h2>🩺 立即健康检查</h2>
            <p>马上跑一遍所有检测器（引擎状态、最近运行、限流、停摆），把发现的问题写入下面的告警列表。已配置邮箱时会同时发送通知邮件。</p>
            <form method="post" action="<?= e(url('admin/pipeline-alerts')) ?>" class="news-fetch-form" data-news-fetch>
                <input type="hidden" name="action" value="check_now">
                <button class="button" type="submit" data-news-fetch-btn data-loading-label="检查中…">
                    <span class="news-fetch-label">🩺 立即检查并告警</span>
                    <span class="news-fetch-spinner" hidden></span>
                </button>
            </form>
            <?php if (is_array($checkResult)): ?>
                <p class="news-action-hint"><?= count($checkResult) > 0 ? '本次发现 ' . count($checkResult) . ' 项，见下方列表。' : '✅ 一切正常，没有发现需要关注的问题。' ?></p>
            <?php endif; ?>
        </section>

        <!-- Settings -->
        <section class="newsletter-block">
            <h2>⚙️ 告警设置</h2>
            <?php if (!$emailOk): ?>
                <div class="pa-insight is-info" style="margin-bottom:0.8rem">
                    <span class="pa-insight-icon">ℹ️</span>
                    <p>当前邮件为 <strong><?= e((string) ($emailReady['provider'] ?? 'log')) ?></strong> 模式，告警只会记录在本页、不会真正发邮件。要收到邮件，请在 <a href="<?= e(url('admin/email-delivery')) ?>">邮件投递</a> 配置真实服务商。</p>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('admin/pipeline-alerts')) ?>" class="cms-form">
                <input type="hidden" name="action" value="save_settings">
                <label>告警邮箱<input type="email" name="alert_email" value="<?= e((string) $st['alert_email']) ?>" placeholder="you@example.com"></label>
                <label class="pa-switch-row">
                    <input type="checkbox" name="alert_on_failure" value="1" <?= $st['alert_on_failure'] ? 'checked' : '' ?>>
                    <span>运行失败 / 连续失败时告警</span>
                </label>
                <label class="pa-switch-row">
                    <input type="checkbox" name="alert_on_throttle" value="1" <?= $st['alert_on_throttle'] ? 'checked' : '' ?>>
                    <span>疑似限流（草稿 0）时告警</span>
                </label>
                <label>停摆判定（小时）<input type="number" name="alert_stale_hours" min="6" max="168" value="<?= e((string) $st['alert_stale_hours']) ?>"></label>
                <div class="cms-form-actions"><button class="button button-small" type="submit">保存设置</button></div>
                <p class="news-action-hint">「停摆判定」：自动驾驶开启后，超过这个小时数没有成功运行就报警，提醒你检查 Cron。</p>
            </form>
        </section>
    </div>

    <!-- Filter tabs -->
    <div class="pa-window-bar">
        <span>筛选</span>
        <div class="pa-window-tabs">
            <?php
            $tabs = ['' => '全部', 'open' => '待处理', 'acknowledged' => '处理中', 'resolved' => '已解决'];
            foreach ($tabs as $key => $label):
            ?>
                <a class="pa-window-tab <?= (string) $filters['status'] === (string) $key ? 'is-active' : '' ?>"
                   href="<?= e(url('admin/pipeline-alerts')) ?><?= $key !== '' ? '?status=' . e($key) : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Alerts list -->
    <section class="newsletter-block">
        <h2>📋 告警列表</h2>
        <div class="pa-alert-list">
            <?php foreach ($alerts as $i => $a): ?>
                <article class="pa-alert <?= e($levelClass[$a['level']] ?? 'is-info') ?> status-<?= e((string) $a['status']) ?>" style="--i: <?= e((string) $i) ?>">
                    <div class="pa-alert-main">
                        <div class="pa-alert-head">
                            <span class="pa-alert-badge"><?= e($levels[$a['level']] ?? (string) $a['level']) ?></span>
                            <span class="pa-alert-type"><?= e((string) $a['type']) ?></span>
                            <span class="pa-alert-status pa-status-<?= e((string) $a['status']) ?>"><?= e($statusLabels[$a['status']] ?? (string) $a['status']) ?></span>
                            <time><?= e(date('m-d H:i', strtotime((string) $a['created_at']))) ?></time>
                        </div>
                        <p class="pa-alert-msg"><?= e((string) $a['message']) ?></p>
                    </div>
                    <div class="pa-alert-actions">
                        <?php if ($a['status'] !== 'resolved'): ?>
                            <?php if ($a['status'] === 'open'): ?>
                                <form method="post" action="<?= e(url('admin/pipeline-alerts')) ?>"><input type="hidden" name="action" value="ack"><input type="hidden" name="id" value="<?= e((string) $a['id']) ?>"><button class="button button-small button-ghost" type="submit">处理中</button></form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('admin/pipeline-alerts')) ?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= e((string) $a['id']) ?>"><button class="button button-small" type="submit">标记解决</button></form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('admin/pipeline-alerts')) ?>"><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?= e((string) $a['id']) ?>"><button class="button button-small button-ghost" type="submit">重新打开</button></form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$alerts): ?>
                <div class="empty-state pa-empty">
                    <strong>🟢 没有告警，引擎运行健康。</strong>
                    <p>当出现失败、限流或停摆时，这里会自动出现告警条目。点上方「立即检查」可手动触发一次自检。</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</section>
