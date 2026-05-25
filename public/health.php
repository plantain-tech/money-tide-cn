<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'day-4-ai-editorial-layer',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
