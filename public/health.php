<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-3-day-3-4-newsletter-research',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
