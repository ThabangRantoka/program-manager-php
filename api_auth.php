<?php

function api_user()
{
    global $conn;

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if($header === '' && function_exists('getallheaders'))
        {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $header = $headers['authorization'] ?? '';

        }

if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
    return null;
}

$tokenHash = hash('sha256', strtolower($matches[1]));

            $stmt = $conn->prepare(
                "SELECT u.id, u.username, u.role
                FROM api_tokens t JOIN users u ON u.id = t.user_id
                WHERE t.token_hash = ? AND t.expires_at > NOW()"
            );

        $stmt->bind_param('s', $tokenHash);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$result->free();
$stmt->close();

return $user ?: null;

}

function api_require_role($roles)
{
    $user = api_user();

    // No valid, unexpired token was found.
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }

    // A user was found, but their role is not permitted.
    if (!in_array($user['role'], (array) $roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Not allowed']);
        exit;
    }

    return $user;
}