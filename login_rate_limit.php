<?php

/*
 * Shared login protection for login.php and api_login.php.
 *
 * Five consecutive failures block a username for 15 minutes.
 * Successful authentication resets the counter.
 * Attempts during a block do not extend it.
 *
 * Call before starting another database transaction.
 */

function rate_limited_login(
    mysqli $conn,
    string $username,
    string $password
): array {
    $username = trim($username);

    // Match the username format used by registration.
    if (
        !preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username) ||
        $password === ''
    ) {
        return ['status' => 'invalid'];
    }

    /*
     * Throw database exceptions rather than silently continuing
     * if a security-related database operation fails.
     */
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn->begin_transaction();

    try {
        /*
         * Create the counter if needed.
         * On an existing row, this obtains an exclusive row lock.
         * The table's collation handles case-insensitive usernames.
         */
        $stmt = $conn->prepare(
            'INSERT INTO login_attempts (username)
             VALUES (?)
             ON DUPLICATE KEY UPDATE username = username'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            'SELECT failed_attempts, blocked_until,
                    CASE
                        WHEN blocked_until > NOW()
                        THEN TIMESTAMPDIFF(SECOND, NOW(), blocked_until)
                        ELSE 0
                    END AS retry_after
             FROM login_attempts
             WHERE username = ?
             FOR UPDATE'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $attempt = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attempt) {
            throw new RuntimeException('Login counter could not be loaded.');
        }

        $retryAfter = (int) $attempt['retry_after'];

        if ($retryAfter > 0) {
            $conn->commit();

            return [
                'status' => 'blocked',
                'retry_after' => $retryAfter
            ];
        }

        /*
         * An expired block starts a fresh sequence.
         * Otherwise retain consecutive failures until a successful login.
         */
        $failures = $attempt['blocked_until'] !== null
            ? 0
            : (int) $attempt['failed_attempts'];

        $stmt = $conn->prepare(
            'SELECT id, username, password_hash, role
             FROM users
             WHERE username = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        /*
         * Use a dummy hash for unknown usernames so a password-hash
         * check still happens. This is not an account credential.
         */
        $dummyHash =
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

        $passwordMatches = password_verify(
            $password,
            $user ? $user['password_hash'] : $dummyHash
        );

        if ($user && $passwordMatches) {
            $stmt = $conn->prepare(
                'UPDATE login_attempts
                 SET failed_attempts = 0,
                     blocked_until = NULL,
                     last_attempt_at = NOW()
                 WHERE username = ?'
            );
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            // Never return the password hash to the calling page.
            unset($user['password_hash']);

            return [
                'status' => 'success',
                'user' => $user
            ];
        }

        $failures++;

        if ($failures >= 5) {
            $stmt = $conn->prepare(
                'UPDATE login_attempts
                 SET failed_attempts = 5,
                     blocked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                     last_attempt_at = NOW()
                 WHERE username = ?'
            );
            $stmt->bind_param('s', $username);
        } else {
            $stmt = $conn->prepare(
                'UPDATE login_attempts
                 SET failed_attempts = ?,
                     blocked_until = NULL,
                     last_attempt_at = NOW()
                 WHERE username = ?'
            );
            $stmt->bind_param('is', $failures, $username);
        }

        $stmt->execute();
        $stmt->close();

        $conn->commit();

        if ($failures >= 5) {
            return [
                'status' => 'blocked',
                'retry_after' => 900
            ];
        }

        return ['status' => 'invalid'];
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }
}