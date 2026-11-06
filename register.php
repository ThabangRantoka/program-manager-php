<?php

require_once __DIR__ . '/auth.php';

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

    // Validate the username.
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username)) {
        $errors[] = 'Username must contain 3–50 characters, using only letters, numbers and underscores.';
    }

    // Validate the password without trimming it.
    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            // Check whether the username already exists.
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

                // Never accept the role from the registration form.
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

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (mysqli_sql_exception $error) {
            if ((int) $error->getCode() === 1062) {
                $errors[] = 'This username is already taken.';
            } else {
                error_log($error->getMessage());
                $errors[] = 'Registration could not be completed. Please try again later.';
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

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        min-height: 100vh;
        min-height: 100dvh;
        padding: 40px 20px;
        display: grid;
        place-items: center;
        background: #e8edef;
        color: #283940;
        font-family: Arial, sans-serif;
    }

    .card {
        width: 100%;
        max-width: 460px;
        margin: 0;
        padding: 38px 34px;
        border-radius: 30px;
        background: #e8edef;
        box-shadow:
            16px 16px 34px #c5ced2,
            -16px -16px 34px #ffffff;
    }

    h1 {
        margin: 0 0 14px;
        color: #075f99;
        text-align: center;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    h2 {
        margin: 0 0 28px;
        color: #283940;
        text-align: center;
        font-size: 28px;
    }

    label {
        display: block;
        margin: 22px 0 9px;
        color: #41545e;
        font-size: 14px;
        font-weight: 700;
    }

    input:not([type="hidden"]) {
        width: 100%;
        min-height: 52px;
        padding: 14px 16px;
        border: 2px solid transparent;
        border-radius: 14px;
        background: #e8edef;
        color: #283940;
        font: inherit;
        box-shadow:
            inset 5px 5px 10px #cbd3d7,
            inset -5px -5px 10px #ffffff;
    }

    input:focus {
        outline: none;
        border-color: #0874b9;
    }

    .hint {
        margin: 10px 0 0;
        color: #52636b;
        font-size: 12px;
        line-height: 1.6;
    }

    button {
        width: 100%;
        min-height: 52px;
        margin-top: 28px;
        padding: 14px;
        border: 0;
        border-radius: 14px;
        background: #e8edef;
        color: #075f99;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
        box-shadow:
            6px 6px 12px #c5ced2,
            -6px -6px 12px #ffffff;
    }

    button:hover:not(:disabled) {
        background: #dfe8ed;
    }

    button:active:not(:disabled) {
        box-shadow:
            inset 4px 4px 8px #c5ced2,
            inset -4px -4px 8px #ffffff;
    }

    button:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    button:focus-visible,
    a:focus-visible {
        outline: 3px solid #0874b9;
        outline-offset: 4px;
    }

    .errors {
        margin-bottom: 20px;
        padding: 16px;
        border: 1px solid #c77d7d;
        border-radius: 14px;
        background: #fff0f0;
        color: #942525;
        font-size: 14px;
        line-height: 1.6;
    }

    .errors ul {
        margin: 8px 0 0;
        padding-left: 20px;
    }

    .errors li {
        margin-bottom: 6px;
    }

    .card > p {
        margin: 26px 0 0;
        color: #52636b;
        text-align: center;
        font-size: 13px;
        line-height: 1.8;
    }

    a {
        color: #075f99;
        font-weight: 700;
        text-underline-offset: 3px;
    }

    @media (max-width: 480px) {
        body {
            padding: 28px 18px;
        }

        .card {
            padding: 30px 24px;
            border-radius: 24px;
        }

        h2 {
            font-size: 25px;
        }
    }
</style>
</head>
<body>
    <main class="card">
        <h1>Program Manager</h1>
        <h2>Create an account</h2>

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
            <a href="login.php">Log in</a>
        </p>
    </main>
</body>
</html>