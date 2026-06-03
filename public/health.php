<?php

header('Content-Type: application/json; charset=utf-8');

// Allow a post-deploy OPcache flush so freshly mirrored files take effect
// immediately (Hostinger can otherwise serve stale compiled bytecode). Harmless
// to call — it only clears the bytecode cache, never any data.
$flushed = false;
if (isset($_GET['oc']) && function_exists('opcache_reset')) {
    $flushed = @opcache_reset();
}

echo json_encode([
    'status' => 'ok',
    'app' => 'money-tide',
    'release' => 'x-manual-export',
    'opcache_flushed' => $flushed,
    'checked_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
