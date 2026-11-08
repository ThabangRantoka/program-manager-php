<?php
require_once __DIR__ . '/auth.php';
require_role(['admin']);   // only admins; everyone else gets the 403 page

const PER_PAGE = 25;

// ---- 1. Read the filters from the URL (GET), and clean them ----
$allowedActions = ['add', 'edit', 'delete'];
$allowedSources = ['php', 'api'];

$action = $_GET['action'] ?? '';
$source = $_GET['source'] ?? '';
$user   = $_GET['user']   ?? '';

$action = (is_string($action) && in_array($action, $allowedActions, true)) ? $action : '';
$source = (is_string($source) && in_array($source, $allowedSources, true)) ? $source : '';
$user   = is_string($user) ? trim(mb_substr($user, 0, 50)) : '';

$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'default' => 1]]
);

// ---- 2. Build the WHERE part safely (values are bound, never pasted in) ----
$where  = [];
$types  = '';
$params = [];

if ($action !== '') { $where[] = 'action = ?';          $types .= 's'; $params[] = $action; }
if ($source !== '') { $where[] = 'source = ?';          $types .= 's'; $params[] = $source; }
if ($user   !== '') { $where[] = 'actor_username LIKE ?'; $types .= 's'; $params[] = '%' . $user . '%'; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- 3. Count the matching rows, to work out the number of pages ----
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM audit_logs $whereSql");
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int) ceil($total / PER_PAGE));
$page   = min($page, $totalPages);
$offset = ($page - 1) * PER_PAGE;

// ---- 4. Fetch just this page of rows, newest first ----
$listStmt = $conn->prepare(
    "SELECT id, actor_username, action, program_id, program_name, source, created_at
     FROM audit_logs
     $whereSql
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
$listTypes  = $types . 'ii';
$listParams = array_merge($params, [PER_PAGE, $offset]);
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

// Helpers
function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function page_url(int $p): string {
    $query = array_filter([
        'action' => $GLOBALS['action'],
        'source' => $GLOBALS['source'],
        'user'   => $GLOBALS['user'],
        'page'   => $p > 1 ? $p : null,
    ], fn($v) => $v !== '' && $v !== null);
    return 'audit_log.php' . ($query ? '?' . http_build_query($query) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log | Program Manager</title>
    <link rel="stylesheet" href="audit-log.css?v=1">
</head>

<body class="audit-page">
    <main class="audit-card">
        <a class="back-link" href="index.php">
            <span aria-hidden="true">←</span>
            Back to programmes
        </a>

        <header class="audit-header">
            <p class="brand">PROGRAM MANAGER</p>
            <h1>Audit log</h1>
            <p class="subtitle">
                Who added, edited or deleted a programme, and when.
                <?= $total ?> <?= $total === 1 ? 'entry' : 'entries' ?> found.
            </p>
        </header>

        <form method="GET" action="audit_log.php" class="audit-filters">
            <div class="field">
                <label for="f-user">User</label>
                <input id="f-user" name="user" type="text" maxlength="50"
                       value="<?= h($user) ?>" placeholder="Username">
            </div>

            <div class="field">
                <label for="f-action">Action</label>
                <select id="f-action" name="action">
                    <option value="">All</option>
                    <?php foreach ($allowedActions as $a): ?>
                        <option value="<?= h($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= h(ucfirst($a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="f-source">Source</label>
                <select id="f-source" name="source">
                    <option value="">All</option>
                    <option value="php" <?= $source === 'php' ? 'selected' : '' ?>>PHP site</option>
                    <option value="api" <?= $source === 'api' ? 'selected' : '' ?>>Angular (API)</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="filter-button">Filter</button>
                <a href="audit_log.php" class="clear-link">Clear</a>
            </div>
        </form>

        <?php if (!$rows): ?>
            <p class="empty-message">No audit entries match your filters.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">User</th>
                            <th scope="col">Action</th>
                            <th scope="col">Programme</th>
                            <th scope="col">Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= h($row['created_at']) ?></td>
                                <td><?= h($row['actor_username']) ?></td>
                                <td><span class="badge badge-<?= h($row['action']) ?>"><?= h($row['action']) ?></span></td>
                                <td>
                                    <?= h($row['program_name']) ?>
                                    <small>#<?= (int) $row['program_id'] ?></small>
                                </td>
                                <td><?= $row['source'] === 'api' ? 'Angular' : 'PHP' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="pager" aria-label="Pages">
                <?php if ($page > 1): ?>
                    <a href="<?= h(page_url($page - 1)) ?>">← Newer</a>
                <?php else: ?>
                    <span class="disabled">← Newer</span>
                <?php endif; ?>

                <span>Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= h(page_url($page + 1)) ?>">Older →</a>
                <?php else: ?>
                    <span class="disabled">Older →</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
</body>
</html>