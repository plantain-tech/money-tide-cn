<?php

declare(strict_types=1);

/**
 * AI draft quality scoring + risk warning heuristics (Week 5 Day 3-4).
 * Local heuristics first; no AI quota burned. Used by both ai_drafts queue
 * and the article edit page warning panel.
 */

function ai_draft_status_options(): array
{
    // Full editorial pipeline. Order matters: it's the stage progression.
    return [
        'idea' => '选题',
        'briefed' => '已立项',
        'generated' => '已起草',
        'needs_review' => '待编辑审核',
        'reviewed' => '已审核',
        'approved' => '已批准',
        'converted' => '已转为文章',
        'rejected' => '已拒绝',
    ];
}

function ai_draft_status_label(string $status): string
{
    $options = ai_draft_status_options();
    return $options[$status] ?? $status;
}

function ensure_ai_quality_columns(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    // Extend the status enum non-destructively. Old values stay valid.
    try {
        $pdo->exec("ALTER TABLE ai_drafts MODIFY status ENUM(
            'idea','briefed','generated','needs_review','reviewed','approved','converted','rejected','accepted'
        ) NOT NULL DEFAULT 'generated'");
    } catch (Throwable $exception) {
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM ai_drafts LIKE 'quality_score'")->fetchAll();
        if (!$cols) {
            $pdo->exec("ALTER TABLE ai_drafts ADD COLUMN quality_score TINYINT NULL AFTER status");
        }
    } catch (Throwable $exception) {
    }
}

function ai_risk_categories(): array
{
    return [
        'investment_advice' => '投资建议风险',
        'unsupported_number' => '未核查的数字',
        'missing_source' => '缺少来源',
        'exaggerated_claim' => '夸大表述',
        'outdated_context' => '过时背景',
        'missing_disclaimer' => '缺少免责声明',
    ];
}

function ai_claim_types(): array
{
    return [
        'numbers' => '关键数字',
        'companies' => '公司与机构',
        'market_claims' => '市场判断',
        'policy_claims' => '政策与监管',
    ];
}

/**
 * Compute a 0-100 quality score from the draft payload itself, no AI call.
 * 100 = excellent, 60 = passable, <40 = needs work.
 */
function ai_draft_quality_score(array $draft): int
{
    $payload = is_array($draft['draft_payload'] ?? null) ? $draft['draft_payload'] : [];
    $score = 0;
    $maxScore = 100;

    // Required field presence (40 pts).
    foreach (['title' => 8, 'dek' => 8, 'brief' => 8, 'why_it_matters' => 8] as $field => $weight) {
        if (trim((string) ($payload[$field] ?? '')) !== '') {
            $score += $weight;
        }
    }
    // body must exist and be long enough (24 pts).
    $body = $payload['body'] ?? [];
    if (!is_array($body)) {
        $body = [(string) $body];
    }
    $bodyText = implode("\n\n", array_map('strval', $body));
    $charCount = mb_strlen(strip_tags($bodyText), 'UTF-8');
    if ($charCount >= 80) {
        $score += 8;
    }
    if ($charCount >= 200) {
        $score += 8;
    }
    if ($charCount >= 400) {
        $score += 8;
    }

    // Sources (15 pts).
    $sources = $draft['source_links'] ?? [];
    if (!is_array($sources)) {
        $sources = [];
    }
    if (count($sources) >= 1) {
        $score += 8;
    }
    if (count($sources) >= 3) {
        $score += 7;
    }

    // Editorial signals — risk/source notes present (10 pts).
    if (!empty($payload['source_notes'])) {
        $score += 5;
    }
    if (!empty($payload['risk_notes'])) {
        $score += 5;
    }

    // No raw AI editor leakage (5 pts).
    $forbidden = ['AI-assisted', 'editor note', 'verify sources'];
    $leak = false;
    foreach ($forbidden as $needle) {
        if (stripos($bodyText, $needle) !== false) {
            $leak = true;
            break;
        }
    }
    if (!$leak) {
        $score += 5;
    }

    return min($maxScore, max(0, $score));
}

