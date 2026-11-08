<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';   // defines csrf_field() etc. if auth.php doesn't
require_role(['scheduler', 'admin']);
require_once __DIR__ . '/audit.php';

$errors = [];

$channels = [
    'SABC1', 'SABC2', 'SABC3', 'eTV', 'Mzansi Magic', 'SuperSport', 'M-Net'
];

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('Invalid programme ID. <a href="index.php">Return to programmes</a>');
}

// Load the programme being edited.
$stmt = $conn->prepare('SELECT * FROM programs WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$program) {
    http_response_code(404);
    exit('Programme not found. <a href="index.php">Return to programmes</a>');
}

// Values shown in the form: database values first (time as HH:MM).
$form = [
    'name'      => (string) $program['name'],
    'duration'  => (string) $program['duration'],
    'time_slot' => substr((string) $program['time_slot'], 0, 5),
    'channel'   => (string) $program['channel'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Reject missing values and unexpected array values.
    foreach (array_keys($form) as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
            $errors[] = 'A required form field is missing or invalid.';
            $form[$field] = '';
        } else {
            $form[$field] = trim($_POST[$field]);
        }
    }

    if (strlen($form['name']) < 3 || strlen($form['name']) > 255) {
        $errors[] = 'Programme name must be between 3 and 255 characters.';
    }

    $duration = filter_var(
        $form['duration'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 600]]
    );
    if ($duration === false) {
        $errors[] = 'Duration must be a whole number between 1 and 600 minutes.';
    }

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $form['time_slot'])) {
        $errors[] = 'Time slot must use the 24-hour HH:MM format.';
    }

    if (!in_array($form['channel'], $channels, true)) {
        $errors[] = 'Please select a valid channel.';
    }

    $errors = array_values(array_unique($errors));

    if (empty($errors)) {
        $transactionStarted = false;

        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $conn->begin_transaction();
            $transactionStarted = true;

            // Lock and recheck the programme before updating it.
            $checkStmt = $conn->prepare(
                'SELECT id FROM programs WHERE id = ? FOR UPDATE'
            );
            $checkStmt->bind_param('i', $id);
            $checkStmt->execute();

            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$existing) {
                throw new RuntimeException(
                    'Programme no longer exists.'
                );
            }

            $updateStmt = $conn->prepare(
                'UPDATE programs
                 SET name = ?, duration = ?, time_slot = ?, channel = ?
                 WHERE id = ?'
            );

            $updateStmt->bind_param(
                'sissi',
                $form['name'],
                $duration,
                $form['time_slot'],
                $form['channel'],
                $id
            );

            $updateStmt->execute();
            $changed = $updateStmt->affected_rows > 0;
            $updateStmt->close();

            // Do not record an edit when nothing actually changed.
            if ($changed) {
                record_program_audit(
                    $conn,
                    current_user(),
                    'edit',
                    (int) $id,
                    $form['name'],
                    'php'
                );
            }

            $conn->commit();
            $transactionStarted = false;

            $_SESSION['program_success'] = $changed
                ? 'Programme updated successfully!'
                : 'No changes were needed.';

            header('Location: index.php', true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable $rollbackError) {
                    error_log(
                        'Edit rollback failed: '
                        . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                'Edit programme failed: ' . $exception->getMessage()
            );

            $errors[] =
                'Unable to update the programme. Refresh and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Program | Program Manager</title>
    <link rel="stylesheet" href="edit-program.css?v=1">
</head>

<body class="edit-page">
    <main class="edit-card">
        <a class="back-link" href="index.php">
            <span aria-hidden="true">←</span>
            Back to programmes
        </a>

        <header class="edit-header">
            <div class="edit-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m15 5 4 4M4 20l4-1L20 7a2.83 2.83 0 0 0-4-4L4 15v5Z"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <p class="brand">PROGRAM MANAGER</p>
            <h1>Edit programme</h1>
            <p class="subtitle">Update the programme details below.</p>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="error-box" role="alert">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit.php?id=<?= (int) $id ?>" class="edit-form">
            <?= csrf_field() ?>

            <div class="field field-wide">
                <label for="program-name">Programme name</label>
                <input
                    id="program-name"
                    name="name"
                    type="text"
                    value="<?= htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8') ?>"
                    minlength="3"
                    maxlength="255"
                    required
                >
            </div>

            <div class="field">
                <label for="program-duration">Duration (minutes)</label>
                <input
                    id="program-duration"
                    name="duration"
                    type="number"
                    value="<?= htmlspecialchars($form['duration'], ENT_QUOTES, 'UTF-8') ?>"
                    min="1"
                    max="600"
                    step="1"
                    required
                >
            </div>

            <div class="field">
                <label for="program-time">Time slot</label>
                <input
                    id="program-time"
                    name="time_slot"
                    type="text"
                    value="<?= htmlspecialchars($form['time_slot'], ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="e.g. 20:00"
                    pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
                    maxlength="5"
                    aria-describedby="time-hint"
                    required
                >
                <small id="time-hint">Use the 24-hour format, HH:MM.</small>
            </div>

            <div class="field field-wide">
                <label for="program-channel">Channel</label>
                <select id="program-channel" name="channel" required>
                    <?php foreach ($channels as $channelOption): ?>
                        <option
                            value="<?= htmlspecialchars($channelOption, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $channelOption === $form['channel'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($channelOption, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="save-button">
                    Save changes
                    <span aria-hidden="true">→</span>
                </button>

                <a href="index.php" class="cancel-button">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>