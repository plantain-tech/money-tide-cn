<?php

declare(strict_types=1);

function editorial_bot_template_defaults(): array
{
    return [
        'markets' => ['name' => 'Markets Bot', 'prompt' => 'Focus on price action, macro data, rates, USD, risk appetite, and asset allocation impact.'],
        'business' => ['name' => 'Business Bot', 'prompt' => 'Focus on company strategy, business models, competition, management moves, and profitability.'],
        'tech' => ['name' => 'Tech Bot', 'prompt' => 'Focus on AI, semiconductors, platforms, cloud, product launches, and supply chains.'],
        'crypto' => ['name' => 'Crypto Bot', 'prompt' => 'Focus on BTC/ETH, regulation, exchanges, on-chain context, stablecoins, and risk.'],
        'policy' => ['name' => 'Policy Bot', 'prompt' => 'Focus on regulation, central banks, fiscal policy, trade, industrial policy, and market transmission.'],
        'world' => ['name' => 'World Bot', 'prompt' => 'Focus on global events, geopolitics, supply chains, FX, and cross-market impact.'],
        'wealth' => ['name' => 'Wealth Bot', 'prompt' => 'Focus on savings, funds, insurance, pensions, consumption, and household cash flow.'],
        'global-china' => ['name' => 'Global China Bot', 'prompt' => 'Focus on Chinese companies going global, cross-border commerce, EVs, brands, channels, compliance, and pricing power.'],
    ];
}

function editorial_bot_templates(): array
{
    $defaults = editorial_bot_template_defaults();
    ensure_ai_prompt_templates_table();
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $rows = $pdo->query('SELECT section_slug, name, prompt FROM ai_prompt_templates')->fetchAll();
            foreach ($rows as $row) {
                $slug = (string) $row['section_slug'];
                $defaults[$slug] = [
                    'name' => (string) ($row['name'] ?: $defaults[$slug]['name'] ?? $slug),
                    'prompt' => (string) ($row['prompt'] ?: $defaults[$slug]['prompt'] ?? ''),
                ];
            }
        } catch (Throwable $exception) {
        }
    }
    return $defaults;
}

function save_editorial_template(string $sectionSlug, string $name, string $prompt): array
{
    $sectionSlug = trim($sectionSlug);
    $name = trim($name);
    $prompt = trim($prompt);
    if ($sectionSlug === '' || $name === '' || $prompt === '') {
        return ['ok' => false, 'errors' => ['栏目、名称、提示词都不能为空。']];
    }
    ensure_ai_prompt_templates_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }
    try {
        $statement = $pdo->prepare('INSERT INTO ai_prompt_templates (section_slug, name, prompt)
            VALUES (:section_slug, :name, :prompt)
            ON DUPLICATE KEY UPDATE name = VALUES(name), prompt = VALUES(prompt)');
        $statement->execute([
            'section_slug' => $sectionSlug,
            'name' => $name,
            'prompt' => $prompt,
        ]);
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存失败：' . $exception->getMessage()]];
    }
}

function reset_editorial_template(string $sectionSlug): bool
{
    ensure_ai_prompt_templates_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $statement = $pdo->prepare('DELETE FROM ai_prompt_templates WHERE section_slug = :slug');
        return $statement->execute(['slug' => $sectionSlug]);
    } catch (Throwable $exception) {
        return false;
    }
}

function ai_draft_form_defaults(): array
{
    return [
        'section_slug' => 'markets',
        'topic_angle' => '',
        'target_reader' => 'Chinese-speaking readers who follow global markets',
        'urgency' => 'normal',
        'source_links' => '',
    ];
}

function ai_provider_status(): array
{
    $provider = (string) app_config('ai.provider', 'ollama_cloud');
    $model = (string) app_config('ai.model', 'gemma4:31b-cloud');

    if ($provider === 'ollama_cloud') {
        $ready = (string) app_config('ai.ollama_api_key', '') !== '';
        return [
            'provider' => $provider,
            'label' => 'Ollama Cloud',
            'model' => $model,
            'ready' => $ready,
            'message' => $ready ? 'Ollama Cloud is configured.' : 'OLLAMA_API_KEY is not configured.',
        ];
    }

    if ($provider === 'openai') {
        $ready = (string) app_config('ai.api_key', '') !== '';
        return [
            'provider' => $provider,
            'label' => 'OpenAI',
            'model' => $model,
            'ready' => $ready,
            'message' => $ready ? 'OpenAI is configured.' : 'OPENAI_API_KEY is not configured.',
        ];
    }

    return [
        'provider' => $provider,
        'label' => $provider,
        'model' => $model,
        'ready' => false,
        'message' => 'Unknown AI provider: ' . $provider,
    ];
}

