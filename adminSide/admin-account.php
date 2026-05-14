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
    
    
<link rel="stylesheet" href="admin-account.css?v=<?= time() ?>">
</head>
<body>

    <div class="grain-overlay"></div>
    <div class="bg-dots"></div>
    <div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
<?php $adminNavActive = 'account'; require __DIR__ . '/admin-sidebar-nav.php'; ?>
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
                <?php $adminTopbarName = $adminName; require __DIR__ . '/admin-topbar-right.php'; ?>
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

                        <form id="profileForm" data-default-name="<?= htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-default-email="<?= htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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

    

    
<script defer src="admin-notifications.js?v=<?= time() ?>"></script>
<script src="admin-account.js?v=<?= time() ?>"></script>
</body>
</html>
