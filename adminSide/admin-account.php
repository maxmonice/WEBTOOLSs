<?php
require_once 'admin-config.php';
requireAdmin(); // 🔒 must be admin

// Get admin user info
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
$adminEmail = htmlspecialchars($_SESSION['user_email'] ?? 'admin@gmail.com');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account - Luke's Seafood Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    
    <style>
        /* Clean Account Layout Styles */
        .account-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .account-header {
            background: var(--card2);
            border: 1px solid var(--line-w);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .account-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        
        .account-info h1 {
            margin: 0 0 4px 0;
            color: #fff;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .account-info p {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
        }
        
        .account-stats {
            margin-left: auto;
            display: flex;
            gap: 32px;
        }
        
        .account-stat {
            text-align: center;
        }
        
        .account-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 2px;
        }
        
        .account-stat-label {
            font-size: 0.8rem;
            color: var(--muted);
        }
        
        .account-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        @media (max-width: 968px) {
            .account-grid {
                grid-template-columns: 1fr;
            }
            
            .account-stats {
                display: none;
            }
        }
        
        @media (max-width: 640px) {
            .account-header {
                flex-direction: column;
                text-align: center;
            }
            
            .account-stats {
                margin-left: 0;
                margin-top: 16px;
            }
        }
        
        .account-card {
            background: var(--card2);
            border: 1px solid var(--line-w);
            border-radius: 12px;
            padding: 24px;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--line-w);
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(194, 38, 38, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--red);
            font-size: 1rem;
        }
        
        .card-title h2 {
            margin: 0 0 2px 0;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .card-title p {
            margin: 0;
            color: var(--muted);
            font-size: 0.85rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #fff;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: var(--card);
            border: 1px solid var(--line-w);
            border-radius: 8px;
            color: #fff;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 0 2px rgba(194, 38, 38, 0.1);
        }
        
        .form-group input::placeholder {
            color: var(--muted);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .security-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .security-item {
            background: var(--card);
            border: 1px solid var(--line-w);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }
        
        .security-item:hover {
            border-color: var(--red);
        }
        
        .security-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .security-info h3 {
            margin: 0 0 2px 0;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .security-info p {
            margin: 0;
            color: var(--muted);
            font-size: 0.8rem;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line-w);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--red);
            margin-top: 6px;
            flex-shrink: 0;
        }
        
        .activity-content h4 {
            margin: 0 0 4px 0;
            color: #fff;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .activity-content p {
            margin: 0 0 6px 0;
            color: var(--muted);
            font-size: 0.85rem;
        }
        
        .activity-time {
            color: var(--muted);
            font-size: 0.75rem;
        }
        
        /* Notification Dropdown Styles */
        .notification-dropdown {
            position: relative;
        }

        .notification-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            background: var(--card2);
            border: 1px solid var(--line-w);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
            margin-top: 10px;
        }

        .notification-menu.show {
            display: block;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--line-w);
        }

        .notification-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
        }

        .mark-all-read {
            background: none;
            border: none;
            color: var(--red);
            font-size: 0.8rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .mark-all-read:hover {
            background: rgba(194, 38, 38, 0.1);
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            padding: 16px 20px;
            border-bottom: 1px solid var(--line-w);
            transition: background-color 0.2s;
            cursor: pointer;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .notification-item.unread {
            background: rgba(194, 38, 38, 0.05);
        }

        .notification-item.unread:hover {
            background: rgba(194, 38, 38, 0.1);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .notification-icon i {
            font-size: 1rem;
        }

        .notification-item:nth-child(1) .notification-icon {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .notification-item:nth-child(2) .notification-icon {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .notification-item:nth-child(3) .notification-icon {
            background: rgba(249, 115, 22, 0.2);
            color: #f97316;
        }

        .notification-item:nth-child(4) .notification-icon {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            color: #fff;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .notification-message {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .notification-time {
            color: var(--muted);
            font-size: 0.75rem;
        }

        .notification-close {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
            margin-left: 8px;
            flex-shrink: 0;
        }

        .notification-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .notification-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--line-w);
            text-align: center;
        }

        .view-all-link {
            color: var(--red);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .view-all-link:hover {
            opacity: 0.8;
        }

        /* Badge dot for unread notifications */
        .badge-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid var(--dark);
        }

        /* Scrollbar styling */
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }

        .notification-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .notification-list::-webkit-scrollbar-thumb {
            background: var(--line-w);
            border-radius: 3px;
        }

        .notification-list::-webkit-scrollbar-thumb:hover {
            background: var(--muted);
        }
    </style>
</head>
<body>

    <div class="grain-overlay"></div>
    <div class="bg-dots"></div>
    <div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-name">Luke's Seafood Trading<span>Admin Panel</span></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Overview</div>
            <a href="admin-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <div class="nav-section-label">Management</div>
            <a href="admin-users.php" class="nav-item"><i class="fa-solid fa-users"></i> User Management</a>
            <a href="admin-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
            <a href="admin-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
            <a href="admin-content.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Content Management</a>
            <div class="nav-section-label">System</div>
            <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
            <a href="admin-account.php" class="nav-item active"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="index.php" class="logout-btn" style="background: #22c55e; color: #fff;"><i class="fa-solid fa-home"></i> Home</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <div class="topbar-title">Account Settings</div>
                    <div class="topbar-breadcrumb">Admin <span>/</span> Account</div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="notification-dropdown">
                    <div class="topbar-badge" onclick="toggleNotifications()">
                        <i class="fa-regular fa-bell"></i>
                    </div>
                    <div class="notification-menu" id="notificationMenu">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item unread">
                                <div class="notification-icon">
                                    <i class="fa-solid fa-shopping-cart"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">New Order Received</div>
                                    <div class="notification-message">Order #ORD-0001 has been placed</div>
                                    <div class="notification-time">2 minutes ago</div>
                                </div>
                                <div class="notification-close" onclick="removeNotification(this)">
                                    <i class="fa-solid fa-times"></i>
                                </div>
                            </div>
                            <div class="notification-item unread">
                                <div class="notification-icon">
                                    <i class="fa-solid fa-calendar-check"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">New Booking Confirmed</div>
                                    <div class="notification-message">Event booking for May 15, 2025</div>
                                    <div class="notification-time">15 minutes ago</div>
                                </div>
                                <div class="notification-close" onclick="removeNotification(this)">
                                    <i class="fa-solid fa-times"></i>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <i class="fa-solid fa-user-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">New User Registered</div>
                                    <div class="notification-message">John Doe joined the platform</div>
                                    <div class="notification-time">1 hour ago</div>
                                </div>
                                <div class="notification-close" onclick="removeNotification(this)">
                                    <i class="fa-solid fa-times"></i>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <i class="fa-solid fa-truck"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">Order Shipped</div>
                                    <div class="notification-message">Order #ORD-0002 has been shipped</div>
                                    <div class="notification-time">2 hours ago</div>
                                </div>
                                <div class="notification-close" onclick="removeNotification(this)">
                                    <i class="fa-solid fa-times"></i>
                                </div>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="admin-logs.php" class="view-all-link">View all notifications</a>
                        </div>
                    </div>
                </div>
                <a href="admin-account.php" class="admin-avatar" title="<?= $adminName ?>">
                    <?= strtoupper(substr($adminName, 0, 1)) ?>
                </a>
            </div>
        </header>

        <div class="page-content">
            <div class="account-container">
                <!-- Account Header -->
                <div class="account-header">
                    <div class="account-avatar">
                        <?= strtoupper(substr($adminName, 0, 1)) ?>
                    </div>
                    <div class="account-info">
                        <h1><?= $adminName ?></h1>
                        <p>Administrator - Luke's Seafood Trading</p>
                    </div>
                    <div class="account-stats">
                        <div class="account-stat">
                            <div class="account-stat-value">Admin</div>
                            <div class="account-stat-label">Role</div>
                        </div>
                        <div class="account-stat">
                            <div class="account-stat-value">Active</div>
                            <div class="account-stat-label">Status</div>
                        </div>
                        <div class="account-stat">
                            <div class="account-stat-value">Full</div>
                            <div class="account-stat-label">Access</div>
                        </div>
                    </div>
                </div>

                <div class="account-grid">
                    <!-- Profile Information -->
                    <div class="account-card">
                        <div class="card-header">
                            <div class="card-icon">
                                <i class="fa-solid fa-user-gear"></i>
                            </div>
                            <div class="card-title">
                                <h2>Profile Information</h2>
                                <p>Update your personal information and account details</p>
                            </div>
                        </div>

                        <form id="profileForm">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?= $adminName ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?= $adminEmail ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" placeholder="Enter current password to change password">
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-outline" onclick="resetForm()">
                                    <i class="fa-solid fa-undo"></i> Reset
                                </button>
                                <a href="admin-logout.php" class="btn btn-danger" style="text-decoration: none;">
                                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Security & Activity -->
                    <div>
                        <!-- Security Overview -->
                        <div class="account-card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <div class="card-icon">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div class="card-title">
                                    <h2>Security Overview</h2>
                                    <p>Monitor your account security settings</p>
                                </div>
                            </div>

                            <div class="security-list">
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fa-solid fa-lock"></i>
                                    </div>
                                    <div class="security-info">
                                        <h3>Password Protection</h3>
                                        <p>Secure password authentication enabled</p>
                                    </div>
                                </div>
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fa-solid fa-envelope"></i>
                                    </div>
                                    <div class="security-info">
                                        <h3>Email Login</h3>
                                        <p><?= $adminEmail ?></p>
                                    </div>
                                </div>
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fa-solid fa-user-shield"></i>
                                    </div>
                                    <div class="security-info">
                                        <h3>Admin Access</h3>
                                        <p>Full system permissions granted</p>
                                    </div>
                                </div>
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                    <div class="security-info">
                                        <h3>Last Login</h3>
                                        <p>Today, <?= date('g:i A') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="account-card">
                            <div class="card-header">
                                <div class="card-icon">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                <div class="card-title">
                                    <h2>Recent Activity</h2>
                                    <p>Track your recent account actions</p>
                                </div>
                            </div>

                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-dot"></div>
                                    <div class="activity-content">
                                        <h4>Account Login</h4>
                                        <p>Successfully logged into admin panel</p>
                                        <div class="activity-time">Today, <?= date('g:i A') ?></div>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-dot"></div>
                                    <div class="activity-content">
                                        <h4>Profile Updated</h4>
                                        <p>Account information was modified</p>
                                        <div class="activity-time">Yesterday</div>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-dot"></div>
                                    <div class="activity-content">
                                        <h4>Security Check</h4>
                                        <p>System security verification completed</p>
                                        <div class="activity-time">2 days ago</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /admin-layout -->

    <!-- Success/Error Messages -->
    <div id="messageContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1000;"></div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        function toggleNotifications() {
            const menu = document.getElementById('notificationMenu');
            menu.classList.toggle('show');
            
            document.addEventListener('click', function closeNotifications(e) {
                if (!e.target.closest('.notification-dropdown')) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeNotifications);
                }
            });
        }

        function removeNotification(element) {
            const item = element.closest('.notification-item');
            item.style.transform = 'translateX(100%)';
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 300);
        }

        function markAllAsRead() {
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(item => {
                item.classList.remove('unread');
            });
        }

        function showMessage(message, type = 'success') {
            const container = document.getElementById('messageContainer');
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                padding: 12px 16px;
                border-radius: 8px;
                margin-bottom: 10px;
                color: #fff;
                font-weight: 500;
                animation: slideIn 0.3s ease;
                ${type === 'success' ? 'background: #22c55e;' : 'background: #ef4444;'}
            `;
            messageDiv.innerHTML = `
                <i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            container.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => messageDiv.remove(), 300);
            }, 3000);
        }

        function resetForm() {
            document.getElementById('profileForm').reset();
            document.getElementById('name').value = '<?= $adminName ?>';
            document.getElementById('email').value = '<?= $adminEmail ?>';
        }

        // Handle form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            showMessage('Profile updated successfully!', 'success');
        });
    </script>

    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>
</body>
</html>
