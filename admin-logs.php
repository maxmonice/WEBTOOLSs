<?php
require_once 'admin-config.php';
require_once 'Notifications.php';
requireAdmin();

// Initialize notifications
$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// Handle log operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data['action'] === 'create_log') {
        $action = $data['action_type'] ?? '';
        $details = $data['details'] ?? '';
        $userEmail = $data['user_email'] ?? '';
        $userName = $data['user_name'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                action, details, user_email, user_name, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        try {
            $stmt->execute([$action, $details, $userEmail, $userName, $ipAddress, $userAgent]);
            
            // Create security notifications for important log events
            $notifications = new Notifications($pdo);
            
            // Trigger notifications for suspicious activities
            if (in_array($action, ['failed_login', 'unauthorized_access', 'suspicious_activity'])) {
                $notifications->autoNotify('security_alert', [
                    'message' => "Security Alert: {$action} - {$details} from IP {$ipAddress}"
                ]);
            }
            
            // Log successful admin actions
            if (in_array($action, ['user_created', 'booking_updated', 'order_processed'])) {
                $notifications->autoNotify('login_attempt', [
                    'success' => true,
                    'email' => $userEmail,
                    'action' => $action,
                    'ip' => $ipAddress
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Log entry created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get logs for display
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist, create it
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            user_email VARCHAR(255),
            user_name VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableSQL);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Security & Logs — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.log-entry {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  transition: border-color 0.2s;
}
.log-entry:hover { border-color: rgba(194,38,38,0.3); }
.log-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.log-action {
  display: flex;
  align-items: center;
  gap: 8px;
}
.log-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}
.log-icon.login { background: rgba(34,197,94,0.2); color: #22c55e; }
.log-icon.logout { background: rgba(239,68,68,0.2); color: #ef4444; }
.log-icon.create { background: rgba(59,130,246,0.2); color: #3b82f6; }
.log-icon.update { background: rgba(249,115,22,0.2); color: #f97316; }
.log-icon.delete { background: rgba(239,68,68,0.2); color: #ef4444; }
.log-icon.security { background: rgba(217,70,239,0.2); color: #d946ef; }

.log-action-text {
  font-weight: 600;
  color: #fff;
  font-size: 0.9rem;
}
.log-time {
  color: var(--muted);
  font-size: 0.75rem;
}
.log-details {
  color: var(--muted);
  font-size: 0.85rem;
  line-height: 1.5;
  margin-bottom: 8px;
}
.log-meta {
  display: flex;
  gap: 20px;
  font-size: 0.75rem;
  color: var(--muted);
}
.log-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
}
.log-meta-label {
  font-weight: 600;
  color: rgba(255,255,255,0.6);
}
.filter-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--line-w);
}
.filter-tab {
  padding: 10px 16px;
  background: transparent;
  border: none;
  color: var(--muted);
  font-weight: 600;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}
.filter-tab:hover {
  color: #fff;
}
.filter-tab.active {
  color: var(--red);
  border-bottom-color: var(--red);
}

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
      <a href="admin-messages.php" class="nav-item"><i class="fa-solid fa-message"></i> Messages</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item active"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-settings.php" class="nav-item"><i class="fa-solid fa-cog"></i> Account Settings</a>
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
          <div class="topbar-title">Security & Logs</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Audit Logs</div>
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
        
        <a href="admin-settings.php" class="admin-avatar" title="Account Settings" style="text-decoration: none; cursor: pointer;">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
        </a>
        <a href="account-dashboard.php?user_view=true" class="btn btn-success" title="Go to User Webpage" style="margin-left: 12px; padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; letter-spacing: 0.5px; border: 2px solid var(--red); border-radius: 6px; background: linear-gradient(135deg, #C22626, #8B0A1E); box-shadow: 0 4px 12px rgba(194, 38, 38, 0.4); transition: all 0.3s; color: #ff6b6b;">
          <i class="fa-solid fa-user"></i> User View
        </a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Security & Audit Logs</h1>
        <p>Monitor system activity, user actions, and security events.</p>
      </div>

      <!-- STATS -->
      <?php
      $logStats = [
        'active_users_today' => 0,
        'security_alerts' => 0,
        'activities_today' => 0,
        'failed_logins_today' => 0
      ];
      try {
        $logStats['active_users_today'] = (int)$pdo->query("SELECT COUNT(DISTINCT user_email) FROM audit_logs WHERE DATE(created_at) = CURDATE() AND user_email IS NOT NULL AND user_email != ''")->fetchColumn();
      } catch (\Throwable $_) {}
      try {
        $logStats['security_alerts'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('failed_login', 'unauthorized_access', 'suspicious_activity') AND DATE(created_at) = CURDATE()")->fetchColumn();
      } catch (\Throwable $_) {}
      try {
        $logStats['activities_today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
      } catch (\Throwable $_) {}
      try {
        $logStats['failed_logins_today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'failed_login' AND DATE(created_at) = CURDATE()")->fetchColumn();
      } catch (\Throwable $_) {}
      ?>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= number_format($logStats['active_users_today']) ?></div>
          <div class="stat-card-label">Active Users Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Recent activity</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="stat-card-value"><?= number_format($logStats['security_alerts']) ?></div>
          <div class="stat-card-label">Security Alerts</div>
          <div class="stat-card-change <?= $logStats['security_alerts'] > 0 ? 'down' : 'up' ?>"><i class="fa-solid fa-<?= $logStats['security_alerts'] > 0 ? 'triangle-exclamation' : 'check' ?>"></i> <?= $logStats['security_alerts'] > 0 ? 'Needs review' : 'All clear' ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= number_format($logStats['activities_today']) ?></div>
          <div class="stat-card-label">Activities Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-calendar-day"></i> Daily audit trail</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-lock"></i></div>
          <div class="stat-card-value"><?= number_format($logStats['failed_logins_today']) ?></div>
          <div class="stat-card-label">Failed Logins Today</div>
          <div class="stat-card-change <?= $logStats['failed_logins_today'] > 0 ? 'down' : 'up' ?>"><i class="fa-solid fa-arrow-<?= $logStats['failed_logins_today'] > 0 ? 'down' : 'up' ?>"></i> <?= $logStats['failed_logins_today'] > 0 ? 'Investigate' : 'Normal' ?></div>
        </div>
      </div>

      <!-- FILTER TABS -->
      <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterLogs('all', this)">All Logs</button>
        <button class="filter-tab" onclick="filterLogs('security', this)">Security</button>
        <button class="filter-tab" onclick="filterLogs('user', this)">User Activity</button>
        <button class="filter-tab" onclick="filterLogs('system', this)">System</button>
      </div>

      <!-- LOGS LIST -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Recent Activity Logs</span>
          <span class="badge badge-gray"><?= number_format(count($logs)) ?> entries</span>
        </div>
        <div style="max-height: 600px; overflow-y: auto;">
          <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
              <?php
                $iconClass = 'login';
                $icon = 'fa-sign-in-alt';
                if (strpos(strtolower($log['action']), 'logout') !== false) {
                  $iconClass = 'logout';
                  $icon = 'fa-sign-out-alt';
                } elseif (strpos(strtolower($log['action']), 'create') !== false) {
                  $iconClass = 'create';
                  $icon = 'fa-plus';
                } elseif (strpos(strtolower($log['action']), 'update') !== false) {
                  $iconClass = 'update';
                  $icon = 'fa-edit';
                } elseif (strpos(strtolower($log['action']), 'delete') !== false) {
                  $iconClass = 'delete';
                  $icon = 'fa-trash';
                } elseif (strpos(strtolower($log['action']), 'security') !== false) {
                  $iconClass = 'security';
                  $icon = 'fa-shield-alt';
                }
                $actionLower = strtolower($log['action']);
                $logType = 'system';
                if (strpos($actionLower, 'failed') !== false || strpos($actionLower, 'security') !== false || strpos($actionLower, 'unauthorized') !== false || strpos($actionLower, 'suspicious') !== false) {
                  $logType = 'security';
                  $statusClass = 'red';
                  $statusText = 'Alert';
                } elseif (strpos($actionLower, 'user') !== false || strpos($actionLower, 'login') !== false || strpos($actionLower, 'logout') !== false || strpos($actionLower, 'profile') !== false) {
                  $logType = 'user';
                  $statusClass = 'green';
                  $statusText = 'Success';
                } else {
                  $statusClass = 'blue';
                  $statusText = 'Recorded';
                }
              ?>
              <div class="log-entry" data-log-type="<?= $logType ?>">
                <div class="log-header">
                  <div class="log-action">
                    <div class="log-icon <?= $iconClass ?>">
                      <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="log-action-text"><?= htmlspecialchars($log['action']) ?></div>
                  </div>
                  <div class="flex-gap">
                    <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                    <div class="log-time"><?= timeAgo($log['created_at']) ?></div>
                  </div>
                </div>
                <?php if ($log['details']): ?>
                  <div class="log-details"><?= htmlspecialchars($log['details']) ?></div>
                <?php endif; ?>
                <div class="log-meta">
                  <div class="log-meta-item">
                    <span class="log-meta-label">User:</span>
                    <?= htmlspecialchars($log['user_name'] ?: 'System') ?>
                  </div>
                  <?php if ($log['ip_address']): ?>
                    <div class="log-meta-item">
                      <span class="log-meta-label">IP:</span>
                      <?= htmlspecialchars($log['ip_address']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align: center; padding: 60px; color: var(--muted);">
              <i class="fa-solid fa-clipboard-list" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
              <h3>No logs yet</h3>
              <p>System activity will appear here once users start interacting with the system.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

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

function filterLogs(type, tab) {
  document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
  tab.classList.add('active');

  document.querySelectorAll('.log-entry').forEach(entry => {
    const shouldShow = type === 'all' || entry.dataset.logType === type;
    entry.style.display = shouldShow ? '' : 'none';
  });
}
</script>
</body>
</html>
