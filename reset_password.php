<?php
require_once __DIR__ . '/auth.php';
require_role(['admin']);   // only admins

$me     = current_user();
$errors = [];

// Success message left by the previous request (shown once).
$success = $_SESSION['reset_success'] ?? '';
unset($_SESSION['reset_success']);

// Pre-select a user if the link was reset_password.php?id=5
$selectedId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$selectedId = $selectedId === false ? null : $selectedId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $targetId = filter_var(
        $_POST['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $selectedId = $targetId === false ? null : $targetId;

    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if ($targetId === false || $targetId === null) {
        $errors[] = 'Please choose a user.';
    } elseif ($targetId === (int) $me['id']) {
        $errors[] = 'Use the Change password page for your own account.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'The temporary password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The passwords do not match.';
    }

    if (!$errors) {
        $transactionStarted = false;

        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn->begin_transaction();
            $transactionStarted = true;

            // Make sure the user exists.
            $find = $conn->prepare('SELECT username FROM users WHERE id = ? FOR UPDATE');
            $find->bind_param('i', $targetId);
            $find->execute();
            $target = $find->get_result()->fetch_assoc();
            $find->close();

            if (!$target) {
                $conn->rollback();
                $transactionStarted = false;
                $errors[] = 'That user no longer exists.';
            } else {
                // Save the new password (hashed, never plain text).
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $update->bind_param('si', $hash, $targetId);
                $update->execute();
                $update->close();

                // Log them out of the Angular app: delete their API tokens.
                $tokens = $conn->prepare('DELETE FROM api_tokens WHERE user_id = ?');
                $tokens->bind_param('i', $targetId);
                $tokens->execute();
                $tokens->close();

                $conn->commit();
                $transactionStarted = false;

                $_SESSION['reset_success'] =
                    'Temporary password set for ' . $target['username'] . '.';
                header('Location: reset_password.php', true, 303);
                exit;
            }
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            error_log($e->getMessage());
            $errors[] = 'Could not reset the password. Please try again.';
        }
    }
}

// Users the admin can reset (everyone except themselves).
$listStmt = $conn->prepare('SELECT id, username, role FROM users WHERE id <> ? ORDER BY username');
$myId = (int) $me['id'];
$listStmt->bind_param('i', $myId);
$listStmt->execute();
$users = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Program Manager</title>
    <link rel="stylesheet" href="edit-program.css?v=1">
    <style>
        .success-box {
            margin-bottom: 24px; padding: 16px 18px;
            border: 1px solid #8fc7a2; border-radius: 14px;
            background: #eefaf1; color: #14663a; font-size: 14px;
        }
    </style>
</head>

<body class="edit-page">
    <main class="edit-card">
        <a class="back-link" href="index.php">
            <span aria-hidden="true">←</span>
            Back to programmes
        </a>

        <header class="edit-header">
            <p class="brand">PROGRAM MANAGER</p>
            <h1>Reset password</h1>
            <p class="subtitle">Set a temporary password for a user.</p>
        </header>

        <?php if ($success !== ''): ?>
            <div class="success-box" role="status"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="error-box" role="alert">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="reset_password.php" class="edit-form">
            <?= csrf_field() ?>

            <div class="field field-wide">
                <label for="user-id">User</label>
                <select id="user-id" name="user_id" required>
                    <option value="">Choose a user…</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $selectedId === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= h($u['username']) ?> (<?= h($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="new-password">Temporary password</label>
                <input id="new-password" name="password" type="password"
                       minlength="8" autocomplete="new-password" required>
            </div>

            <div class="field">
                <label for="confirm-password">Confirm password</label>
                <input id="confirm-password" name="confirm_password" type="password"
                       minlength="8" autocomplete="new-password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="save-button">Set temporary password</button>
                <a href="index.php" class="cancel-button">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>