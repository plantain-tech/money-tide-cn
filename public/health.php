<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-2-day-4-editorial-workflow',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
