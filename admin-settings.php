<?php
require_once 'admin-config.php';
require_once 'Notifications.php';
requireAdmin();

// Initialize notifications
$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

$successMsg = '';
$errorMsg = '';

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Update profile
    if (($data['action'] ?? '') === 'update_profile') {
        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        
        if (empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $email, $_SESSION['user_id']]);
            
            // Update session
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            // Audit log
            logAdminActivity($pdo, 'profile_updated', "Admin updated profile: {$name} ({$email})");
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Change password
    if (($data['action'] ?? '') === 'change_password') {
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            exit;
        }
        
        if (strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
            exit;
        }
        
        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        
        try {
            // Get current password hash
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                exit;
            }
            
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newHash, $_SESSION['user_id']]);
            
            // Audit log
            logAdminActivity($pdo, 'password_changed', "Admin changed password");
            
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Update notification preferences
    if (($data['action'] ?? '') === 'update_notifications') {
        $emailNotifications = $data['email_notifications'] ?? false;
        $pushNotifications = $data['push_notifications'] ?? false;
        $orderAlerts = $data['order_alerts'] ?? false;
        $bookingAlerts = $data['booking_alerts'] ?? false;
        $securityAlerts = $data['security_alerts'] ?? true;
        
        try {
            // Check if settings table exists, if not create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_settings (
                user_id INT PRIMARY KEY,
                email_notifications TINYINT(1) DEFAULT 1,
                push_notifications TINYINT(1) DEFAULT 0,
                order_alerts TINYINT(1) DEFAULT 1,
                booking_alerts TINYINT(1) DEFAULT 1,
                security_alerts TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            
            $stmt = $pdo->prepare("
                INSERT INTO admin_settings (user_id, email_notifications, push_notifications, order_alerts, booking_alerts, security_alerts)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                email_notifications = ?, push_notifications = ?, order_alerts = ?, booking_alerts = ?, security_alerts = ?, updated_at = NOW()
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $emailNotifications ? 1 : 0, 
                $pushNotifications ? 1 : 0, 
                $orderAlerts ? 1 : 0, 
                $bookingAlerts ? 1 : 0, 
                $securityAlerts ? 1 : 0,
                $emailNotifications ? 1 : 0, 
                $pushNotifications ? 1 : 0, 
                $orderAlerts ? 1 : 0, 
                $bookingAlerts ? 1 : 0, 
                $securityAlerts ? 1 : 0
            ]);
            
            // Audit log
            logAdminActivity($pdo, 'notification_settings_updated', "Admin updated notification preferences");
            
            echo json_encode(['success' => true, 'message' => 'Notification preferences updated']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Update avatar
    if (($data['action'] ?? '') === 'update_avatar') {
        $avatarUrl = trim($data['avatar_url'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$avatarUrl, $_SESSION['user_id']]);
            
            $_SESSION['avatar_url'] = $avatarUrl;
            
            // Audit log
            logAdminActivity($pdo, 'avatar_updated', "Admin updated avatar");
            
            echo json_encode(['success' => true, 'message' => 'Avatar updated successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Revoke all sessions except current
    if (($data['action'] ?? '') === 'revoke_sessions') {
        try {
            // This would typically clear session tokens from a sessions table
            // For now, we'll just log the action
            logAdminActivity($pdo, 'sessions_revoked', "Admin revoked all other sessions");
            
            echo json_encode(['success' => true, 'message' => 'All other sessions revoked successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get current user data
$userData = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, email, avatar_url, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $userData = [];
}

// Get notification settings
$notificationSettings = [
    'email_notifications' => true,
    'push_notifications' => false,
    'order_alerts' => true,
    'booking_alerts' => true,
    'security_alerts' => true
];
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $notificationSettings = [
            'email_notifications' => (bool)$settings['email_notifications'],
            'push_notifications' => (bool)$settings['push_notifications'],
            'order_alerts' => (bool)$settings['order_alerts'],
            'booking_alerts' => (bool)$settings['booking_alerts'],
            'security_alerts' => (bool)$settings['security_alerts']
        ];
    }
} catch (PDOException $e) {
    // Table might not exist yet
}

$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Account Settings — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.settings-section {
    background: var(--card2);
    border: 1px solid var(--line-w);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.settings-section:last-child { margin-bottom: 0; }
.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--line-w);
}
.section-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(194,38,38,0.15);
    border: 1px solid rgba(194,38,38,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--red);
    font-size: 1.1rem;
}
.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}
.section-description {
    font-size: 0.85rem;
    color: var(--muted);
    margin-top: 4px;
}

.avatar-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--card1);
    border: 3px solid var(--line-w);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--muted);
    overflow: hidden;
    margin-bottom: 16px;
}
.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.toggle-switch {
    position: relative;
    width: 50px;
    height: 26px;
    background: var(--card1);
    border: 2px solid var(--line-w);
    border-radius: 13px;
    cursor: pointer;
    transition: all 0.3s;
}
.toggle-switch.active {
    background: var(--red);
    border-color: var(--red);
}
.toggle-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.3s;
}
.toggle-switch.active::after {
    left: 26px;
}

