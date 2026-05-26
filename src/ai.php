<?php

declare(strict_types=1);

function editorial_bot_templates(): array
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
        return ['ok' => false, 'errors' => ['AI draft was generated, but the database save failed. Please try once more; if it repeats, check the AI usage log for the save error.'], 'form' => $form];
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
        . "Remind editors to verify sources. This is not investment advice. "
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
    ensure_ai_drafts_table();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }

    try {
        $sourceLinksJson = json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $draftPayloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($sourceLinksJson === false || $draftPayloadJson === false) {
            return 0;
        }

        $statement = $pdo->prepare('INSERT INTO ai_drafts (section_slug, prompt_name, source_links, draft_payload, status)
            VALUES (:section_slug, :prompt_name, :source_links, :draft_payload, "generated")');
        $statement->execute([
            'section_slug' => $sectionSlug,
            'prompt_name' => $promptName,
            'source_links' => $sourceLinksJson,
            'draft_payload' => $draftPayloadJson,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        log_ai_usage('system', 'database', $sectionSlug, 0, 'error', 'AI draft save failed: ' . $exception->getMessage());
        return 0;
    }
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
    $body[] = 'Editor note: This article was AI-assisted. Verify sources, facts, numbers, and tone before publishing.';
    $body[] = (string) ($payload['disclaimer'] ?? 'This is for information only and is not investment advice.');

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
        $pdo->exec('ALTER TABLE ai_drafts MODIFY source_links LONGTEXT NULL');
        $pdo->exec('ALTER TABLE ai_drafts MODIFY draft_payload LONGTEXT NOT NULL');
    } catch (Throwable $exception) {
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
