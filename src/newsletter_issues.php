<?php

declare(strict_types=1);

function ensure_newsletter_issues_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_issues (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NULL,
            subject VARCHAR(255) NOT NULL,
            intro TEXT NULL,
            outro TEXT NULL,
            status ENUM('draft','ready','scheduled','sent','archived') NOT NULL DEFAULT 'draft',
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_by_user_id INT UNSIGNED NULL,
            recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
            sent_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_newsletter_issue_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pdo->exec("ALTER TABLE newsletter_issues MODIFY status ENUM('draft','ready','scheduled','sent','archived') NOT NULL DEFAULT 'draft'");
        } catch (Throwable $exception) {
        }
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM newsletter_issues LIKE 'slug'")->fetchAll();
            if (!$cols) {
                $pdo->exec("ALTER TABLE newsletter_issues ADD COLUMN slug VARCHAR(120) NULL AFTER id");
            }
        } catch (Throwable $exception) {
        }
        try {
            $pdo->exec("ALTER TABLE newsletter_issues ADD UNIQUE KEY uniq_newsletter_slug (slug)");
        } catch (Throwable $exception) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_issue_articles (
            issue_id INT UNSIGNED NOT NULL,
            article_id INT UNSIGNED NOT NULL,
            position INT UNSIGNED NOT NULL DEFAULT 0,
            blurb VARCHAR(500) NULL,
            PRIMARY KEY (issue_id, article_id),
            INDEX idx_issue_articles_position (issue_id, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_sends (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issue_id INT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
            error_message VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sends_issue (issue_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $exception) {
    }
}

function newsletter_issue_form_defaults(): array
{
    return [
        'subject' => '钱潮早报 · ' . date('Y年n月j日'),
        'intro' => '',
        'outro' => '本邮件由钱潮 Money Tide 自动生成。回复邮件可联系编辑部。',
        'scheduled_at' => '',
    ];
}

function newsletter_issues(array $filters = []): array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT id, subject, status, scheduled_at, sent_at, recipients_count, sent_count, failed_count, created_at, updated_at
            FROM newsletter_issues WHERE 1 = 1';
    $params = [];
    if (!empty($filters['status'])) {
        $sql .= ' AND status = :status';
        $params['status'] = $filters['status'];
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 100';
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function newsletter_issue_by_id(int $id): ?array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM newsletter_issues WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $issue = $statement->fetch();
        if (!$issue) {
            return null;
        }
        $issue['articles'] = newsletter_issue_articles($id);
        return $issue;
    } catch (Throwable $exception) {
        return null;
    }
}

function newsletter_issue_articles(int $issueId): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare("SELECT a.id, a.slug, a.title, a.dek, a.brief, a.hero_image_path,
                                          c.name AS category_name, c.slug AS category_slug,
                                          ia.position, ia.blurb
                                   FROM newsletter_issue_articles ia
                                   INNER JOIN articles a ON a.id = ia.article_id
                                   INNER JOIN categories c ON c.id = a.category_id
                                   WHERE ia.issue_id = :issue_id
                                   ORDER BY ia.position ASC, a.id ASC");
        $statement->execute(['issue_id' => $issueId]);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function save_newsletter_issue(array $input, ?int $id = null): array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }
    $subject = trim((string) ($input['subject'] ?? ''));
    if ($subject === '') {
        return ['ok' => false, 'errors' => ['邮件标题不能为空。']];
    }
    $intro = trim((string) ($input['intro'] ?? ''));
    $outro = trim((string) ($input['outro'] ?? ''));
    $scheduledAt = trim((string) ($input['scheduled_at'] ?? ''));
    if ($scheduledAt !== '' && strlen($scheduledAt) <= 16) {
        $scheduledAt = str_replace('T', ' ', $scheduledAt) . ':00';
    }
    if ($scheduledAt === '') {
        $scheduledAt = null;
    }

    try {
        $slug = generate_newsletter_slug($subject, $id);
        if ($id === null) {
            $userId = (int) (current_user()['id'] ?? 0);
            $statement = $pdo->prepare('INSERT INTO newsletter_issues (slug, subject, intro, outro, scheduled_at, created_by_user_id, status)
                VALUES (:slug, :subject, :intro, :outro, :scheduled_at, :created_by, "draft")');
            $statement->execute([
                'slug' => $slug,
                'subject' => $subject,
                'intro' => $intro,
                'outro' => $outro,
                'scheduled_at' => $scheduledAt,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
        }
        $statement = $pdo->prepare('UPDATE newsletter_issues SET subject = :subject, intro = :intro, outro = :outro, scheduled_at = :scheduled_at, slug = COALESCE(slug, :slug) WHERE id = :id');
        $statement->execute([
            'subject' => $subject,
            'intro' => $intro,
            'outro' => $outro,
            'scheduled_at' => $scheduledAt,
            'slug' => $slug,
            'id' => $id,
        ]);
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存失败：' . $exception->getMessage()]];
    }
}

function generate_newsletter_slug(string $subject, ?int $ignoreId = null): string
{
    $base = 'issue-' . date('Ymd');
    $latin = preg_replace('/[^a-z0-9]+/i', '-', strtolower($subject)) ?: '';
    $latin = trim($latin, '-');
    if ($latin !== '') {
        $base .= '-' . substr($latin, 0, 60);
    }
    $base = trim($base, '-');
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return $base;
    }
    $candidate = $base;
    $suffix = 2;
    try {
        while (true) {
            $sql = 'SELECT id FROM newsletter_issues WHERE slug = :slug';
            $params = ['slug' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            if (!$statement->fetch()) {
                return $candidate;
            }
            $candidate = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 50) {
                return $base . '-' . substr((string) time(), -4);
            }
        }
    } catch (Throwable $exception) {
        return $base;
    }
}

function transition_newsletter_status(int $id, string $next): array
{
    $allowed = ['draft', 'ready', 'sent', 'archived'];
    if (!in_array($next, $allowed, true)) {
        return ['ok' => false, 'message' => '未知状态。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $pdo->prepare('UPDATE newsletter_issues SET status = :status WHERE id = :id')
            ->execute(['status' => $next, 'id' => $id]);
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function public_newsletter_archive(int $limit = 30): array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->query("SELECT id, slug, subject, intro, sent_at, created_at, status
            FROM newsletter_issues
            WHERE status IN ('sent', 'archived')
            ORDER BY COALESCE(sent_at, created_at) DESC
            LIMIT " . (int) $limit);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function public_newsletter_issue(string $slug): ?array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare("SELECT * FROM newsletter_issues WHERE slug = :slug AND status IN ('sent','archived') LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $issue = $statement->fetch();
        if (!$issue) {
            return null;
        }
        $issue['articles'] = newsletter_issue_articles((int) $issue['id']);
        return $issue;
    } catch (Throwable $exception) {
        return null;
    }
}

function add_article_to_issue(int $issueId, int $articleId, string $blurb = ''): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $existing = $pdo->prepare('SELECT MAX(position) FROM newsletter_issue_articles WHERE issue_id = :id');
        $existing->execute(['id' => $issueId]);
        $next = (int) $existing->fetchColumn() + 1;
        $statement = $pdo->prepare('INSERT INTO newsletter_issue_articles (issue_id, article_id, position, blurb)
            VALUES (:issue_id, :article_id, :position, :blurb)
            ON DUPLICATE KEY UPDATE position = VALUES(position), blurb = VALUES(blurb)');
        $statement->execute([
            'issue_id' => $issueId,
            'article_id' => $articleId,
            'position' => $next,
            'blurb' => substr($blurb, 0, 500),
        ]);
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function remove_article_from_issue(int $issueId, int $articleId): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $statement = $pdo->prepare('DELETE FROM newsletter_issue_articles WHERE issue_id = :issue_id AND article_id = :article_id');
        return $statement->execute(['issue_id' => $issueId, 'article_id' => $articleId]);
    } catch (Throwable $exception) {
        return false;
    }
}

function delete_newsletter_issue(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $pdo->prepare('DELETE FROM newsletter_issue_articles WHERE issue_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM newsletter_sends WHERE issue_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM newsletter_issues WHERE id = :id')->execute(['id' => $id]);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function publishable_articles_for_issue(int $issueId): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare("SELECT a.id, a.slug, a.title, a.dek, c.name AS category_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'published'
              AND a.id NOT IN (SELECT article_id FROM newsletter_issue_articles WHERE issue_id = :issue_id)
            ORDER BY a.published_at DESC, a.id DESC
            LIMIT 30");
        $statement->execute(['issue_id' => $issueId]);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function render_newsletter_issue_html(array $issue, string $recipientEmail = ''): string
{
    $subject = (string) ($issue['subject'] ?? '');
    $intro = (string) ($issue['intro'] ?? '');
    $outro = (string) ($issue['outro'] ?? '');
    $articles = $issue['articles'] ?? [];
    $baseUrl = rtrim((string) app_config('app_url', 'https://moneytidecn.avanturadeals.com'), '/');
    $unsubscribeUrl = '';
    if ($recipientEmail !== '' && function_exists('generate_unsubscribe_token')) {
        $unsubscribeUrl = $baseUrl . '/unsubscribe?token=' . rawurlencode(generate_unsubscribe_token($recipientEmail));
    }

    $blocks = '';
    foreach ($articles as $article) {
        $url = $baseUrl . '/article/' . htmlspecialchars((string) $article['slug'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string) $article['title'], ENT_QUOTES, 'UTF-8');
        $dek = htmlspecialchars((string) ($article['dek'] ?? ''), ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars((string) ($article['category_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $blurb = htmlspecialchars((string) ($article['blurb'] ?? $article['brief'] ?? ''), ENT_QUOTES, 'UTF-8');
        $blocks .= <<<HTML
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 28px;">
<tr><td style="border-left: 4px solid #dcff00; padding: 0 0 0 14px;">
<p style="margin:0 0 4px;font-size:12px;color:#666;text-transform:uppercase;letter-spacing:1px;">{$category}</p>
<h2 style="margin:0 0 8px;font-size:20px;line-height:1.3;"><a href="{$url}" style="color:#0a0a0a;text-decoration:none;">{$title}</a></h2>
<p style="margin:0 0 8px;color:#444;line-height:1.5;">{$dek}</p>
<p style="margin:0 0 10px;color:#666;line-height:1.5;font-size:14px;">{$blurb}</p>
<a href="{$url}" style="color:#0a0a0a;border-bottom:2px solid #dcff00;font-weight:700;text-decoration:none;">阅读全文 →</a>
</td></tr></table>
HTML;
    }

    $subjectHtml = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $introHtml = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'));
    $outroHtml = nl2br(htmlspecialchars($outro, ENT_QUOTES, 'UTF-8'));

    $footerExtra = '';
    if ($unsubscribeUrl !== '') {
        $unsubscribeSafe = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');
        $footerExtra = '<br><a href="' . $unsubscribeSafe . '" style="color:#dcff00;text-decoration:underline;">一键退订</a> · <a href="' . $baseUrl . '/account" style="color:#dcff00;text-decoration:underline;">订阅偏好</a>';
    }

    return <<<HTML
<!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><title>{$subjectHtml}</title></head>
<body style="margin:0;padding:0;background:#f6f4ee;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif;">
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f6f4ee;padding:24px 0;">
<tr><td align="center">
<table cellpadding="0" cellspacing="0" border="0" width="600" style="background:#ffffff;border:2px solid #0a0a0a;">
<tr><td style="padding:24px 28px;border-bottom:2px solid #0a0a0a;">
<p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#666;">钱潮早报 · Money Tide</p>
<h1 style="margin:6px 0 0;font-size:24px;line-height:1.3;">{$subjectHtml}</h1>
</td></tr>
<tr><td style="padding:22px 28px;">
<div style="color:#0a0a0a;line-height:1.6;margin-bottom:24px;">{$introHtml}</div>
{$blocks}
<div style="color:#666;line-height:1.6;font-size:13px;border-top:2px dashed #0a0a0a;padding-top:18px;">{$outroHtml}</div>
</td></tr>
<tr><td style="padding:16px 28px;background:#0a0a0a;color:#f6f4ee;font-size:12px;text-align:center;">
钱潮 Money Tide · <a href="{$baseUrl}" style="color:#dcff00;text-decoration:none;">moneytidecn.avanturadeals.com</a>{$footerExtra}
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
}

function email_provider_status(): array
{
    $provider = (string) app_config('email.provider', 'log');
    $apiKey = (string) app_config('email.api_key', '');
    $from = (string) app_config('email.from_address', '');
    $ready = $provider === 'log' || ($apiKey !== '' && $from !== '');
    return [
        'provider' => $provider,
        'from_address' => $from,
        'from_name' => (string) app_config('email.from_name', '钱潮 Money Tide'),
        'ready' => $ready,
        'message' => $ready
            ? ('使用 ' . $provider . ($provider === 'log' ? '（不会真正发送，只记录）' : ''))
            : '请在部署 Secrets 配置 EMAIL_PROVIDER、EMAIL_API_KEY、EMAIL_FROM。',
    ];
}

function send_email_via_provider(string $to, string $subject, string $html): array
{
    $provider = (string) app_config('email.provider', 'log');
    $apiKey = (string) app_config('email.api_key', '');
    $fromAddr = (string) app_config('email.from_address', 'no-reply@moneytidecn.avanturadeals.com');
    $fromName = (string) app_config('email.from_name', '钱潮 Money Tide');

    if ($provider === 'log') {
        return ['ok' => true, 'message' => 'logged'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'cURL not available'];
    }

    $payload = null;
    $endpoint = '';
    $headers = ['Content-Type: application/json'];

    switch ($provider) {
        case 'resend':
            $endpoint = 'https://api.resend.com/emails';
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $payload = [
                'from' => $fromName . ' <' . $fromAddr . '>',
                'to' => [$to],
                'subject' => $subject,
                'html' => $html,
            ];
            break;
        case 'brevo':
            $endpoint = 'https://api.brevo.com/v3/smtp/email';
            $headers[] = 'api-key: ' . $apiKey;
            $payload = [
                'sender' => ['name' => $fromName, 'email' => $fromAddr],
                'to' => [['email' => $to]],
                'subject' => $subject,
                'htmlContent' => $html,
            ];
            break;
        case 'mailgun':
            $domain = (string) app_config('email.mailgun_domain', '');
            if ($domain === '') {
                return ['ok' => false, 'message' => 'mailgun domain missing'];
            }
            $endpoint = 'https://api.mailgun.net/v3/' . $domain . '/messages';
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_USERPWD => 'api:' . $apiKey,
                CURLOPT_POSTFIELDS => http_build_query([
                    'from' => $fromName . ' <' . $fromAddr . '>',
                    'to' => $to,
                    'subject' => $subject,
                    'html' => $html,
                ]),
                CURLOPT_TIMEOUT => 30,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false || $status >= 400) {
                return ['ok' => false, 'message' => 'Mailgun HTTP ' . $status . ' ' . $error];
            }
            return ['ok' => true];
        default:
            return ['ok' => false, 'message' => 'Unknown provider: ' . $provider];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status >= 400) {
        return ['ok' => false, 'message' => $provider . ' HTTP ' . $status . ' ' . substr((string) $body, 0, 200) . ' ' . $error];
    }
    return ['ok' => true];
}

function send_newsletter_test(int $issueId, string $toEmail): array
{
    $issue = newsletter_issue_by_id($issueId);
    if (!$issue) {
        return ['ok' => false, 'message' => 'Issue 不存在。'];
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => '测试邮箱无效。'];
    }
    $html = render_newsletter_issue_html($issue);
    $subject = '[测试] ' . (string) $issue['subject'];
    return send_email_via_provider($toEmail, $subject, $html);
}

function send_newsletter_broadcast(int $issueId): array
{
    ensure_newsletter_issues_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    $issue = newsletter_issue_by_id($issueId);
    if (!$issue) {
        return ['ok' => false, 'message' => 'Issue 不存在。'];
    }
    if (empty($issue['articles'])) {
        return ['ok' => false, 'message' => '没有文章不能发送。'];
    }
    if ((string) $issue['status'] === 'sent') {
        return ['ok' => false, 'message' => '这一期已发送。'];
    }

    try {
        $statement = $pdo->query("SELECT email FROM subscribers WHERE status = 'active' ORDER BY id ASC");
        $subscribers = $statement->fetchAll();
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '无法读取订阅者：' . $exception->getMessage()];
    }

    $subject = (string) $issue['subject'];
    $sent = 0;
    $failed = 0;
    $sendStmt = $pdo->prepare('INSERT INTO newsletter_sends (issue_id, email, status, error_message) VALUES (:issue_id, :email, :status, :error)');
    foreach ($subscribers as $row) {
        $email = (string) $row['email'];
        $html = render_newsletter_issue_html($issue, $email);
        $result = send_email_via_provider($email, $subject, $html);
        $status = $result['ok'] ? 'sent' : 'failed';
        if ($result['ok']) {
            $sent++;
        } else {
            $failed++;
        }
        try {
            $sendStmt->execute([
                'issue_id' => $issueId,
                'email' => $email,
                'status' => $status,
                'error' => $result['ok'] ? null : substr((string) ($result['message'] ?? ''), 0, 500),
            ]);
        } catch (Throwable $exception) {
        }
    }

    try {
        $pdo->prepare('UPDATE newsletter_issues
            SET status = "sent", sent_at = CURRENT_TIMESTAMP, recipients_count = :recipients, sent_count = :sent, failed_count = :failed
            WHERE id = :id')->execute([
            'recipients' => count($subscribers),
            'sent' => $sent,
            'failed' => $failed,
            'id' => $issueId,
        ]);
    } catch (Throwable $exception) {
    }

    return ['ok' => true, 'recipients' => count($subscribers), 'sent' => $sent, 'failed' => $failed];
}

function newsletter_issue_sends(int $issueId): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $statement = $pdo->prepare('SELECT email, status, error_message, created_at FROM newsletter_sends WHERE issue_id = :id ORDER BY created_at DESC LIMIT 200');
        $statement->execute(['id' => $issueId]);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}
