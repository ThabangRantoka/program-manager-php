<?php
// sidebar.php - shared side navigation.
// Before including it, set:  $activePage = 'overview';
// Keys: overview, change-password, users, reset-password, audit-log
 
$activePage = $activePage ?? '';
 
$isAdmin = has_role('admin');
 
function nav_item(string $key, string $href, string $label, string $icon, string $active): string
{
    $isActive = $key === $active;
 
    return '<a class="side-link' . ($isActive ? ' is-active' : '') . '" href="' . $href . '"'
        . ($isActive ? ' aria-current="page"' : '') . '>'
        . '<svg viewBox="0 0 24 24" aria-hidden="true">' . $icon . '</svg>'
        . '<span>' . $label . '</span></a>';
}
 
$icons = [
    'overview' => '<path d="M12 3 4 6v6c0 4.4 3.1 7.7 8 9 4.9-1.3 8-4.6 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
    'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    'key'      => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.8-9.8M17 6l3 3M14 9l2 2"/>',
    'audit'    => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M12 11h4M12 16h4M8 11h.01M8 16h.01"/>',
    'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
];
?>
<aside class="sidebar" aria-label="Main navigation">
    <div class="side-brand">
        <div class="side-logo" aria-hidden="true">
            <svg viewBox="0 0 24 24"><?= $icons['overview'] ?></svg>
        </div>
        <div>
            <p class="side-title">Program Manager</p>
            <p class="side-subtitle">Account center</p>
        </div>
    </div>
 
    <nav class="side-nav">
        <p class="side-label">Workspace</p>
        <?= nav_item('overview', 'index.php', 'Overview', $icons['overview'], $activePage) ?>
 
        <p class="side-label">Account</p>
        <?= nav_item('change-password', 'change_password.php', 'Change password', $icons['key'], $activePage) ?>
 
        <?php if ($isAdmin): ?>
            <p class="side-label">Admin</p>
            <?= nav_item('users', 'users.php', 'Manage users', $icons['users'], $activePage) ?>
            <?= nav_item('reset-password', 'reset_password.php', 'Reset a password', $icons['lock'], $activePage) ?>
            <?= nav_item('audit-log', 'audit_log.php', 'Audit log', $icons['audit'], $activePage) ?>
        <?php endif; ?>
    </nav>
</aside>
 
