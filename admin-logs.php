<?php
require_once 'admin-config.php';
require_once 'activity-logger.php';
requireAdmin();

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
            echo json_encode(['success' => true, 'message' => 'Log entry created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get logs for display using activity logger
$logs = getRecentActivities(100);

// Get activity statistics
$stats = getActivityStats();

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
      <a href="admin-logs.php" class="nav-item active"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-account.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
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
          <div class="topbar-title">Security & Logs</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Audit Logs</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="notification-dropdown">
          <div class="topbar-badge" onclick="toggleNotifications()">
            <i class="fa-regular fa-bell"></i>
            <span class="badge-dot"></span>
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
        <a href="admin-account.php" class="admin-avatar">A</a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Security & Audit Logs</h1>
        <p>Monitor system activity, user actions, and security events.</p>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= $stats['users_today'] ?></div>
          <div class="stat-card-label">Active Users Today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="stat-card-value"><?= $stats['security'] ?></div>
          <div class="stat-card-label">Security Events</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= $stats['today'] ?></div>
          <div class="stat-card-label">Activities Today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-server"></i></div>
          <div class="stat-card-value">Online</div>
          <div class="stat-card-label">System Status</div>
        </div>
      </div>

      <!-- FILTER TABS -->
      <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterLogs('all')">All Logs</button>
        <button class="filter-tab" onclick="filterLogs('security')">Security</button>
        <button class="filter-tab" onclick="filterLogs('user')">User Activity</button>
        <button class="filter-tab" onclick="filterLogs('system')">System</button>
      </div>

      <!-- LOGS LIST -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Recent Activity Logs</span>
          <span class="badge badge-gray">Last 100 entries</span>
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
              ?>
              <div class="log-entry">
                <div class="log-header">
                  <div class="log-action">
                    <div class="log-icon <?= $iconClass ?>">
                      <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="log-action-text"><?= htmlspecialchars($log['action']) ?></div>
                  </div>
                  <div class="log-time"><?= timeAgo($log['created_at']) ?></div>
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
function filterLogs(type) {
  // Update active tab
  document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
  event.target.classList.add('active');
  
  // Filter logic would go here
  console.log('Filtering logs by:', type);
}
</script>

<style>
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

<script>
function toggleNotifications() {
  const menu = document.getElementById('notificationMenu');
  menu.classList.toggle('show');
  
  // Close when clicking outside
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
  
  // Remove badge dot
  const badgeDot = document.querySelector('.badge-dot');
  if (badgeDot) {
    badgeDot.style.display = 'none';
  }
}
</script>
</body>
</html>
