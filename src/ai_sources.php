<?php

declare(strict_types=1);

function ensure_ai_sources_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS source_profiles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(180) NOT NULL,
            url VARCHAR(500) NOT NULL,
            section_slug VARCHAR(120) NULL,
            credibility ENUM('trusted','standard','caution','blocked') NOT NULL DEFAULT 'standard',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sources_section (section_slug, credibility)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS source_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section_slug VARCHAR(120) NOT NULL,
            name VARCHAR(180) NOT NULL,
            topic_angle TEXT NULL,
            source_links TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_source_templates_section (section_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS research_briefs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section_slug VARCHAR(120) NOT NULL,
            topic_angle TEXT NOT NULL,
            source_links LONGTEXT NULL,
            brief_payload LONGTEXT NOT NULL,
            status ENUM('draft','used','archived') NOT NULL DEFAULT 'draft',
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_briefs_section (section_slug, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function source_credibility_options(): array
{
    return [
        'trusted' => '可信',
        'standard' => '常规',
        'caution' => '谨慎',
        'blocked' => '禁用',
    ];
}

function source_profiles(array $filters = []): array
{
    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT id, name, url, section_slug, credibility, notes, updated_at FROM source_profiles WHERE 1 = 1';
    $params = [];
    if (!empty($filters['section_slug'])) {
        $sql .= ' AND section_slug = :section_slug';
        $params['section_slug'] = $filters['section_slug'];
    }
    if (!empty($filters['credibility'])) {
        $sql .= ' AND credibility = :credibility';
        $params['credibility'] = $filters['credibility'];
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND (name LIKE :q_name OR url LIKE :q_url)';
        $params['q_name'] = '%' . $filters['q'] . '%';
        $params['q_url'] = '%' . $filters['q'] . '%';
    }
    $sql .= " ORDER BY FIELD(credibility,'trusted','standard','caution','blocked'), name ASC LIMIT 300";
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function source_profile_by_id(int $id): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM source_profiles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function save_source_profile(array $input, ?int $id = null): array
{
    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }
    $name = trim((string) ($input['name'] ?? ''));
    $url = trim((string) ($input['url'] ?? ''));
    $section = trim((string) ($input['section_slug'] ?? ''));
    $credibility = (string) ($input['credibility'] ?? 'standard');
    $notes = trim((string) ($input['notes'] ?? ''));
    if (!array_key_exists($credibility, source_credibility_options())) {
        $credibility = 'standard';
    }
    $errors = [];
    if ($name === '') {
        $errors[] = '名称不能为空。';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'URL 无效。';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }
    try {
        if ($id === null) {
            $statement = $pdo->prepare('INSERT INTO source_profiles (name, url, section_slug, credibility, notes)
                VALUES (:name, :url, :section, :credibility, :notes)');
            $statement->execute([
                'name' => $name,
                'url' => $url,
                'section' => $section !== '' ? $section : null,
                'credibility' => $credibility,
                'notes' => $notes,
            ]);
            return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
        }
        $statement = $pdo->prepare('UPDATE source_profiles SET name = :name, url = :url, section_slug = :section, credibility = :credibility, notes = :notes WHERE id = :id');
        $statement->execute([
            'name' => $name,
            'url' => $url,
            'section' => $section !== '' ? $section : null,
            'credibility' => $credibility,
            'notes' => $notes,
            'id' => $id,
        ]);
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存失败：' . $exception->getMessage()]];
    }
}

function delete_source_profile(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM source_profiles WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

function source_templates(array $filters = []): array
{
    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT id, section_slug, name, topic_angle, source_links, updated_at FROM source_templates WHERE 1 = 1';
    $params = [];
    if (!empty($filters['section_slug'])) {
        $sql .= ' AND section_slug = :section_slug';
        $params['section_slug'] = $filters['section_slug'];
    }
    $sql .= ' ORDER BY section_slug ASC, name ASC LIMIT 200';
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function source_template_by_id(int $id): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM source_templates WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function save_source_template(array $input, ?int $id = null): array
{
    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }
    $name = trim((string) ($input['name'] ?? ''));
    $section = trim((string) ($input['section_slug'] ?? ''));
    $topic = trim((string) ($input['topic_angle'] ?? ''));
    $links = trim((string) ($input['source_links'] ?? ''));
    $errors = [];
    if ($name === '') {
        $errors[] = '模板名称不能为空。';
    }
    if ($section === '') {
        $errors[] = '请选择栏目。';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }
    try {
        if ($id === null) {
            $statement = $pdo->prepare('INSERT INTO source_templates (section_slug, name, topic_angle, source_links)
                VALUES (:section, :name, :topic, :links)');
            $statement->execute([
                'section' => $section,
                'name' => $name,
                'topic' => $topic,
                'links' => $links,
            ]);
            return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
        }
        $statement = $pdo->prepare('UPDATE source_templates SET section_slug = :section, name = :name, topic_angle = :topic, source_links = :links WHERE id = :id');
        $statement->execute([
            'section' => $section,
            'name' => $name,
            'topic' => $topic,
            'links' => $links,
            'id' => $id,
        ]);
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存失败：' . $exception->getMessage()]];
    }
}

function delete_source_template(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM source_templates WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

function research_briefs(array $filters = []): array
{
    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT id, section_slug, topic_angle, status, created_at FROM research_briefs WHERE 1 = 1';
    $params = [];
    if (!empty($filters['section_slug'])) {
        $sql .= ' AND section_slug = :section_slug';
        $params['section_slug'] = $filters['section_slug'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND status = :status';
        $params['status'] = $filters['status'];
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 100';
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function research_brief_by_id(int $id): ?array
{
    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM research_briefs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        $row['source_links'] = json_decode((string) ($row['source_links'] ?? '[]'), true) ?: [];
        $row['brief_payload'] = json_decode((string) ($row['brief_payload'] ?? '{}'), true) ?: [];
        return $row;
    } catch (Throwable $exception) {
        return null;
    }
}

function generate_research_brief(array $input): array
{
    $sectionSlug = trim((string) ($input['section_slug'] ?? ''));
    $topic = trim((string) ($input['topic_angle'] ?? ''));
    $rawLinks = (string) ($input['source_links'] ?? '');

    if ($sectionSlug === '') {
        return ['ok' => false, 'errors' => ['请选择栏目。']];
    }
    if ($topic === '') {
        return ['ok' => false, 'errors' => ['请输入选题角度。']];
    }
    $sources = function_exists('normalize_source_links') ? normalize_source_links($rawLinks) : [];
    if (!$sources) {
        return ['ok' => false, 'errors' => ['至少提供一个有效来源链接。']];
    }

    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'errors' => [$provider['message']]];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'errors' => ['今日 AI 额度已用完。']];
    }

    $templates = editorial_bot_templates();
    $bot = $templates[$sectionSlug] ?? ['name' => $sectionSlug, 'prompt' => ''];
    $prompt = "You are {$bot['name']} for Money Tide, a Chinese financial news product.\n"
        . "Section mission: {$bot['prompt']}\n"
        . "Topic angle: {$topic}\n"
        . "Source links:\n- " . implode("\n- ", $sources) . "\n\n"
        . "Produce a research brief in Simplified Chinese to help a human editor write the article."
        . " Do NOT write the article. Only output a structured brief."
        . (function_exists('ai_proper_noun_rule') ? ai_proper_noun_rule() : '');

    $response = call_research_brief_api($prompt);
    log_ai_usage($provider['provider'], $provider['model'], $sectionSlug, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));

    if (!$response['ok']) {
        return ['ok' => false, 'errors' => [$response['message'] ?? 'AI 调用失败。']];
    }

    $payload = normalize_research_brief_payload($response['payload']);

    ensure_ai_sources_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }
    try {
        $userId = (int) (current_user()['id'] ?? 0);
        $statement = $pdo->prepare('INSERT INTO research_briefs (section_slug, topic_angle, source_links, brief_payload, created_by_user_id)
            VALUES (:section, :topic, :links, :payload, :user)');
        $statement->execute([
            'section' => $sectionSlug,
            'topic' => $topic,
            'links' => json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user' => $userId > 0 ? $userId : null,
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存研究简报失败：' . $exception->getMessage()]];
    }
}

function normalize_research_brief_payload(array $payload): array
{
    $defaults = [
        'summary' => '',
        'key_facts' => [],
        'numbers' => [],
        'quotes' => [],
        'risks' => [],
        'angles' => [],
        'questions' => [],
        'recommended_dek' => '',
        'recommended_title' => '',
    ];
    $payload = array_replace($defaults, $payload);
    foreach (['key_facts', 'numbers', 'quotes', 'risks', 'angles', 'questions'] as $field) {
        if (!is_array($payload[$field])) {
            $payload[$field] = $payload[$field] === '' ? [] : [(string) $payload[$field]];
        }
        $payload[$field] = array_values(array_filter(array_map(static fn ($x): string => trim((string) $x), $payload[$field])));
    }
    return $payload;
}

function call_research_brief_api(string $prompt): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'cURL not available'];
    }
    $provider = (string) app_config('ai.provider', 'ollama_cloud');
    $model = (string) app_config('ai.model', 'gemma4:31b-cloud');
    $apiKey = $provider === 'openai'
        ? (string) app_config('ai.api_key', '')
        : (string) app_config('ai.ollama_api_key', '');

    $jsonSpec = "\n\nReturn strict JSON only. Required shape: {"
        . "\"summary\": \"...\","
        . " \"key_facts\": [\"...\"],"
        . " \"numbers\": [\"...\"],"
        . " \"quotes\": [\"...\"],"
        . " \"risks\": [\"...\"],"
        . " \"angles\": [\"...\"],"
        . " \"questions\": [\"...\"],"
        . " \"recommended_title\": \"...\","
        . " \"recommended_dek\": \"...\""
        . "}";

    if ($provider === 'openai') {
        $payload = [
            'model' => $model,
            'input' => $prompt . $jsonSpec,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'money_tide_research_brief',
                    'strict' => true,
                    'schema' => research_brief_json_schema(),
                ],
            ],
        ];
        $endpoint = 'https://api.openai.com/v1/responses';
    } else {
        $payload = [
            'model' => $model,
            'stream' => false,
            'format' => 'json',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a careful Chinese financial research assistant. Return strict JSON only.'],
                ['role' => 'user', 'content' => $prompt . $jsonSpec],
            ],
        ];
        $endpoint = 'https://ollama.com/api/chat';
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $status >= 400) {
        return ['ok' => false, 'message' => 'Research brief API failed: ' . ($error ?: 'HTTP ' . $status)];
    }
    $decoded = json_decode((string) $raw, true);
    if ($provider === 'openai') {
        $text = function_exists('extract_response_text') ? extract_response_text(is_array($decoded) ? $decoded : []) : '';
    } else {
        $text = (string) ($decoded['message']['content'] ?? '');
    }
    $payload = robust_json_decode($text);
    if (!is_array($payload)) {
        return ['ok' => false, 'message' => 'AI 返回的 JSON 无效。Raw: ' . substr($text, 0, 200)];
    }
    return ['ok' => true, 'payload' => $payload];
}

function robust_json_decode(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    $payload = json_decode($text, true);
    if (is_array($payload)) {
        return $payload;
    }
    // Strip ``` and ```json fences
    if (preg_match('/```(?:json)?\s*(.+?)```/s', $text, $m)) {
        $payload = json_decode(trim($m[1]), true);
        if (is_array($payload)) {
            return $payload;
        }
    }
    // Extract first { ... last }
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $payload = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($payload)) {
            return $payload;
        }
    }
    return null;
}

function research_brief_json_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['summary', 'key_facts', 'numbers', 'quotes', 'risks', 'angles', 'questions', 'recommended_title', 'recommended_dek'],
        'properties' => [
            'summary' => ['type' => 'string'],
            'key_facts' => ['type' => 'array', 'items' => ['type' => 'string']],
            'numbers' => ['type' => 'array', 'items' => ['type' => 'string']],
            'quotes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
            'angles' => ['type' => 'array', 'items' => ['type' => 'string']],
            'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
            'recommended_title' => ['type' => 'string'],
            'recommended_dek' => ['type' => 'string'],
        ],
    ];
}

function mark_research_brief_used(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare("UPDATE research_briefs SET status = 'used' WHERE id = :id")
            ->execute(['id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}
