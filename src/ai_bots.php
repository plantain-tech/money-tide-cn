<?php

declare(strict_types=1);

function ai_bot_profile_defaults(): array
{
    return [
        'markets' => [
            'name' => '市场编辑 Bot',
            'section_slug' => 'markets',
            'tone' => '冷静、数据优先、解释市场传导链条。',
            'target_reader' => '关注美股、利率、汇率、商品和全球资产配置的中文读者。',
            'source_requirements' => '至少 1 个可信来源；涉及价格、指数、利率、宏观数据时必须标注来源。',
            'risk_rules' => '不得给出买卖建议；区分市场事实、分析判断和不确定性。',
            'prompt_template' => 'Focus on price action, macro data, rates, USD, risk appetite, and asset allocation impact.',
            'status' => 'active',
        ],
        'business' => [
            'name' => '商业编辑 Bot',
            'section_slug' => 'business',
            'tone' => '清晰、商业化、强调公司战略与盈利逻辑。',
            'target_reader' => '关注公司、财报、消费品牌和商业模式的中文读者。',
            'source_requirements' => '优先使用公司公告、财报、监管文件或主流财经媒体。',
            'risk_rules' => '避免夸大公司前景；所有收入、利润、估值数字都需要来源。',
            'prompt_template' => 'Focus on company strategy, business models, competition, management moves, and profitability.',
            'status' => 'active',
        ],
        'tech' => [
            'name' => '科技编辑 Bot',
            'section_slug' => 'tech',
            'tone' => '专业但易懂，解释技术如何变成商业和市场影响。',
            'target_reader' => '关注 AI、芯片、平台、云计算和科技公司资本开支的中文读者。',
            'source_requirements' => '产品发布、公司博客、开发者文档、财报或可信科技媒体至少一个。',
            'risk_rules' => '不得把产品宣传当事实；区分已发布能力和路线图。',
            'prompt_template' => 'Focus on AI, semiconductors, platforms, cloud, product launches, and supply chains.',
            'status' => 'active',
        ],
        'crypto' => [
            'name' => '加密编辑 Bot',
            'section_slug' => 'crypto',
            'tone' => '谨慎、风险优先、避免情绪化喊单。',
            'target_reader' => '关注 BTC、ETH、ETF、监管和链上资金流的中文读者。',
            'source_requirements' => '价格和链上数据需来自交易所、ETF/监管文件或数据平台。',
            'risk_rules' => '必须提示波动风险；不得承诺收益或给出交易建议。',
            'prompt_template' => 'Focus on BTC/ETH, regulation, exchanges, on-chain context, stablecoins, and risk.',
            'status' => 'active',
        ],
        'policy' => [
            'name' => '政策编辑 Bot',
            'section_slug' => 'policy',
            'tone' => '克制、结构化，解释政策对市场和企业的影响路径。',
            'target_reader' => '关注央行、财政、贸易、监管和产业政策的中文读者。',
            'source_requirements' => '优先使用官方文件、央行/监管机构发布、法律文本或权威媒体。',
            'risk_rules' => '不得过度推断政策意图；政策影响需标注不确定性。',
            'prompt_template' => 'Focus on regulation, central banks, fiscal policy, trade, industrial policy, and market transmission.',
            'status' => 'active',
        ],
        'world' => [
            'name' => '全球编辑 Bot',
            'section_slug' => 'world',
            'tone' => '全球视角、解释地缘事件与资金流动。',
            'target_reader' => '关注全球经济、地缘政治、供应链和外汇影响的中文读者。',
            'source_requirements' => '至少 1 个国际主流来源；涉及国家政策时优先官方来源。',
            'risk_rules' => '避免单一立场叙事；区分事实、背景和市场影响。',
            'prompt_template' => 'Focus on global events, geopolitics, supply chains, FX, and cross-market impact.',
            'status' => 'active',
        ],
        'wealth' => [
            'name' => '理财编辑 Bot',
            'section_slug' => 'wealth',
            'tone' => '耐心、教育型、强调风险和长期视角。',
            'target_reader' => '关注个人财务、基金、保险、养老金和现金流管理的中文读者。',
            'source_requirements' => '个人理财建议必须基于公开数据、监管说明或教育性来源。',
            'risk_rules' => '不得替读者做投资决定；必须避免个性化投资建议。',
            'prompt_template' => 'Focus on savings, funds, insurance, pensions, consumption, and household cash flow.',
            'status' => 'active',
        ],
        'global-china' => [
            'name' => '出海编辑 Bot',
            'section_slug' => 'global-china',
            'tone' => '商业观察型，关注渠道、合规、品牌和定价权。',
            'target_reader' => '关注中国公司出海、跨境电商、EV、消费品牌和全球竞争的中文读者。',
            'source_requirements' => '优先使用公司公告、海关/监管数据、行业报告或当地媒体。',
            'risk_rules' => '避免民族主义或宣传式表达；强调合规、竞争和执行风险。',
            'prompt_template' => 'Focus on Chinese companies going global, cross-border commerce, EVs, brands, channels, compliance, and pricing power.',
            'status' => 'active',
        ],
    ];
}

