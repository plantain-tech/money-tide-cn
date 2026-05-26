<?php

declare(strict_types=1);

return [
    'app_env' => 'production',
    'app_url' => 'https://moneytidecn.avanturadeals.com',
    'db' => [
        'host' => 'localhost',
        'name' => 'hostinger_database_name',
        'user' => 'hostinger_database_user',
        'pass' => 'hostinger_database_password',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'email' => 'editor@example.com',
        'password_hash' => 'replace_with_password_hash',
    ],
    'ai' => [
        'provider' => 'ollama_cloud',
        'api_key' => '',
        'ollama_api_key' => '',
        'model' => 'gemma4:31b-cloud',
        'daily_limit' => 10,
    ],
    'auth' => [
        'google_client_id' => '',
        'google_client_secret' => '',
        'apple_client_id' => '',
        'apple_team_id' => '',
        'apple_key_id' => '',
        'wechat_app_id' => '',
        'wechat_app_secret' => '',
    ],
    'mail' => [
        'from_email' => 'hello@avanturadeals.com',
        'from_name' => '钱潮 Money Tide',
    ],
];