function ai_draft_warnings(array $draft): array
{
    $payload = is_array($draft['draft_payload'] ?? null) ? $draft['draft_payload'] : [];
    $body = $payload['body'] ?? [];
    if (!is_array($body)) {
        $body = [(string) $body];
    }
    $bodyText = implode("\n\n", array_map('strval', $body));
    $sources = $draft['source_links'] ?? [];
    if (!is_array($sources)) {
        $sources = [];
    }

    $warnings = [];

    if (!$sources) {
        $warnings[] = ['type' => 'missing_source', 'severity' => 'high', 'message' => '缺少来源链接。至少需要一个可信来源才能发布。'];
    } elseif (count($sources) === 1) {
        $warnings[] = ['type' => 'missing_source', 'severity' => 'low', 'message' => '只有一个来源。考虑加入第二个独立来源以提高可信度。'];
    }

    // Investment-advice tone detection (Chinese + English).
    $advicePatterns = ['/建议买入/u', '/建议卖出/u', '/应该买/u', '/应该卖/u', '/必涨/u', '/必跌/u', '/\bbuy now\b/i', '/\bsell now\b/i'];
    foreach ($advicePatterns as $pattern) {
        if (preg_match($pattern, $bodyText)) {
            $warnings[] = ['type' => 'investment_advice', 'severity' => 'high', 'message' => '正文出现"建议买入/卖出"等表述，可能被理解为投资建议。'];
            break;
        }
    }

    // Exaggeration patterns.
    $exaggerationPatterns = ['/史无前例/u', '/百分百/u', '/绝对/u', '/最强大/u', '/\bguaranteed\b/i'];
    foreach ($exaggerationPatterns as $pattern) {
        if (preg_match($pattern, $bodyText)) {
            $warnings[] = ['type' => 'exaggerated_claim', 'severity' => 'medium', 'message' => '出现绝对化或夸大词汇，建议改写为更中立的表述。'];
            break;
        }
    }

    // Numbers without context — naive: percentages and large numbers count.
    if (preg_match_all('/\d+[\.\d]*\s*[%％]|\d{2,}\s*[亿万]/u', $bodyText, $matches)) {
        $numCount = count($matches[0]);
        if ($numCount >= 3 && empty($payload['source_notes'])) {
            $warnings[] = ['type' => 'unsupported_number', 'severity' => 'medium', 'message' => '正文中出现 ' . $numCount . ' 个百分比或大数字。请在 source_notes 里逐一核对来源。'];
        }
    }

    // Disclaimer — production normalize_article_input adds it on publish, but flag at draft stage.
    if (stripos($bodyText, '本文内容仅供参考') === false && stripos($bodyText, '不构成投资建议') === false) {
        $warnings[] = ['type' => 'missing_disclaimer', 'severity' => 'low', 'message' => '正文未包含合规免责声明。发布时会自动追加，但提前包含更清晰。'];
    }

    // AI editor leakage.
    if (stripos($bodyText, 'AI-assisted') !== false || stripos($bodyText, 'editor note') !== false) {
        $warnings[] = ['type' => 'outdated_context', 'severity' => 'medium', 'message' => '检测到 AI 编辑备注泄漏，请清理后再转为文章。'];
    }

    return $warnings;
}

/**
 * Warnings for an already-saved article (used on article edit page).
 * Same heuristics, different source shape.
 */
function article_warnings(array $article): array
{
    $body = $article['body'] ?? '';
    $paragraphs = is_array($body) ? $body : (json_decode((string) $body, true) ?: preg_split('/\R{2,}/', trim((string) $body)) ?: []);
    if (!is_array($paragraphs)) {
        $paragraphs = [(string) $paragraphs];
    }
    $bodyText = implode("\n\n", array_map('strval', $paragraphs));

    $warnings = [];

    if (trim((string) ($article['why_it_matters'] ?? '')) === '') {
        $warnings[] = ['type' => 'missing_source', 'severity' => 'medium', 'message' => '"为什么重要" 还没填写。'];
    }

    $advicePatterns = ['/建议买入/u', '/建议卖出/u', '/应该买/u', '/应该卖/u', '/必涨/u', '/必跌/u', '/\bbuy now\b/i', '/\bsell now\b/i'];
    foreach ($advicePatterns as $pattern) {
        if (preg_match($pattern, $bodyText)) {
            $warnings[] = ['type' => 'investment_advice', 'severity' => 'high', 'message' => '正文中出现"建议买入/卖出"等表述。这会被视为投资建议，需要改写。'];
            break;
        }
    }

    $exaggerationPatterns = ['/史无前例/u', '/百分百/u', '/绝对/u', '/最强大/u', '/\bguaranteed\b/i'];
    foreach ($exaggerationPatterns as $pattern) {
        if (preg_match($pattern, $bodyText)) {
            $warnings[] = ['type' => 'exaggerated_claim', 'severity' => 'medium', 'message' => '出现绝对化或夸大词汇，建议改为更中立的表述。'];
            break;
        }
    }

    if (preg_match_all('/\d+[\.\d]*\s*[%％]|\d{2,}\s*[亿万]/u', $bodyText, $matches)) {
        $numCount = count($matches[0]);
        if ($numCount >= 3) {
            $warnings[] = ['type' => 'unsupported_number', 'severity' => 'low', 'message' => '正文中出现 ' . $numCount . ' 个百分比或大数字。发布前确认每个都有可追溯来源。'];
        }
    }

    if (stripos($bodyText, '本文内容仅供参考') === false && (string) ($article['status'] ?? '') !== 'published') {
        $warnings[] = ['type' => 'missing_disclaimer', 'severity' => 'low', 'message' => '正文目前没有免责声明。系统会在发布时自动追加，无需手工添加。'];
    }

    if (stripos($bodyText, 'AI-assisted') !== false || stripos($bodyText, 'editor note') !== false) {
        $warnings[] = ['type' => 'outdated_context', 'severity' => 'high', 'message' => '正文里出现了 AI 编辑备注，请清理。'];
    }

    return $warnings;
}

