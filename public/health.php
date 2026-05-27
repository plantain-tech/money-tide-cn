<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-4-day-1-4-accounts-oauth-newsletter',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
