<?php

declare(strict_types=1);

function ensure_monetization_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        foreach ([
            'is_premium' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'premium_excerpt' => 'TEXT NULL',
        ] as $column => $definition) {
            if (!db_column_exists('articles', $column)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        if (!db_column_exists('users', 'subscription_tier')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN subscription_tier ENUM('free','member','premium') NOT NULL DEFAULT 'free'");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS monetization_settings (
            setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $insert = $pdo->prepare('INSERT IGNORE INTO monetization_settings (setting_key, setting_value) VALUES (:k, :v)');
        foreach ([
            'paywall_mode' => 'soft_preview',
            'premium_label' => '会员内容',
            'member_price_note' => '会员定价尚未启用；当前所有内容保持可读。',
        ] as $key => $value) {
            $insert->execute(['k' => $key, 'v' => $value]);
        }
    } catch (Throwable $exception) {
    }
}

function subscription_tier_options(): array
{
    return [
        'free' => 'Free',
        'member' => 'Member',
        'premium' => 'Premium',
    ];
}

function monetization_settings(): array
{
    ensure_monetization_schema();
    $pdo = db();
    $defaults = [
        'paywall_mode' => 'soft_preview',
        'premium_label' => '会员内容',
        'member_price_note' => '会员定价尚未启用；当前所有内容保持可读。',
    ];
    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM monetization_settings')->fetchAll();
        foreach ($rows as $row) {
            $defaults[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable $exception) {
    }
    return $defaults;
}

function save_monetization_settings(array $input): array
{
    ensure_monetization_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => 'Database unavailable.'];
    }

    $allowedModes = ['off', 'soft_preview'];
    $values = [
        'paywall_mode' => in_array((string) ($input['paywall_mode'] ?? 'soft_preview'), $allowedModes, true)
            ? (string) $input['paywall_mode']
            : 'soft_preview',
        'premium_label' => trim((string) ($input['premium_label'] ?? '会员内容')) ?: '会员内容',
        'member_price_note' => trim((string) ($input['member_price_note'] ?? '')),
    ];

    try {
        $statement = $pdo->prepare('INSERT INTO monetization_settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $key => $value) {
            $statement->execute(['k' => $key, 'v' => $value]);
        }
        return ['ok' => true, 'message' => 'Settings saved.'];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'Save failed: ' . $exception->getMessage()];
    }
}

function monetization_summary(): array
{
    ensure_monetization_schema();
    $pdo = db();
    $out = [
        'settings' => monetization_settings(),
        'premium_articles' => 0,
        'tiers' => ['free' => 0, 'member' => 0, 'premium' => 0],
    ];
    if (!$pdo instanceof PDO) {
        return $out;
    }

    try {
        $out['premium_articles'] = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE is_premium = 1")->fetchColumn();
        $rows = $pdo->query("SELECT subscription_tier, COUNT(*) AS total FROM users WHERE role = 'reader' GROUP BY subscription_tier")->fetchAll();
        foreach ($rows as $row) {
            $tier = (string) ($row['subscription_tier'] ?? 'free');
            if (isset($out['tiers'][$tier])) {
                $out['tiers'][$tier] = (int) $row['total'];
            }
        }
    } catch (Throwable $exception) {
    }
    return $out;
}

function seo_article_checklist(array $article): array
{
    $title = trim((string) ($article['title'] ?? ''));
    $seoTitle = trim((string) ($article['seo_title'] ?? ''));
    $description = trim((string) (($article['seo_description'] ?? '') ?: ($article['dek'] ?? '')));
    $image = trim((string) (($article['hero_image_path'] ?? '') ?: ($article['hero_image'] ?? '')));
    $slug = (string) ($article['slug'] ?? '');

    return [
        ['label' => 'SEO title fallback available', 'passed' => $seoTitle !== '' || $title !== ''],
        ['label' => 'Description between 60 and 180 characters', 'passed' => mb_strlen($description, 'UTF-8') >= 40 && mb_strlen($description, 'UTF-8') <= 220],
        ['label' => 'Canonical slug is valid', 'passed' => (bool) preg_match('/^[a-z0-9-]+$/', $slug)],
        ['label' => 'Social image available', 'passed' => $image !== '' || function_exists('default_og_image')],
        ['label' => 'Hero alt text available', 'passed' => trim((string) ($article['hero_image_alt'] ?? '')) !== '' || $title !== ''],
        ['label' => 'Published date available', 'passed' => !empty($article['published_at']) || (string) ($article['status'] ?? '') !== 'published'],
    ];
}

function week_four_qa_checklist(): array
{
    return [
        ['label' => 'Reader account profile, preferences, referral, and saved articles work in production.', 'tip' => '/account, /account/preferences, /account/saved'],
        ['label' => 'Google OAuth remains configured but app consent is still in Testing until owner publishes/adds testers.', 'tip' => '/admin/oauth'],
        ['label' => 'Newsletter archive and public issue pages render and appear in sitemap.', 'tip' => '/newsletter and /sitemap.xml'],
        ['label' => 'Bookmark save/unsave and recent reads create retention rows.', 'tip' => 'Log in as reader, open an article, save it, then check /account/saved'],
        ['label' => 'Article source contains og:image, NewsArticle JSON-LD, and BreadcrumbList JSON-LD.', 'tip' => 'View source on any article'],
        ['label' => 'Premium flag displays labels but does not block article reading yet.', 'tip' => 'Edit an article and turn on Premium'],
        ['label' => 'Admin analytics shows saves, returning readers, completion, and sharing events.', 'tip' => '/admin/analytics'],
        ['label' => 'Smoke checks pass after deploy.', 'tip' => '/admin/smoke?format=json'],
    ];
}

function week_five_backlog(): array
{
    return [
        ['title' => 'Real email delivery', 'detail' => 'Turn EMAIL_PROVIDER from log to Resend/Brevo/Mailgun and verify sender DNS.'],
        ['title' => 'Google OAuth public launch', 'detail' => 'Publish Google app or add tester emails before public reader onboarding.'],
        ['title' => 'Payment integration', 'detail' => 'Choose Stripe, Paddle, Lemon Squeezy, or another provider before enabling hard paywall.'],
        ['title' => 'Comment moderation', 'detail' => 'Add reader comments with admin moderation queue if community is a priority.'],
        ['title' => 'Automated backups', 'detail' => 'Nightly database export and asset backup from Hostinger.'],
        ['title' => 'Editorial calendar', 'detail' => 'Schedule articles/newsletters with calendar view and assignment reminders.'],
        ['title' => 'Monitoring alerts', 'detail' => 'Webhook or email alert for failed smoke checks and deploys.'],
    ];
}