function ai_drafts(array $filters = []): array
{
    ensure_ai_drafts_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $sql = 'SELECT id, section_slug, prompt_name, status, source_links, draft_payload, created_at FROM ai_drafts WHERE 1 = 1';
    $params = [];
    if (!empty($filters['status'])) {
        $sql .= ' AND status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['section_slug'])) {
        $sql .= ' AND section_slug = :section_slug';
        $params['section_slug'] = $filters['section_slug'];
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 100';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return array_map('hydrate_ai_draft', $statement->fetchAll());
    } catch (Throwable $exception) {
        return [];
    }
}

function ai_draft_by_id(int $id): ?array
{
    ensure_ai_drafts_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, section_slug, prompt_name, status, source_links, draft_payload, created_at FROM ai_drafts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $draft = $statement->fetch();
        return $draft ? hydrate_ai_draft($draft) : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function hydrate_ai_draft(array $draft): array
{
    $draft['source_links'] = json_decode((string) ($draft['source_links'] ?? '[]'), true) ?: [];
    $draft['draft_payload'] = json_decode((string) ($draft['draft_payload'] ?? '{}'), true) ?: [];
    return $draft;
}

function generate_ai_draft(array $input): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['Database is not connected.']];
    }

    $form = array_replace(ai_draft_form_defaults(), $input);
    $sources = normalize_source_links((string) $form['source_links']);
    $templates = editorial_bot_templates();
    $sectionSlug = (string) $form['section_slug'];
    $errors = [];

    if (!isset($templates[$sectionSlug])) {
        $errors[] = 'Choose a valid section.';
    }
    if (trim((string) $form['topic_angle']) === '') {
        $errors[] = 'Topic angle is required.';
    }
    if (!$sources) {
        $errors[] = 'At least one source link is required.';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'form' => $form];
    }

    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'errors' => [$provider['message']], 'form' => $form];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'errors' => ['Daily AI draft limit reached. Try tomorrow or raise AI_DAILY_LIMIT.'], 'form' => $form];
    }

    $template = $templates[$sectionSlug];
    $prompt = build_ai_draft_prompt($form, $sources, $template);
    $response = call_ai_draft_api($prompt);
    log_ai_usage($provider['provider'], $provider['model'], $sectionSlug, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : $response['message']);
    if (!$response['ok']) {
        return ['ok' => false, 'errors' => [$response['message']], 'form' => $form];
    }

    $draftId = save_ai_draft_record($sectionSlug, $template['name'], $sources, $response['payload']);
    if ($draftId <= 0) {
        $saveError = last_ai_draft_save_error();
        return ['ok' => false, 'errors' => ['AI draft was generated, but the database save failed. ' . ($saveError !== '' ? $saveError : 'Please try once more; if it repeats, check the AI usage log for the save error.')], 'form' => $form];
    }

    return ['ok' => true, 'id' => $draftId];
}

function normalize_source_links(string $value): array
{
    $lines = preg_split('/\R+/', trim($value)) ?: [];
    $links = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL)) {
            $links[] = $line;
        }
    }
    return array_values(array_unique($links));
}

function build_ai_draft_prompt(array $form, array $sources, array $template): string
{
    return "You are {$template['name']} for Money Tide, a Chinese financial news product.\n"
        . "Section mission: {$template['prompt']}\n"
        . "Topic angle: {$form['topic_angle']}\n"
        . "Target reader: {$form['target_reader']}\n"
        . "Urgency: {$form['urgency']}\n"
        . "Source links:\n- " . implode("\n- ", $sources) . "\n\n"
        . "Write in Simplified Chinese. Do not invent facts, numbers, quotes, or source details. "
        . "Keep editor verification reminders, source concerns, and risk notes in source_notes or risk_notes only. "
        . "Do not add editor notes, AI-assisted labels, or disclaimers at the end of the article body. "
        . "Return fields that can become a CMS article draft.";
}

