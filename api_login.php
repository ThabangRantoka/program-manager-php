<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Expose-Headers: Retry-After');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);

    echo json_encode([
        'error' => 'Use POST to log in.'
    ]);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login_rate_limit.php';

$data = json_decode(file_get_contents('php://input'));

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_object($data)
) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Send a valid JSON object.'
    ]);
    exit;
}

if (
    !isset($data->username, $data->password) ||
    !is_string($data->username) ||
    !is_string($data->password)
) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Username and password must be provided as text.'
    ]);
    exit;
}

$username = trim($data->username);
$password = $data->password;

if ($username === '' || $password === '') {
    http_response_code(400);

    echo json_encode([
        'error' => 'Username and password are required.'
    ]);
    exit;
}

try {
    $result = rate_limited_login(
        $conn,
        $username,
        $password
    );

    if ($result['status'] === 'blocked') {
        $seconds = max(
            1,
            (int) $result['retry_after']
        );

        http_response_code(429);
        header('Retry-After: ' . $seconds);

        echo json_encode([
            'error' =>
                'Too many failed login attempts. Please try again later.',
            'retry_after' => $seconds
        ]);
        exit;
    }

    if ($result['status'] !== 'success') {
        http_response_code(401);

        echo json_encode([
            'error' => 'Invalid username or password.'
        ]);
        exit;
    }

    $user = $result['user'];
    $userId = (int) $user['id'];

    // Generate the original token and store only its hash.
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $stmt = $conn->prepare(
        'INSERT INTO api_tokens (
            user_id,
            token_hash,
            expires_at
         )
         VALUES (
            ?,
            ?,
            DATE_ADD(NOW(), INTERVAL 8 HOUR)
         )'
    );

    $stmt->bind_param('is', $userId, $tokenHash);
    $stmt->execute();
    $stmt->close();

    $response = json_encode([
        'token' => $token,
        'username' => $user['username'],
        'role' => $user['role']
    ], JSON_THROW_ON_ERROR);

    http_response_code(200);
    echo $response;
    exit;
} catch (Throwable $error) {
    error_log(
        'API login failed: ' . $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'error' =>
            'Login is temporarily unavailable. Please try again later.'
    ]);
    exit;
}