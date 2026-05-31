<?php

declare(strict_types=1);

/**
 * AI newsletter assistant (Week 5 Day 6).
 * Pulls latest published articles, generates intro + per-theme blocks.
 */

function newsletter_theme_blocks(): array
{
    return [
        'today_five' => ['label' => '今日5件事', 'description' => '今天最值得知道的 5 个事件，每条 1-2 句话。'],
        'global_markets' => ['label' => '全球市场', 'description' => '主要指数、利率、汇率的当日动向和成因。'],
        'tech_ai' => ['label' => '科技与 AI', 'description' => 'AI、芯片、平台公司、产品发布。'],
        'crypto' => ['label' => '加密市场', 'description' => 'BTC/ETH、监管、ETF、链上资金流。'],
        'global_china' => ['label' => '中国公司出海', 'description' => '中国品牌、产业链、并购、海外扩张。'],
        'watch' => ['label' => '今日值得关注', 'description' => '未来 24 小时值得关注的数据、事件或财报。'],
    ];
}

function newsletter_theme_to_categories(string $themeKey): array
{
    return match ($themeKey) {
        'global_markets' => ['markets', 'policy'],
        'tech_ai' => ['tech'],
        'crypto' => ['crypto'],
        'global_china' => ['global-china', 'business'],
        default => [],
    };
}

function latest_articles_for_newsletter(int $limit = 12, int $hoursWindow = 72): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare("SELECT a.id, a.slug, a.title, a.dek, a.brief, a.why_it_matters,
                c.slug AS category, c.name AS category_name, a.published_at
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'published'
              AND a.published_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ORDER BY a.published_at DESC, a.id DESC
            LIMIT :lim");
        $statement->bindValue(':hours', $hoursWindow, PDO::PARAM_INT);
        $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function articles_for_theme(string $themeKey, int $limit = 5): array
{
    $cats = newsletter_theme_to_categories($themeKey);
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        if ($cats) {
            $placeholders = [];
            $params = [];
            foreach ($cats as $i => $slug) {
                $key = 'c' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $slug;
            }
            $sql = "SELECT a.id, a.slug, a.title, a.dek, a.brief, c.slug AS category, c.name AS category_name
                FROM articles a INNER JOIN categories c ON c.id = a.category_id
                WHERE a.status = 'published' AND c.slug IN (" . implode(',', $placeholders) . ")
                ORDER BY a.published_at DESC, a.id DESC LIMIT " . (int) $limit;
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
        } else {
            $statement = $pdo->prepare("SELECT a.id, a.slug, a.title, a.dek, a.brief, c.slug AS category, c.name AS category_name
                FROM articles a INNER JOIN categories c ON c.id = a.category_id
                WHERE a.status = 'published'
                ORDER BY a.published_at DESC, a.id DESC LIMIT " . (int) $limit);
            $statement->execute();
        }
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function generate_newsletter_intro(int $issueId): array
{
    $issue = newsletter_issue_by_id($issueId);
    if (!$issue) {
        return ['ok' => false, 'message' => 'Issue not found.'];
    }
    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'message' => $provider['message']];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'message' => '今日 AI 额度已用完。'];
    }

    $articles = $issue['articles'] ?? [];
    if (!$articles) {
        return ['ok' => false, 'message' => '本期还没有文章，AI 没有素材可写。请先添加文章。'];
    }

    $bullets = '';
    foreach (array_slice($articles, 0, 8) as $a) {
        $bullets .= '- ' . (string) ($a['title'] ?? '') . '（' . (string) ($a['category_name'] ?? '') . '）' . PHP_EOL;
    }

    $prompt = "你是钱潮早报的总编辑。今天的 newsletter 包含以下文章：\n" . $bullets
        . "\n请用 2-3 句中文写一段开场白（intro），引导读者今天最值得读什么、为什么。不要用列表，写成自然段落。"
        . "\n语气克制、专业，不夸张。不要包含投资建议、行情预测、或'必涨/必跌'之类表述。"
        . ai_proper_noun_rule()
        . "\n严格按 JSON 返回：{\"intro\": \"…\"}。";

    $response = call_simple_json_api($prompt, ['intro']);
    log_ai_usage($provider['provider'], $provider['model'], 'newsletter-intro', strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));
    if (!$response['ok']) {
        return $response;
    }
    $intro = trim((string) ($response['payload']['intro'] ?? ''));
    if ($intro === '') {
        return ['ok' => false, 'message' => 'AI 返回空 intro。'];
    }
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $pdo->prepare('UPDATE newsletter_issues SET intro = :intro WHERE id = :id')
                ->execute(['intro' => $intro, 'id' => $issueId]);
        } catch (Throwable $exception) {
        }
    }
    return ['ok' => true, 'intro' => $intro];
}

