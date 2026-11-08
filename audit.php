<?php
declare(strict_types=1);

/**
 * Record a successful programme change.
 *
 * The caller must:
 * - authenticate the user and check permission;
 * - begin a database transaction;
 * - save the programme change;
 * - call this function before committing.
 * What it does: inserts the authenticated user’s identity, action, programme details -
 * -and source into audit_logs. 
 * -The database supplies the timestamp. It does not record passwords or tokens.
 * This function deliberately does not commit or swallow errors.
 */
function record_program_audit(
    mysqli $conn,
    array $actor,
    string $action,
    int $programId,
    string $programName,
    string $source
): void {
    $actorId = filter_var(
        $actor['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $actorUsername = $actor['username'] ?? null;

    if (
        $actorId === false
        || !is_string($actorUsername)
        || $actorUsername === ''
    ) {
        throw new InvalidArgumentException('Invalid audit actor.');
    }

    if (!in_array($action, ['add', 'edit', 'delete'], true)) {
        throw new InvalidArgumentException('Invalid audit action.');
    }

    if (!in_array($source, ['php', 'api'], true)) {
        throw new InvalidArgumentException('Invalid audit source.');
    }

    if ($programId < 1 || $programName === '') {
        throw new InvalidArgumentException('Invalid audit programme.');
    }

    $stmt = $conn->prepare(
        'INSERT INTO audit_logs
            (
                actor_user_id,
                actor_username,
                action,
                program_id,
                program_name,
                source
            )
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($stmt === false) {
        throw new RuntimeException('Could not prepare audit entry.');
    }

    try {
        if (!$stmt->bind_param(
            'ississ',
            $actorId,
            $actorUsername,
            $action,
            $programId,
            $programName,
            $source
        )) {
            throw new RuntimeException('Could not bind audit entry.');
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Could not save audit entry.');
        }
    } finally {
        $stmt->close();
    }
}