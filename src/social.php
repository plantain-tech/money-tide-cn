<?php

declare(strict_types=1);

/**
 * Week 6 Day 1-3: Social distribution
 * One social_posts row per (article, channel). Status lifecycle:
 *   draft → ready → posted → archived
 */

function social_channels(): array
{
    return [
        'wechat' => [
            'label' => '微信',
            'limit' => 600,
            'hint' => '正文导语风格，2-4 段。',
            'tone' => '克制、文章导读、读完愿意点开原文。',
        ],
        'xiaohongshu' => [
            'label' => '小红书',
            'limit' => 1000,
            'hint' => '钩子开头 + 短段落 + 3-5 个 hashtag。',
            'tone' => '更生活化、口语化，可以加 emoji。',
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'limit' => 1500,
            'hint' => '中英混排可以，专业商业语气，最后留行动建议。',
            'tone' => '稳重、专业，为决策者解释影响。',
        ],
        'twitter' => [
            'label' => 'X / Twitter',
            'limit' => 280,
            'hint' => '一句钩子 + 关键数字或洞察，必要时加 1-2 个 hashtag。',
            'tone' => '紧凑、点到为止，英文 OK。',
        ],
        'email_short' => [
            'label' => '早报短推荐',
            'limit' => 280,
            'hint' => '2-3 句，引出本文核心结论。',
            'tone' => '克制、与早报整体口吻一致。',
        ],
    ];
}

function social_post_status_options(): array
{
    return [
        'draft' => '草稿',
        'ready' => '可发布',
        'posted' => '已手动发布',
        'archived' => '已归档',
    ];
}

function ensure_social_posts_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS social_posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id INT UNSIGNED NOT NULL,
            channel VARCHAR(40) NOT NULL,
            status ENUM('draft','ready','posted','archived') NOT NULL DEFAULT 'draft',
            content MEDIUMTEXT NULL,
            hashtags VARCHAR(500) NULL,
            note VARCHAR(500) NULL,
            scheduled_at DATETIME NULL,
            generated_by VARCHAR(40) NULL,
            generated_at TIMESTAMP NULL,
            posted_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_article_channel (article_id, channel),
            INDEX idx_social_status (status, updated_at),
            INDEX idx_social_schedule (scheduled_at, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM social_posts LIKE 'scheduled_at'")->fetchAll();
            if (!$cols) {
                $pdo->exec("ALTER TABLE social_posts ADD COLUMN scheduled_at DATETIME NULL AFTER note");
            }
        } catch (Throwable $exception) {
        }
        try {
            $pdo->exec("ALTER TABLE social_posts ADD INDEX idx_social_schedule (scheduled_at, status)");
        } catch (Throwable $exception) {
        }
    } catch (Throwable $exception) {
    }
}

function social_posts_for_article(int $articleId): array
{
    ensure_social_posts_schema();
    $pdo = db();
    $channels = social_channels();
    $out = [];
    foreach ($channels as $key => $meta) {
        $out[$key] = [
            'id' => 0,
            'article_id' => $articleId,
            'channel' => $key,
            'status' => 'draft',
            'content' => '',
            'hashtags' => '',
            'note' => '',
            'scheduled_at' => null,
            'generated_by' => null,
            'generated_at' => null,
            'posted_at' => null,
            'updated_at' => null,
            'meta' => $meta,
        ];
    }
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM social_posts WHERE article_id = :id');
        $statement->execute(['id' => $articleId]);
        foreach ($statement->fetchAll() as $row) {
            $ch = (string) $row['channel'];
            if (isset($out[$ch])) {
                $out[$ch] = array_replace($out[$ch], $row);
                $out[$ch]['meta'] = $channels[$ch];
            }
        }
    } catch (Throwable $exception) {
    }
    return $out;
}