function generate_newsletter_blurbs(int $issueId): array
{
    $issue = newsletter_issue_by_id($issueId);
    if (!$issue) {
        return ['ok' => false, 'message' => 'Issue not found.'];
    }
    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'message' => $provider['message']];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'message' => '今日 AI 额度已用完。'];
    }
    $articles = $issue['articles'] ?? [];
    if (!$articles) {
        return ['ok' => false, 'message' => '本期还没有文章。'];
    }

    $items = '';
    foreach (array_slice($articles, 0, 8) as $i => $a) {
        $items .= ($i + 1) . '. 标题：' . (string) ($a['title'] ?? '') . PHP_EOL;
        $items .= '   栏目：' . (string) ($a['category_name'] ?? '') . PHP_EOL;
        $items .= '   摘要：' . (string) ($a['brief'] ?? $a['dek'] ?? '') . PHP_EOL;
    }

    $prompt = "你是钱潮早报的编辑助理。为下面这些文章每篇写一句 newsletter 推荐语（1-2 句），用读者关心的角度引出文章。"
        . "\n不要复制标题或摘要原文，要让读者觉得'读了这条值得点开'。语气克制、不夸张，不要写'必读、绝对'等绝对词。"
        . "\n\n文章列表：\n" . $items
        . ai_proper_noun_rule()
        . "\n严格按 JSON 返回：{\"blurbs\": [\"第 1 条推荐语\", \"第 2 条…\", …]}，数组长度等于上面文章数量。";

    $response = call_simple_json_api($prompt, ['blurbs']);
    log_ai_usage($provider['provider'], $provider['model'], 'newsletter-blurbs', strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));
    if (!$response['ok']) {
        return $response;
    }
    $blurbs = $response['payload']['blurbs'] ?? [];
    if (!is_array($blurbs)) {
        return ['ok' => false, 'message' => 'AI 返回的 blurbs 不是数组。'];
    }
    $pdo = db();
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('UPDATE newsletter_issue_articles SET blurb = :blurb WHERE issue_id = :issue_id AND article_id = :article_id');
        foreach (array_slice($articles, 0, 8) as $i => $a) {
            $b = trim((string) ($blurbs[$i] ?? ''));
            if ($b === '') {
                continue;
            }
            try {
                $stmt->execute([
                    'blurb' => mb_substr($b, 0, 500, 'UTF-8'),
                    'issue_id' => $issueId,
                    'article_id' => (int) $a['id'],
                ]);
            } catch (Throwable $exception) {
            }
        }
    }
    return ['ok' => true, 'blurbs' => $blurbs];
}

function generate_themed_block(int $issueId, string $themeKey): array
{
    $themes = newsletter_theme_blocks();
    if (!isset($themes[$themeKey])) {
        return ['ok' => false, 'message' => '未知主题块。'];
    }
    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'message' => $provider['message']];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'message' => '今日 AI 额度已用完。'];
    }
    $articles = articles_for_theme($themeKey, 5);
    if (!$articles) {
        // Fall back to any recent
        $articles = latest_articles_for_newsletter(5, 96);
    }
    if (!$articles) {
        return ['ok' => false, 'message' => '过去几天没有可用文章。'];
    }

    $items = '';
    foreach ($articles as $i => $a) {
        $items .= ($i + 1) . '. ' . (string) ($a['title'] ?? '') . '：' . (string) ($a['brief'] ?? $a['dek'] ?? '') . PHP_EOL;
    }

    $themeMeta = $themes[$themeKey];
    $prompt = "你是钱潮早报的栏目编辑。请用中文为「{$themeMeta['label']}」板块写一段简短的总结（3-5 句）。"
        . "\n板块要求：{$themeMeta['description']}"
        . "\n请基于下面的素材，提炼可读、克制的描述。不要列表，写成段落。不出现投资建议或绝对词。"
        . "\n素材：\n" . $items
        . ai_proper_noun_rule()
        . "\n严格按 JSON 返回：{\"content\": \"…\"}。";

    $response = call_simple_json_api($prompt, ['content']);
    log_ai_usage($provider['provider'], $provider['model'], 'newsletter-theme-' . $themeKey, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));
    if (!$response['ok']) {
        return $response;
    }
    $content = trim((string) ($response['payload']['content'] ?? ''));
    if ($content === '') {
        return ['ok' => false, 'message' => 'AI 返回空内容。'];
    }
    return ['ok' => true, 'theme' => $themeKey, 'label' => $themeMeta['label'], 'content' => $content];
}

function call_simple_json_api(string $prompt, array $requiredKeys): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'cURL not available'];
    }
    $provider = (string) app_config('ai.provider', 'ollama_cloud');
    $apiKey = $provider === 'openai'
        ? (string) app_config('ai.api_key', '')
        : (string) app_config('ai.ollama_api_key', '');
    $model = (string) app_config('ai.model', 'gemma4:31b-cloud');

    if ($provider === 'openai') {
        $payload = [
            'model' => $model,
            'input' => $prompt,
            'text' => ['format' => ['type' => 'json_object']],
        ];
        $endpoint = 'https://api.openai.com/v1/responses';
    } else {
        $payload = [
            'model' => $model,
            'stream' => false,
            'format' => 'json',
            'messages' => [
                ['role' => 'system', 'content' => '你是一名中文财经编辑助手。严格按要求返回 JSON。'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        $endpoint = 'https://ollama.com/api/chat';
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 90,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false || $status >= 400) {
        return ['ok' => false, 'message' => 'AI API HTTP ' . $status . ' ' . $error];
    }
    $decoded = json_decode((string) $raw, true);
    $text = $provider === 'openai'
        ? (function_exists('extract_response_text') ? extract_response_text(is_array($decoded) ? $decoded : []) : '')
        : (string) ($decoded['message']['content'] ?? '');
    $parsed = function_exists('robust_json_decode') ? robust_json_decode($text) : json_decode((string) $text, true);
    if (!is_array($parsed)) {
        return ['ok' => false, 'message' => 'AI 返回的 JSON 无效。Raw: ' . substr((string) $text, 0, 200)];
    }
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $parsed)) {
            return ['ok' => false, 'message' => 'AI 返回缺少字段：' . $key];
        }
    }
    return ['ok' => true, 'payload' => $parsed];
}
