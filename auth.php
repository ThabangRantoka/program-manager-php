<?php

// auth.php
// Include this file at the top of every page that requires authentication.
require_once __DIR__ . '/config.php';


// Start a secure PHP session if one has not already been started.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS'])
        && strtolower((string) $_SERVER['HTTPS']) !== 'off'
    ]);

    session_start();
}

/**
 * Return the currently logged-in user.
 *
 * Returns an array containing id, username and role,
 * or null when nobody is logged in.
 */
function current_user()
{
    global $conn;

    // Cache the user during the current request.
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $user = null;

    if (!empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];

        $stmt = $conn->prepare(
            "SELECT id, username, role
             FROM users
             WHERE id = ?"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc() ?: null;

        $stmt->close();
    }

    return $user;
}

/**
 * Check whether the logged-in user has one of the supplied roles.
 */
function has_role($roles)
{
    $user = current_user();

    return $user !== null
        && in_array($user['role'], (array) $roles, true);
}

/**
 * Require the user to be logged in.
 */
function require_login()
{
    if (current_user() === null) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require the logged-in user to have an allowed role.
 */
function require_role($roles)
{
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);

        echo "<h1>403 - Access denied</h1>";
        echo "<p>You do not have permission to access this page.</p>";
        echo "<p><a href='index.php'>Return to Program Manager</a></p>";

        exit;
    }
}

function csrf_token()
{
    if(empty($_SESSION['csrf_token']))
        {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
}
function csrf_field()
{
    $token = htmlspecialchars(
        csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    );

    return '<input type="hidden" name="csrf_token" value="'
    . $token .'">';
}

function verify_csrf()
{
     $stored = $_SESSION['csrf_token'] ?? '';
     $sent = $_POST['csrf_token']?? '';

     if(
        !is_string($stored)
        || !is_string($sent)
        || $stored ===''
        || $sent ===''
        || !hash_equals($stored, $sent)
     )
     {
        http_response_code(400);
        exit('Invalid form submission. Please refresh and try again.');
     }
}