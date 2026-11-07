<?php

require_once __DIR__ . '/auth.php';

// Only allow a fixed return destination, never an arbitrary URL.
$fromAngular =
    ($_POST['return_to'] ?? $_GET['return_to'] ?? '') === 'app';

$loginUrl = $fromAngular ? '/app/login' : 'login.php';

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = is_string($_POST['username'] ?? null)
        ? trim($_POST['username'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    $confirmPassword = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password']
        : '';

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username)) {
        $errors[] =
            'Username must contain 3–50 characters, using only letters, numbers and underscores.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare(
                'SELECT id FROM users WHERE username = ?'
            );

            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            $usernameExists = $stmt->num_rows > 0;
            $stmt->close();

            if ($usernameExists) {
                $errors[] = 'This username is already taken.';
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // The user must never select their own privileged role.
                $role = 'viewer';

                $stmt = $conn->prepare(
                    'INSERT INTO users (username, password_hash, role)
                     VALUES (?, ?, ?)'
                );

                $stmt->bind_param(
                    'sss',
                    $username,
                    $passwordHash,
                    $role
                );

                $stmt->execute();
                $stmt->close();

                header('Location: ' . $loginUrl . '?registered=1');
                exit;
            }
        } catch (mysqli_sql_exception $error) {
            if ((int) $error->getCode() === 1062) {
                $errors[] = 'This username is already taken.';
            } else {
                error_log($error->getMessage());

                $errors[] =
                    'Registration could not be completed. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | Program Manager</title>

    <link rel="stylesheet" href="auth-ui.css?v=2">
    <script src="auth-ui.js?v=2" defer></script>
</head>

<body class="auth-page">
    <div class="decoration circle-one" aria-hidden="true"></div>
    <div class="decoration circle-two" aria-hidden="true"></div>

    <main class="card">
        <div class="lock-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <rect
                    x="5" y="10" width="14" height="11"
                    rx="3" fill="currentColor"
                />
                <path
                    d="M8 10V7a4 4 0 0 1 8 0v3"
                    stroke="currentColor"
                    stroke-width="2.5"
                />
                <circle cx="12" cy="15" r="1.5" fill="#e8edef"/>
            </svg>
        </div>

        <p class="brand">PROGRAM MANAGER</p>
        <h1>Create an account</h1>
        <p class="subtitle">Create your account to view programmes.</p>

        <?php if (!empty($errors)): ?>
            <div class="errors" role="alert">
                <strong>Please correct the following:</strong>

                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li>
                            <?= htmlspecialchars(
                                $message,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="return_to"
                value="<?= $fromAngular ? 'app' : 'php' ?>"
            >

            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                autocomplete="username"
                maxlength="50"
                value="<?= htmlspecialchars(
                    $username,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

            <p class="hint">
                Use 3–50 letters, numbers or underscores.
            </p>

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                required
            >

            <p class="hint">Use at least 8 characters.</p>

            <label for="confirm_password">Confirm password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                autocomplete="new-password"
                required
            >

            <button type="submit">Register</button>
        </form>

        <p>
            Already have an account?
            <a href="<?= htmlspecialchars(
                $loginUrl,
                ENT_QUOTES,
                'UTF-8'
            ) ?>">Log in</a>
        </p>
    </main>
</body>
</html>