<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·4 — Fact-check gate (the human 5%).
 *
 * After synthesis, an AI "fact-check editor" scores each draft's confidence
 * (0-100) and flags risky / unverifiable claims. Routing:
 *   confidence >= threshold AND no HIGH-severity flag  -> auto-approve
 *   otherwise                                          -> human review queue
 *
 * The human queue is the 5%: one-click 批准 / 退回. Builds on the existing
 * ai_drafts statuses (generated -> needs_review -> approved/rejected).
 */

function ensure_auto_review_schema(): void
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
        $pdo->exec("CREATE TABLE IF NOT EXISTS auto_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            draft_id INT UNSIGNED NOT NULL,
            confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
            recommendation ENUM('auto_approve','needs_review') NOT NULL DEFAULT 'needs_review',
            flags LONGTEXT NULL,
            assessment TEXT NULL,
            decision ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            decided_by VARCHAR(160) NULL,
            decided_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_draft (draft_id),
            INDEX idx_review_state (recommendation, decision, confidence)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

/**
 * Confidence threshold for auto-approval. Configurable via secret
 * AI_REVIEW_THRESHOLD (config ai.review_threshold), default 80.
 */
function auto_review_threshold(): int
{
    // Live override from the autopilot/finale UI (pipeline_settings) takes
    // precedence over the AI_REVIEW_THRESHOLD secret so the owner can tune it
    // without a redeploy. 0/unset = fall back to the secret/default.
    if (function_exists('pipeline_setting')) {
        $override = (int) pipeline_setting('review_threshold', '0');
        if ($override > 0) {
            return max(50, min(100, $override));
        }
    }
    $t = (int) app_config('ai.review_threshold', 80);
    return max(50, min(100, $t > 0 ? $t : 80));
}

function auto_review_severity_labels(): array
{
    return ['high' => '高风险', 'medium' => '中等', 'low' => '轻微'];
}

/**
 * AI fact-check assessment of one draft. Stores result + auto-routes status.
 */
function assess_draft(int $draftId): array
{
    ensure_auto_review_schema();
    $draft = ai_draft_by_id($draftId);
    if (!$draft) {
        return ['ok' => false, 'code' => 'missing', 'message' => '草稿不存在。'];
    }
    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'code' => 'provider', 'message' => $provider['message']];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'code' => 'quota', 'message' => '今日 AI 额度已用完。'];
    }

    $p = is_array($draft['draft_payload'] ?? null) ? $draft['draft_payload'] : [];
    $title = (string) ($p['title'] ?? '');
    $body = is_array($p['body'] ?? null) ? implode("\n", array_map('strval', $p['body'])) : (string) ($p['body'] ?? '');
    $articleText = "标题：{$title}\n"
        . "副标题：" . (string) ($p['dek'] ?? '') . "\n"
        . "一句话看懂：" . (string) ($p['brief'] ?? '') . "\n"
        . "为什么重要：" . (string) ($p['why_it_matters'] ?? '') . "\n"
        . "正文：\n" . mb_substr($body, 0, 4000, 'UTF-8');
    $sourceList = implode("\n", array_map('strval', (array) ($draft['source_links'] ?? [])));

    $prompt = "你是钱潮 Money Tide 的事实核查主编。下面是一篇由 AI 生成、等待发布的中文文章草稿及其声称的来源链接。\n\n"
        . "【文章】\n" . $articleText . "\n\n"
        . "【来源链接】\n" . ($sourceList !== '' ? $sourceList : '（无）') . "\n\n"
        . "请像严格的事实核查编辑那样判断这篇文章能否直接发布，给出：\n"
        . "1) confidence：0-100 的整数，表示你对「文章事实准确、有来源支撑、无高风险表述、可直接发布」的信心。\n"
        . "2) flags：需要人工核查的具体点的数组，每项 {\"claim\":\"原文里的具体句子或数字\",\"severity\":\"high|medium|low\",\"note\":\"为什么需要核查\"}。"
        . "重点关注：未核实/可能编造的数字、投资建议、夸大或绝对化表述、缺少来源支撑的断言、可能过时的事实。没有问题则返回空数组。\n"
        . "3) assessment：一句话总体结论。\n"
        . "严格只返回 JSON：{\"confidence\":数字,\"flags\":[{\"claim\":\"…\",\"severity\":\"…\",\"note\":\"…\"}],\"assessment\":\"…\"}";

    $resp = call_simple_json_api($prompt, ['confidence']);
    log_ai_usage($provider['provider'], $provider['model'], 'review-' . (string) ($draft['section_slug'] ?? ''), strlen($prompt), $resp['ok'] ? 'ok' : 'error', $resp['ok'] ? '' : ($resp['message'] ?? ''));
    if (!$resp['ok']) {
        return ['ok' => false, 'code' => 'ai_error', 'message' => $resp['message'] ?? 'AI 审核失败。'];
    }

    $payload = $resp['payload'];
    $confidence = max(0, min(100, (int) round((float) ($payload['confidence'] ?? 0))));
    $flags = is_array($payload['flags'] ?? null) ? $payload['flags'] : [];
    $normFlags = [];
    foreach ($flags as $f) {
        if (!is_array($f)) {
            continue;
        }
        $sev = strtolower((string) ($f['severity'] ?? 'medium'));
        if (!in_array($sev, ['high', 'medium', 'low'], true)) {
            $sev = 'medium';
        }
        $claim = mb_substr(trim((string) ($f['claim'] ?? '')), 0, 300, 'UTF-8');
        if ($claim === '') {
            continue;
        }
        $normFlags[] = ['claim' => $claim, 'severity' => $sev, 'note' => mb_substr(trim((string) ($f['note'] ?? '')), 0, 300, 'UTF-8')];
    }
    $assessment = mb_substr(trim((string) ($payload['assessment'] ?? '')), 0, 500, 'UTF-8');

    $hasHigh = false;
    foreach ($normFlags as $f) {
        if ($f['severity'] === 'high') {
            $hasHigh = true;
            break;
        }
    }
    $threshold = auto_review_threshold();
    $recommendation = ($confidence >= $threshold && !$hasHigh) ? 'auto_approve' : 'needs_review';
    $decision = $recommendation === 'auto_approve' ? 'approved' : 'pending';
    $decidedBy = $recommendation === 'auto_approve' ? 'ai' : null;
    $decidedAt = $recommendation === 'auto_approve' ? date('Y-m-d H:i:s') : null;

    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("INSERT INTO auto_reviews (draft_id, confidence, recommendation, flags, assessment, decision, decided_by, decided_at)
                VALUES (:d, :c, :r, :f, :a, :dec, :by, :at)
                ON DUPLICATE KEY UPDATE confidence = VALUES(confidence), recommendation = VALUES(recommendation),
                    flags = VALUES(flags), assessment = VALUES(assessment), decision = VALUES(decision),
                    decided_by = VALUES(decided_by), decided_at = VALUES(decided_at)");
            $stmt->execute([
                'd' => $draftId,
                'c' => $confidence,
                'r' => $recommendation,
                'f' => json_encode($normFlags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'a' => $assessment,
                'dec' => $decision,
                'by' => $decidedBy,
                'at' => $decidedAt,
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'code' => 'db', 'message' => '保存审核结果失败：' . $exception->getMessage()];
        }
    }

    // Auto-route the draft status.
    update_ai_draft_status($draftId, $recommendation === 'auto_approve' ? 'approved' : 'needs_review');

    if (function_exists('record_event')) {
        record_event('draft_assessed', ['slug' => (string) ($draft['section_slug'] ?? ''), 'source' => $recommendation . ':' . $confidence]);
    }

    return [
        'ok' => true,
        'code' => 'ok',
        'recommendation' => $recommendation,
        'confidence' => $confidence,
        'flags' => count($normFlags),
        'message' => ($recommendation === 'auto_approve' ? '✅ 自动通过' : '🔎 转人工审核') . '（置信度 ' . $confidence . '，' . count($normFlags) . ' 个标记）',
    ];
}

