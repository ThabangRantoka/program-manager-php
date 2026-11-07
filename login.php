<?php

require_once __DIR__ . '/auth.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$errors = [];
$username = '';
$registered = ($_GET['registered'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $mode = ($_POST['action'] ?? '') === 'register'
        ? 'register'
        : 'login';

    $username = is_string($_POST['username'] ?? null)
        ? trim($_POST['username'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    if ($mode === 'login') {
        if ($username === '' || $password === '') {
            $errors[] = 'Please enter your username and password.';
        } else {
            try {
                $stmt = $conn->prepare(
                    'SELECT id, password_hash
                     FROM users
                     WHERE username = ?'
                );

                $stmt->bind_param('s', $username);
                $stmt->execute();

                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (
                    $user &&
                    password_verify($password, $user['password_hash'])
                ) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];

                    header('Location: index.php');
                    exit;
                }

                $errors[] = 'Invalid username or password.';
            } catch (mysqli_sql_exception $error) {
                error_log($error->getMessage());
                $errors[] = 'Login is temporarily unavailable.';
            }
        }
    } else {
        $confirmation = is_string($_POST['confirm_password'] ?? null)
            ? $_POST['confirm_password']
            : '';

        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username)) {
            $errors[] =
                'Use 3–50 letters, numbers or underscores for your username.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Your password must contain at least 8 characters.';
        }

        if ($password !== $confirmation) {
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

                $exists = $stmt->num_rows > 0;
                $stmt->close();

                if ($exists) {
                    $errors[] = 'This username is already taken.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    // Registration never grants an elevated role.
                    $role = 'viewer';

                    $stmt = $conn->prepare(
                        'INSERT INTO users (username, password_hash, role)
                         VALUES (?, ?, ?)'
                    );

                    $stmt->bind_param('sss', $username, $hash, $role);
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
                    $errors[] = 'Registration is temporarily unavailable.';
                }
            }
        }
    }
}

$isRegister = $mode === 'register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Program Manager</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="auth-flip.css?v=1">
    <script src="auth-flip.js?v=1" defer></script>
</head>