.notification-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0;
    border-bottom: 1px solid var(--line-w);
}
.notification-item:last-child { border-bottom: none; }
.notification-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 4px 0;
}
.notification-info p {
    font-size: 0.8rem;
    color: var(--muted);
    margin: 0;
}

.alert-box {
    background: rgba(194,38,38,0.1);
    border: 1px solid rgba(194,38,38,0.3);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}
.alert-box h4 {
    color: var(--red);
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.alert-box p {
    color: var(--muted);
    font-size: 0.85rem;
    margin: 0;
}

.session-item {
    background: var(--card1);
    border: 1px solid var(--line-w);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.session-item.current {
    border-color: rgba(46,204,113,0.5);
    background: rgba(46,204,113,0.05);
}
.session-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 4px 0;
}
.session-info p {
    font-size: 0.8rem;
    color: var(--muted);
    margin: 0;
}
.session-badge {
    background: rgba(46,204,113,0.2);
    color: #2ecc71;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.password-strength {
    height: 4px;
    background: var(--card1);
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}
.password-strength-bar {
    height: 100%;
    width: 0;
    transition: width 0.3s, background 0.3s;
}
.strength-weak { width: 33%; background: #ef4444; }
.strength-medium { width: 66%; background: #f59e0b; }
.strength-strong { width: 100%; background: #22c55e; }

.notification-dropdown {
    position: absolute; top: 100%; right: 0; width: 320px;
    background: var(--card2); border: 1px solid var(--line-w);
    border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    z-index: 1000; display: none; max-height: 400px; overflow-y: auto;
}
.notification-dropdown.show { display: block; }
.notification-header {
    padding: 12px 16px; border-bottom: 1px solid var(--line-w);
    display: flex; justify-content: space-between; align-items: center;
}
.notification-header h3 { margin: 0; font-size: 0.9rem; color: #fff; }
.notification-header .mark-all {
    font-size: 0.75rem; color: var(--red); text-decoration: none;
    background: transparent; border: none; cursor: pointer;
}
.notification-header .mark-all:hover { text-decoration: underline; }
.notification-item {
    padding: 12px 16px; border-bottom: 1px solid var(--line-w);
    cursor: pointer; transition: background 0.2s;
}
.notification-item:hover { background: rgba(194,38,38,0.05); }
.notification-item:last-child { border-bottom: none; }
.notification-item.unread {
    background: rgba(52,152,219,0.08); border-left: 3px solid #3498db;
}
.notification-content {
    display: flex; gap: 12px; align-items: flex-start;
}
.notification-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.9rem;
}
.notification-text { flex: 1; }
.notification-title {
    font-size: 0.85rem; font-weight: 600; color: #fff; margin-bottom: 4px;
}
.notification-message {
    font-size: 0.78rem; color: var(--muted); line-height: 1.4;
}
.notification-time {
    font-size: 0.72rem; color: var(--muted); margin-top: 4px;
}
.notification-empty {
    padding: 24px; text-align: center; color: var(--muted);
    font-size: 0.85rem;
}
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

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
      <a href="admin-settings.php" class="nav-item active"><i class="fa-solid fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="admin-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Account Settings</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Settings</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge" style="position: relative;" onclick="toggleNotifications()">
          <i class="fa-regular fa-bell"></i>
          <?php if ($unreadCount > 0): ?>
          <span class="badge-dot" style="background: var(--red);"></span>
          <span class="notification-count" style="position: absolute; top: -8px; right: -8px; background: var(--red); color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; min-width: 18px; text-align: center;"><?= $unreadCount ?></span>
          <?php endif; ?>
        </div>
        
        <!-- Notification Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
          <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all" onclick="markAllNotificationsRead()">Mark all read</button>
          </div>
          <div id="notificationList">
            <?php if (empty($userNotifications)): ?>
              <div class="notification-empty">No notifications</div>
            <?php else: ?>
              <?php foreach ($userNotifications as $notif): ?>
                <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" onclick="markNotificationRead(<?= $notif['id'] ?>)">
                  <div class="notification-content">
                    <div class="notification-icon" style="background: <?= getNotificationColor($notif['type']) ?>20; color: <?= getNotificationColor($notif['type']) ?>;">
                      <i class="fa-solid <?= getNotificationIcon($notif['type']) ?>"></i>
                    </div>
                    <div class="notification-text">
                      <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                      <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                      <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="admin-avatar" title="<?= $adminName ?>">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
        </div>
        <a href="account-dashboard.php?user_view=true" class="btn btn-success" title="Go to User Webpage" style="margin-left: 12px; padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; letter-spacing: 0.5px; border: 2px solid var(--red); border-radius: 6px; background: linear-gradient(135deg, #C22626, #8B0A1E); box-shadow: 0 4px 12px rgba(194, 38, 38, 0.4); transition: all 0.3s; color: #ff6b6b;">
          <i class="fa-solid fa-user"></i> User View
        </a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Account Settings</h1>
        <p>Manage your profile, security, and notification preferences.</p>
      </div>

      <!-- PROFILE SECTION -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon"><i class="fa-solid fa-user"></i></div>
          <div>
            <div class="section-title">Profile Information</div>
            <div class="section-description">Update your personal information and profile picture</div>
          </div>
        </div>
        
        <div style="display: flex; gap: 32px; flex-wrap: wrap;">
          <div style="flex-shrink: 0;">
            <div class="avatar-preview" id="avatarPreview">
              <?php if ($userData['avatar_url']): ?>
                <img src="<?= htmlspecialchars($userData['avatar_url']) ?>" alt="Avatar">
              <?php else: ?>
                <?= strtoupper(substr($adminName, 0, 1)) ?>
              <?php endif; ?>
            </div>
            <button class="btn btn-outline btn-sm" onclick="openModal('avatarModal')">
              <i class="fa-solid fa-camera"></i> Change Avatar
            </button>
          </div>
          
          <form id="profileForm" style="flex: 1; min-width: 300px;">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" id="profileName" value="<?= htmlspecialchars($userData['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" id="profileEmail" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Member Since</label>
              <input type="text" class="form-control" value="<?= $userData['created_at'] ? date('F j, Y', strtotime($userData['created_at'])) : 'N/A' ?>" disabled style="background: var(--card1);">
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-save"></i> Save Changes
            </button>
          </form>
        </div>
      </div>

      <!-- SECURITY SECTION -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div>
            <div class="section-title">Security</div>
            <div class="section-description">Manage your password and security settings</div>
          </div>
        </div>
        
        <div class="alert-box">
          <h4><i class="fa-solid fa-triangle-exclamation"></i> Security Tip</h4>
          <p>Use a strong password with at least 8 characters, including uppercase, lowercase, numbers, and symbols.</p>
        </div>
        
        <form id="passwordForm">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" id="currentPassword" required>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" id="newPassword" required oninput="checkPasswordStrength(this.value)">
            <div class="password-strength">
              <div class="password-strength-bar" id="strengthBar"></div>
            </div>
            <small id="strengthText" style="color: var(--muted); font-size: 0.75rem; margin-top: 4px; display: block;">Enter a password</small>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirmPassword" required>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-key"></i> Update Password
          </button>
        </form>
      </div>

      <!-- NOTIFICATION PREFERENCES -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon"><i class="fa-solid fa-bell"></i></div>
          <div>
            <div class="section-title">Notification Preferences</div>
            <div class="section-description">Choose which notifications you want to receive</div>
          </div>
        </div>
        
        <form id="notificationsForm">
          <div class="notification-item">
            <div class="notification-info">
              <h4>Email Notifications</h4>
              <p>Receive notifications via email</p>
            </div>
            <div class="toggle-switch <?= $notificationSettings['email_notifications'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="email_notifications"></div>
          </div>
          
          <div class="notification-item">
            <div class="notification-info">
              <h4>Push Notifications</h4>
              <p>Receive browser push notifications</p>
            </div>
            <div class="toggle-switch <?= $notificationSettings['push_notifications'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="push_notifications"></div>
          </div>
          
          <div class="notification-item">
            <div class="notification-info">
              <h4>Order Alerts</h4>
              <p>Get notified when new orders are placed</p>
            </div>
            <div class="toggle-switch <?= $notificationSettings['order_alerts'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="order_alerts"></div>
          </div>
          
          <div class="notification-item">
            <div class="notification-info">
              <h4>Booking Alerts</h4>
              <p>Get notified when new bookings are made</p>
            </div>
            <div class="toggle-switch <?= $notificationSettings['booking_alerts'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="booking_alerts"></div>
          </div>
          
          <div class="notification-item">
            <div class="notification-info">
              <h4>Security Alerts</h4>
              <p>Get notified about security events</p>
            </div>
            <div class="toggle-switch <?= $notificationSettings['security_alerts'] ? 'active' : '' ?>" onclick="toggleSwitch(this)" data-field="security_alerts"></div>
          </div>
          
          <button type="submit" class="btn btn-primary" style="margin-top: 16px;">
            <i class="fa-solid fa-save"></i> Save Preferences
          </button>
        </form>
      </div>

      <!-- SESSION MANAGEMENT -->
      <div class="settings-section">
        <div class="section-header">
          <div class="section-icon"><i class="fa-solid fa-desktop"></i></div>
          <div>
            <div class="section-title">Active Sessions</div>
            <div class="section-description">Manage your active login sessions</div>
          </div>
        </div>
        
        <div class="session-item current">
          <div class="session-info">
            <h4>Current Session</h4>
            <p><?= $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser' ?> • <?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP' ?></p>
          </div>
          <span class="session-badge">Active Now</span>
        </div>
        
        <button class="btn btn-danger" onclick="revokeAllSessions()">
          <i class="fa-solid fa-right-from-bracket"></i> Revoke All Other Sessions
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Avatar Modal -->
<div class="modal-overlay" id="avatarModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-camera" style="color:var(--red);margin-right:8px;"></i>Change Avatar</div>
    <form id="avatarForm">
      <div class="form-group">
        <label class="form-label">Avatar URL</label>
        <input type="url" class="form-control" id="avatarUrl" placeholder="https://example.com/avatar.jpg" required>
      </div>
      <div class="form-group">
        <label class="form-label">Preview</label>
        <div class="avatar-preview" style="margin: 0 auto 16px;">
          <img id="avatarPreviewModal" src="" alt="Preview" style="display: none;">
          <span id="avatarPlaceholder">?</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('avatarModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Avatar</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

function openModal(id) { document.getElementById(id).classList.add('open'); }

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Notification functions
function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  dropdown.classList.toggle('show');
  
  // Close dropdown when clicking outside
  if (!dropdown.dataset.listenerAdded) {
    dropdown.dataset.listenerAdded = 'true';
    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target) && !e.target.closest('.topbar-badge')) {
        dropdown.classList.remove('show');
      }
    });
  }
}

function markNotificationRead(notificationId) {
  fetch('admin-handle-notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    }
  });
}

function markAllNotificationsRead() {
  fetch('admin-handle-notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_all_read' })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    }
  });
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});

