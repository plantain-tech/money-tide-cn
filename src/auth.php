<?php

declare(strict_types=1);

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

function require_admin(): void
{
    if (current_user() !== null) {
        return;
    }

    header('Location: ' . url('admin/login'));
    exit;
}

function login_admin(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $pdo = db();

    if ($pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare("SELECT id, email, display_name, role, password_hash FROM users
                WHERE email = :email AND role IN ('admin', 'editor', 'writer') LIMIT 1");
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
            if ($user && $user['password_hash'] && password_verify($password, (string) $user['password_hash'])) {
                start_session();
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'email' => (string) $user['email'],
                    'name' => (string) ($user['display_name'] ?: $user['email']),
                    'role' => (string) $user['role'],
                ];
                return true;
            }
        } catch (Throwable $exception) {
        }
    }

    $configEmail = strtolower((string) app_config('admin.email', ''));
    $configHash = (string) app_config('admin.password_hash', '');
    if ($configEmail !== '' && $configHash !== '' && hash_equals($configEmail, $email) && password_verify($password, $configHash)) {
        start_session();
        $_SESSION['user'] = [
            'id' => 0,
            'email' => $configEmail,
            'name' => 'Money Tide Admin',
            'role' => 'admin',
        ];
        return true;
    }

    return false;
}

function current_user_role(): string
{
    $user = current_user();
    return is_array($user) ? (string) ($user['role'] ?? '') : '';
}

function user_has_role(array $roles): bool
{
    return in_array(current_user_role(), $roles, true);
}

function can_create_article(): bool
{
    return user_has_role(['writer', 'editor', 'admin']);
}

function can_assign_editor(): bool
{
    return user_has_role(['editor', 'admin']);
}

function can_publish_article(): bool
{
    return user_has_role(['editor', 'admin']);
}

function can_archive_article(): bool
{
    return user_has_role(['editor', 'admin']);
}

function can_delete_article(): bool
{
    return user_has_role(['admin']);
}

function can_edit_article(array $article): bool
{
    if (user_has_role(['editor', 'admin'])) {
        return true;
    }

    $user = current_user();
    if (!is_array($user) || current_user_role() !== 'writer') {
        return false;
    }

    return (int) ($article['created_by_user_id'] ?? -1) === (int) ($user['id'] ?? -2);
}

function can_transition_article(array $article, string $nextStatus): bool
{
    $currentStatus = (string) ($article['status'] ?? 'draft');
    if ($nextStatus === $currentStatus) {
        return can_edit_article($article);
    }

    if (current_user_role() === 'writer') {
        return can_edit_article($article) && $currentStatus === 'draft' && $nextStatus === 'review';
    }

    if ($nextStatus === 'published') {
        return can_publish_article();
    }

    if ($nextStatus === 'archived') {
        return can_archive_article();
    }

    return user_has_role(['editor', 'admin']);
}

function logout_admin(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function admin_stats(): array
{
    return [
        'subscribers' => db_count('subscribers', "status = 'active'"),
        'articles' => db_count('articles'),
        'published' => db_count('articles', "status = 'published'"),
        'drafts' => db_count('articles', "status IN ('draft', 'review')"),
    ];
}
