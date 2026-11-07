<?php

require_once __DIR__ . '/auth.php';

require_login();

$loggedInUser = current_user();
$canManage = has_role(['scheduler', 'admin']);

$errors = [];
$success = $_SESSION['program_success'] ?? '';
unset($_SESSION['program_success']);

$channels = [
    'SABC1',
    'SABC2',
    'SABC3',
    'eTV',
    'Mzansi Magic',
    'SuperSport',
    'M-Net'
];

// Safely escape text before displaying it in HTML.
$escape = static function ($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

// Read a text input safely, rejecting arrays.
$readText = static function (array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
};

$name = '';
$duration = '';
$time_slot = '';
$channel = '';

// --------------------------------------------------
// ADD PROGRAM: process before fetching the list.
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['scheduler', 'admin']);
    verify_csrf();

    $name = $readText($_POST, 'name');
    $duration = $readText($_POST, 'duration');
    $time_slot = $readText($_POST, 'time_slot');
    $channel = $readText($_POST, 'channel');

    if (strlen($name) < 3) {
        $errors[] = 'Program Name must be at least 3 characters.';
    }

    $durationValue = filter_var(
        $duration,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 600
            ]
        ]
    );

    if ($durationValue === false) {
        $errors[] = 'Duration must be a whole number between 1 and 600.';
    }

    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D', $time_slot)) {
        $errors[] = 'Time Slot must be in valid HH:MM format, for example 08:00.';
    }

    if (!in_array($channel, $channels, true)) {
        $errors[] = 'Please select a valid channel.';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare(
                'INSERT INTO programs (name, duration, time_slot, channel)
                 VALUES (?, ?, ?, ?)'
            );

            if ($stmt === false) {
                throw new RuntimeException($conn->error);
            }

            $stmt->bind_param(
                'siss',
                $name,
                $durationValue,
                $time_slot,
                $channel
            );

            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }

            $stmt->close();

            // Redirect so refreshing does not repeat the INSERT.
            $_SESSION['program_success'] = 'Program added successfully!';
            header('Location: index.php', true, 303);
            exit;
        } catch (mysqli_sql_exception | RuntimeException $exception) {
            error_log('Add program failed: ' . $exception->getMessage());
            $errors[] = 'Unable to add the program. Please try again.';
        }
    }
}

// --------------------------------------------------
// SEARCH AND FILTER: load the current program list.
// --------------------------------------------------
$search_term = $readText($_GET, 'search');
$filter_channel = $readText($_GET, 'channel', 'All');

if (
    $filter_channel !== 'All'
    && !in_array($filter_channel, $channels, true)
) {
    $filter_channel = 'All';
}

$programs = [];
$listLoadFailed = false;

$sql = 'SELECT id, name, duration, time_slot, channel
        FROM programs
        WHERE 1=1';

$params = [];
$types = '';

