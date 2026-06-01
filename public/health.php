<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'sprint1-ai-tags',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
