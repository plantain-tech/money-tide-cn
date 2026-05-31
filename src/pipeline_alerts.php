<?php

declare(strict_types=1);

/**
 * Sprint 2 · Day 10·2 — Failure alerts & self-heal (pipeline resilience).
 *
 * Day 10·1 made the engine *observable*; this makes it *react*:
 *   - evaluate_pipeline_health(): detectors that turn the analytics signals
 *     (failures, throttling, staleness, provider down, error streaks) into
 *     deduplicated alerts.
 *   - dispatch_pipeline_alerts(): emails the owner when configured.
 *   - the self-heal retry lives in pipeline.php (one backoff retry of a
 *     throttled synthesize stage); this module records what happened.
 *
 * Alerts are advisory — they never publish or send anything to the public.
 */

function ensure_pipeline_alerts_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS pipeline_alerts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(12) NOT NULL DEFAULT 'warn',
            type VARCHAR(40) NOT NULL,
            message VARCHAR(600) NOT NULL,
            context LONGTEXT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'open',
            notified TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX idx_alerts_status (status),
            INDEX idx_alerts_type (type),
            INDEX idx_alerts_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

function pipeline_alert_levels(): array
{
    return ['critical' => '严重', 'warn' => '警告', 'info' => '提示'];
}

/**
 * Alert settings, stored in pipeline_settings (shared with the rest of the
 * autopilot config). Email defaults to the configured from-address.
 */
function pipeline_alert_settings(): array
{
    $defaultEmail = '';
    if (function_exists('email_provider_status')) {
        $defaultEmail = (string) (email_provider_status()['from_address'] ?? '');
    }
    return [
        'alert_email' => pipeline_setting('alert_email', $defaultEmail),
        'alert_on_failure' => pipeline_setting('alert_on_failure', '1') === '1',
        'alert_on_throttle' => pipeline_setting('alert_on_throttle', '1') === '1',
        'alert_stale_hours' => max(6, min(168, (int) pipeline_setting('alert_stale_hours', '36'))),
    ];
}

