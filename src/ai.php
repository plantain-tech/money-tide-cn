<?php

declare(strict_types=1);

function editorial_bot_templates(): array
{
    return [
        'markets' => [
            'name' => '市场 Bot',
            'prompt' => '关注价格走势、宏观数据、利率、美元、风险偏好和资产配置含义。',
        ],
        'business' => [
            'name' => '商业 Bot',
            'prompt' => '关注公司战略、盈利模式、竞争格局、管理层动作和商业模式变化。',
        ],
        'tech' => [
            'name' => '科技 Bot',
            'prompt' => '关注 AI、半导体、平台公司、云服务、产品发布和产业链订单。',
        ],
        'crypto' => [
            'name' => '加密 Bot',
            'prompt' => '关注 BTC/ETH、监管、交易所、链上数据、稳定币和风险披露。',
        ],
        'policy' => [
            'name' => '政策 Bot',
            'prompt' => '关注监管政策、央行、财政、贸易、产业政策和市场传导。',
        ],
        'world' => [
            'name' => '全球 Bot',
            'prompt' => '关注全球重大事件、地缘政治、供应链、汇率和跨市场影响。',
        ],
        'wealth' => [
            'name' => '理财 Bot',
            'prompt' => '关注普通读者的储蓄、基金、保险、养老金、消费和家庭现金流。',
        ],
        'global-china' => [
            'name' => '出海 Bot',
            'prompt' => '关注中国公司全球化、跨境电商、新能源、品牌、渠道、合规和定价权。',
        ],
    ];
}

function ai_draft_form_defaults(): array
{
    return [
        'section_slug' => 'markets',
        'topic_angle' => '',
        'target_reader' => '关注全球市场的中文读者',
        'urgency' => 'normal',
        'source_links' => '',
    ];
}

function ai_drafts(array $filters = []): array
{
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
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }

    $form = array_replace(ai_draft_form_defaults(), $input);
    $sources = normalize_source_links((string) $form['source_links']);
    $templates = editorial_bot_templates();
    $sectionSlug = (string) $form['section_slug'];
    $errors = [];

    if (!isset($templates[$sectionSlug])) {
        $errors[] = '请选择有效栏目。';
    }
    if (trim((string) $form['topic_angle']) === '') {
        $errors[] = '请填写选题角度。';
    }
    if (!$sources) {
        $errors[] = '至少需要一个来源链接。';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'form' => $form];
    }

    $apiKey = (string) app_config('ai.api_key', '');
    if ($apiKey === '') {
        return ['ok' => false, 'errors' => ['尚未配置 OPENAI_API_KEY。请先在 GitHub Secrets 添加 OPENAI_API_KEY。'], 'form' => $form];
    }

    $template = $templates[$sectionSlug];
    $prompt = build_ai_draft_prompt($form, $sources, $template);
    $response = call_openai_draft_api($prompt, $apiKey);
    if (!$response['ok']) {
        return ['ok' => false, 'errors' => [$response['message']], 'form' => $form];
    }

    $draftId = save_ai_draft_record($sectionSlug, $template['name'], $sources, $response['payload']);
    if ($draftId <= 0) {
        return ['ok' => false, 'errors' => ['AI 草稿已生成，但保存失败。'], 'form' => $form];
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
    return "你是《钱潮 Money Tide》的{$template['name']}，为中文读者写财经新闻草稿。\n"
        . "栏目职责：{$template['prompt']}\n"
        . "选题角度：{$form['topic_angle']}\n"
        . "目标读者：{$form['target_reader']}\n"
        . "紧急程度：{$form['urgency']}\n"
        . "来源链接：\n- " . implode("\n- ", $sources) . "\n\n"
        . "要求：\n"
        . "1. 必须用简体中文。\n"
        . "2. 不要编造事实、数字、人物发言或未给出的来源细节。\n"
        . "3. 明确提醒编辑需要人工核查来源。\n"
        . "4. 不构成投资建议。\n"
        . "5. 输出字段必须适合转成 CMS 文章草稿。";
}

function call_openai_draft_api(string $prompt, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => '服务器未启用 cURL，无法调用 AI API。'];
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
        return ['ok' => false, 'message' => 'AI API 调用失败：' . ($error ?: 'HTTP ' . $status)];
    }

    $decoded = json_decode((string) $raw, true);
    $text = extract_response_text(is_array($decoded) ? $decoded : []);
    $draft = json_decode($text, true);
    if (!is_array($draft)) {
        return ['ok' => false, 'message' => 'AI 返回内容不是有效 JSON。'];
    }

    return ['ok' => true, 'payload' => $draft];
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
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }

    try {
        $statement = $pdo->prepare('INSERT INTO ai_drafts (section_slug, prompt_name, source_links, draft_payload, status)
            VALUES (:section_slug, :prompt_name, :source_links, :draft_payload, "generated")');
        $statement->execute([
            'section_slug' => $sectionSlug,
            'prompt_name' => $promptName,
            'source_links' => json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'draft_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
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
        return ['ok' => false, 'message' => 'AI 草稿不存在。'];
    }

    $categoryId = category_id_by_slug((string) $draft['section_slug']);
    if ($categoryId <= 0) {
        return ['ok' => false, 'message' => '找不到对应栏目。'];
    }

    $payload = $draft['draft_payload'];
    $body = $payload['body'] ?? [];
    if (!is_array($body)) {
        $body = [(string) $body];
    }
    $body[] = '编辑提示：本文由 AI 辅助生成，发布前必须人工核查来源、事实、数字和语气。';
    $body[] = (string) ($payload['disclaimer'] ?? '本文仅供信息参考，不构成投资建议。');

    $result = save_article([
        'category_id' => $categoryId,
        'status' => 'draft',
        'title' => (string) ($payload['title'] ?? 'AI 草稿'),
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

    return ['ok' => false, 'message' => implode(' ', $result['errors'] ?? ['转换失败。'])];
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
