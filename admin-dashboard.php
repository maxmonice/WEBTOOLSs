<?php
require_once 'admin-config.php';
require_once 'Notifications.php';
requireAdmin();  // 🔒 redirects to account.php if not admin

$stats    = getAdminStats($pdo);
$activity = getRecentActivity($pdo, 6);
$orders   = getRecentOrders($pdo, 5);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// Initialize notifications
$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.activity-item {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
}
.activity-item:last-child { border-bottom: none; }
.activity-dot {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: grid; place-items: center; font-size: 0.8rem;
    margin-top: 2px;
}
.activity-dot.red    { background: rgba(194,38,38,0.15); color: var(--red); }
.activity-dot.green  { background: rgba(46,204,113,0.12); color: var(--success); }
.activity-dot.blue   { background: rgba(52,152,219,0.12); color: var(--info); }
.activity-dot.yellow { background: rgba(243,156,18,0.12); color: var(--warning); }
.activity-meta { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }
.activity-text { font-size: 0.84rem; color: rgba(255,255,255,0.82); }

.quick-action {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; padding: 20px; background: var(--card2);
    border: 1px solid var(--line-w); border-radius: 10px; text-decoration: none;
    color: var(--muted); font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em;
    text-transform: uppercase; text-align: center;
    transition: all 0.25s; cursor: pointer;
}
.quick-action i { font-size: 1.4rem; color: var(--red); }
.quick-action:hover { border-color: var(--red); color: #fff; background: rgba(194,38,38,0.08); transform: translateY(-2px); }
.empty-feed { padding: 24px; text-align: center; color: var(--muted); font-size: 0.85rem; }

/* Notification Styles */
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

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Admin Panel</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="admin-dashboard.php" class="nav-item active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="admin-users.php" class="nav-item"><i class="fa-solid fa-users"></i> User Management</a>
      <a href="admin-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
      <a href="admin-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
      <a href="admin-content.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Content Management</a>
      <a href="admin-messages.php" class="nav-item"><i class="fa-solid fa-message"></i> Messages</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-settings.php" class="nav-item"><i class="fa-solid fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="admin-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Dashboard</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Overview</div>
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
        
        <a href="admin-settings.php" class="admin-avatar" title="<?= $adminName ?>" style="text-decoration: none; cursor: pointer;">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
        </a>
        <a href="account-dashboard.php?user_view=true" class="btn btn-success" title="Go to User Webpage" style="margin-left: 12px; padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; letter-spacing: 0.5px; border: 2px solid var(--red); border-radius: 6px; background: linear-gradient(135deg, #C22626, #8B0A1E); box-shadow: 0 4px 12px rgba(194, 38, 38, 0.4); transition: all 0.3s; color: #ff6b6b;">
          <i class="fa-solid fa-user"></i> User View
        </a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Welcome back, <?= $adminName ?> 👋</h1>
        <p>Here's what's happening at Luke's Seafood Trading today.</p>
      </div>

      <!-- LIVE STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= number_format($stats['total_users']) ?></div>
          <div class="stat-card-label">Registered Customers</div>
          <div class="stat-card-change up">
            <i class="fa-solid fa-arrow-up"></i>
            +<?= $stats['new_users_week'] ?> this week
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value"><?= number_format($stats['active_bookings']) ?></div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change <?= $stats['pending_bookings'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-<?= $stats['pending_bookings'] > 0 ? 'arrow-down' : 'check' ?>"></i>
            <?= $stats['pending_bookings'] ?> pending
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value"><?= number_format($stats['pending_orders']) ?></div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change <?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-<?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>"></i>
            <?= $stats['pending_orders'] > 0 ? 'needs attention' : 'all clear' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="stat-card-value"><?= $stats['revenue_month'] >= 1000
            ? '₱' . number_format($stats['revenue_month'] / 1000, 1) . 'K'
            : peso($stats['revenue_month']) ?></div>
          <div class="stat-card-label">Revenue This Month</div>
          <div class="stat-card-change up">
            <i class="fa-solid fa-arrow-up"></i> <?= date('M Y') ?>
          </div>
        </div>
      </div>

      <!-- ROW 1 -->
      <div class="grid-2">

        <!-- RECENT ACTIVITY (live from DB) -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Activity</span>
            <span class="badge badge-blue">Live</span>
          </div>
          <div class="panel-body" style="padding: 0 20px;">
            <?php if (empty($activity)): ?>
              <div class="empty-feed">
                <i class="fa-solid fa-inbox" style="font-size:1.6rem;margin-bottom:8px;display:block;"></i>
                No activity yet. Data will appear as customers register and place orders.
              </div>
            <?php else: ?>
              <?php foreach ($activity as $event): ?>
              <div class="activity-item">
                <div class="activity-dot <?= $event['color'] ?>">
                  <i class="fa-solid <?= $event['icon'] ?>"></i>
                </div>
                <div>
                  <div class="activity-text"><?= $event['text'] ?></div>
                  <div class="activity-meta"><?= timeAgo($event['time']) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="panel">
          <div class="panel-header"><span class="panel-title">Quick Actions</span></div>
          <div class="panel-body">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <a href="admin-users.php" class="quick-action"><i class="fa-solid fa-user-plus"></i>View Users</a>
              <a href="admin-bookings.php" class="quick-action"><i class="fa-solid fa-calendar-plus"></i>New Booking</a>
              <a href="admin-orders.php" class="quick-action"><i class="fa-solid fa-clipboard-list"></i>View Orders</a>
              <a href="admin-content.php" class="quick-action"><i class="fa-solid fa-plus"></i>Add Product</a>
              <a href="admin-orders.php" class="quick-action"><i class="fa-solid fa-chart-line"></i>Sales Report</a>
              <a href="admin-logs.php" class="quick-action"><i class="fa-solid fa-shield-halved"></i>Audit Logs</a>
            </div>
          </div>
        </div>
      </div>

      <!-- RECENT ORDERS (live from DB) -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Recent Orders</span>
          <a href="admin-orders.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php if (empty($orders)): ?>
          <div style="padding:28px;text-align:center;color:var(--muted);">
            <i class="fa-solid fa-bag-shopping" style="font-size:1.8rem;margin-bottom:8px;display:block;"></i>
            No orders yet. They'll show up here once customers start ordering.
          </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">
                  #ORD-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
                </td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar">
                      <?= strtoupper(substr($order['customer_name'], 0, 2)) ?>
                    </div>
                    <?= htmlspecialchars($order['customer_name']) ?>
                  </div>
                </td>
                <td><?= peso((float)$order['total']) ?></td>
                <td><?= statusBadge($order['status']) ?></td>
                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /admin-layout -->

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

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
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const item = document.querySelector(`[onclick="markNotificationRead(${notificationId})"]`);
      if (item) {
        item.classList.remove('unread');
      }
      updateNotificationCount();
    }
  });
}

function markAllNotificationsRead() {
  fetch('admin-handle-notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_all_read' })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      document.querySelectorAll('.notification-item.unread').forEach(item => {
        item.classList.remove('unread');
      });
      updateNotificationCount();
    }
  });
}

function updateNotificationCount() {
  const count = document.querySelectorAll('.notification-item.unread').length;
  const countElement = document.querySelector('.notification-count');
  const badgeDot = document.querySelector('.badge-dot');
  
  if (countElement) {
    if (count > 0) {
      countElement.textContent = count;
      countElement.style.display = 'block';
      if (badgeDot) badgeDot.style.display = 'block';
    } else {
      countElement.style.display = 'none';
      if (badgeDot) badgeDot.style.display = 'none';
    }
  }
}

function goToUserWebpage() {
  window.location.href = '/FINAL/WEBTOOLSs/account-dashboard.php?user_view=true';
}
</script>
</body>
</html>