function call_ai_draft_api(string $prompt): array
{
    $provider = (string) app_config('ai.provider', 'ollama_cloud');
    if ($provider === 'ollama_cloud') {
        return call_ollama_cloud_draft_api($prompt);
    }
    return call_openai_draft_api($prompt, (string) app_config('ai.api_key', ''));
}

function call_ollama_cloud_draft_api(string $prompt): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Server cURL is not enabled.'];
    }

    $apiKey = (string) app_config('ai.ollama_api_key', '');
    $model = (string) app_config('ai.model', 'gemma4:31b-cloud');
    $jsonInstruction = "\n\nReturn strict JSON only. No Markdown. Required keys: title, dek, brief, why_it_matters, body, body_outline, social_headline, newsletter_blurb, source_notes, risk_notes, disclaimer. body, body_outline, source_notes, and risk_notes must be arrays of strings.";
    $payload = [
        'model' => $model,
        'stream' => false,
        'format' => 'json',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a careful Chinese financial news editorial assistant. Return strict JSON only.'],
            ['role' => 'user', 'content' => $prompt . $jsonInstruction],
        ],
    ];

    $ch = curl_init('https://ollama.com/api/chat');
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
        return ['ok' => false, 'message' => 'Ollama Cloud API failed: ' . ($error ?: 'HTTP ' . $status)];
    }

    $decoded = json_decode((string) $raw, true);
    $content = (string) ($decoded['message']['content'] ?? '');
    $draft = json_decode($content, true);
    if (!is_array($draft)) {
        return ['ok' => false, 'message' => 'Ollama Cloud returned invalid JSON.'];
    }

    return ['ok' => true, 'payload' => normalize_ai_payload($draft)];
}

function call_openai_draft_api(string $prompt, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Server cURL is not enabled.'];
    }

    $model = (string) app_config('ai.model', 'gpt-4.1-mini');
    $payload = [
        'model' => $model,
        'input' => $prompt,
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'money_tide_ai_draft',
                'strict' => true,
                'schema' => ai_draft_json_schema(),
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 60,
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $status >= 400) {
        return ['ok' => false, 'message' => 'OpenAI API failed: ' . ($error ?: 'HTTP ' . $status)];
    }

    $decoded = json_decode((string) $raw, true);
    $text = extract_response_text(is_array($decoded) ? $decoded : []);
    $draft = json_decode($text, true);
    if (!is_array($draft)) {
        return ['ok' => false, 'message' => 'OpenAI returned invalid JSON.'];
    }

    return ['ok' => true, 'payload' => normalize_ai_payload($draft)];
}

function normalize_ai_payload(array $draft): array
{
    $defaults = [
        'title' => '',
        'dek' => '',
        'brief' => '',
        'why_it_matters' => '',
        'body' => [],
        'body_outline' => [],
        'social_headline' => '',
        'newsletter_blurb' => '',
        'source_notes' => [],
        'risk_notes' => [],
        'disclaimer' => 'This is for information only and is not investment advice.',
    ];
    $draft = array_replace($defaults, $draft);
    foreach (['body', 'body_outline', 'source_notes', 'risk_notes'] as $field) {
        if (!is_array($draft[$field])) {
            $draft[$field] = [(string) $draft[$field]];
        }
    }
    return $draft;
}

function ai_draft_json_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['title', 'dek', 'brief', 'why_it_matters', 'body', 'body_outline', 'social_headline', 'newsletter_blurb', 'source_notes', 'risk_notes', 'disclaimer'],
        'properties' => [
            'title' => ['type' => 'string'],
            'dek' => ['type' => 'string'],
            'brief' => ['type' => 'string'],
            'why_it_matters' => ['type' => 'string'],
            'body' => ['type' => 'array', 'items' => ['type' => 'string']],
            'body_outline' => ['type' => 'array', 'items' => ['type' => 'string']],
            'social_headline' => ['type' => 'string'],
            'newsletter_blurb' => ['type' => 'string'],
            'source_notes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'risk_notes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'disclaimer' => ['type' => 'string'],
        ],
    ];
}

function extract_response_text(array $response): string
{
    if (isset($response['output_text'])) {
        return (string) $response['output_text'];
    }
    foreach (($response['output'] ?? []) as $item) {
        foreach (($item['content'] ?? []) as $content) {
            if (isset($content['text'])) {
                return (string) $content['text'];
            }
        }
    }
    return '';
}