function ensure_ai_bots_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_bots (
            section_slug VARCHAR(120) NOT NULL PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            tone TEXT NULL,
            target_reader TEXT NULL,
            source_requirements TEXT NULL,
            risk_rules TEXT NULL,
            prompt_template TEXT NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $insert = $pdo->prepare('INSERT IGNORE INTO ai_bots
            (section_slug, name, tone, target_reader, source_requirements, risk_rules, prompt_template, status)
            VALUES (:section_slug, :name, :tone, :target_reader, :source_requirements, :risk_rules, :prompt_template, :status)');
        foreach (ai_bot_profile_defaults() as $slug => $bot) {
            $insert->execute([
                'section_slug' => $slug,
                'name' => $bot['name'],
                'tone' => $bot['tone'],
                'target_reader' => $bot['target_reader'],
                'source_requirements' => $bot['source_requirements'],
                'risk_rules' => $bot['risk_rules'],
                'prompt_template' => $bot['prompt_template'],
                'status' => $bot['status'],
            ]);
        }
    } catch (Throwable $exception) {
    }
}

function ai_bot_profiles(bool $activeOnly = false): array
{
    ensure_ai_bots_schema();
    $bots = ai_bot_profile_defaults();
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $rows = $pdo->query('SELECT section_slug, name, tone, target_reader, source_requirements, risk_rules, prompt_template, status, updated_at FROM ai_bots')->fetchAll();
            foreach ($rows as $row) {
                $slug = (string) $row['section_slug'];
                $bots[$slug] = [
                    'name' => (string) $row['name'],
                    'section_slug' => $slug,
                    'tone' => (string) ($row['tone'] ?? ''),
                    'target_reader' => (string) ($row['target_reader'] ?? ''),
                    'source_requirements' => (string) ($row['source_requirements'] ?? ''),
                    'risk_rules' => (string) ($row['risk_rules'] ?? ''),
                    'prompt_template' => (string) ($row['prompt_template'] ?? ''),
                    'status' => (string) ($row['status'] ?? 'active'),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
            }
        } catch (Throwable $exception) {
        }
    }
    if ($activeOnly) {
        $bots = array_filter($bots, static fn (array $bot): bool => ($bot['status'] ?? 'active') === 'active');
    }
    return $bots;
}

function save_ai_bot_profile(array $input): array
{
    ensure_ai_bots_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Database unavailable.'];
    }

    $slug = trim((string) ($input['section_slug'] ?? ''));
    $status = (string) ($input['status'] ?? 'active');
    if (!isset(ai_bot_profile_defaults()[$slug])) {
        return ['ok' => false, 'message' => 'Choose a valid bot section.'];
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    $data = [
        'section_slug' => $slug,
        'name' => trim((string) ($input['name'] ?? '')),
        'tone' => trim((string) ($input['tone'] ?? '')),
        'target_reader' => trim((string) ($input['target_reader'] ?? '')),
        'source_requirements' => trim((string) ($input['source_requirements'] ?? '')),
        'risk_rules' => trim((string) ($input['risk_rules'] ?? '')),
        'prompt_template' => trim((string) ($input['prompt_template'] ?? '')),
        'status' => $status,
    ];
    if ($data['name'] === '' || $data['prompt_template'] === '') {
        return ['ok' => false, 'message' => 'Bot name and prompt template are required.'];
    }

    try {
        $statement = $pdo->prepare('INSERT INTO ai_bots
            (section_slug, name, tone, target_reader, source_requirements, risk_rules, prompt_template, status)
            VALUES (:section_slug, :name, :tone, :target_reader, :source_requirements, :risk_rules, :prompt_template, :status)
            ON DUPLICATE KEY UPDATE name = VALUES(name), tone = VALUES(tone), target_reader = VALUES(target_reader),
                source_requirements = VALUES(source_requirements), risk_rules = VALUES(risk_rules),
                prompt_template = VALUES(prompt_template), status = VALUES(status)');
        $statement->execute($data);
        save_editorial_template($slug, $data['name'], $data['prompt_template']);
        return ['ok' => true, 'message' => 'Bot profile saved.'];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'Save failed: ' . $exception->getMessage()];
    }
}