function ensure_article_claims_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS article_claims (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id INT UNSIGNED NOT NULL,
            claim_type ENUM('numbers','companies','market_claims','policy_claims') NOT NULL,
            content VARCHAR(500) NOT NULL,
            source_url VARCHAR(500) NULL,
            status ENUM('unverified','verified','disputed') NOT NULL DEFAULT 'unverified',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_claims_article (article_id, claim_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function article_claims(int $articleId): array
{
    ensure_article_claims_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare("SELECT id, claim_type, content, source_url, status, created_at FROM article_claims WHERE article_id = :id ORDER BY claim_type ASC, id DESC");
        $statement->execute(['id' => $articleId]);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function extract_article_claims_locally(array $article): array
{
    // Local heuristic — no AI quota burned. Returns proposed claims grouped by type.
    $body = $article['body'] ?? '';
    $paragraphs = is_array($body) ? $body : (json_decode((string) $body, true) ?: preg_split('/\R{2,}/', trim((string) $body)) ?: []);
    if (!is_array($paragraphs)) {
        $paragraphs = [(string) $paragraphs];
    }
    $bodyText = implode("\n\n", array_map('strval', $paragraphs));

    $out = ['numbers' => [], 'companies' => [], 'market_claims' => [], 'policy_claims' => []];

    if (preg_match_all('/[^\s。，；！？]{0,8}\d+[\.\d]*\s*[%％亿万千]?[^\s。，；！？]{0,8}/u', $bodyText, $matches)) {
        foreach (array_unique(array_slice($matches[0], 0, 12)) as $hit) {
            $hit = trim((string) $hit);
            if ($hit !== '' && preg_match('/\d/', $hit)) {
                $out['numbers'][] = $hit;
            }
        }
    }
    if (preg_match_all('/[A-Z][A-Za-z]{2,}(?:\s+[A-Z][A-Za-z]{1,})*/', $bodyText, $matches)) {
        foreach (array_unique(array_slice($matches[0], 0, 12)) as $hit) {
            $hit = trim((string) $hit);
            $skip = ['AI', 'CEO', 'IPO', 'GDP', 'USD', 'CNY', 'ETF', 'API'];
            if (!in_array($hit, $skip, true) && mb_strlen($hit, 'UTF-8') >= 4) {
                $out['companies'][] = $hit;
            }
        }
    }
    foreach ($paragraphs as $p) {
        $p = trim((string) $p);
        if ($p === '') {
            continue;
        }
        if (preg_match('/(估值|上涨|下跌|涨幅|跌幅|创新高|创新低|走势|盘整|反弹)/u', $p)) {
            $out['market_claims'][] = mb_substr($p, 0, 120, 'UTF-8');
        } elseif (preg_match('/(监管|政策|法案|规定|央行|证监会|发改委|商务部|工信部)/u', $p)) {
            $out['policy_claims'][] = mb_substr($p, 0, 120, 'UTF-8');
        }
        if (count($out['market_claims']) >= 6 && count($out['policy_claims']) >= 6) {
            break;
        }
    }
    return $out;
}

function save_article_claim(int $articleId, string $type, string $content, string $sourceUrl = ''): array
{
    ensure_article_claims_schema();
    if (!in_array($type, array_keys(ai_claim_types()), true)) {
        return ['ok' => false, 'message' => '未知 claim 类型。'];
    }
    $content = trim($content);
    if ($content === '') {
        return ['ok' => false, 'message' => '内容不能为空。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $statement = $pdo->prepare('INSERT INTO article_claims (article_id, claim_type, content, source_url) VALUES (:aid, :type, :content, :src)');
        $statement->execute(['aid' => $articleId, 'type' => $type, 'content' => mb_substr($content, 0, 500, 'UTF-8'), 'src' => $sourceUrl !== '' ? $sourceUrl : null]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function update_article_claim_status(int $claimId, string $status): bool
{
    if (!in_array($status, ['unverified', 'verified', 'disputed'], true)) {
        return false;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('UPDATE article_claims SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $claimId]);
    } catch (Throwable $exception) {
        return false;
    }
}

function delete_article_claim(int $claimId): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM article_claims WHERE id = :id')->execute(['id' => $claimId]);
    } catch (Throwable $exception) {
        return false;
    }
}