function save_ai_draft_record(string $sectionSlug, string $promptName, array $sources, array $payload): int
{
    set_last_ai_draft_save_error('');
    ensure_ai_drafts_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        set_last_ai_draft_save_error('Database is not connected.');
        return 0;
    }

    $sourceLinksJson = json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $draftPayloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($sourceLinksJson === false || $draftPayloadJson === false) {
        set_last_ai_draft_save_error('Generated draft could not be encoded as JSON.');
        return 0;
    }

    $insert = static function () use ($pdo, $sectionSlug, $promptName, $sourceLinksJson, $draftPayloadJson): int {
        $statement = $pdo->prepare('INSERT INTO ai_drafts (section_slug, prompt_name, source_links, draft_payload, status)
            VALUES (:section_slug, :prompt_name, :source_links, :draft_payload, "generated")');
        $statement->execute([
            'section_slug' => $sectionSlug,
            'prompt_name' => $promptName,
            'source_links' => $sourceLinksJson,
            'draft_payload' => $draftPayloadJson,
        ]);
        return (int) $pdo->lastInsertId();
    };

    try {
        return $insert();
    } catch (Throwable $exception) {
        ensure_ai_drafts_table();
        try {
            return $insert();
        } catch (Throwable $retryException) {
            $message = 'AI draft save failed: ' . $retryException->getMessage();
            set_last_ai_draft_save_error($message);
            log_ai_usage('system', 'database', $sectionSlug, 0, 'error', $message . ' Schema: ' . ai_drafts_schema_summary());
        }
        return 0;
    }
}

function set_last_ai_draft_save_error(string $message): void
{
    $GLOBALS['money_tide_last_ai_draft_save_error'] = substr($message, 0, 500);
}

function last_ai_draft_save_error(): string
{
    return (string) ($GLOBALS['money_tide_last_ai_draft_save_error'] ?? '');
}

