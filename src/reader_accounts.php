<?php

declare(strict_types=1);

function ensure_reader_accounts_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    // users table exists from schema; ensure required columns
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
        $existing = array_map(static fn ($c) => $c['Field'], $cols);
        if (!in_array('password_hash', $existing, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
        }
        if (!in_array('display_name', $existing, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(120) NULL");
        }
        if (!in_array('email_verified_at', $existing, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL");
        }
    } catch (Throwable $exception) {
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reader_preferences (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            digest_frequency ENUM('daily','weekly','alerts','off') NOT NULL DEFAULT 'daily',
            language VARCHAR(20) NOT NULL DEFAULT 'zh-CN',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Idempotent upgrade for existing installs that pre-date 'alerts'.
        try {
            $pdo->exec("ALTER TABLE reader_preferences MODIFY digest_frequency ENUM('daily','weekly','alerts','off') NOT NULL DEFAULT 'daily'");
        } catch (Throwable $exception) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS reader_preference_topics (
            user_id INT UNSIGNED NOT NULL,
            topic VARCHAR(80) NOT NULL,
            PRIMARY KEY (user_id, topic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // login_providers already exists from base schema
    } catch (Throwable $exception) {
    }
}

function reader_session(): ?array
{
    start_session();
    return $_SESSION['reader'] ?? null;
}

function require_reader(): void
{
    if (reader_session() !== null) {
        return;
    }
    header('Location: ' . url('account/login'));
    exit;
}

function reader_signup(string $email, string $password, string $displayName = ''): array
{
    ensure_reader_accounts_schema();
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => '请输入有效邮箱。'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => '密码至少 6 位。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $check = $pdo->prepare('SELECT id, role, password_hash FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        $existing = $check->fetch();
        if ($existing && !empty($existing['password_hash'])) {
            return ['ok' => false, 'message' => '该邮箱已注册，请直接登录。'];
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $name = trim($displayName) !== '' ? trim($displayName) : strstr($email, '@', true);
        if ($existing) {
            $pdo->prepare('UPDATE users SET password_hash = :hash, display_name = :name WHERE id = :id')
                ->execute(['hash' => $hash, 'name' => $name, 'id' => (int) $existing['id']]);
            $userId = (int) $existing['id'];
        } else {
            $pdo->prepare('INSERT INTO users (email, display_name, password_hash, role) VALUES (:email, :name, :hash, "reader")')
                ->execute(['email' => $email, 'name' => $name, 'hash' => $hash]);
            $userId = (int) $pdo->lastInsertId();
        }

        // Link/upsert subscribers entry for the same email so newsletter pipeline reaches them
        $pdo->prepare('INSERT INTO subscribers (email, status, referral_code, source)
            VALUES (:email, "active", :ref, "account-signup")
            ON DUPLICATE KEY UPDATE status = "active", updated_at = CURRENT_TIMESTAMP')
            ->execute(['email' => $email, 'ref' => bin2hex(random_bytes(8))]);

        start_session();
        $_SESSION['reader'] = ['id' => $userId, 'email' => $email, 'name' => $name];
        if (function_exists('record_event')) {
            record_event('reader_signup', ['source' => 'account-signup']);
        }
        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '注册失败：' . $exception->getMessage()];
    }
}

function reader_login(string $email, string $password): array
{
    ensure_reader_accounts_schema();
    $email = strtolower(trim($email));
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $statement = $pdo->prepare('SELECT id, email, display_name, password_hash FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!$user || empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
            return ['ok' => false, 'message' => '邮箱或密码不正确。'];
        }
        start_session();
        $_SESSION['reader'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) ($user['display_name'] ?: $user['email']),
        ];
        if (function_exists('record_event')) {
            record_event('reader_login', ['source' => 'account-login']);
        }
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '登录失败：' . $exception->getMessage()];
    }
}

function reader_logout(): void
{
    start_session();
    unset($_SESSION['reader']);
}

function reader_account_data(int $userId): array
{
    ensure_reader_accounts_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['preferences' => ['digest_frequency' => 'daily', 'language' => 'zh-CN'], 'topics' => [], 'subscriber' => null];
    }
    $prefs = ['digest_frequency' => 'daily', 'language' => 'zh-CN'];
    $topics = [];
    $subscriber = null;
    try {
        $row = $pdo->prepare('SELECT digest_frequency, language FROM reader_preferences WHERE user_id = :id LIMIT 1');
        $row->execute(['id' => $userId]);
        $r = $row->fetch();
        if ($r) {
            $prefs = ['digest_frequency' => (string) $r['digest_frequency'], 'language' => (string) $r['language']];
        }
        $statement = $pdo->prepare('SELECT topic FROM reader_preference_topics WHERE user_id = :id');
        $statement->execute(['id' => $userId]);
        foreach ($statement->fetchAll() as $t) {
            $topics[] = (string) $t['topic'];
        }
        $email = (string) ($_SESSION['reader']['email'] ?? '');
        if ($email !== '') {
            $sub = $pdo->prepare('SELECT email, status, referral_code, source FROM subscribers WHERE email = :email LIMIT 1');
            $sub->execute(['email' => $email]);
            $subscriber = $sub->fetch() ?: null;
        }
    } catch (Throwable $exception) {
    }
    return ['preferences' => $prefs, 'topics' => $topics, 'subscriber' => $subscriber];
}

