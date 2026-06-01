<?php

declare(strict_types=1);

function db(bool $reset = false): ?PDO
{
    static $pdo = false;

    if ($reset) {
        $pdo = false;
    }

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
        // The autonomous pipeline idles the connection for 60+ seconds during AI
        // HTTP calls. Raise the session idle timeout so MySQL doesn't drop us
        // ("server has gone away") before we write the run record. Best-effort:
        // ignored on hosts that disallow SET SESSION.
        try {
            $pdo->exec('SET SESSION wait_timeout = 1200, interactive_timeout = 1200');
        } catch (Throwable $ignore) {
        }
    } catch (Throwable $exception) {
        $pdo = null;
    }

    return $pdo instanceof PDO ? $pdo : null;
}

/**
 * Return a connection guaranteed to be alive right now. If the singleton has
 * been dropped by the server (idle timeout during long AI work), this pings it
 * and transparently reconnects. Use before DB writes that follow long pauses.
 */
function db_live(): ?PDO
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $pdo->query('SELECT 1');
        return $pdo;
    } catch (Throwable $exception) {
        return db(true); // force a fresh connection
    }
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
    } catch (Throwable $exception) {
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
    } catch (Throwable $exception) {
        return 0;
    }
}