function update_ai_draft_status(int $id, string $status): bool
{
    if (!in_array($status, ['generated', 'reviewed', 'accepted', 'rejected'], true)) {
        return false;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $statement = $pdo->prepare('UPDATE ai_drafts SET status = :status WHERE id = :id');
        return $statement->execute(['status' => $status, 'id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

function convert_ai_draft_to_article(int $id): array
{
    $draft = ai_draft_by_id($id);
    if (!$draft) {
        return ['ok' => false, 'message' => 'AI draft not found.'];
    }

    $categoryId = category_id_by_slug((string) $draft['section_slug']);
    if ($categoryId <= 0) {
        return ['ok' => false, 'message' => 'Matching section not found.'];
    }

    $payload = $draft['draft_payload'];
    $body = $payload['body'] ?? [];
    if (!is_array($body)) {
        $body = [(string) $body];
    }

    $result = save_article([
        'category_id' => $categoryId,
        'status' => 'draft',
        'title' => (string) ($payload['title'] ?? 'AI Draft'),
        'slug' => '',
        'dek' => (string) ($payload['dek'] ?? ''),
        'brief' => (string) ($payload['brief'] ?? ''),
        'why_it_matters' => (string) ($payload['why_it_matters'] ?? ''),
        'body' => implode("\n\n", $body),
        'read_time_minutes' => 4,
        'published_at' => '',
    ]);

    if ($result['ok']) {
        update_ai_draft_status($id, 'accepted');
        return ['ok' => true, 'article_id' => $result['id']];
    }
    return ['ok' => false, 'message' => implode(' ', $result['errors'] ?? ['Conversion failed.'])];
}

function category_id_by_slug(string $slug): int
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $statement = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function ensure_ai_usage_table(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_usage_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(60) NOT NULL,
            model VARCHAR(120) NOT NULL,
            section_slug VARCHAR(120) NULL,
            prompt_chars INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('ok', 'error') NOT NULL,
            error_message VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ai_usage_created (created_at),
            INDEX idx_ai_usage_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function ensure_ai_drafts_table(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_drafts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section_slug VARCHAR(120) NOT NULL,
            prompt_name VARCHAR(120) NOT NULL,
            source_links LONGTEXT NULL,
            draft_payload LONGTEXT NOT NULL,
            status ENUM('generated', 'reviewed', 'accepted', 'rejected') NOT NULL DEFAULT 'generated',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
    try {
        $pdo->exec('ALTER TABLE ai_drafts MODIFY source_links LONGTEXT NULL');
    } catch (Throwable $exception) {
    }
    try {
        $pdo->exec('ALTER TABLE ai_drafts MODIFY draft_payload LONGTEXT NOT NULL');
    } catch (Throwable $exception) {
    }
}

function ai_drafts_schema_summary(): string
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 'database unavailable';
    }
    try {
        $statement = $pdo->query('SHOW COLUMNS FROM ai_drafts');
        $columns = [];
        foreach ($statement->fetchAll() as $column) {
            $columns[] = (string) ($column['Field'] ?? '') . ':' . (string) ($column['Type'] ?? '');
        }
        return substr(implode(', ', $columns), 0, 500);
    } catch (Throwable $exception) {
        return 'schema unavailable: ' . $exception->getMessage();
    }
}

function ai_usage_allowed(): bool
{
    ensure_ai_usage_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    $limit = max(1, (int) app_config('ai.daily_limit', 10));
    try {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE created_at >= CURDATE()")->fetchColumn();
        return $count < $limit;
    } catch (Throwable $exception) {
        return true;
    }
}

function log_ai_usage(string $provider, string $model, string $sectionSlug, int $promptChars, string $status, string $errorMessage = ''): void
{
    ensure_ai_usage_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $statement = $pdo->prepare('INSERT INTO ai_usage_logs (provider, model, section_slug, prompt_chars, status, error_message)
            VALUES (:provider, :model, :section_slug, :prompt_chars, :status, :error_message)');
        $statement->execute([
            'provider' => $provider,
            'model' => $model,
            'section_slug' => $sectionSlug,
            'prompt_chars' => $promptChars,
            'status' => $status === 'ok' ? 'ok' : 'error',
            'error_message' => substr($errorMessage, 0, 500),
        ]);
    } catch (Throwable $exception) {
    }
}

function ai_usage_summary(): array
{
    ensure_ai_usage_table();
    $pdo = db();
    $limit = max(1, (int) app_config('ai.daily_limit', 10));
    $used = 0;
    if ($pdo instanceof PDO) {
        try {
            $used = (int) $pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE created_at >= CURDATE()")->fetchColumn();
        } catch (Throwable $exception) {
        }
    }
    return ['used_today' => $used, 'daily_limit' => $limit, 'remaining_today' => max(0, $limit - $used)];
}

function ai_rewrite_targets(): array
{
    return [
        'title' => ['label' => '标题 / Headline', 'scalar' => true, 'hint' => '中文标题，10-22 字，钩子明确，不夸大。'],
        'dek' => ['label' => '副标题 / Dek', 'scalar' => true, 'hint' => '一句副标题，补充关键背景。'],
        'brief' => ['label' => '一句话看懂', 'scalar' => true, 'hint' => '一句话告诉读者本文核心结论。'],
        'why_it_matters' => ['label' => '为什么重要', 'scalar' => true, 'hint' => '解释这件事的影响和读者关切。'],
        'social_headline' => ['label' => '社交标题', 'scalar' => true, 'hint' => '微博/X 等社交平台标题，吸引点击但不标题党。'],
        'newsletter_blurb' => ['label' => 'Newsletter blurb', 'scalar' => true, 'hint' => '邮件 newsletter 推荐语，2-3 句。'],
        'body' => ['label' => '正文', 'scalar' => false, 'hint' => '完整正文，3-6 段，每段一个观点。'],
    ];
}

function ai_rewrite_section(int $draftId, string $target, string $instruction = ''): array
{
    $targets = ai_rewrite_targets();
    if (!isset($targets[$target])) {
        return ['ok' => false, 'message' => '未知改写目标。'];
    }

    $draft = ai_draft_by_id($draftId);
    if (!$draft) {
        return ['ok' => false, 'message' => 'AI 草稿不存在。'];
    }

    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'message' => $provider['message']];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'message' => '今日 AI 额度已用完。'];
    }

    $payload = $draft['draft_payload'];
    $original = $payload[$target] ?? '';
    $isArray = !$targets[$target]['scalar'];
    if ($isArray && is_array($original)) {
        $originalText = implode("\n\n", array_map('strval', $original));
    } else {
        $originalText = is_array($original) ? implode(' ', $original) : (string) $original;
    }

    $sectionSlug = (string) $draft['section_slug'];
    $template = editorial_bot_templates()[$sectionSlug] ?? ['name' => $sectionSlug, 'prompt' => ''];
    $prompt = build_ai_rewrite_prompt($template, $target, $targets[$target], $payload, $originalText, $instruction);
    $response = call_ai_rewrite_api($prompt, $target, $isArray);
    log_ai_usage($provider['provider'], $provider['model'], $sectionSlug, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));
    if (!$response['ok']) {
        return ['ok' => false, 'message' => $response['message'] ?? 'AI 改写失败。'];
    }

    $newValue = $response['value'];
    if ($isArray && !is_array($newValue)) {
        $newValue = preg_split('/\R{2,}/', trim((string) $newValue)) ?: [];
    }
    if (!$isArray && is_array($newValue)) {
        $newValue = implode(' ', array_map('strval', $newValue));
    }

    $newPayload = $payload;
    $newPayload[$target] = $newValue;
    record_draft_version($draftId, $payload, 'rewrite:' . $target . ($instruction !== '' ? ':' . substr($instruction, 0, 80) : ''));
    persist_ai_draft_payload($draftId, $newPayload);

    return ['ok' => true, 'value' => $newValue];
}