function reset_ai_bot_profile(string $sectionSlug): array
{
    ensure_ai_bots_schema();
    $defaults = ai_bot_profile_defaults();
    if (!isset($defaults[$sectionSlug])) {
        return ['ok' => false, 'message' => 'Unknown bot.'];
    }
    $bot = $defaults[$sectionSlug];
    $bot['section_slug'] = $sectionSlug;
    return save_ai_bot_profile($bot);
}

function ai_story_intake_defaults(): array
{
    return [
        'bot_slug' => 'markets',
        'topic_angle' => '',
        'source_links' => '',
        'urgency' => 'normal',
        'target_reader' => '',
    ];
}

function ensure_ai_story_intakes_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_story_intakes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bot_slug VARCHAR(120) NOT NULL,
            topic_angle TEXT NOT NULL,
            source_links LONGTEXT NULL,
            urgency ENUM('low','normal','high','breaking') NOT NULL DEFAULT 'normal',
            target_reader TEXT NULL,
            brief_payload LONGTEXT NOT NULL,
            status ENUM('briefed','draft_created','archived') NOT NULL DEFAULT 'briefed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_intake_bot_time (bot_slug, created_at),
            INDEX idx_intake_status_time (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function ai_story_intakes(int $limit = 40): array
{
    ensure_ai_story_intakes_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        $statement = $pdo->query('SELECT id, bot_slug, topic_angle, source_links, urgency, target_reader, brief_payload, status, created_at
            FROM ai_story_intakes ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(100, $limit)));
        $rows = $statement->fetchAll() ?: [];
        return array_map('hydrate_ai_story_intake', $rows);
    } catch (Throwable $exception) {
        return [];
    }
}

function ai_story_intake_by_id(int $id): ?array
{
    ensure_ai_story_intakes_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id, bot_slug, topic_angle, source_links, urgency, target_reader, brief_payload, status, created_at
            FROM ai_story_intakes WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row ? hydrate_ai_story_intake($row) : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function hydrate_ai_story_intake(array $row): array
{
    $row['source_links'] = json_decode((string) ($row['source_links'] ?? '[]'), true) ?: [];
    $row['brief_payload'] = json_decode((string) ($row['brief_payload'] ?? '{}'), true) ?: [];
    return $row;
}

function generate_ai_story_brief(array $input): array
{
    ensure_ai_story_intakes_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Database unavailable.', 'form' => $input];
    }

    $form = array_replace(ai_story_intake_defaults(), $input);
    $bots = ai_bot_profiles(true);
    $botSlug = (string) ($form['bot_slug'] ?? '');
    $sources = normalize_source_links((string) ($form['source_links'] ?? ''));
    $errors = [];
    if (!isset($bots[$botSlug])) {
        $errors[] = 'Choose an active AI bot.';
    }
    if (trim((string) ($form['topic_angle'] ?? '')) === '') {
        $errors[] = 'Story angle is required.';
    }
    if (!$sources) {
        $errors[] = 'At least one source link is required.';
    }
    if ($errors) {
        return ['ok' => false, 'message' => implode(' ', $errors), 'form' => $form];
    }

    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'message' => $provider['message'], 'form' => $form];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'message' => 'Daily AI limit reached.', 'form' => $form];
    }

    $bot = $bots[$botSlug];
    $prompt = build_ai_story_brief_prompt($form, $sources, $bot);
    $response = call_ai_story_brief_api($prompt);
    log_ai_usage($provider['provider'], $provider['model'], $botSlug, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : $response['message']);
    if (!$response['ok']) {
        return ['ok' => false, 'message' => $response['message'], 'form' => $form];
    }

    $payload = normalize_ai_story_brief_payload($response['payload']);
    try {
        $statement = $pdo->prepare('INSERT INTO ai_story_intakes
            (bot_slug, topic_angle, source_links, urgency, target_reader, brief_payload, status)
            VALUES (:bot_slug, :topic_angle, :source_links, :urgency, :target_reader, :brief_payload, "briefed")');
        $statement->execute([
            'bot_slug' => $botSlug,
            'topic_angle' => trim((string) $form['topic_angle']),
            'source_links' => json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'urgency' => in_array((string) $form['urgency'], ['low', 'normal', 'high', 'breaking'], true) ? (string) $form['urgency'] : 'normal',
            'target_reader' => trim((string) ($form['target_reader'] ?: $bot['target_reader'])),
            'brief_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'Brief generated but save failed: ' . $exception->getMessage(), 'form' => $form];
    }
}

function build_ai_story_brief_prompt(array $form, array $sources, array $bot): string
{
    return "You are {$bot['name']} for Money Tide, a Chinese-language financial news platform.\n"
        . "Bot tone: {$bot['tone']}\n"
        . "Bot mission: {$bot['prompt_template']}\n"
        . "Target reader: " . ((string) ($form['target_reader'] ?: $bot['target_reader'])) . "\n"
        . "Source requirements: {$bot['source_requirements']}\n"
        . "Risk rules: {$bot['risk_rules']}\n"
        . "Urgency: {$form['urgency']}\n"
        . "Story angle: {$form['topic_angle']}\n"
        . "Source links:\n- " . implode("\n- ", $sources) . "\n\n"
        . "Create an editorial brief in Simplified Chinese. Do not invent facts, numbers, or quotes. "
        . "If a fact needs verification, put it in risk_notes or source_questions. "
        . "Return strict JSON only with keys: suggested_headline, brief, why_it_matters, key_numbers, suggested_tags, risk_notes, source_questions, next_steps.";
}

function call_ai_story_brief_api(string $prompt): array
{
    $provider = (string) app_config('ai.provider', 'ollama_cloud');
    if ($provider === 'ollama_cloud') {
        return call_ollama_story_brief_api($prompt);
    }
    return call_openai_story_brief_api($prompt, (string) app_config('ai.api_key', ''));
}

function call_ollama_story_brief_api(string $prompt): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Server cURL is not enabled.'];
    }
    $payload = [
        'model' => (string) app_config('ai.model', 'gemma4:31b-cloud'),
        'stream' => false,
        'format' => 'json',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a careful Chinese financial news assignment editor. Return strict JSON only.'],
            ['role' => 'user', 'content' => $prompt],
        ],
    ];
    $ch = curl_init('https://ollama.com/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string) app_config('ai.ollama_api_key', ''),
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
    $payload = json_decode((string) ($decoded['message']['content'] ?? ''), true);
    return is_array($payload) ? ['ok' => true, 'payload' => $payload] : ['ok' => false, 'message' => 'Ollama Cloud returned invalid JSON.'];
}