function unsubscribe_secret(): string
{
    $secret = (string) app_config('security.unsubscribe_secret', '');
    if ($secret !== '') {
        return $secret;
    }
    // Stable fallback so existing tokens keep working when the secret is unset.
    return 'money-tide-default-unsubscribe-secret';
}

function generate_unsubscribe_token(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return '';
    }
    $payload = base64_encode($email);
    $sig = substr(hash_hmac('sha256', $email, unsubscribe_secret()), 0, 24);
    return rtrim(strtr($payload, '+/', '-_'), '=') . '.' . $sig;
}

function verify_unsubscribe_token(string $token): ?string
{
    if (strpos($token, '.') === false) {
        return null;
    }
    [$payload, $sig] = explode('.', $token, 2);
    $email = base64_decode(strtr($payload, '-_', '+/'), true);
    if (!$email) {
        return null;
    }
    $expected = substr(hash_hmac('sha256', $email, unsubscribe_secret()), 0, 24);
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    return strtolower((string) $email);
}

function unsubscribe_email_by_token(string $token): array
{
    $email = verify_unsubscribe_token($token);
    if ($email === null) {
        return ['ok' => false, 'message' => '退订链接无效或已过期。'];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        $pdo->prepare('UPDATE subscribers SET status = "unsubscribed", updated_at = CURRENT_TIMESTAMP WHERE email = :email')
            ->execute(['email' => $email]);
        $userRow = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $userRow->execute(['email' => $email]);
        $userId = (int) ($userRow->fetchColumn() ?: 0);
        if ($userId > 0) {
            $pdo->prepare("INSERT INTO reader_preferences (user_id, digest_frequency)
                VALUES (:id, 'off') ON DUPLICATE KEY UPDATE digest_frequency = 'off'")
                ->execute(['id' => $userId]);
        }
        if (function_exists('record_event')) {
            record_event('reader_unsubscribe', ['source' => 'email-token']);
        }
        return ['ok' => true, 'email' => $email];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '退订失败：' . $exception->getMessage()];
    }
}

function reader_frequency_options(): array
{
    return [
        'daily' => '每天早报',
        'weekly' => '每周精选',
        'alerts' => '仅重大提醒',
        'off' => '暂停订阅',
    ];
}

function save_reader_preferences(int $userId, array $topics, string $frequency): array
{
    ensure_reader_accounts_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    if (!array_key_exists($frequency, reader_frequency_options())) {
        $frequency = 'daily';
    }
    try {
        $pdo->prepare('INSERT INTO reader_preferences (user_id, digest_frequency)
            VALUES (:id, :freq)
            ON DUPLICATE KEY UPDATE digest_frequency = VALUES(digest_frequency)')
            ->execute(['id' => $userId, 'freq' => $frequency]);

        $pdo->prepare('DELETE FROM reader_preference_topics WHERE user_id = :id')->execute(['id' => $userId]);
        if ($topics) {
            $insert = $pdo->prepare('INSERT INTO reader_preference_topics (user_id, topic) VALUES (:id, :topic)');
            foreach ($topics as $topic) {
                $topic = preg_replace('/[^a-z0-9_-]/i', '', (string) $topic);
                if ($topic !== '') {
                    $insert->execute(['id' => $userId, 'topic' => $topic]);
                }
            }
        }
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '保存失败：' . $exception->getMessage()];
    }
}

