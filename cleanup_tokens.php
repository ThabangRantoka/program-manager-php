<?php
// cleanup_tokens.php
// Deletes expired API tokens. Run from the command line only.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);   // hide it from the browser
    exit;
}

require_once __DIR__ . '/config.php';

$stmt = $conn->prepare('DELETE FROM api_tokens WHERE expires_at < NOW()');
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

echo date('Y-m-d H:i:s') . " - removed {$deleted} expired token(s).\n";