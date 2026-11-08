<?php
require_once __DIR__ . '/auth.php';

require_login();

header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function password_page_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// The account ID comes from the authenticated session, not the form.
$userId = (int) current_user()['id'];

$errors = [];
$success = $_SESSION['password_change_success'] ?? '';
unset($_SESSION['password_change_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmation = $_POST['confirm_password'] ?? '';

    if (
        !is_string($currentPassword) ||
        !is_string($newPassword) ||
        !is_string($confirmation)
    ) {
        $errors[] = 'Invalid form submission.';
    } elseif (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmation === ''
    ) {
        $errors[] = 'Please complete all three password fields.';
    } else {
        // Do not trim passwords: spaces can be intentional.
        // The 72-byte maximum avoids bcrypt password truncation.
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $errors[] = 'Use a new password between 8 and 72 bytes long.';
        }

        if (strpos($newPassword, "\0") !== false) {
            $errors[] = 'The new password contains an unsupported character.';
        }

        if ($newPassword !== $confirmation) {
            $errors[] = 'The new passwords do not match.';
        }
    }

    if (empty($errors)) {
        $transactionStarted = false;

        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            // Lock the account while checking and changing its password.
            $stmt = $conn->prepare(
                'SELECT password_hash
                 FROM users
                 WHERE id = ?
                 FOR UPDATE'
            );

            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$account) {
                $errors[] = 'Your account is no longer available.';
            } elseif (
                !password_verify(
                    $currentPassword,
                    $account['password_hash']
                )
            ) {
                $errors[] = 'Your current password is incorrect.';
            } elseif (
                password_verify(
                    $newPassword,
                    $account['password_hash']
                )
            ) {
                $errors[] = 'Choose a password different from your current password.';
            }

            if (!empty($errors)) {
                $conn->rollback();
                $transactionStarted = false;
            } else {
                $newHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    'UPDATE users
                     SET password_hash = ?
                     WHERE id = ?'
                );

                $stmt->bind_param('si', $newHash, $userId);
                $stmt->execute();
                $stmt->close();

                // Revoke the API tokens currently stored for this user.
                $stmt = $conn->prepare(
                    'DELETE FROM api_tokens WHERE user_id = ?'
                );

                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $transactionStarted = false;

                // Refresh this browser session and its CSRF token.
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);

                $_SESSION['password_change_success'] =
                    'Password changed successfully. '
                    . 'Existing stored API tokens were revoked.';

                header('Location: change_password.php', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            error_log(
                'Password change failed: ' . $exception->getMessage()
            );

            http_response_code(500);
            $errors[] =
                'The password change could not be completed. '
                . 'Please try again later.';
        }
    }

    // Passwords are never repopulated into the form.
    unset($currentPassword, $newPassword, $confirmation);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change password | Program Manager</title>

    <link rel="stylesheet" href="auth-ui.css?v=2">

    <style>
        .password-card {
            max-width: 480px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 24px;
        }

        .password-card h1 {
            font-size: 28px;
        }

        .password-card form {
            margin-top: 20px;
        }

        .password-card .session-note {
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.6;
            color: #52636b;
        }
    </style>
</head>
<body>
<main class="auth-page">
    <section class="card password-card" aria-labelledby="page-title">
        <a class="back-link" href="index.php">
            ← Back to programmes
        </a>

        <p class="brand">PROGRAM MANAGER</p>

        <h1 id="page-title">Change password</h1>

        <p class="subtitle">
            Confirm your current password before choosing a new one.
        </p>

        <?php if ($success !== ''): ?>
            <p class="success" role="status">
                <?= password_page_escape($success) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= password_page_escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="change_password.php">
            <?= csrf_field() ?>

            <div class="field">
                <label for="current-password">Current password</label>

                <input
                    id="current-password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="field">
                <label for="new-password">New password</label>

                <input
                    id="new-password"
                    name="new_password"
                    type="password"
                    autocomplete="new-password"
                    aria-describedby="password-help"
                    minlength="8"
                    required
                >

                <p id="password-help" class="hint">
                    Use a unique passphrase, at least 8 characters long.
                    Maximum 72 bytes; some characters use more than one byte.
                </p>
            </div>

            <div class="field">
                <label for="confirm-password">Confirm new password</label>

                <input
                    id="confirm-password"
                    name="confirm_password"
                    type="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit">Update password</button>
        </form>

        <p class="session-note">
            This browser stays signed in. Existing stored API tokens are
            revoked, so affected Angular sessions must log in again.
            Other PHP browser sessions are not signed out by this page.
        </p>
    </section>
</main>
</body>
</html>