function unsubscribe_reader(int $userId): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $email = (string) ($_SESSION['reader']['email'] ?? '');
        if ($email === '') {
            return false;
        }
        $pdo->prepare('UPDATE subscribers SET status = "unsubscribed", updated_at = CURRENT_TIMESTAMP WHERE email = :email')
            ->execute(['email' => $email]);
        $pdo->prepare("UPDATE reader_preferences SET digest_frequency = 'off' WHERE user_id = :id")
            ->execute(['id' => $userId]);
        if (function_exists('record_event')) {
            record_event('reader_unsubscribe', ['source' => 'account-page']);
        }
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function resubscribe_reader(int $userId): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        $email = (string) ($_SESSION['reader']['email'] ?? '');
        if ($email === '') {
            return false;
        }
        $pdo->prepare('UPDATE subscribers SET status = "active", updated_at = CURRENT_TIMESTAMP WHERE email = :email')
            ->execute(['email' => $email]);
        $pdo->prepare("UPDATE reader_preferences SET digest_frequency = 'daily' WHERE user_id = :id")
            ->execute(['id' => $userId]);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function reader_referral_data(int $userId): array
{
    $pdo = db();
    $out = ['referral_code' => '', 'referral_url' => '', 'invited_count' => 0];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $email = (string) ($_SESSION['reader']['email'] ?? '');
        if ($email === '') {
            return $out;
        }
        $row = $pdo->prepare('SELECT referral_code FROM subscribers WHERE email = :email LIMIT 1');
        $row->execute(['email' => $email]);
        $code = (string) ($row->fetchColumn() ?: '');
        $out['referral_code'] = $code;
        $out['referral_url'] = canonical_url('subscribe?ref=' . rawurlencode($code));
        if ($code !== '') {
            $count = $pdo->prepare('SELECT COUNT(*) FROM subscribers WHERE referred_by_code = :code');
            $count->execute(['code' => $code]);
            $out['invited_count'] = (int) $count->fetchColumn();
        }
    } catch (Throwable $exception) {
    }
    return $out;
}

function update_reader_profile(int $userId, array $input): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $newPassword = (string) ($input['new_password'] ?? '');
    $currentPassword = (string) ($input['current_password'] ?? '');
    if ($displayName === '') {
        return ['ok' => false, 'message' => '显示名不能为空。'];
    }

    try {
        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) {
                return ['ok' => false, 'message' => '新密码至少 6 位。'];
            }
            $row = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $row->execute(['id' => $userId]);
            $existingHash = (string) $row->fetchColumn();
            if ($existingHash === '' || !password_verify($currentPassword, $existingHash)) {
                return ['ok' => false, 'message' => '当前密码不正确。'];
            }
            $pdo->prepare('UPDATE users SET display_name = :name, password_hash = :hash WHERE id = :id')
                ->execute(['name' => $displayName, 'hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
        } else {
            $pdo->prepare('UPDATE users SET display_name = :name WHERE id = :id')
                ->execute(['name' => $displayName, 'id' => $userId]);
        }
        start_session();
        if (isset($_SESSION['reader'])) {
            $_SESSION['reader']['name'] = $displayName;
        }
        return ['ok' => true];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '保存失败：' . $exception->getMessage()];
    }
}

function oauth_provider_status(): array
{
    return [
        'google' => [
            'configured' => (string) app_config('oauth.google.client_id', '') !== ''
                && (string) app_config('oauth.google.client_secret', '') !== '',
            'label' => 'Google',
            'env_keys' => ['OAUTH_GOOGLE_CLIENT_ID', 'OAUTH_GOOGLE_CLIENT_SECRET'],
        ],
    ];
}

function oauth_initiate(string $provider): array
{
    $status = oauth_provider_status();
    if (!isset($status[$provider])) {
        return ['ok' => false, 'message' => '未知登录方式。'];
    }
    if (!$status[$provider]['configured']) {
        return ['ok' => false, 'message' => $status[$provider]['label'] . ' 登录尚未配置。管理员需要在部署 Secrets 中设置 ' . implode(' 和 ', $status[$provider]['env_keys']) . '。'];
    }

    if ($provider === 'google') {
        return oauth_google_initiate();
    }
    return ['ok' => false, 'message' => $status[$provider]['label'] . ' 登录尚未实现。'];
}

function oauth_handle_callback(string $provider, array $request): array
{
    $status = oauth_provider_status();
    if (!isset($status[$provider]) || !$status[$provider]['configured']) {
        return ['ok' => false, 'message' => '该登录方式不可用。'];
    }
    if ($provider === 'google') {
        return oauth_google_callback($request);
    }
    return ['ok' => false, 'message' => $status[$provider]['label'] . ' 回调暂未实现。'];
}

function oauth_google_redirect_uri(): string
{
    return canonical_url('account/oauth/google/callback');
}

