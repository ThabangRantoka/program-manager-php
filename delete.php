<?php

require_once __DIR__ . '/auth.php';
require_role(['scheduler', 'admin']);

// 1. Get the ID from the URL
$id = $_GET['id'] ?? null;

// Security: Validate that ID is a positive integer
if (!$id || !filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    die("Error: Invalid program ID. <a href='index.php'>Return to Home</a>");
}

// 2. Fetch the program data using a Prepared Statement
$stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$program = $result->fetch_assoc();
$stmt->close();

// Security: Check if the program actually exists
if (!$program) {
    die("Error: Program not found. It may have already been deleted. <a href='index.php'>Return to Home</a>");
}

// 3. Handle Confirmation (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
verify_csrf();

    // Verify the submitted ID matches (prevent tampering)
    $confirmId = $_POST['confirm_id'] ?? null;
    
    if ($confirmId == $id) {
        // Execute DELETE using a Prepared Statement
        $deleteStmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
        $deleteStmt->bind_param("i", $id);
        
        if ($deleteStmt->execute()) {
            $deleteStmt->close();
            // Redirect to index.php with a success flag
                $_SESSION['program_success'] = 'Program deleted successfully!';
                header('Location: index.php', true, 303);
                exit;
        } else {
            $error = "Database Error: " . $conn->error;
        }
        $deleteStmt->close();
    } else {
        $error = "Error: ID mismatch. Deletion aborted.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delete Program</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .delete-container {
            max-width: 500px;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            border-left: 5px solid #e74c3c;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .delete-container h2 {
            color: #e74c3c;
            margin-top: 0;
        }
        .program-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .program-details p {
            margin: 8px 0;
        }
        .program-details strong {
            display: inline-block;
            width: 100px;
            color: #555;
        }
        .btn-confirm {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-confirm:hover {
            background-color: #c0392b;
        }
        .btn-cancel {
            background-color: #95a5a6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .btn-cancel:hover {
            background-color: #7f8c8d;
        }
        .error-message {
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <h2>⚠️ Confirm Deletion</h2>

        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <p>Are you sure you want to delete this program? This action cannot be undone.</p>

        <div class="program-details">
            <p><strong>Program:</strong> <?php echo htmlspecialchars($program['name']); ?></p>
            <p><strong>Duration:</strong> <?php echo $program['duration']; ?> minutes</p>
            <p><strong>Time Slot:</strong> <?php echo htmlspecialchars($program['time_slot']); ?></p>
            <p><strong>Channel:</strong> <?php echo htmlspecialchars($program['channel']); ?></p>
        </div>

        <!-- Confirmation Form (POST method ensures deletion only happens on explicit submit) -->
        <form method="POST" action="delete.php?id=<?php echo $id; ?>">
            <?= csrf_field() ?>

            <input type="hidden" name="confirm_id" value="<?php echo $id; ?>">
            <button type="submit" class="btn-confirm">Confirm Delete</button>
            <a href="index.php" class="btn-cancel">Cancel</a>
        </form>
    </div>
</body>
</html>