<?php

declare(strict_types=1);

function subscribe_email(string $email, array $topics = [], string $source = ''): array
{
    ensure_newsletter_growth_columns();
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => '请输入有效邮箱。'];
    }

    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '订阅系统还没有连接数据库，请稍后再试。'];
    }

    $topics = array_values(array_unique(array_filter(array_map(static function ($topic): string {
        return preg_replace('/[^a-z0-9_-]/i', '', (string) $topic);
    }, $topics))));

    try {
        $pdo->beginTransaction();

        $referral = normalize_referral_code((string) ($_POST['ref'] ?? $_POST['referral_code'] ?? $_GET['ref'] ?? ''));
        $landingPath = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 180);
        $statement = $pdo->prepare('INSERT INTO subscribers (email, status, referral_code, referred_by_code, source, landing_path)
            VALUES (:email, "active", :referral_code, :referred_by_code, :source, :landing_path)
            ON DUPLICATE KEY UPDATE status = "active",
                referred_by_code = COALESCE(NULLIF(VALUES(referred_by_code), ""), referred_by_code),
                source = VALUES(source),
                landing_path = VALUES(landing_path),
                updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'email' => $email,
            'referral_code' => bin2hex(random_bytes(8)),
            'referred_by_code' => $referral,
            'source' => substr($source, 0, 120),
            'landing_path' => $landingPath,
        ]);

        $subscriberId = (int) $pdo->query('SELECT id FROM subscribers WHERE email = ' . $pdo->quote($email))->fetchColumn();
        if ($subscriberId > 0 && $topics) {
            $pdo->prepare('DELETE FROM newsletter_preferences WHERE subscriber_id = :id')->execute(['id' => $subscriberId]);
            $preference = $pdo->prepare('INSERT INTO newsletter_preferences (subscriber_id, topic) VALUES (:id, :topic)');
            foreach ($topics as $topic) {
                $preference->execute(['id' => $subscriberId, 'topic' => $topic]);
            }
        }

        $pdo->commit();

        $subscriberReferral = (string) $pdo->query('SELECT referral_code FROM subscribers WHERE email = ' . $pdo->quote($email))->fetchColumn();

        if (function_exists('record_event')) {
            record_event('subscribe', [
                'source' => $source,
                'path' => $landingPath !== '' ? $landingPath : 'subscribe',
            ]);
        }

        return [
            'ok' => true,
            'message' => '订阅成功，明天早报见。',
            'referral_code' => $subscriberReferral,
            'referral_url' => canonical_url('subscribe?ref=' . rawurlencode($subscriberReferral)),
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => '订阅暂时失败，请稍后再试。'];
    }
}

function normalize_referral_code(string $value): string
{
    return substr(preg_replace('/[^a-z0-9]/i', '', $value) ?: '', 0, 40);
}

function ensure_newsletter_growth_columns(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $pdo->exec('ALTER TABLE subscribers ADD COLUMN referred_by_code VARCHAR(40) NULL AFTER referral_code');
    } catch (Throwable $exception) {
    }

    try {
        $pdo->exec('ALTER TABLE subscribers ADD COLUMN landing_path VARCHAR(180) NULL AFTER source');
    } catch (Throwable $exception) {
    }
}
