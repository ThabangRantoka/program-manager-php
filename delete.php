<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';   // defines csrf_field() etc. if auth.php doesn't
require_role(['scheduler', 'admin']);

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('Invalid programme ID. <a href="index.php">Return to programmes</a>');
}

// Load the programme for the confirmation page.
$stmt = $conn->prepare('SELECT * FROM programs WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$program) {
    http_response_code(404);
    exit('Programme not found. <a href="index.php">Return to programmes</a>');
}

// Opening this page does not delete anything.
// Deletion requires a POST request and a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $confirmId = filter_var(
        $_POST['confirm_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($confirmId !== $id) {
        http_response_code(400);
        $error = 'Programme ID mismatch. Nothing was deleted.';
    } else {
        try {
            $deleteStmt = $conn->prepare('DELETE FROM programs WHERE id = ?');
            $deleteStmt->bind_param('i', $id);
            $deleted = $deleteStmt->execute();
            $deleteStmt->close();

            if ($deleted) {
                $_SESSION['program_success'] = 'Programme deleted successfully!';
                header('Location: index.php', true, 303);
                exit;
            }

            $error = 'Unable to delete the programme. Please try again.';
        } catch (mysqli_sql_exception $exception) {
            error_log($exception->getMessage());
            $error = 'Unable to delete the programme. It may be linked to other records.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Programme | Program Manager</title>
    <link rel="stylesheet" href="delete-program.css?v=1">
</head>

<body class="delete-page">
    <main class="delete-card">
        <a href="index.php" class="back-link">
            <span aria-hidden="true">←</span>
            Back to programmes
        </a>

        <header class="delete-header">
            <div class="warning-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 3 2 21h20L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="17.5" r="1" fill="currentColor"/>
                </svg>
            </div>

            <p class="brand">PROGRAM MANAGER</p>
            <h1>Delete programme?</h1>
            <p class="subtitle">Please review the details before confirming.</p>
        </header>

        <?php if (isset($error)): ?>
            <p class="error-message" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <dl class="program-details">
            <div class="detail-row">
                <dt>Programme</dt>
                <dd><?= htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>

            <div class="detail-row">
                <dt>Duration</dt>
                <dd><?= (int) $program['duration'] ?> minutes</dd>
            </div>

            <div class="detail-row">
                <dt>Time slot</dt>
                <dd><?= htmlspecialchars(substr((string) $program['time_slot'], 0, 5), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>

            <div class="detail-row">
                <dt>Channel</dt>
                <dd><?= htmlspecialchars($program['channel'], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
        </dl>

        <p class="warning-note" id="delete-warning">
            <strong>This action cannot be undone.</strong>
            The programme will be removed from the programme list.
        </p>

        <form method="POST" action="delete.php?id=<?= (int) $id ?>" class="delete-actions">
            <?= csrf_field() ?>

            <input type="hidden" name="confirm_id" value="<?= (int) $id ?>">

            <a href="index.php" class="cancel-button">Keep programme</a>

            <button type="submit" class="delete-button" aria-describedby="delete-warning">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 6h18M9 6V4h6v2M5 6l1 15h12l1-15M10 10v7M14 10v7"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Delete programme
            </button>
        </form>
    </main>
</body>
</html>