/**
 * Batch-assess freshly generated drafts that have no review yet.
 * Paced + retried for free-tier rate limits.
 */
function assess_pending_drafts(int $limit = 8, int $pauseSec = 2, int $maxTries = 2): array
{
    ensure_auto_review_schema();
    $pdo = db();
    $summary = ['total' => 0, 'auto' => 0, 'review' => 0, 'failed' => 0, 'details' => []];
    if (!$pdo instanceof PDO) {
        return $summary;
    }
    try {
        $rows = $pdo->query("SELECT d.id, d.draft_payload FROM ai_drafts d
            LEFT JOIN auto_reviews ar ON ar.draft_id = d.id
            WHERE d.status = 'generated' AND ar.id IS NULL
            ORDER BY d.id DESC LIMIT " . max(1, min(20, $limit)))->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return $summary;
    }

    foreach ($rows as $row) {
        if (!ai_usage_allowed()) {
            $summary['details'][] = ['id' => (int) $row['id'], 'ok' => false, 'message' => 'AI 额度用完，已停止。'];
            $summary['failed']++;
            break;
        }
        $payload = json_decode((string) ($row['draft_payload'] ?? '{}'), true) ?: [];
        $title = (string) ($payload['title'] ?? ('草稿 #' . (int) $row['id']));

        $attempt = 1;
        $r = ['ok' => false, 'code' => 'unknown', 'message' => '未执行'];
        while ($attempt <= $maxTries) {
            $r = assess_draft((int) $row['id']);
            if ($r['ok'] || in_array((string) ($r['code'] ?? ''), ['missing', 'provider'], true)) {
                break;
            }
            if ((string) ($r['code'] ?? '') === 'quota') {
                break 2;
            }
            if ($attempt < $maxTries) {
                sleep($attempt * 3);
            }
            $attempt++;
        }

        $summary['total']++;
        if ($r['ok']) {
            ($r['recommendation'] ?? '') === 'auto_approve' ? $summary['auto']++ : $summary['review']++;
        } else {
            $summary['failed']++;
        }
        $summary['details'][] = ['id' => (int) $row['id'], 'title' => $title, 'ok' => $r['ok'], 'message' => $r['message']];

        if ($pauseSec > 0) {
            sleep($pauseSec);
        }
    }

    if (function_exists('record_event')) {
        record_event('draft_assess_batch', ['source' => $summary['auto'] . ' auto / ' . $summary['review'] . ' review']);
    }
    return $summary;
}

function auto_review_for_draft(int $draftId): ?array
{
    ensure_auto_review_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT * FROM auto_reviews WHERE draft_id = :d LIMIT 1');
        $st->execute(['d' => $draftId]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $row['flags'] = json_decode((string) ($row['flags'] ?? '[]'), true) ?: [];
        return $row;
    } catch (Throwable $exception) {
        return null;
    }
}

/**
 * The human queue: drafts the AI routed to needs_review, still pending.
 */
function review_queue(array $filters = []): array
{
    ensure_auto_review_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = "SELECT ar.*, d.section_slug, d.draft_payload, d.status AS draft_status
            FROM auto_reviews ar
            INNER JOIN ai_drafts d ON d.id = ar.draft_id
            WHERE ar.recommendation = 'needs_review' AND ar.decision = 'pending'";
    $params = [];
    if (!empty($filters['section_slug'])) {
        $sql .= ' AND d.section_slug = :slug';
        $params['slug'] = $filters['section_slug'];
    }
    $sql .= ' ORDER BY ar.confidence ASC, ar.id DESC LIMIT 100';
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
    foreach ($rows as &$row) {
        $payload = json_decode((string) ($row['draft_payload'] ?? '{}'), true) ?: [];
        $row['title'] = (string) ($payload['title'] ?? ('草稿 #' . (int) $row['draft_id']));
        $row['dek'] = (string) ($payload['dek'] ?? '');
        $row['flags'] = json_decode((string) ($row['flags'] ?? '[]'), true) ?: [];
    }
    return $rows;
}

/**
 * One-click human decision: approve -> draft approved; reject -> draft rejected.
 */
function record_human_decision(int $draftId, string $decision, string $note = ''): array
{
    ensure_auto_review_schema();
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['ok' => false, 'message' => '无效的决定。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    $user = function_exists('current_user') ? current_user() : null;
    $by = is_array($user) ? (string) ($user['email'] ?? 'admin') : 'admin';
    try {
        $pdo->prepare('UPDATE auto_reviews SET decision = :dec, decided_by = :by, decided_at = NOW() WHERE draft_id = :d')
            ->execute(['dec' => $decision, 'by' => $by, 'd' => $draftId]);
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '保存失败：' . $exception->getMessage()];
    }
    update_ai_draft_status($draftId, $decision === 'approved' ? 'approved' : 'rejected');
    if (function_exists('record_event')) {
        record_event('draft_human_decision', ['source' => $decision]);
    }
    return ['ok' => true, 'message' => $decision === 'approved' ? '已批准，进入已批准状态。' : '已退回。'];
}

function auto_review_summary(): array
{
    ensure_auto_review_schema();
    $pdo = db();
    $out = ['pending_review' => 0, 'auto_approved' => 0, 'human_approved' => 0, 'rejected' => 0, 'unassessed' => 0, 'avg_confidence' => 0, 'threshold' => auto_review_threshold()];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['pending_review'] = (int) $pdo->query("SELECT COUNT(*) FROM auto_reviews WHERE recommendation='needs_review' AND decision='pending'")->fetchColumn();
        $out['auto_approved'] = (int) $pdo->query("SELECT COUNT(*) FROM auto_reviews WHERE recommendation='auto_approve'")->fetchColumn();
        $out['human_approved'] = (int) $pdo->query("SELECT COUNT(*) FROM auto_reviews WHERE recommendation='needs_review' AND decision='approved'")->fetchColumn();
        $out['rejected'] = (int) $pdo->query("SELECT COUNT(*) FROM auto_reviews WHERE decision='rejected'")->fetchColumn();
        $out['unassessed'] = (int) $pdo->query("SELECT COUNT(*) FROM ai_drafts d LEFT JOIN auto_reviews ar ON ar.draft_id=d.id WHERE d.status='generated' AND ar.id IS NULL")->fetchColumn();
        $avg = $pdo->query('SELECT AVG(confidence) FROM auto_reviews')->fetchColumn();
        $out['avg_confidence'] = $avg !== null ? (int) round((float) $avg) : 0;
    } catch (Throwable $exception) {
    }
    return $out;
}
