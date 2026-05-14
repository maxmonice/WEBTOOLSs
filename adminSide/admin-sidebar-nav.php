<?php
declare(strict_types=1);
/**
 * Shared admin sidebar (brand + nav + footer).
 * Before include, set:
 *   $adminNavActive — one of: dashboard, users, bookings, orders, content, archive, messages, promos, chat, settings, logs
 *   $adminNavFromRoot — true when this file is required from project root (e.g. admin-archive.php); omit or false from adminSide/*.php
 */

$nav = $adminNavActive ?? '';
$fromRoot = !empty($adminNavFromRoot);

$ap = $fromRoot ? 'adminSide/' : '';
$rp = $fromRoot ? '' : '../';
$homeHref = $fromRoot ? 'index.php' : '../index.php';

$na = static function (string $key) use ($nav): string {
    return 'nav-item' . ($nav === $key ? ' active' : '');
};
?>
<div class="sidebar-brand">
    <div class="sidebar-name">Luke's Seafood Trading<span>Admin Panel</span></div>
</div>
<nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="<?= htmlspecialchars($ap) ?>admin-dashboard.php" class="<?= $na('dashboard') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <div class="nav-section-label">Management</div>
    <a href="<?= htmlspecialchars($ap) ?>admin-users.php" class="<?= $na('users') ?>"><i class="fa-solid fa-users"></i> User Management</a>
    <a href="<?= htmlspecialchars($ap) ?>admin-bookings.php" class="<?= $na('bookings') ?>"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
    <a href="<?= htmlspecialchars($ap) ?>admin-orders.php" class="<?= $na('orders') ?>"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
    <a href="<?= htmlspecialchars($ap) ?>admin-content.php" class="<?= $na('content') ?>"><i class="fa-solid fa-layer-group"></i> Content Management</a>
    <a href="<?= htmlspecialchars($rp) ?>admin-archive.php" class="<?= $na('archive') ?>"><i class="fa-solid fa-box-archive"></i> Archive</a>
    <a href="<?= htmlspecialchars($rp) ?>admin-messages.php" class="<?= $na('messages') ?>"><i class="fa-solid fa-message"></i> Messages</a>
    <a href="<?= htmlspecialchars($ap) ?>admin-promos.php" class="<?= $na('promos') ?>"><i class="fa-solid fa-ticket"></i> Promo Management</a>
    <a href="<?= htmlspecialchars($ap) ?>admin-chat.php" class="<?= $na('chat') ?>"><i class="fa-solid fa-comment-dots"></i> Support Chat</a>
    <div class="nav-section-label">System</div>
    <a href="<?= htmlspecialchars($rp) ?>admin-settings.php" class="<?= $na('settings') ?>"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
    <a href="<?= htmlspecialchars($ap) ?>admin-logs.php" class="<?= $na('logs') ?>"><i class="fa-solid fa-shield-halved"></i> Security &amp; Logs</a>
</nav>
<div class="sidebar-footer">
    <a href="#" onclick="showLogoutModal(event)" class="logout-btn" style="background: #ef4444; color: #fff;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal" style="display: none;">
    <div class="logout-modal-overlay" onclick="hideLogoutModal()"></div>
    <div class="logout-modal-content">
        <div class="logout-modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h2 class="logout-modal-title">Confirm Logout</h2>
        <p class="logout-modal-message">Are you sure you want to log out? You'll need to log in again to access the admin panel.</p>
        <div class="logout-modal-actions">
            <button class="logout-modal-btn logout-modal-cancel" onclick="hideLogoutModal()">Cancel</button>
            <button class="logout-modal-btn logout-modal-confirm" onclick="confirmLogout()">Yes, Logout</button>
        </div>
    </div>
</div>

<style>
.logout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logout-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    cursor: pointer;
}

.logout-modal-content {
    position: relative;
    background: #1a1a1a;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 40px;
    max-width: 420px;
    width: 90%;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.logout-modal-icon {
    font-size: 48px;
    color: #ef4444;
    margin-bottom: 16px;
}

.logout-modal-title {
    font-size: 24px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 12px;
}

.logout-modal-message {
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 24px;
}

.logout-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.logout-modal-btn {
    padding: 10px 24px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.logout-modal-cancel {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.logout-modal-cancel:hover {
    background: rgba(255, 255, 255, 0.2);
}

.logout-modal-confirm {
    background: #ef4444;
    color: #fff;
}

.logout-modal-confirm:hover {
    background: #dc2626;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
</style>

<script>
function showLogoutModal(e) {
    e.preventDefault();
    document.getElementById('logoutModal').style.display = 'flex';
}

function hideLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    window.location.href = '<?= htmlspecialchars($ap) ?>admin-logout.php';
}

// Close modal when pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('logoutModal').style.display !== 'none') {
        hideLogoutModal();
    }
});
</script>


