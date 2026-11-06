<?php

header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Answer the browser's preflight request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Revoking a token requires POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['error' => 'Use POST to log out.']);
    exit;
}
// Read the Authorization header.
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($header === '' && function_exists('getallheaders')) {
    $headers = array_change_key_case(
        getallheaders(),
        CASE_LOWER
    );

    $header = $headers['authorization'] ?? '';
}

// Require: Authorization: Bearer followed by a 64-character token.
if (
    !is_string($header)
    || !preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)
) {
    http_response_code(401);
    echo json_encode(['error' => 'A valid bearer token is required.']);
    exit;
}

// Calculate the hash used to look up the token in the database.
$tokenHash = hash('sha256', strtolower($matches[1]));

try {
    require_once __DIR__ . '/config.php';

    // Delete only the matching token if it has not expired.
    $stmt = $conn->prepare(
        'DELETE FROM api_tokens
         WHERE token_hash = ? AND expires_at > NOW()'
    );

    if ($stmt === false) {
        throw new RuntimeException('Could not prepare token revocation.');
    }

    $stmt->bind_param('s', $tokenHash);

    if (!$stmt->execute()) {
        throw new RuntimeException('Could not revoke the token.');
    }

    $deletedRows = $stmt->affected_rows;
    $stmt->close();

    // No matching, unexpired token was found.
    if ($deletedRows === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token.']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;

} catch (Throwable $error) {
    error_log('API logout failed: ' . $error->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Logout is temporarily unavailable. Please try again later.'
    ]);
    exit;
}