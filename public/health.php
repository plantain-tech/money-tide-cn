<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-3-day-5-6-7-accounts-seo-ops',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
