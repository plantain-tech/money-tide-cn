<?php

declare(strict_types=1);

function subscribe_email(string $email, array $topics = [], string $source = ''): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => '请输入有效邮箱。'];
    }

    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '订阅系统还没有连接数据库，请稍后再试。'];
    }

    $topics = array_values(array_unique(array_filter(array_map(static function (mixed $topic): string {
        return preg_replace('/[^a-z0-9_-]/i', '', (string) $topic);
    }, $topics))));

    try {
        $pdo->beginTransaction();

        $statement = $pdo->prepare('INSERT INTO subscribers (email, status, referral_code, source)
            VALUES (:email, "active", :referral_code, :source)
            ON DUPLICATE KEY UPDATE status = "active", source = VALUES(source), updated_at = CURRENT_TIMESTAMP');
        $statement->execute([
            'email' => $email,
            'referral_code' => bin2hex(random_bytes(8)),
            'source' => substr($source, 0, 120),
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

        return ['ok' => true, 'message' => '订阅成功，明天早报见。'];
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => '订阅暂时失败，请稍后再试。'];
    }
}