function build_ai_rewrite_prompt(array $template, string $target, array $targetMeta, array $payload, string $originalText, string $instruction): string
{
    $context = "Article context:\n"
        . "- title: " . (string) ($payload['title'] ?? '') . "\n"
        . "- dek: " . (string) ($payload['dek'] ?? '') . "\n"
        . "- brief: " . (string) ($payload['brief'] ?? '') . "\n";

    $extra = $instruction !== '' ? "\nEditor instruction: " . $instruction . "\n" : "";

    return "You are {$template['name']} for Money Tide, a Chinese financial news product.\n"
        . "Section mission: {$template['prompt']}\n"
        . $context
        . "\nRewrite ONLY the {$target} field in Simplified Chinese.\n"
        . "Style guide: {$targetMeta['hint']}\n"
        . "Do not invent facts, numbers, or quotes. Do not add editor notes or AI-assisted disclaimers."
        . $extra
        . "\n\nOriginal {$target}:\n" . $originalText . "\n";
}

function call_ai_rewrite_api(string $prompt, string $target, bool $isArray): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Server cURL is not enabled.'];
    }
    $provider = (string) app_config('ai.provider', 'ollama_cloud');
    $apiKey = $provider === 'openai'
        ? (string) app_config('ai.api_key', '')
        : (string) app_config('ai.ollama_api_key', '');
    $model = (string) app_config('ai.model', 'gemma4:31b-cloud');

    $jsonInstruction = $isArray
        ? "\n\nReturn strict JSON only. No Markdown. Required shape: {\"value\": [\"paragraph one\", \"paragraph two\"]}."
        : "\n\nReturn strict JSON only. No Markdown. Required shape: {\"value\": \"...\"}.";

    $payload = [
        'model' => $model,
        'stream' => false,
        'format' => 'json',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a careful Chinese financial news editorial assistant. Return strict JSON only.'],
            ['role' => 'user', 'content' => $prompt . $jsonInstruction],
        ],
    ];

    $endpoint = $provider === 'openai' ? 'https://api.openai.com/v1/responses' : 'https://ollama.com/api/chat';

    if ($provider === 'openai') {
        $payload = [
            'model' => $model,
            'input' => $prompt . $jsonInstruction,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'money_tide_rewrite',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['value'],
                        'properties' => [
                            'value' => $isArray
                                ? ['type' => 'array', 'items' => ['type' => 'string']]
                                : ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
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
        return ['ok' => false, 'message' => 'AI rewrite API failed: ' . ($error ?: 'HTTP ' . $status)];
    }

    $decoded = json_decode((string) $raw, true);
    if ($provider === 'openai') {
        $text = extract_response_text(is_array($decoded) ? $decoded : []);
    } else {
        $text = (string) ($decoded['message']['content'] ?? '');
    }
    $parsed = json_decode((string) $text, true);
    if (!is_array($parsed) || !array_key_exists('value', $parsed)) {
        return ['ok' => false, 'message' => 'AI 返回的 JSON 无效。'];
    }
    return ['ok' => true, 'value' => $parsed['value']];
}

function persist_ai_draft_payload(int $draftId, array $payload): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $statement = $pdo->prepare('UPDATE ai_drafts SET draft_payload = :payload WHERE id = :id');
        return $statement->execute(['payload' => $json, 'id' => $draftId]);
    } catch (Throwable $exception) {
        return false;
    }
}