function call_openai_story_brief_api(string $prompt, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'Server cURL is not enabled.'];
    }
    $payload = [
        'model' => (string) app_config('ai.model', 'gpt-4.1-mini'),
        'input' => $prompt,
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'money_tide_story_brief',
                'strict' => true,
                'schema' => ai_story_brief_json_schema(),
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
    $payload = json_decode(extract_response_text(is_array($decoded) ? $decoded : []), true);
    return is_array($payload) ? ['ok' => true, 'payload' => $payload] : ['ok' => false, 'message' => 'OpenAI returned invalid JSON.'];
}

function ai_story_brief_json_schema(): array
{
    $array = ['type' => 'array', 'items' => ['type' => 'string']];
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['suggested_headline', 'brief', 'why_it_matters', 'key_numbers', 'suggested_tags', 'risk_notes', 'source_questions', 'next_steps'],
        'properties' => [
            'suggested_headline' => ['type' => 'string'],
            'brief' => ['type' => 'string'],
            'why_it_matters' => ['type' => 'string'],
            'key_numbers' => $array,
            'suggested_tags' => $array,
            'risk_notes' => $array,
            'source_questions' => $array,
            'next_steps' => $array,
        ],
    ];
}

function normalize_ai_story_brief_payload(array $payload): array
{
    $defaults = [
        'suggested_headline' => '',
        'brief' => '',
        'why_it_matters' => '',
        'key_numbers' => [],
        'suggested_tags' => [],
        'risk_notes' => [],
        'source_questions' => [],
        'next_steps' => [],
    ];
    $payload = array_replace($defaults, $payload);
    foreach (['key_numbers', 'suggested_tags', 'risk_notes', 'source_questions', 'next_steps'] as $key) {
        if (!is_array($payload[$key])) {
            $payload[$key] = [(string) $payload[$key]];
        }
    }
    return $payload;
}

function ai_story_brief_to_draft_query(array $intake): string
{
    $payload = $intake['brief_payload'] ?? [];
    return http_build_query([
        'section_slug' => (string) ($intake['bot_slug'] ?? 'markets'),
        'topic_angle' => (string) ($payload['suggested_headline'] ?? $intake['topic_angle'] ?? ''),
        'source_links' => implode("\n", (array) ($intake['source_links'] ?? [])),
        'target_reader' => (string) ($intake['target_reader'] ?? ''),
        'urgency' => (string) ($intake['urgency'] ?? 'normal'),
    ]);
}
