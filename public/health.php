<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'sprint-9-2-yahoo-tech-source',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