if ($search_term !== '') {
    $sql .= ' AND name LIKE ?';
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if ($filter_channel !== 'All') {
    $sql .= ' AND channel = ?';
    $params[] = $filter_channel;
    $types .= 's';
}

$sql .= ' ORDER BY time_slot ASC, id ASC';

try {
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new RuntimeException($conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }

    $result = $stmt->get_result();

    if ($result === false) {
        throw new RuntimeException($stmt->error);
    }

    $programs = $result->fetch_all(MYSQLI_ASSOC);

    $result->free();
    $stmt->close();
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log('Load programs failed: ' . $exception->getMessage());
    $listLoadFailed = true;
    $errors[] = 'Unable to load programs. Please try again later.';
}

$total_programs = count($programs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Program Manager</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .login-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .login-header form {
            margin: 0;
        }

        .error-box,
        .success-box,
        .empty-message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error-box,
        .empty-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .error-box ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .success-box {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }

        .search-container {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .search-form .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 200px;
        }

        .search-form button {
            padding: 9px 20px;
        }

        .clear-filters {
            margin-left: 10px;
        }

        .add-program-form {
            max-width: 500px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
    </style>

<link rel="stylesheet" href="dashboard.css?v=1">

</head>

<body class="dashboard-page">
    <h1>Program Manager</h1>

    <div class="login-header">
        <p>
            Logged in as
            <strong><?= $escape($loggedInUser['username']) ?></strong>
            (<?= $escape($loggedInUser['role']) ?>)
        </p>

        <form method="POST" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit">Log out</button>
        </form>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-box" role="alert">
            <strong>Please check the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= $escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="success-box" role="status">
            <?= $escape($success) ?>
        </div>
    <?php endif; ?>

    <!-- All logged-in users may search and view programs. -->
    <div class="search-container">
        <h2>Search &amp; Filter Programs</h2>

        <form method="GET" action="index.php" class="search-form">
            <div class="form-group">
                <label for="search">Search by Name:</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= $escape($search_term) ?>"
                    placeholder="e.g. News"
                >
            </div>

            <div class="form-group">
                <label for="filter-channel">Filter by Channel:</label>
                <select id="filter-channel" name="channel">
                    <option
                        value="All"
                        <?= $filter_channel === 'All' ? 'selected' : '' ?>
                    >
                        All Channels
                    </option>

                    <?php foreach ($channels as $ch): ?>
                        <option
                            value="<?= $escape($ch) ?>"
                            <?= $filter_channel === $ch ? 'selected' : '' ?>
                        >
                            <?= $escape($ch) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <button type="submit">Search</button>
                <a href="index.php" class="clear-filters">Clear Filters</a>
            </div>
        </form>
    </div>

    <!-- Only schedulers and admins see the add form. -->
    <?php if ($canManage): ?>
        <h2>Add New Program</h2>

        <form method="POST" action="index.php" class="add-program-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="program-name">Program Name:</label>
                <input
                    type="text"
                    id="program-name"
                    name="name"
                    value="<?= $escape($name) ?>"
                    minlength="3"
                    required
                >
            </div>

            <div class="form-group">
                <label for="duration">Duration (minutes):</label>
                <input
                    type="number"
                    id="duration"
                    name="duration"
                    value="<?= $escape($duration) ?>"
                    min="1"
                    max="600"
                    step="1"
                    required
                >
            </div>

            <div class="form-group">
                <label for="time-slot">Time Slot (HH:MM):</label>
                <input
                    type="text"
                    id="time-slot"
                    name="time_slot"
                    value="<?= $escape($time_slot) ?>"
                    placeholder="e.g. 08:00"
                    pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
                    maxlength="5"
                    required
                >
            </div>

            <div class="form-group">
                <label for="program-channel">Channel:</label>
                <select id="program-channel" name="channel" required>
                    <option value="">-- Select Channel --</option>

                    <?php foreach ($channels as $ch): ?>
                        <option
                            value="<?= $escape($ch) ?>"
                            <?= $channel === $ch ? 'selected' : '' ?>
                        >
                            <?= $escape($ch) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="add_program" value="1">
                Add Program
            </button>
        </form>
    <?php endif; ?>

    <hr>

    <h2>Program List</h2>

    <?php if (!$listLoadFailed): ?>

        <?php if ($search_term !== '' || $filter_channel !== 'All'): ?>
            <p>
                Found <strong><?= $total_programs ?></strong>
                program(s) matching your criteria.
            </p>
        <?php endif; ?>

        <?php if ($total_programs === 0): ?>
            <p class="empty-message">
                No programs found. Try adjusting your search or filters.
            </p>
        <?php else: ?>


            <div
    class="table-container"
    role="region"
    aria-label="Programme list"
    tabindex="0"
>
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Duration (minutes)</th>
                            <th scope="col">Time Slot</th>
                            <th scope="col">Channel</th>

                            <?php if ($canManage): ?>
                                <th scope="col">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($programs as $row): ?>
                            <tr>
                                <td><?= $escape($row['name']) ?></td>
                                <td><?= $escape($row['duration']) ?></td>
                                <td><?= $escape($row['time_slot']) ?></td>
                                <td><?= $escape($row['channel']) ?></td>

                                <?php if ($canManage): ?>
                                    <td>
                                        <a href="edit.php?id=<?= (int) $row['id'] ?>">
                                            Edit
                                        </a>
                                        |
                                        <!-- This should open a confirmation page,
                                             not delete the record on GET. -->
                                        <a href="delete.php?id=<?= (int) $row['id'] ?>">
                                            Delete
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</body>
</html>