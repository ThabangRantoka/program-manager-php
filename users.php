<?php
require_once __DIR__ . '/auth.php';

require_role(['admin']);

header('Cache-Control: no-store');

function users_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$allowedRoles = ['viewer', 'scheduler', 'admin'];
$actorId = (int) current_user()['id'];

$error = '';
$success = $_SESSION['users_success'] ?? '';
unset($_SESSION['users_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $targetId = filter_var(
        $_POST['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $newRole = $_POST['role'] ?? '';

    if (
        !is_string($action) ||
        !in_array($action, ['change_role', 'delete_user'], true) ||
        $targetId === false ||
        $targetId === null
    ) {
        http_response_code(400);
        $error = 'Invalid request. Please refresh and try again.';
    } elseif ($targetId === $actorId) {
        http_response_code(403);
        $error = 'You cannot change your own role or delete yourself.';
    } elseif (
        $action === 'change_role' &&
        (!is_string($newRole) || !in_array($newRole, $allowedRoles, true))
    ) {
        http_response_code(400);
        $error = 'Select a valid role.';
    } else {
        $transactionStarted = false;

        try {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to begin transaction.');
            }

            $transactionStarted = true;

            /*
             * Lock users in a consistent order.
             * This serializes management requests so two admins cannot
             * simultaneously remove each other's admin access.
             *
             * Appropriate for this small project; a large application
             * would use a more targeted locking strategy.
             */
            $result = $conn->query(
                'SELECT id, username, role
                 FROM users
                 ORDER BY id
                 FOR UPDATE'
            );

            if ($result === false) {
                throw new RuntimeException('Unable to lock user records.');
            }

            $lockedUsers = [];
            $adminCount = 0;

            while ($row = $result->fetch_assoc()) {
                $lockedUsers[(int) $row['id']] = $row;

                if ($row['role'] === 'admin') {
                    $adminCount++;
                }
            }

            $result->free();

            /*
             * Recheck the acting admin after obtaining the locks.
             * Their role may have changed since the initial page check.
             */
            $actor = $lockedUsers[$actorId] ?? null;
            $target = $lockedUsers[$targetId] ?? null;

            if ($actor === null || $actor['role'] !== 'admin') {
                http_response_code(403);
                $error = 'Your account no longer has administrator access.';
            } elseif ($target === null) {
                http_response_code(404);
                $error = 'That user no longer exists.';
            } else {
                $removesAdmin = $target['role'] === 'admin' &&
                    (
                        $action === 'delete_user' ||
                        ($action === 'change_role' && $newRole !== 'admin')
                    );

                if ($removesAdmin && $adminCount <= 1) {
                    http_response_code(409);
                    $error = 'At least one administrator must remain.';
                }
            }

            if ($error !== '') {
                $conn->rollback();
                $transactionStarted = false;
            } else {
                if ($action === 'change_role') {
                    $stmt = $conn->prepare(
                        'UPDATE users SET role = ? WHERE id = ?'
                    );

                    if ($stmt === false) {
                        throw new RuntimeException('Unable to prepare role update.');
                    }

                    $stmt->bind_param('si', $newRole, $targetId);
                    $message = 'User role saved successfully.';
                } else {
                    /*
                     * The foreign key automatically removes this
                     * user's API tokens when their account is deleted.
                     */
                    $stmt = $conn->prepare(
                        'DELETE FROM users WHERE id = ?'
                    );

                    if ($stmt === false) {
                        throw new RuntimeException('Unable to prepare user deletion.');
                    }

                    $stmt->bind_param('i', $targetId);
                    $message = 'User account and its API tokens were deleted.';
                }

                if (!$stmt->execute()) {
                    throw new RuntimeException('Unable to save user change.');
                }

                $stmt->close();

                if (!$conn->commit()) {
                    throw new RuntimeException('Unable to commit user change.');
                }

                $transactionStarted = false;
                $_SESSION['users_success'] = $message;

                header('Location: users.php', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            error_log('User management: ' . $exception->getMessage());
            http_response_code(500);
            $error = 'The change could not be completed. Please refresh and try again.';
        }
    }
}

$users = [];
$listAvailable = true;

try {
    $result = $conn->query(
        'SELECT id, username, role, created_at
         FROM users
         ORDER BY username, id'
    );

    if ($result === false) {
        throw new RuntimeException('Unable to retrieve users.');
    }

    $users = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} catch (Throwable $exception) {
    error_log('User listing: ' . $exception->getMessage());
    http_response_code(500);
    $listAvailable = false;
    $error = 'The user list is temporarily unavailable.';
}

$displayAdminCount = 0;

foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $displayAdminCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User management | Program Manager</title>
    <link rel="stylesheet" href="users.css?v=1">
</head>
<body>
<main class="users-page">
    <a class="back-link" href="index.php">← Back to programmes</a>

    <header class="page-header">
        <p class="brand">PROGRAM MANAGER · ADMINISTRATION</p>
        <h1>User management</h1>
        <p>Manage account roles and access to your programme manager.</p>
    </header>

    <?php if ($success !== ''): ?>
        <p class="message success" role="status">
            <?= users_escape($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="message error" role="alert">
            <?= users_escape($error) ?>
        </p>
    <?php endif; ?>

    <section class="panel" aria-labelledby="accounts-heading">
        <div class="panel-heading">
            <h2 id="accounts-heading">User accounts</h2>

            <?php if ($listAvailable): ?>
                <span class="count"><?= count($users) ?> accounts</span>
            <?php endif; ?>
        </div>

        <p class="help">
            Viewers can view programmes. Schedulers and admins can manage
            programmes. Only admins can manage users.
        </p>

        <p class="help">
            Your own account is protected, and at least one admin must remain.
            Deleting an account also revokes its API tokens.
        </p>

        <?php if ($listAvailable): ?>
            <div
                class="table-scroll"
                role="region"
                aria-label="User accounts"
                tabindex="0"
            >
                <table>
                    <caption class="sr-only">
                        Accounts, assigned roles and administration actions
                    </caption>
                    <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <th scope="col">Created</th>
                        <th scope="col">Change role</th>
                        <th scope="col">Delete account</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId = (int) $user['id'];
                        $isSelf = $userId === $actorId;
                        $isLastAdmin = $user['role'] === 'admin' &&
                            $displayAdminCount <= 1;
                        $protected = $isSelf || $isLastAdmin;
                        ?>
                        <tr>
                            <th scope="row">
                                <?= users_escape($user['username']) ?>

                                <?php if ($isSelf): ?>
                                    <span class="you">You</span>
                                <?php endif; ?>
                            </th>

                            <td>
                                <span class="role">
                                    <?= users_escape(ucfirst($user['role'])) ?>
                                </span>
                            </td>

                            <td>
                                <?= users_escape($user['created_at']) ?>
                            </td>

                            <td>
                                <?php if ($protected): ?>
                                    <span class="muted">
                                        <?= $isSelf ? 'Your account' : 'Last admin' ?>
                                    </span>
                                <?php else: ?>
                                    <form method="post" action="users.php" class="role-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= $userId ?>">

                                        <label class="sr-only" for="role-<?= $userId ?>">
                                            Role for <?= users_escape($user['username']) ?>
                                        </label>

                                        <select id="role-<?= $userId ?>" name="role" required>
                                            <?php foreach ($allowedRoles as $role): ?>
                                                <option
                                                    value="<?= users_escape($role) ?>"
                                                    <?= $user['role'] === $role ? 'selected' : '' ?>
                                                >
                                                    <?= users_escape(ucfirst($role)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit">Save</button>
                                    </form>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($protected): ?>
                                    <span class="muted">Protected</span>
                                <?php else: ?>
                                    <form method="post" action="users.php" class="delete-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $userId ?>">

                                        <button
                                            type="submit"
                                            class="danger"
                                            aria-label="Delete account <?= users_escape($user['username']) ?>"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.querySelectorAll('.delete-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const confirmed = window.confirm(
            'Permanently delete this user account and revoke its API tokens? ' +
            'This cannot be undone.'
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
});
</script>
</body>
</html>