function social_posts_index(array $filters = []): array
{
    ensure_social_posts_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = "SELECT s.id, s.article_id, s.channel, s.status, s.content, s.scheduled_at, s.posted_at, s.updated_at,
                   a.title, a.slug, c.name AS category_name
            FROM social_posts s
            INNER JOIN articles a ON a.id = s.article_id
            INNER JOIN categories c ON c.id = a.category_id
            WHERE 1 = 1";
    $params = [];
    if (!empty($filters['channel'])) {
        $sql .= ' AND s.channel = :channel';
        $params['channel'] = $filters['channel'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND s.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND (a.title LIKE :q_title OR s.content LIKE :q_content)';
        $params['q_title'] = '%' . $filters['q'] . '%';
        $params['q_content'] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['scheduled'])) {
        if ($filters['scheduled'] === 'today') {
            $sql .= ' AND DATE(s.scheduled_at) = CURDATE()';
        } elseif ($filters['scheduled'] === 'upcoming') {
            $sql .= ' AND s.scheduled_at >= NOW()';
        } elseif ($filters['scheduled'] === 'overdue') {
            $sql .= " AND s.scheduled_at < NOW() AND s.status IN ('draft','ready')";
        } elseif ($filters['scheduled'] === 'unscheduled') {
            $sql .= ' AND s.scheduled_at IS NULL';
        }
    }
    $order = !empty($filters['scheduled'])
        ? " ORDER BY COALESCE(s.scheduled_at, s.updated_at) ASC, s.id DESC"
        : ' ORDER BY s.updated_at DESC, s.id DESC';
    $sql .= $order . ' LIMIT 200';
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function social_status_counts(): array
{
    ensure_social_posts_schema();
    $pdo = db();
    $counts = ['draft' => 0, 'ready' => 0, 'posted' => 0, 'archived' => 0, 'all' => 0];
    if (!$pdo instanceof PDO) {
        return $counts;
    }
    try {
        foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM social_posts GROUP BY status')->fetchAll() as $row) {
            $st = (string) $row['status'];
            if (isset($counts[$st])) {
                $counts[$st] = (int) $row['n'];
            }
            $counts['all'] += (int) $row['n'];
        }
    } catch (Throwable $exception) {
    }
    return $counts;
}