<body>
    <!-- Reusable icons -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        class="icon-definitions"
        aria-hidden="true"
    >
        <defs>
            <symbol id="icon-lock" viewBox="0 0 24 24">
                <rect
                    x="5" y="10" width="14" height="11"
                    rx="3" fill="currentColor"
                />
                <path
                    d="M8 10V7a4 4 0 0 1 8 0v3"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                />
                <circle cx="12" cy="15" r="1.5" fill="#e7ebf0"/>
            </symbol>

            <symbol id="icon-user" viewBox="0 0 24 24">
                <circle cx="12" cy="7" r="4" fill="currentColor"/>
                <path
                    d="M4 21v-2a8 8 0 0 1 16 0v2z"
                    fill="currentColor"
                />
            </symbol>

            <symbol id="icon-eye" viewBox="0 0 24 24">
                <path
                    d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                />
                <circle
                    cx="12" cy="12" r="3"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                />
            </symbol>
        </defs>
    </svg>

    <div class="bg-circle bg-one" aria-hidden="true"></div>
    <div class="bg-circle bg-two" aria-hidden="true"></div>
    <div class="bg-circle bg-three" aria-hidden="true"></div>

    <main class="auth-wrapper" aria-label="Program Manager account access">
        <div
            id="auth-card"
            class="auth-card<?= $isRegister ? ' flipped' : '' ?>"
        >
            <!-- LOGIN SIDE -->
            <section
                id="login-panel"
                class="card-face card-front"
                aria-labelledby="login-title"
                <?= $isRegister ? 'inert aria-hidden="true"' : '' ?>
            >
                <div class="logo" aria-hidden="true">
                    <svg><use href="#icon-lock"></use></svg>
                </div>

                <h1 id="login-title" tabindex="-1">Welcome</h1>
                <p class="subtitle">Login to continue your journey</p>

                <?php if ($registered): ?>
                    <div class="notice success" role="status">
                        Account created. Please log in.
                    </div>
                <?php endif; ?>

                <?php if (!$isRegister && !empty($errors)): ?>
                    <div class="notice error" role="alert">
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

                <form method="POST" action="login.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login">

                    <div class="input-group">
                        <label class="sr-only" for="login-username">
                            Username
                        </label>

                        <input
                            id="login-username"
                            name="username"
                            type="text"
                            placeholder="Username"
                            autocomplete="username"
                            maxlength="50"
                            value="<?= !$isRegister
                                ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8')
                                : '' ?>"
                            required
                        >
                    </div>

                    <div class="input-group password-group">
                        <label class="sr-only" for="login-password">
                            Password
                        </label>

                        <input
                            id="login-password"
                            name="password"
                            type="password"
                            placeholder="Password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            class="eye-button"
                            type="button"
                            data-password="login-password"
                            aria-controls="login-password"
                            aria-label="Show password"
                            aria-pressed="false"
                            hidden
                        >
                            <svg aria-hidden="true">
                                <use href="#icon-eye"></use>
                            </svg>
                        </button>
                    </div>

                    <button class="main-button" type="submit">
                        <span>Login</span>
                    </button>
                </form>

                <div class="switch-area">
                    <span>Don’t have an account?</span>

                    <a
                        class="switch-circle"
                        href="login.php?mode=register"
                        data-switch="register"
                        aria-label="Create an account"
                    >
                        <span aria-hidden="true">+</span>
                    </a>
                </div>
            </section>

            <!-- REGISTRATION SIDE -->
            <section
                id="register-panel"
                class="card-face card-back"
                aria-labelledby="register-title"
                <?= !$isRegister ? 'inert aria-hidden="true"' : '' ?>
            >
                <div class="logo" aria-hidden="true">
                    <svg><use href="#icon-user"></use></svg>
                </div>

                <h1 id="register-title" tabindex="-1">Create Account</h1>
                <p class="subtitle">Start your journey with us</p>

                <?php if ($isRegister && !empty($errors)): ?>
                    <div class="notice error" role="alert">
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

                <form method="POST" action="login.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register">

                    <div class="input-group">
                        <label class="sr-only" for="register-username">
                            Username
                        </label>

                        <input
                            id="register-username"
                            name="username"
                            type="text"
                            placeholder="Username"
                            autocomplete="username"
                            maxlength="50"
                            aria-describedby="username-hint"
                            value="<?= $isRegister
                                ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8')
                                : '' ?>"
                            required
                        >
                    </div>

                    <div class="input-group password-group">
                        <label class="sr-only" for="register-password">
                            Password
                        </label>

                        <input
                            id="register-password"
                            name="password"
                            type="password"
                            placeholder="Password"
                            autocomplete="new-password"
                            aria-describedby="password-hint"
                            required
                        >

                        <button
                            class="eye-button"
                            type="button"
                            data-password="register-password"
                            aria-controls="register-password"
                            aria-label="Show password"
                            aria-pressed="false"
                            hidden
                        >
                            <svg aria-hidden="true">
                                <use href="#icon-eye"></use>
                            </svg>
                        </button>
                    </div>

                    <div class="input-group password-group">
                        <label class="sr-only" for="confirm-password">
                            Confirm password
                        </label>

                        <input
                            id="confirm-password"
                            name="confirm_password"
                            type="password"
                            placeholder="Confirm password"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            class="eye-button"
                            type="button"
                            data-password="confirm-password"
                            aria-controls="confirm-password"
                            aria-label="Show confirm password"
                            aria-pressed="false"
                            hidden
                        >
                            <svg aria-hidden="true">
                                <use href="#icon-eye"></use>
                            </svg>
                        </button>
                    </div>

                    <div class="form-hints">
                        <p id="username-hint">
                            Username: 3–50 letters, numbers or underscores.
                        </p>
                        <p id="password-hint">
                            Password: at least 8 characters.
                        </p>
                    </div>

                    <button class="main-button" type="submit">
                        <span>Create account</span>
                    </button>
                </form>

                <div class="switch-area">
                    <span>Already have an account?</span>

                    <a
                        class="switch-circle"
                        href="login.php"
                        data-switch="login"
                        aria-label="Return to login"
                    >
                        <span aria-hidden="true">←</span>
                    </a>
                </div>
            </section>
        </div>
    </main>
</body>
</html>