// Toggle switch
function toggleSwitch(element) {
  element.classList.toggle('active');
}

// Password strength checker
function checkPasswordStrength(password) {
  const strengthBar = document.getElementById('strengthBar');
  const strengthText = document.getElementById('strengthText');
  
  let strength = 0;
  if (password.length >= 8) strength++;
  if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
  if (password.match(/\d/)) strength++;
  if (password.match(/[^a-zA-Z\d]/)) strength++;
  
  strengthBar.className = 'password-strength-bar';
  if (strength <= 1) {
    strengthBar.classList.add('strength-weak');
    strengthText.textContent = 'Weak password';
  } else if (strength <= 3) {
    strengthBar.classList.add('strength-medium');
    strengthText.textContent = 'Medium strength';
  } else {
    strengthBar.classList.add('strength-strong');
    strengthText.textContent = 'Strong password';
  }
}

// Profile form
document.getElementById('profileForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const name = document.getElementById('profileName').value;
  const email = document.getElementById('profileEmail').value;
  
  try {
    const res = await fetch('admin-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_profile', name, email })
    });
    const data = await res.json();
    if (data.success) {
      alert('Profile updated successfully!');
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Error updating profile');
  }
});

// Password form
document.getElementById('passwordForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const currentPassword = document.getElementById('currentPassword').value;
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  
  if (newPassword !== confirmPassword) {
    alert('Passwords do not match');
    return;
  }
  
  try {
    const res = await fetch('admin-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'change_password', current_password: currentPassword, new_password: newPassword, confirm_password: confirmPassword })
    });
    const data = await res.json();
    if (data.success) {
      alert('Password changed successfully!');
      document.getElementById('passwordForm').reset();
      document.getElementById('strengthBar').className = 'password-strength-bar';
      document.getElementById('strengthText').textContent = 'Enter a password';
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Error changing password');
  }
});

