<?php

declare(strict_types=1);

/**
 * Week 6 Day 5: "60秒看懂" short format.
 * One skimmable, shareable block per article: 1-line summary, 3 bullets,
 * key number, why it matters, risk note.
 */

function ensure_short_format_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS article_short_format (
            article_id INT UNSIGNED NOT NULL PRIMARY KEY,
            summary VARCHAR(280) NULL,
            bullets LONGTEXT NULL,
            key_number VARCHAR(120) NULL,
            why_it_matters VARCHAR(500) NULL,
            risk_note VARCHAR(500) NULL,
            generated_by VARCHAR(40) NULL,
            generated_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function short_format_for_article(int $articleId): ?array
{
    ensure_short_format_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM article_short_format WHERE article_id = :id LIMIT 1');
        $statement->execute(['id' => $articleId]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        $row['bullets'] = json_decode((string) ($row['bullets'] ?? '[]'), true) ?: [];
        return $row;
    } catch (Throwable $exception) {
        return null;
    }
}

function short_format_form_defaults(): array
{
    return [
        'summary' => '',
        'bullets' => ['', '', ''],
        'key_number' => '',
        'why_it_matters' => '',
        'risk_note' => '',
    ];
}

function save_short_format(int $articleId, array $input): array
{
    ensure_short_format_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    $bullets = $input['bullets'] ?? [];
    if (!is_array($bullets)) {
        $bullets = preg_split('/\R+/', (string) $bullets) ?: [];
    }
    $bullets = array_values(array_filter(array_map(static fn ($b) => trim((string) $b), $bullets), static fn ($b) => $b !== ''));
    $bullets = array_slice($bullets, 0, 5);

    try {
        $statement = $pdo->prepare("INSERT INTO article_short_format
            (article_id, summary, bullets, key_number, why_it_matters, risk_note)
            VALUES (:id, :summary, :bullets, :key_number, :why, :risk)
            ON DUPLICATE KEY UPDATE
                summary = VALUES(summary),
                bullets = VALUES(bullets),
                key_number = VALUES(key_number),
                why_it_matters = VALUES(why_it_matters),
                risk_note = VALUES(risk_note),
                updated_at = CURRENT_TIMESTAMP");
        $statement->execute([
            'id' => $articleId,
            'summary' => mb_substr(trim((string) ($input['summary'] ?? '')), 0, 280, 'UTF-8'),
            'bullets' => json_encode($bullets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'key_number' => mb_substr(trim((string) ($input['key_number'] ?? '')), 0, 120, 'UTF-8'),
            'why' => mb_substr(trim((string) ($input['why_it_matters'] ?? '')), 0, 500, 'UTF-8'),
            'risk' => mb_substr(trim((string) ($input['risk_note'] ?? '')), 0, 500, 'UTF-8'),
        ]);
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function delete_short_format(int $articleId): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM article_short_format WHERE article_id = :id')->execute(['id' => $articleId]);
    } catch (Throwable $exception) {
        return false;
    }
}

function generate_short_format(int $articleId): array
{
    $article = admin_article_by_id($articleId);
    if (!$article) {
        return ['ok' => false, 'message' => '文章不存在。'];
    }
    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'message' => $provider['message']];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'message' => '今日 AI 额度已用完。'];
    }

    $bodyParagraphs = json_decode((string) ($article['body'] ?? '[]'), true);
    if (!is_array($bodyParagraphs)) {
        $bodyParagraphs = preg_split('/\R{2,}/', trim((string) ($article['body'] ?? ''))) ?: [];
    }
    $bodyText = implode("\n\n", array_slice(array_map('strval', $bodyParagraphs), 0, 6));

    $prompt = "你是钱潮 Money Tide 的编辑。把下面这篇文章压缩成一个 60 秒看懂 的速读卡片。"
        . "\n要求克制、准确、不夸张，不出现投资建议或 必涨/必跌 类表述。"
        . "\n\n标题：{$article['title']}"
        . "\n副标题：{$article['dek']}"
        . "\n一句话看懂：{$article['brief']}"
        . "\n为什么重要：{$article['why_it_matters']}"
        . "\n正文摘录：\n{$bodyText}"
        . "\n\n严格按 JSON 返回："
        . "{\"summary\": \"一句话总结(不超过40字)\", "
        . "\"bullets\": [\"要点1\", \"要点2\", \"要点3\"], "
        . "\"key_number\": \"一个关键数字或事实(没有就空字符串)\", "
        . "\"why_it_matters\": \"为什么重要(2句)\", "
        . "\"risk_note\": \"读者需要注意的风险或不确定性(1句)\"}";

    $response = function_exists('call_simple_json_api')
        ? call_simple_json_api($prompt, ['summary', 'bullets'])
        : ['ok' => false, 'message' => 'AI helper unavailable'];

    log_ai_usage($provider['provider'], $provider['model'], 'short-format', strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));

    if (!$response['ok']) {
        return ['ok' => false, 'message' => $response['message'] ?? 'AI 调用失败。'];
    }
    $payload = $response['payload'];
    $bullets = $payload['bullets'] ?? [];
    if (!is_array($bullets)) {
        $bullets = [(string) $bullets];
    }

    ensure_short_format_schema();
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare("INSERT INTO article_short_format
                (article_id, summary, bullets, key_number, why_it_matters, risk_note, generated_by, generated_at)
                VALUES (:id, :summary, :bullets, :key_number, :why, :risk, 'ai', CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    summary = VALUES(summary), bullets = VALUES(bullets), key_number = VALUES(key_number),
                    why_it_matters = VALUES(why_it_matters), risk_note = VALUES(risk_note),
                    generated_by = 'ai', generated_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP");
            $statement->execute([
                'id' => $articleId,
                'summary' => mb_substr(trim((string) ($payload['summary'] ?? '')), 0, 280, 'UTF-8'),
                'bullets' => json_encode(array_slice(array_map(static fn ($b) => trim((string) $b), $bullets), 0, 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'key_number' => mb_substr(trim((string) ($payload['key_number'] ?? '')), 0, 120, 'UTF-8'),
                'why' => mb_substr(trim((string) ($payload['why_it_matters'] ?? '')), 0, 500, 'UTF-8'),
                'risk' => mb_substr(trim((string) ($payload['risk_note'] ?? '')), 0, 500, 'UTF-8'),
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => '保存失败：' . $exception->getMessage()];
        }
    }
    return ['ok' => true];
}

/**
 * Plain-text rendering for the admin copy/export button.
 */
function short_format_as_text(array $sf, array $article): string
{
    $lines = [];
    $lines[] = '【60秒看懂】' . (string) ($article['title'] ?? '');
    if (!empty($sf['summary'])) {
        $lines[] = '';
        $lines[] = (string) $sf['summary'];
    }
    if (!empty($sf['bullets'])) {
        $lines[] = '';
        foreach ((array) $sf['bullets'] as $b) {
            $b = trim((string) $b);
            if ($b !== '') {
                $lines[] = '· ' . $b;
            }
        }
    }
    if (!empty($sf['key_number'])) {
        $lines[] = '';
        $lines[] = '关键数字：' . (string) $sf['key_number'];
    }
    if (!empty($sf['why_it_matters'])) {
        $lines[] = '';
        $lines[] = '为什么重要：' . (string) $sf['why_it_matters'];
    }
    if (!empty($sf['risk_note'])) {
        $lines[] = '';
        $lines[] = '注意：' . (string) $sf['risk_note'];
    }
    $lines[] = '';
    $lines[] = '本文内容仅供参考，不构成投资建议。';
    return implode("\n", $lines);
}