function save_social_post(int $articleId, string $channel, array $input): array
{
    ensure_social_posts_schema();
    if (!array_key_exists($channel, social_channels())) {
        return ['ok' => false, 'message' => '未知渠道。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    $content = trim((string) ($input['content'] ?? ''));
    $hashtags = trim((string) ($input['hashtags'] ?? ''));
    $note = trim((string) ($input['note'] ?? ''));
    $scheduledAt = social_normalize_datetime((string) ($input['scheduled_at'] ?? ''));
    try {
        $statement = $pdo->prepare("INSERT INTO social_posts (article_id, channel, content, hashtags, note, scheduled_at)
            VALUES (:aid, :ch, :content, :hashtags, :note, :scheduled_at)
            ON DUPLICATE KEY UPDATE
                content = VALUES(content),
                hashtags = VALUES(hashtags),
                note = VALUES(note),
                scheduled_at = VALUES(scheduled_at),
                updated_at = CURRENT_TIMESTAMP");
        $statement->execute([
            'aid' => $articleId,
            'ch' => $channel,
            'content' => $content,
            'hashtags' => $hashtags,
            'note' => $note,
            'scheduled_at' => $scheduledAt,
        ]);
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function social_normalize_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) <= 16) {
        $value = str_replace('T', ' ', $value) . ':00';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}

function social_schedule_segments(): array
{
    return [
        'today' => '今天待发',
        'upcoming' => '未来排期',
        'overdue' => '已经逾期',
        'unscheduled' => '未排期',
    ];
}

function social_scheduled_posts(array $filters = []): array
{
    $filters['scheduled'] = $filters['scheduled'] ?? 'today';
    return social_posts_index($filters);
}

function social_schedule_summary(): array
{
    ensure_social_posts_schema();
    $pdo = db();
    $summary = ['today' => 0, 'upcoming' => 0, 'overdue' => 0, 'unscheduled' => 0];
    if (!$pdo instanceof PDO) {
        return $summary;
    }
    try {
        $summary['today'] = (int) $pdo->query("SELECT COUNT(*) FROM social_posts WHERE DATE(scheduled_at) = CURDATE()")->fetchColumn();
        $summary['upcoming'] = (int) $pdo->query("SELECT COUNT(*) FROM social_posts WHERE scheduled_at >= NOW()")->fetchColumn();
        $summary['overdue'] = (int) $pdo->query("SELECT COUNT(*) FROM social_posts WHERE scheduled_at < NOW() AND status IN ('draft','ready')")->fetchColumn();
        $summary['unscheduled'] = (int) $pdo->query("SELECT COUNT(*) FROM social_posts WHERE scheduled_at IS NULL")->fetchColumn();
    } catch (Throwable $exception) {
    }
    return $summary;
}

function export_social_schedule_csv(array $filters = []): bool
{
    $rows = social_scheduled_posts($filters);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="social-schedule-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        return false;
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['scheduled_at', 'status', 'channel', 'article_title', 'category', 'content_preview', 'admin_url']);
    foreach ($rows as $row) {
        $channel = (string) ($row['channel'] ?? '');
        fputcsv($output, [
            (string) ($row['scheduled_at'] ?? ''),
            (string) ($row['status'] ?? ''),
            $channel,
            (string) ($row['title'] ?? ''),
            (string) ($row['category_name'] ?? ''),
            mb_substr(trim((string) ($row['content'] ?? '')), 0, 240, 'UTF-8'),
            canonical_url('admin/articles/' . (int) $row['article_id'] . '/social#ch-' . $channel),
        ]);
    }
    fclose($output);
    return true;
}

function update_social_post_status(int $articleId, string $channel, string $status): array
{
    if (!array_key_exists($status, social_post_status_options())) {
        return ['ok' => false, 'message' => '未知状态。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $sql = 'UPDATE social_posts SET status = :status, updated_at = CURRENT_TIMESTAMP';
        $params = ['status' => $status, 'aid' => $articleId, 'ch' => $channel];
        if ($status === 'posted') {
            $sql .= ', posted_at = CURRENT_TIMESTAMP';
        }
        $sql .= ' WHERE article_id = :aid AND channel = :ch';
        $pdo->prepare($sql)->execute($params);
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function delete_social_post(int $articleId, string $channel): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM social_posts WHERE article_id = :aid AND channel = :ch')
            ->execute(['aid' => $articleId, 'ch' => $channel]);
    } catch (Throwable $exception) {
        return false;
    }
}

/**
 * Generate a channel-specific caption with a single AI call.
 * Burns 1 quota per call.
 */
function generate_social_caption(int $articleId, string $channel): array
{
    $channels = social_channels();
    if (!isset($channels[$channel])) {
        return ['ok' => false, 'message' => '未知渠道。'];
    }
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

    $meta = $channels[$channel];
    $bodyParagraphs = json_decode((string) ($article['body'] ?? '[]'), true);
    if (!is_array($bodyParagraphs)) {
        $bodyParagraphs = preg_split('/\R{2,}/', trim((string) ($article['body'] ?? ''))) ?: [];
    }
    $bodyText = implode("\n\n", array_slice(array_map('strval', $bodyParagraphs), 0, 4));

    $prompt = "你是钱潮 Money Tide 的社交编辑。请基于下面这篇文章，为 [{$meta['label']}] 平台写一条社交文案。"
        . "\n字数限制：不超过 {$meta['limit']} 字符。"
        . "\n要求：{$meta['hint']}"
        . "\n语气：{$meta['tone']}"
        . "\n不要复制原文，要有自己的钩子。绝不出现 必涨 / 必跌 / 建议买入 / 建议卖出 等表述。"
        . "\n\n文章标题：{$article['title']}"
        . "\n副标题：{$article['dek']}"
        . "\n一句话看懂：{$article['brief']}"
        . "\n为什么重要：{$article['why_it_matters']}"
        . "\n正文摘录：\n{$bodyText}"
        . "\n\n严格按 JSON 返回：{\"content\": \"…\", \"hashtags\": \"#tag1 #tag2 …\"}。"
        . "（hashtags 字段空字符串也可；twitter/email 通常不需要 hashtags。）";

    $response = function_exists('call_simple_json_api')
        ? call_simple_json_api($prompt, ['content'])
        : ['ok' => false, 'message' => 'AI helper unavailable'];

    log_ai_usage($provider['provider'], $provider['model'], 'social-' . $channel, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));

    if (!$response['ok']) {
        return ['ok' => false, 'message' => $response['message'] ?? 'AI 调用失败。'];
    }
    $payload = $response['payload'];
    $content = trim((string) ($payload['content'] ?? ''));
    $hashtags = trim((string) ($payload['hashtags'] ?? ''));
    if ($content === '') {
        return ['ok' => false, 'message' => 'AI 返回空内容。'];
    }

    // Persist + mark as AI-generated, leave status as draft for editor review.
    ensure_social_posts_schema();
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare("INSERT INTO social_posts (article_id, channel, content, hashtags, generated_by, generated_at, status)
                VALUES (:aid, :ch, :content, :hashtags, 'ai', CURRENT_TIMESTAMP, 'draft')
                ON DUPLICATE KEY UPDATE
                    content = VALUES(content),
                    hashtags = VALUES(hashtags),
                    generated_by = 'ai',
                    generated_at = CURRENT_TIMESTAMP,
                    status = IF(status = 'archived', 'draft', status),
                    updated_at = CURRENT_TIMESTAMP");
            $statement->execute([
                'aid' => $articleId,
                'ch' => $channel,
                'content' => $content,
                'hashtags' => $hashtags,
            ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => '保存失败：' . $exception->getMessage()];
        }
    }
    return ['ok' => true, 'content' => $content, 'hashtags' => $hashtags];
}

/**
 * Render an article in WeChat-friendly HTML for manual copy-paste publishing.
 */
function render_wechat_article_html(array $article): string
{
    $title = htmlspecialchars((string) $article['title'], ENT_QUOTES, 'UTF-8');
    $dek = htmlspecialchars((string) $article['dek'], ENT_QUOTES, 'UTF-8');
    $brief = htmlspecialchars((string) $article['brief'], ENT_QUOTES, 'UTF-8');
    $why = htmlspecialchars((string) $article['why_it_matters'], ENT_QUOTES, 'UTF-8');
    $author = htmlspecialchars((string) ($article['author_name'] ?? '钱潮编辑部'), ENT_QUOTES, 'UTF-8');
    $publishedAt = !empty($article['published_at']) ? date('Y-m-d', strtotime((string) $article['published_at'])) : date('Y-m-d');
    $category = htmlspecialchars((string) ($article['category_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $url = function_exists('canonical_url') ? canonical_url('article/' . $article['slug']) : '';
    $urlSafe = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');

    $body = $article['body'] ?? '';
    $paragraphs = is_array($body) ? $body : (json_decode((string) $body, true) ?: preg_split('/\R{2,}/', trim((string) $body)) ?: []);
    if (!is_array($paragraphs)) {
        $paragraphs = [(string) $paragraphs];
    }
    $bodyHtml = '';
    foreach ($paragraphs as $p) {
        $p = trim((string) $p);
        if ($p === '') {
            continue;
        }
        $bodyHtml .= '<p style="margin:0 0 16px;line-height:1.85;color:#222;font-size:16px;">' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return <<<HTML
<section style="max-width:677px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;color:#0a0a0a;">
<p style="margin:0 0 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#888;">钱潮 MONEY TIDE · {$category}</p>
<h1 style="margin:0 0 14px;font-size:24px;line-height:1.35;font-weight:900;color:#0a0a0a;">{$title}</h1>
<p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#555;">{$dek}</p>
<p style="margin:0 0 24px;font-size:13px;color:#888;">{$author} · {$publishedAt}</p>

<div style="border-left:4px solid #dcff00;padding:6px 0 6px 14px;margin:0 0 22px;background:#fafaf2;">
<p style="margin:0 0 10px;font-size:13px;letter-spacing:1px;color:#666;font-weight:900;">一句话看懂</p>
<p style="margin:0;font-size:15px;line-height:1.7;color:#222;">{$brief}</p>
</div>

<div style="border-left:4px solid #0a0a0a;padding:6px 0 6px 14px;margin:0 0 26px;">
<p style="margin:0 0 10px;font-size:13px;letter-spacing:1px;color:#666;font-weight:900;">为什么重要</p>
<p style="margin:0;font-size:15px;line-height:1.7;color:#222;">{$why}</p>
</div>

{$bodyHtml}

<hr style="border:0;border-top:1px solid #e5e5dd;margin:28px 0;">

<p style="margin:0 0 8px;font-size:12px;color:#888;">本文内容仅供参考，不构成投资建议。</p>
<p style="margin:0 0 8px;font-size:12px;color:#888;">阅读完整版本：<a href="{$urlSafe}" style="color:#0a0a0a;border-bottom:1px solid #dcff00;text-decoration:none;">{$urlSafe}</a></p>

<div style="margin:24px 0 8px;padding:16px;border:2px solid #0a0a0a;background:#fafaf2;text-align:center;">
<p style="margin:0 0 6px;font-size:13px;color:#666;letter-spacing:1px;">钱潮早报 · MONEY TIDE</p>
<p style="margin:0 0 12px;font-size:17px;font-weight:900;color:#0a0a0a;">每天 5 分钟，看懂全球市场</p>
<p style="margin:0;font-size:13px;color:#666;">关注公众号 / 长按二维码订阅 / 在网站留下邮箱</p>
</div>
</section>
HTML;
}
