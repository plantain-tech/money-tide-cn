<?php

declare(strict_types=1);

function app_config(?string $key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        $config = [
            'app_env' => 'production',
            'app_url' => '',
            'db' => [
                'host' => '',
                'name' => '',
                'user' => '',
                'pass' => '',
                'charset' => 'utf8mb4',
            ],
            'admin' => [
                'email' => '',
                'password_hash' => '',
            ],
            'email' => [
                'provider' => 'log',
                'api_key' => '',
                'from_address' => '',
                'from_name' => '钱潮 Money Tide',
                'mailgun_domain' => '',
            ],
            'oauth' => [
                'google' => ['client_id' => '', 'client_secret' => ''],
            ],
            'security' => [
                'unsubscribe_secret' => '',
            ],
        ];

        $configFile = APP_BASE_PATH . '/config.php';
        if (is_file($configFile)) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                $config = array_replace_recursive($config, $loaded);
            }
        }
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function app_url(string $path = ''): string
{
    $baseUrl = rtrim((string) app_config('app_url', ''), '/');

    // If app_url isn't configured, derive an absolute base from the current
    // request. Crawlers (X/Facebook/WeChat/知乎) REQUIRE an absolute og:image
    // URL, and canonical/og:url should be absolute too — so we must never emit
    // a relative URL here just because config.php happens to omit app_url.
    if ($baseUrl === '') {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
            $baseUrl = ($https ? 'https://' : 'http://') . $host;
        }
    }

    $path = '/' . ltrim($path, '/');

    return $baseUrl === '' ? $path : $baseUrl . $path;
}