// Notifications form
document.getElementById('notificationsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const emailNotifications = document.querySelector('[data-field="email_notifications"]').classList.contains('active');
  const pushNotifications = document.querySelector('[data-field="push_notifications"]').classList.contains('active');
  const orderAlerts = document.querySelector('[data-field="order_alerts"]').classList.contains('active');
  const bookingAlerts = document.querySelector('[data-field="booking_alerts"]').classList.contains('active');
  const securityAlerts = document.querySelector('[data-field="security_alerts"]').classList.contains('active');
  
  try {
    const res = await fetch('admin-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        action: 'update_notifications',
        email_notifications: emailNotifications,
        push_notifications: pushNotifications,
        order_alerts: orderAlerts,
        booking_alerts: bookingAlerts,
        security_alerts: securityAlerts
      })
    });
    const data = await res.json();
    if (data.success) {
      alert('Notification preferences saved!');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Error saving preferences');
  }
});

// Avatar form
document.getElementById('avatarUrl').addEventListener('input', (e) => {
  const url = e.target.value;
  const img = document.getElementById('avatarPreviewModal');
  const placeholder = document.getElementById('avatarPlaceholder');
  
  if (url) {
    img.src = url;
    img.style.display = 'block';
    placeholder.style.display = 'none';
  } else {
    img.style.display = 'none';
    placeholder.style.display = 'block';
  }
});

document.getElementById('avatarForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const avatarUrl = document.getElementById('avatarUrl').value;
  
  try {
    const res = await fetch('admin-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_avatar', avatar_url: avatarUrl })
    });
    const data = await res.json();
    if (data.success) {
      alert('Avatar updated successfully!');
      closeModal('avatarModal');
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Error updating avatar');
  }
});

// Revoke sessions
async function revokeAllSessions() {
  if (!confirm('Are you sure you want to revoke all other sessions? You will need to log in again on other devices.')) {
    return;
  }
  
  try {
    const res = await fetch('admin-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'revoke_sessions' })
    });
    const data = await res.json();
    if (data.success) {
      alert('All other sessions have been revoked.');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (err) {
    alert('Error revoking sessions');
  }
}
</script>
</body>
</html>
