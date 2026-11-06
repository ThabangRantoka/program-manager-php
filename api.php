<?php

// --------------------------------------------------
// 1. RESPONSE HEADERS
// --------------------------------------------------
header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Send one JSON response, then stop the script.
function send_json(int $status, array $body): void
{
    // Encode before sending, so an encoding failure can be caught.
    $json = json_encode($body, JSON_THROW_ON_ERROR);

    http_response_code($status);
    echo $json;
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// --------------------------------------------------
// 2. HANDLE PREFLIGHT AND UNSUPPORTED METHODS
// --------------------------------------------------
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST, OPTIONS');

    send_json(405, [
        'error' => 'Method not allowed.'
    ]);
}

// --------------------------------------------------
// 3. LOAD DEPENDENCIES AND PROCESS THE REQUEST
// --------------------------------------------------
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/api_auth.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    if (!$conn->set_charset('utf8mb4')) {
        throw new RuntimeException('Could not set the database charset.');
    }

    // --------------------------------------------------
    // 4. POST: ADD A PROGRAMME
    // --------------------------------------------------
    if ($method === 'POST') {
        // Authenticate the token and check permission first.
        $user = api_require_role(['scheduler', 'admin']);

        // Read the JSON request body.
        $rawBody = file_get_contents('php://input');

        if ($rawBody === false) {
            throw new RuntimeException('Could not read the request body.');
        }

        $data = json_decode($rawBody);

        // Require a valid JSON object.
        if (
            json_last_error() !== JSON_ERROR_NONE
            || !is_object($data)
        ) {
            send_json(400, [
                'error' => 'Send a valid JSON object.'
            ]);
        }

        // Read text fields safely.
        $name = is_string($data->name ?? null)
            ? trim($data->name)
            : '';

        $time_slot = is_string($data->time_slot ?? null)
            ? trim($data->time_slot)
            : '';

        $channel = is_string($data->channel ?? null)
            ? trim($data->channel)
            : '';

        // Accept an integer or an integer supplied as text.
        $rawDuration = $data->duration ?? null;
        $duration = false;

        if (is_int($rawDuration) || is_string($rawDuration)) {
            $duration = filter_var(
                $rawDuration,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 600
                    ]
                ]
            );
        }

        // Validate the programme details.
        $errors = [];

        if (strlen($name) < 3) {
            $errors[] = 'Program Name must be at least 3 characters.';
        }

        // strlen measures bytes, not Unicode characters.
        if (strlen($name) > 255) {
            $errors[] = 'Program Name must not exceed 255 bytes.';
        }

        if ($duration === false) {
            $errors[] = 'Duration must be a whole number between 1 and 600.';
        }

        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D', $time_slot)) {
            $errors[] = 'Time Slot must use HH:MM format, for example 08:30.';
        }

        $allowedChannels = [
            'SABC1',
            'SABC2',
            'SABC3',
            'eTV',
            'Mzansi Magic',
            'SuperSport',
            'M-Net'
        ];

        if (!in_array($channel, $allowedChannels, true)) {
            $errors[] = 'Please select a valid channel.';
        }

        // Stop before INSERT if any details are invalid.
        if (!empty($errors)) {
            send_json(422, [
                'success' => false,
                'errors' => $errors
            ]);
        }

        // Save the validated programme.
        $stmt = $conn->prepare(
            'INSERT INTO programs (name, duration, time_slot, channel)
             VALUES (?, ?, ?, ?)'
        );

        if ($stmt === false) {
            throw new RuntimeException('Could not prepare the INSERT query.');
        }

        $stmt->bind_param(
            'siss',
            $name,
            $duration,
            $time_slot,
            $channel
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Could not insert the programme.');
        }

        $newId = (int) $conn->insert_id;
        $stmt->close();

        send_json(201, [
            'success' => true,
            'id' => $newId
        ]);
    }

    // --------------------------------------------------
    // 5. GET: LIST PROGRAMMES
    // --------------------------------------------------
    // POST stops above, so only GET reaches this section.
    $user = api_require_role(['viewer', 'scheduler', 'admin']);

    $stmt = $conn->prepare(
        'SELECT * FROM programs ORDER BY time_slot ASC, id ASC'
    );

    if ($stmt === false) {
        throw new RuntimeException('Could not prepare the SELECT query.');
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Could not execute the SELECT query.');
    }

    $result = $stmt->get_result();

    if ($result === false) {
        throw new RuntimeException('Could not read the programme results.');
    }

    $programs = $result->fetch_all(MYSQLI_ASSOC);

    $result->free();
    $stmt->close();

    send_json(200, $programs);

} catch (Throwable $error) {
    // Keep internal details in the server log.
    error_log('Program API failed: ' . $error->getMessage());

    send_json(500, [
        'success' => false,
        'error' => 'The server could not complete the request. Please try again later.'
    ]);
}