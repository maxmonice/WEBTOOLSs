<?php
declare(strict_types=1);
/**
 * Standard admin topbar right (same as admin-dashboard.php).
 *
 * From adminSide/*.php: require as-is.
 * From project root admin pages: set $adminTopbarFromRoot = true before this require.
 *
 * Optional: $adminTopbarName — already HTML-escaped name for the avatar title attribute.
 */
$__tbFromRoot = !empty($adminTopbarFromRoot);
$__settingsHref = $__tbFromRoot ? 'admin-settings.php' : '../admin-settings.php';
$__userViewHref = $__tbFromRoot ? 'account-dashboard.php?user_view=true' : '../account-dashboard.php?user_view=true';
$__tbName = isset($adminTopbarName)
    ? (string) $adminTopbarName
    : htmlspecialchars((string) ($_SESSION['user_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
$__initial = strtoupper(substr((string) ($_SESSION['user_name'] ?? 'A'), 0, 1));

$adminNotifLogsHref = $__tbFromRoot ? 'adminSide/admin-logs.php' : 'admin-logs.php';
require __DIR__ . '/admin-topbar-notifications.php';
?>
        <a href="<?= htmlspecialchars($__settingsHref, ENT_QUOTES, 'UTF-8') ?>" class="admin-avatar" title="<?= $__tbName ?>">
          <?= htmlspecialchars($__initial, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <a href="<?= htmlspecialchars($__userViewHref, ENT_QUOTES, 'UTF-8') ?>" class="btn-user-view-dash" title="Open customer account page">
          <i class="fa-solid fa-user"></i> User view
        </a>


