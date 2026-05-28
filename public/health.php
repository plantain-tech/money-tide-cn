<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-7-day-1-2-search-rss',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
