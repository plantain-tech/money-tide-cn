<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-10-day-4-wechat',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
