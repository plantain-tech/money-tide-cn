<?php

declare(strict_types=1);

function db(): ?PDO
{
    static $pdo = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if ($pdo === null) {
        return null;
    }

    $host = (string) app_config('db.host', '');
    $name = (string) app_config('db.name', '');
    $user = (string) app_config('db.user', '');
    $pass = (string) app_config('db.pass', '');
    $charset = (string) app_config('db.charset', 'utf8mb4');

    if ($host === '' || $name === '' || $user === '') {
        $pdo = null;
        return null;
    }

    try {
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable) {
        $pdo = null;
    }

    return $pdo instanceof PDO ? $pdo : null;
}

function db_is_ready(): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }

    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function db_count(string $table, string $where = '1=1'): int
{
    $pdo = db();
    if (!$pdo instanceof PDO || !preg_match('/^[a-z_]+$/', $table)) {
        return 0;
    }

    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}
