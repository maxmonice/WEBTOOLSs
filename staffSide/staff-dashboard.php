<?php
require_once 'staff-config.php';
require_once __DIR__ . '/../Notifications.php';
requireStaff();

$stats    = getStaffStats($pdo);
$bookings = getRecentBookings($pdo, 5);
$orders   = getRecentOrders($pdo, 5);
$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');

$notifications       = new Notifications($pdo);
$userNotifications   = $notifications->getForUser('staff', $_SESSION['user_id'], 8);
$unreadCount         = $notifications->getUnreadCount('staff', $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — Luke's Staff</title>
<link rel="stylesheet" href="../adminSide/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="staff-dashboard.css?v=<?= time() ?>">
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
<?php $staffNavActive = 'dashboard'; require __DIR__ . '/staff-sidebar-nav.php'; ?>
  </aside>

  <!-- MAIN -->
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Staff Dashboard</div>
          <div class="topbar-breadcrumb">Staff <span>/</span> Overview</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="live-notif-wrap">
          <div class="topbar-badge live-notif-trigger" onclick="toggleLiveNotifications(event)">
            <i class="fa-regular fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="badge-dot live-notif-dot"></span>
            <span class="live-notif-count"><?= (int) $unreadCount ?></span>
            <?php endif; ?>
          </div>
          <div class="live-notif-menu" id="liveNotifMenu" role="menu">
            <div class="live-notif-header">
              <h4>Notifications</h4>
              <button type="button" class="live-notif-mark-all" onclick="markAllLiveNotificationsRead()">Mark all read</button>
            </div>
            <div class="live-notif-list" id="liveNotifList">
              <?php if (empty($userNotifications)): ?>
                <div class="live-notif-empty">No notifications</div>
              <?php else: ?>
                <?php foreach ($userNotifications as $notif): ?>
                <div class="live-notif-item<?= !$notif['is_read'] ? ' unread' : '' ?>" data-id="<?= (int) $notif['id'] ?>" onclick="markLiveNotificationRead(<?= (int) $notif['id'] ?>, this)">
                  <div class="live-notif-row">
                    <div class="live-notif-icon" style="background: <?= htmlspecialchars(getNotificationColor($notif['type'])) ?>20; color: <?= htmlspecialchars(getNotificationColor($notif['type'])) ?>;">
                      <i class="fa-solid <?= htmlspecialchars(getNotificationIcon($notif['type'])) ?>"></i>
                    </div>
                    <div class="live-notif-body">
                      <div class="live-notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                      <div class="live-notif-msg"><?= htmlspecialchars($notif['message']) ?></div>
                      <div class="live-notif-time"><?= htmlspecialchars(timeAgo($notif['created_at'])) ?></div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);" title="<?= $staffName ?>">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
        </div>
        <a href="../account-dashboard.php?user_view=true" class="btn-user-view-dash staff-user-view" title="Open customer account page">
          <i class="fa-solid fa-user"></i> User view
        </a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Good day, <?= $staffName ?> 👋</h1>
        <p>Here's your work overview for today at Luke's Seafood Trading.</p>
      </div>

      <!-- PERMISSION NOTICE -->
      <div class="access-banner">
        <i class="fa-solid fa-circle-info"></i>
        <span>You're logged in as <strong>Staff</strong>. You can manage bookings and orders, and view customer info. System configuration, audit logs, and user management are <strong>admin-only</strong>.</span>
      </div>

      <!-- STATS (no revenue exposed to staff) -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value"><?= number_format($stats['active_bookings']) ?></div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change <?= $stats['pending_bookings'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-<?= $stats['pending_bookings'] > 0 ? 'arrow-down' : 'check' ?>"></i>
            <?= $stats['pending_bookings'] ?> pending review
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div class="stat-card-value"><?= number_format($stats['my_bookings_today']) ?></div>
          <div class="stat-card-label">Today's Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-clock"></i> scheduled today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value"><?= number_format($stats['pending_orders']) ?></div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change <?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-<?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>"></i>
            <?= $stats['pending_orders'] > 0 ? 'needs processing' : 'all clear' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-truck-fast"></i></div>
          <div class="stat-card-value"><?= number_format($stats['processing_orders']) ?></div>
          <div class="stat-card-label">In Progress</div>
          <div class="stat-card-change up"><i class="fa-solid fa-spinner"></i> being processed</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-motorcycle"></i></div>
          <div class="stat-card-value"><?= number_format($stats['confirmed_orders']) ?></div>
          <div class="stat-card-label">Ready for Rider</div>
          <div class="stat-card-change up"><i class="fa-solid fa-check-circle"></i> awaiting pickup</div>
        </div>
      </div>

      <!-- ROW 1 -->
      <div class="grid-2">

        <!-- TODAY'S TASKS -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Today's Tasks</span>
            <span class="badge badge-yellow"><?= date('M d') ?></span>
          </div>
          <div class="panel-body" style="padding: 0 20px;">
            <?php if ($stats['my_bookings_today'] === 0 && $stats['pending_orders'] === 0): ?>
              <div class="empty-feed">
                <i class="fa-solid fa-sun" style="font-size:1.6rem;margin-bottom:8px;display:block;color:var(--warning);"></i>
                No tasks assigned for today. Check bookings for upcoming events.
              </div>
            <?php else: ?>
              <?php if ($stats['pending_bookings'] > 0): ?>
              <div class="task-item">
                <div class="task-dot urgent"></div>
                <div class="task-text">
                  <strong><?= $stats['pending_bookings'] ?> booking<?= $stats['pending_bookings'] > 1 ? 's' : '' ?></strong> awaiting confirmation
                </div>
                <span class="task-time"><a href="staff-bookings.php" style="color:var(--red);text-decoration:none;font-size:0.72rem;">Review →</a></span>
              </div>
              <?php endif; ?>
              <?php if ($stats['pending_orders'] > 0): ?>
              <div class="task-item">
                <div class="task-dot urgent"></div>
                <div class="task-text">
                  <strong><?= $stats['pending_orders'] ?> order<?= $stats['pending_orders'] > 1 ? 's' : '' ?></strong> need to be processed
                </div>
                <span class="task-time"><a href="staff-orders.php" style="color:var(--red);text-decoration:none;font-size:0.72rem;">Process →</a></span>
              </div>
              <?php endif; ?>
              <?php if ($stats['my_bookings_today'] > 0): ?>
              <div class="task-item">
                <div class="task-dot normal"></div>
                <div class="task-text">
                  <strong><?= $stats['my_bookings_today'] ?> event<?= $stats['my_bookings_today'] > 1 ? 's' : '' ?></strong> scheduled for today
                </div>
                <span class="task-time">Today</span>
              </div>
              <?php endif; ?>
              <?php if ($stats['processing_orders'] > 0): ?>
              <div class="task-item">
                <div class="task-dot done"></div>
                <div class="task-text">
                  <strong><?= $stats['processing_orders'] ?> order<?= $stats['processing_orders'] > 1 ? 's' : '' ?></strong> currently in processing
                </div>
                <span class="task-time">In progress</span>
              </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- QUICK ACTIONS (staff-limited) -->
        <div class="panel">
          <div class="panel-header"><span class="panel-title">Quick Actions</span></div>
          <div class="panel-body">
            <div class="quick-actions-grid">
              <a href="staff-bookings.php" class="quick-action"><i class="fa-solid fa-calendar-plus"></i>Bookings</a>
              <a href="staff-orders.php" class="quick-action"><i class="fa-solid fa-clipboard-list"></i>Orders</a>
              <a href="staff-customers.php" class="quick-action"><i class="fa-solid fa-users"></i>View Customers</a>
              <a href="staff-orders.php?filter=pending" class="quick-action"><i class="fa-solid fa-truck-fast"></i>Pending Orders</a>
              <!-- Locked actions -->
              <span class="quick-action disabled" title="Admin only"><i class="fa-solid fa-chart-line"></i>Sales Report</span>
              <span class="quick-action disabled" title="Admin only"><i class="fa-solid fa-shield-halved"></i>Audit Logs</span>
            </div>
            <p style="font-size:0.7rem;color:var(--muted);text-align:center;margin-top:14px;">
              <i class="fa-solid fa-lock" style="margin-right:4px;"></i>Greyed-out actions require Admin access
            </p>
          </div>
        </div>
      </div>

      <!-- RECENT BOOKINGS -->
      <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Bookings</span>
            <a href="staff-bookings.php" class="btn btn-outline btn-sm">View All</a>
          </div>
        <?php if (empty($bookings)): ?>
          <div style="padding:28px;text-align:center;color:var(--muted);">
            <i class="fa-solid fa-calendar-days" style="font-size:1.8rem;margin-bottom:8px;display:block;"></i>
            No bookings yet.
          </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Booking ID</th><th>Customer</th><th>Event Date</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($b['customer_name'], 0, 2)) ?></div>
                    <?= htmlspecialchars($b['customer_name']) ?>
                  </div>
                </td>
                <td><?= $b['event_date'] ? date('M d, Y', strtotime($b['event_date'])) : '—' ?></td>
                <td><?= statusBadge($b['status']) ?></td>
                <td>
                  <a href="staff-bookings.php?id=<?= $b['id'] ?>" class="action-btn edit" title="View / Update" style="display:inline-grid;width:30px;height:30px;border-radius:6px;border:1px solid var(--line-w);background:transparent;color:var(--muted);font-size:0.8rem;place-items:center;text-decoration:none;">
                    <i class="fa-solid fa-pen"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- RECENT ORDERS -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Recent Orders</span>
          <a href="staff-orders.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php if (empty($orders)): ?>
          <div style="padding:28px;text-align:center;color:var(--muted);">
            <i class="fa-solid fa-bag-shopping" style="font-size:1.8rem;margin-bottom:8px;display:block;"></i>
            No orders yet.
          </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Order ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($order['customer_name'], 0, 2)) ?></div>
                    <?= htmlspecialchars($order['customer_name']) ?>
                  </div>
                </td>
                <td><?= peso((float)$order['total']) ?></td>
                <td><?= statusBadge($order['status']) ?></td>
                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                <td>
                  <a href="staff-orders.php?id=<?= $order['id'] ?>" class="action-btn edit" title="Update Status" style="display:inline-grid;width:30px;height:30px;border-radius:6px;border:1px solid var(--line-w);background:transparent;color:var(--muted);font-size:0.8rem;place-items:center;text-decoration:none;">
                    <i class="fa-solid fa-pen"></i>
                  </a>
                </td>
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

<script src="staff-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>
