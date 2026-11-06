<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['error' => 'Use POST to log in.']);
    exit;
}

require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents('php://input'));

if (json_last_error() !== JSON_ERROR_NONE || !is_object($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Send a valid JSON object.']);
    exit;
}

if (
    !isset($data->username, $data->password)
    || !is_string($data->username)
    || !is_string($data->password)
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
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

try {
    // Find the user.
    $stmt = $conn->prepare(
        'SELECT id, username, password_hash, role
         FROM users
         WHERE username = ?'
    );

if ($stmt === false) {
    throw new RuntimeException('Could not prepare the user query.');
}

$stmt->bind_param('s', $username);

if (!$stmt->execute()) {
    throw new RuntimeException('Could not execute the user query.');
}

    $result = $stmt->get_result();

    if ($result === false) {
        throw new RuntimeException('Could not read the user result.');
    }

    $user = $result->fetch_assoc();
    $result->free();
    $stmt->close();

    // Use the same message for unknown users and wrong passwords.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }

    // Generate the token, but store only its hash.
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $userId = (int) $user['id'];

    $stmt = $conn->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, NOW() + INTERVAL 8 HOUR)'
    );

    if ($stmt === false) {
        throw new RuntimeException('Could not prepare token storage.');
    }

    $stmt->bind_param('is', $userId, $tokenHash);

    if (!$stmt->execute()) {
        throw new RuntimeException('Could not store the token.');
    }

    $stmt->close();

    // Return the token only after storage succeeds.
    $response = json_encode([
        'token' => $token,
        'username' => $user['username'],
        'role' => $user['role']
    ], JSON_THROW_ON_ERROR);

    http_response_code(200);
    echo $response;
    exit;

} catch (Throwable $error) {
    error_log('API login failed: ' . $error->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Login is temporarily unavailable. Please try again later.'
    ]);
    exit;
}
$stmt ->execute();
$user = $stmt ->get_result()->fetch_assoc();

if(!$user || !password_verify($password, $user['password_hash']))
    {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $stmt = $conn->prepare(
        "INSERT INTO api_tokens (user_id, token_hash,expires_at)
        VALUES (?, ?, NOW() + INTERVAL 8 HOUR)"
    );

    $stmt->bind_param("is", $user['id'], $tokenHash);
    $stmt->execute();

    echo json_encode([
        'token' => $token,
        'username' => $user['username'],
        'role'  => $user['role'],
        ]);