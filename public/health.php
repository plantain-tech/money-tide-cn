<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'day-7-launch-hardening-layer',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
