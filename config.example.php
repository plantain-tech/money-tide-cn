<?php

declare(strict_types=1);

return [
    'app_env' => 'production',
    'app_url' => 'https://your-domain.com',
    'db' => [
        'host' => 'localhost',
        'name' => 'hostinger_database_name',
        'user' => 'hostinger_database_user',
        'pass' => 'hostinger_database_password',
        'charset' => 'utf8mb4',
    ],
    'ai' => [
        'provider' => 'openai',
        'api_key' => '',
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
        'from_email' => 'hello@your-domain.com',
        'from_name' => '钱潮 Money Tide',
    ],
];
