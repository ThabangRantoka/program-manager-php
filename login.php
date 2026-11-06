<?php

require_once __DIR__ . '/auth.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';

$registered = ($_GET['registered'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = is_string($_POST['username'] ?? null)
        ? trim($_POST['username'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    if ($username === '' || $password === '') {
        $errors[] = 'Invalid username or password.';
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

            $errors[] = 'Login is temporarily unavailable. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Program Manager</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #243447;
        }

        .card {
            max-width: 480px;
            margin: 0 auto;
            padding: 32px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #287d32;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #b8c1cc;
            border-radius: 6px;
            font: inherit;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 12px;
            background: #287d32;
            color: white;
            border: 0;
            border-radius: 6px;
            font: inherit;
            cursor: pointer;
        }

        button:hover {
            background: #206628;
        }

        .success {
            padding: 16px;
            background: #edf9ee;
            border: 1px solid #92c998;
            border-radius: 6px;
            color: #205c29;
        }

        .errors {
            padding: 16px;
            background: #fff0f0;
            border: 1px solid #e7a0a0;
            border-radius: 6px;
            color: #a51d1d;
        }

        .errors ul {
            margin: 0;
            padding-left: 20px;
        }

        a {
            color: #287d32;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Program Manager</h1>
        <h2>Log in</h2>
         <?php if ($registered): ?>
            <div style="
                padding:15px;
                margin-bottom: 20px;
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
                border-radius: 6px;
                " role="status">
                Account created, please log in.
         </div>
         <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors" role="alert">
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

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Log in</button>
        </form>

        <p>
            No account yet?
            <a href="register.php">Create an account</a>
        </p>
    </main>
</body>
</html>