function oauth_google_initiate(): array
{
    start_session();
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_google_state'] = $state;
    $params = [
        'client_id' => (string) app_config('oauth.google.client_id', ''),
        'redirect_uri' => oauth_google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ];
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    return ['ok' => true, 'redirect_url' => $url];
}

function oauth_google_callback(array $request): array
{
    start_session();
    $expected = (string) ($_SESSION['oauth_google_state'] ?? '');
    unset($_SESSION['oauth_google_state']);
    $given = (string) ($request['state'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        return ['ok' => false, 'message' => '登录状态校验失败，请重试。'];
    }
    if (!empty($request['error'])) {
        return ['ok' => false, 'message' => 'Google 返回错误：' . (string) $request['error']];
    }
    $code = (string) ($request['code'] ?? '');
    if ($code === '') {
        return ['ok' => false, 'message' => '缺少授权码。'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'cURL not available'];
    }

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => (string) app_config('oauth.google.client_id', ''),
            'client_secret' => (string) app_config('oauth.google.client_secret', ''),
            'redirect_uri' => oauth_google_redirect_uri(),
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $status >= 400) {
        return ['ok' => false, 'message' => 'Google 令牌交换失败：HTTP ' . $status . ' ' . $err];
    }
    $token = json_decode((string) $raw, true);
    if (!is_array($token) || empty($token['id_token'])) {
        return ['ok' => false, 'message' => 'Google 返回无效令牌。'];
    }
    $claims = oauth_decode_jwt_payload((string) $token['id_token']);
    $email = isset($claims['email']) ? strtolower((string) $claims['email']) : '';
    if ($email === '' || empty($claims['email_verified'])) {
        return ['ok' => false, 'message' => 'Google 账号没有可用的已验证邮箱。'];
    }
    $sub = (string) ($claims['sub'] ?? '');
    $name = (string) ($claims['name'] ?? '');
    if ($name === '') {
        $name = strstr($email, '@', true) ?: $email;
    }

    return oauth_login_or_create_reader('google', $sub, $email, $name);
}

function oauth_decode_jwt_payload(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return [];
    }
    $payload = strtr($parts[1], '-_', '+/');
    $padded = $payload . str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $decoded = base64_decode($padded, true);
    if ($decoded === false) {
        return [];
    }
    $claims = json_decode($decoded, true);
    return is_array($claims) ? $claims : [];
}

function oauth_login_or_create_reader(string $provider, string $providerUserId, string $email, string $name): array
{
    ensure_reader_accounts_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。'];
    }
    try {
        // 1) Existing link?
        $stmt = $pdo->prepare('SELECT user_id FROM login_providers WHERE provider = :provider AND provider_user_id = :pid LIMIT 1');
        $stmt->execute(['provider' => $provider, 'pid' => $providerUserId]);
        $userId = (int) ($stmt->fetchColumn() ?: 0);

        if ($userId === 0) {
            // 2) Existing user by email?
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            if ($userId === 0) {
                // 3) Create new reader account, no password_hash
                $pdo->prepare('INSERT INTO users (email, display_name, role) VALUES (:email, :name, "reader")')
                    ->execute(['email' => $email, 'name' => $name]);
                $userId = (int) $pdo->lastInsertId();
            } else {
                // Backfill display name if missing
                $pdo->prepare('UPDATE users SET display_name = COALESCE(NULLIF(display_name, ""), :name) WHERE id = :id')
                    ->execute(['name' => $name, 'id' => $userId]);
            }
            // Link the provider
            try {
                $pdo->prepare('INSERT INTO login_providers (user_id, provider, provider_user_id) VALUES (:id, :provider, :pid)')
                    ->execute(['id' => $userId, 'provider' => $provider, 'pid' => $providerUserId]);
            } catch (Throwable $exception) {
                // unique violation if already linked under a race — ignore
            }
        }

        // Ensure a subscribers row exists so the newsletter pipeline reaches them
        $pdo->prepare('INSERT INTO subscribers (email, status, referral_code, source)
            VALUES (:email, "active", :ref, "oauth-' . $provider . '")
            ON DUPLICATE KEY UPDATE status = "active", updated_at = CURRENT_TIMESTAMP')
            ->execute(['email' => $email, 'ref' => bin2hex(random_bytes(8))]);

        start_session();
        $_SESSION['reader'] = [
            'id' => $userId,
            'email' => $email,
            'name' => $name,
        ];
        if (function_exists('record_event')) {
            record_event('reader_login', ['source' => 'oauth-' . $provider]);
        }
        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => 'OAuth 登录失败：' . $exception->getMessage()];
    }
}
