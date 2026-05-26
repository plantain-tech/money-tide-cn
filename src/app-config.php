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
    $path = '/' . ltrim($path, '/');

    return $baseUrl === '' ? $path : $baseUrl . $path;
}
