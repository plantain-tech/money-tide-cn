<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'sprint-9-1-news-ingestion',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