function save_pipeline_alert_settings(array $input): void
{
    $email = trim((string) ($input['alert_email'] ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_pipeline_setting('alert_email', $email);
    }
    set_pipeline_setting('alert_on_failure', !empty($input['alert_on_failure']) ? '1' : '0');
    set_pipeline_setting('alert_on_throttle', !empty($input['alert_on_throttle']) ? '1' : '0');
    set_pipeline_setting('alert_stale_hours', (string) max(6, min(168, (int) ($input['alert_stale_hours'] ?? 36))));
}

/**
 * Create an alert, de-duplicating against an open alert of the same type from
 * the last 12 hours so a recurring condition doesn't spam the list.
 * Returns the alert id (existing or new), or 0 on failure.
 */
function record_pipeline_alert(string $level, string $type, string $message, array $context = []): int
{
    ensure_pipeline_alerts_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $existing = $pdo->prepare("SELECT id FROM pipeline_alerts
            WHERE type = :t AND status = 'open' AND created_at >= (NOW() - INTERVAL 12 HOUR)
            ORDER BY id DESC LIMIT 1");
        $existing->execute(['t' => $type]);
        $id = (int) ($existing->fetchColumn() ?: 0);
        if ($id > 0) {
            // Refresh message/time so the dashboard shows the latest occurrence.
            $pdo->prepare('UPDATE pipeline_alerts SET message = :m, context = :c, level = :l, created_at = NOW() WHERE id = :id')
                ->execute([
                    'm' => mb_substr($message, 0, 590, 'UTF-8'),
                    'c' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'l' => $level,
                    'id' => $id,
                ]);
            return $id;
        }
        $pdo->prepare('INSERT INTO pipeline_alerts (level, type, message, context) VALUES (:l, :t, :m, :c)')
            ->execute([
                'l' => $level,
                't' => $type,
                'm' => mb_substr($message, 0, 590, 'UTF-8'),
                'c' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Run all health detectors against the current state + recent runs and record
 * alerts. Returns the list of conditions found (whether new or refreshed).
 */
function evaluate_pipeline_health(array $opts = []): array
{
    ensure_pipeline_alerts_schema();
    $settings = pipeline_alert_settings();
    $found = [];

    // 1) AI provider down — nothing downstream can run.
    if (function_exists('ai_provider_status')) {
        $provider = ai_provider_status();
        if (empty($provider['ready'])) {
            $found[] = ['level' => 'critical', 'type' => 'provider_down', 'message' => 'AI 引擎离线：' . ($provider['label'] ?? '') . ' 不可用，整条流水线无法写稿与审核。'];
        }
    }

    // Recent runs (exclude paused skips).
    $runs = function_exists('pipeline_runs') ? pipeline_runs(10) : [];
    $real = array_values(array_filter($runs, static fn ($r) => ($r['status'] ?? '') !== 'paused'));

    // 2) Most recent real run failed.
    if ($settings['alert_on_failure'] && !empty($real) && ($real[0]['status'] ?? 'ok') !== 'ok') {
        $found[] = ['level' => 'critical', 'type' => 'run_failed', 'message' => '最近一次流水线运行失败（' . date('m-d H:i', strtotime((string) $real[0]['started_at'])) . '）。请查看运行记录定位失败阶段。'];
    }

    // 3) Consecutive failure streak.
    $streak = 0;
    foreach ($real as $r) {
        if (($r['status'] ?? 'ok') !== 'ok') {
            $streak++;
        } else {
            break;
        }
    }
    if ($settings['alert_on_failure'] && $streak >= 2) {
        $found[] = ['level' => 'critical', 'type' => 'error_streak', 'message' => '连续 ' . $streak . ' 次运行失败。引擎可能停摆，请尽快排查（额度、网络或数据库）。'];
    }

    // 4) Throttling — ran with material but produced 0 drafts.
    if ($settings['alert_on_throttle'] && function_exists('pipeline_analytics_summary')) {
        $sum = pipeline_analytics_summary(7);
        if (($sum['zero_draft_runs'] ?? 0) > 0) {
            $found[] = ['level' => 'warn', 'type' => 'throttle', 'message' => ($sum['zero_draft_runs']) . ' 次运行有选题却产出 0 草稿（疑似免费额度限流）。建议把「AI 阶段间隔」调到 10–15 秒。'];
        }
    }

    // 5) Stale — autopilot ON but hasn't run within the threshold.
    if (function_exists('autopilot_enabled') && autopilot_enabled()) {
        $lastRun = pipeline_setting('last_run_at', '');
        $staleSecs = $settings['alert_stale_hours'] * 3600;
        if ($lastRun === '' || (time() - strtotime($lastRun)) > $staleSecs) {
            $found[] = ['level' => 'warn', 'type' => 'stale', 'message' => '自动驾驶已开启，但超过 ' . $settings['alert_stale_hours'] . ' 小时没有成功运行。请检查 Cron 是否在跑（cli/run-daily.php）。'];
        }
    }

    foreach ($found as &$f) {
        $f['id'] = record_pipeline_alert($f['level'], $f['type'], $f['message'], $opts['context'] ?? []);
    }
    unset($f);

    // Auto-resolve alert types whose condition is no longer present.
    $activeTypes = array_column($found, 'type');
    auto_resolve_cleared_alerts($activeTypes);

    return $found;
}

/**
 * Resolve open alerts whose condition cleared on the latest evaluation, so the
 * board self-cleans once things recover.
 */
function auto_resolve_cleared_alerts(array $stillActiveTypes): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    $managed = ['provider_down', 'run_failed', 'error_streak', 'throttle', 'stale'];
    $clear = array_values(array_diff($managed, $stillActiveTypes));
    if (empty($clear)) {
        return;
    }
    try {
        $in = implode(',', array_fill(0, count($clear), '?'));
        $stmt = $pdo->prepare("UPDATE pipeline_alerts SET status = 'resolved', resolved_at = NOW()
            WHERE status = 'open' AND type IN ($in)");
        $stmt->execute($clear);
    } catch (Throwable $exception) {
    }
}

/**
 * Email open alerts that haven't been notified yet (if an email is configured
 * and a real provider is ready). Marks them notified. Returns count sent.
 */
function dispatch_pipeline_alerts(): int
{
    $settings = pipeline_alert_settings();
    $email = (string) $settings['alert_email'];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 0;
    }
    if (!function_exists('email_provider_status') || empty(email_provider_status()['ready'])) {
        return 0;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $rows = $pdo->query("SELECT id, level, type, message, created_at FROM pipeline_alerts
            WHERE status = 'open' AND notified = 0 ORDER BY id DESC LIMIT 20")->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return 0;
    }
    if (empty($rows)) {
        return 0;
    }
    $levels = pipeline_alert_levels();
    $items = '';
    $ids = [];
    foreach ($rows as $r) {
        $ids[] = (int) $r['id'];
        $tag = $levels[$r['level']] ?? $r['level'];
        $items .= '<li style="margin:0 0 10px"><strong>[' . htmlspecialchars($tag) . ']</strong> '
            . htmlspecialchars((string) $r['message']) . '<br><small style="color:#888">' . htmlspecialchars((string) $r['created_at']) . '</small></li>';
    }
    $url = function_exists('site_meta') ? rtrim((string) (site_meta()['base_url'] ?? ''), '/') : '';
    $html = '<div style="font-family:system-ui,sans-serif;max-width:560px">'
        . '<h2 style="margin:0 0 4px">钱潮 Money Tide · 流水线告警</h2>'
        . '<p style="color:#555;margin:0 0 16px">自动新闻流水线检测到以下需要关注的情况：</p>'
        . '<ul style="padding-left:18px">' . $items . '</ul>'
        . ($url !== '' ? '<p style="margin-top:18px"><a href="' . htmlspecialchars($url) . '/admin/pipeline-alerts">打开告警台 →</a></p>' : '')
        . '<p style="color:#999;font-size:12px;margin-top:20px">本邮件为系统自动发送。告警仅供参考，不会自动发布或群发任何内容。</p></div>';

    $result = send_email_via_provider($email, '【钱潮】流水线告警 · ' . count($rows) . ' 条', $html);
    if (!empty($result['ok'])) {
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE pipeline_alerts SET notified = 1 WHERE id IN ($in)")->execute($ids);
        } catch (Throwable $exception) {
        }
        return count($ids);
    }
    return 0;
}

function pipeline_alerts(array $filters = []): array
{
    ensure_pipeline_alerts_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $where = [];
    $params = [];
    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && in_array($status, ['open', 'acknowledged', 'resolved'], true)) {
        $where[] = 'status = :s';
        $params['s'] = $status;
    }
    $sql = 'SELECT * FROM pipeline_alerts';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY (status = "open") DESC, id DESC LIMIT 60';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['context'] = json_decode((string) ($row['context'] ?? '{}'), true) ?: [];
    }
    return $rows;
}

function pipeline_alert_summary(): array
{
    ensure_pipeline_alerts_schema();
    $pdo = db();
    $out = ['open' => 0, 'critical_open' => 0, 'warn_open' => 0, 'resolved' => 0, 'total' => 0];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['open'] = (int) $pdo->query("SELECT COUNT(*) FROM pipeline_alerts WHERE status = 'open'")->fetchColumn();
        $out['critical_open'] = (int) $pdo->query("SELECT COUNT(*) FROM pipeline_alerts WHERE status = 'open' AND level = 'critical'")->fetchColumn();
        $out['warn_open'] = (int) $pdo->query("SELECT COUNT(*) FROM pipeline_alerts WHERE status = 'open' AND level = 'warn'")->fetchColumn();
        $out['resolved'] = (int) $pdo->query("SELECT COUNT(*) FROM pipeline_alerts WHERE status = 'resolved'")->fetchColumn();
        $out['total'] = (int) $pdo->query("SELECT COUNT(*) FROM pipeline_alerts")->fetchColumn();
    } catch (Throwable $exception) {
    }
    return $out;
}

function set_pipeline_alert_status(int $id, string $status): bool
{
    if (!in_array($status, ['open', 'acknowledged', 'resolved'], true)) {
        return false;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $resolved = $status === 'resolved' ? 'NOW()' : 'NULL';
        $pdo->prepare("UPDATE pipeline_alerts SET status = :s, resolved_at = $resolved WHERE id = :id")
            ->execute(['s' => $status, 'id' => $id]);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}
