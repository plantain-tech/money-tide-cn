<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'sprint-9-6-ssh-key-deploy',
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