function record_draft_version(int $draftId, array $payload, string $source): int
{
    ensure_ai_draft_versions_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $statement = $pdo->prepare('INSERT INTO ai_draft_versions (draft_id, payload, source) VALUES (:draft_id, :payload, :source)');
        $statement->execute(['draft_id' => $draftId, 'payload' => $json, 'source' => $source]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        return 0;
    }
}

function ai_draft_versions(int $draftId): array
{
    ensure_ai_draft_versions_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare('SELECT id, draft_id, source, created_at FROM ai_draft_versions WHERE draft_id = :id ORDER BY created_at DESC, id DESC LIMIT 30');
        $statement->execute(['id' => $draftId]);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function restore_ai_draft_version(int $versionId): array
{
    ensure_ai_draft_versions_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $statement = $pdo->prepare('SELECT draft_id, payload FROM ai_draft_versions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $versionId]);
        $row = $statement->fetch();
        if (!$row) {
            return ['ok' => false, 'message' => '版本不存在。'];
        }
        $draftId = (int) $row['draft_id'];
        $current = ai_draft_by_id($draftId);
        if ($current) {
            record_draft_version($draftId, $current['draft_payload'], 'restore-from:' . $versionId);
        }
        $payload = json_decode((string) $row['payload'], true);
        if (!is_array($payload)) {
            return ['ok' => false, 'message' => '版本数据已损坏。'];
        }
        persist_ai_draft_payload($draftId, $payload);
        return ['ok' => true, 'draft_id' => $draftId];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '恢复失败：' . $exception->getMessage()];
    }
}

function ai_draft_check_keys(): array
{
    return [
        'source_links_open' => '来源链接已逐一打开',
        'numbers_verified' => '数字与日期与来源一致',
        'quotes_attributed' => '引语已注明出处',
        'facts_verified' => '关键事实已交叉核查',
        'tone_appropriate' => '口吻克制，未夸大或预测',
        'no_unsupported_speculation' => '没有无来源的推测',
        'disclaimer_present' => '免责声明会随发布添加',
    ];
}

function ai_draft_checks(int $draftId): array
{
    ensure_ai_draft_checks_table();
    $pdo = db();
    $state = [];
    foreach (ai_draft_check_keys() as $key => $label) {
        $state[$key] = ['key' => $key, 'label' => $label, 'passed' => false, 'updated_at' => null];
    }
    if (!$pdo instanceof PDO) {
        return array_values($state);
    }
    try {
        $statement = $pdo->prepare('SELECT check_key, passed, updated_at FROM ai_draft_checks WHERE draft_id = :id');
        $statement->execute(['id' => $draftId]);
        foreach ($statement->fetchAll() as $row) {
            $key = (string) $row['check_key'];
            if (isset($state[$key])) {
                $state[$key]['passed'] = (bool) (int) $row['passed'];
                $state[$key]['updated_at'] = (string) $row['updated_at'];
            }
        }
    } catch (Throwable $exception) {
    }
    return array_values($state);
}

function update_ai_draft_check(int $draftId, string $key, bool $passed): bool
{
    if (!array_key_exists($key, ai_draft_check_keys())) {
        return false;
    }
    ensure_ai_draft_checks_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $statement = $pdo->prepare('INSERT INTO ai_draft_checks (draft_id, check_key, passed)
            VALUES (:draft_id, :check_key, :passed)
            ON DUPLICATE KEY UPDATE passed = VALUES(passed), updated_at = CURRENT_TIMESTAMP');
        return $statement->execute([
            'draft_id' => $draftId,
            'check_key' => $key,
            'passed' => $passed ? 1 : 0,
        ]);
    } catch (Throwable $exception) {
        return false;
    }
}

function ensure_ai_draft_versions_table(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_draft_versions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            draft_id INT UNSIGNED NOT NULL,
            payload LONGTEXT NOT NULL,
            source VARCHAR(255) NOT NULL DEFAULT 'edit',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ai_draft_versions_draft (draft_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function ensure_ai_draft_checks_table(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_draft_checks (
            draft_id INT UNSIGNED NOT NULL,
            check_key VARCHAR(64) NOT NULL,
            passed TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (draft_id, check_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function ensure_ai_prompt_templates_table(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_prompt_templates (
            section_slug VARCHAR(120) NOT NULL PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            prompt TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}
