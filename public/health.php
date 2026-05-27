<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-5-day-3-6-queue-fact-rewrite-newsletter',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
