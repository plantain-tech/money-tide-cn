<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'week-7-day-4-5-scheduling-prep',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
