<?php
declare(strict_types=1);
/**
 * Shared staff sidebar (brand + nav + footer).
 * Before include, set:
 *   $staffNavActive — one of: dashboard, bookings, orders, customers, messages, chat
 *   $staffNavFromRoot — true when required from project root (e.g. staff-bookings.php in web root)
 */

$nav = $staffNavActive ?? '';
$fromRoot = !empty($staffNavFromRoot);

$sp = $fromRoot ? 'staffSide/' : '';
$logoutHref = $fromRoot ? 'staffSide/staff-logout.php' : 'staff-logout.php';

$na = static function (string $key) use ($nav): string {
    return 'nav-item' . ($nav === $key ? ' active' : '');
};
?>
<div class="sidebar-brand">
    <div class="sidebar-name">Luke's Seafood Trading<span>Staff Panel</span></div>
    <div class="role-pill"><i class="fa-solid fa-id-badge"></i> Staff Access</div>
</div>
<nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="<?= htmlspecialchars($sp) ?>staff-dashboard.php" class="<?= $na('dashboard') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <div class="nav-section-label">My Work</div>
    <a href="<?= htmlspecialchars($sp) ?>staff-bookings.php" class="<?= $na('bookings') ?>"><i class="fa-solid fa-calendar-days"></i> Bookings</a>
    <a href="<?= htmlspecialchars($sp) ?>staff-orders.php" class="<?= $na('orders') ?>"><i class="fa-solid fa-bag-shopping"></i> Orders</a>
    <div class="nav-section-label">View Only</div>
    <a href="<?= htmlspecialchars($sp) ?>staff-customers.php" class="<?= $na('customers') ?>"><i class="fa-solid fa-users"></i> Customers</a>
    <div class="nav-section-label">Tools</div>
    <a href="<?= htmlspecialchars($sp) ?>staff-messages.php" class="<?= $na('messages') ?>"><i class="fa-solid fa-message"></i> Messages</a>
    <a href="<?= htmlspecialchars($sp) ?>staff-chat.php" class="<?= $na('chat') ?>"><i class="fa-solid fa-comment-dots"></i> Support Chat</a>
    <div class="nav-section-label">Restricted</div>
    <span class="nav-item locked"><i class="fa-solid fa-layer-group"></i> Content Management</span>
    <span class="nav-item locked"><i class="fa-solid fa-shield-halved"></i> Security &amp; Logs</span>
    <span class="nav-item locked"><i class="fa-solid fa-sliders"></i> System Config</span>
</nav>
<div class="sidebar-footer">
    <a href="<?= htmlspecialchars($logoutHref) ?>" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>
