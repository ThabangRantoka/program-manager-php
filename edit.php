<?php
require_once __DIR__ . '/auth.php';
require_role(['scheduler', 'admin']);


$id = $_GET['id'] ?? null;
$errors = [];

// Fetch existing program
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $program = $result->fetch_assoc();
    $stmt->close();

    if (!$program) {
        die("Error: Program not found. <a href='index.php'>Return to Home</a>");
    }
} else {
    die("Error: No ID provided. <a href='index.php'>Return to Home</a>");
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf();

    $name = trim($_POST['name']);
    $duration = trim($_POST['duration']);
    $time_slot = trim($_POST['time_slot']);
    $channel = trim($_POST['channel']);

    // Validation Rules (Same as Add)
    if (empty($name)) $errors[] = "Program Name is required.";
    elseif (strlen($name) < 3) $errors[] = "Program Name must be at least 3 characters.";
    elseif (strlen($name) > 255) $errors[] = "Program Name cannot exceed 255 characters.";

    if (empty($duration)) $errors[] = "Duration is required.";
    elseif (!filter_var($duration, FILTER_VALIDATE_INT)) $errors[] = "Duration must be a valid integer.";
    elseif ($duration < 1 || $duration > 600) $errors[] = "Duration must be between 1 and 600 minutes.";

    if (empty($time_slot)) $errors[] = "Time Slot is required.";
    elseif (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D', $time_slot)) {
    $errors[] = "Time Slot must be in valid HH:MM format.";
}

    $allowed_channels = ["SABC1", "SABC2", "SABC3", "eTV", "Mzansi Magic", "SuperSport", "M-Net"];
    if (empty($channel)) $errors[] = "Channel is required.";
    elseif (!in_array($channel, $allowed_channels)) $errors[] = "Invalid channel selected.";

    if (empty($errors)) {
        $updateStmt = $conn->prepare("UPDATE programs SET name=?, duration=?, time_slot=?, channel=? WHERE id=?");
        $updateStmt->bind_param("sissi", $name, $duration, $time_slot, $channel, $id);

        if ($updateStmt->execute()) {
            $_SESSION['program_success'] = 'Program update successfully!';
            header('Location: index.php',true,303);
            exit;

        } else {
            $errors[] = "Database Error: " . $conn->error;
        }
        $updateStmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Program</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error-box { background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px; }
        .error-box ul { margin: 0; padding-left: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
    <h1>Edit Program</h1>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?php echo $id; ?>" style="max-width: 500px;">
    <?= csrf_field() ?>

        <div class="form-group">
            <label>Program Name:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $program['name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Duration (minutes):</label>
            <input type="number" name="duration" value="<?php echo htmlspecialchars($_POST['duration'] ?? $program['duration']); ?>" required>
        </div>
        <div class="form-group">
            <label>Time Slot (HH:MM):</label>
            <input type="text" name="time_slot" value="<?php echo htmlspecialchars($_POST['time_slot'] ?? $program['time_slot']); ?>" required>
        </div>
        <div class="form-group">
            <label>Channel:</label>
            <select name="channel" required>
                <?php
                $channels = ["SABC1", "SABC2", "SABC3", "eTV", "Mzansi Magic", "SuperSport", "M-Net"];
                foreach ($channels as $ch) {
                    $selected = ($ch == ($_POST['channel'] ?? $program['channel'])) ? 'selected' : '';
                    echo "<option value='$ch' $selected>$ch</option>";
                }
                ?>
            </select>
        </div>
        <button type="submit">Update Program</button>
        <a href="index.php" style="margin-left: 10px;">Cancel</a>
    </form>
</body>
</html>