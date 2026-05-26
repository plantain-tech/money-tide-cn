<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-2-day-1-homepage-upgrade',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
