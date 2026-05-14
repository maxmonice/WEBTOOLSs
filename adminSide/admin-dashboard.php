<?php
require_once 'admin-config.php';
requireAdmin();  // 🔒 redirects to account.php if not admin

$stats    = getAdminStats($pdo);
$activity = getRecentActivity($pdo, 6);
$orders   = getRecentOrders($pdo, 5);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<link rel="stylesheet" href="admin-dashboard.css?v=<?= time() ?>">
<script defer src="admin-notifications.js?v=<?= time() ?>"></script>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
<?php $adminNavActive = 'dashboard'; require __DIR__ . '/admin-sidebar-nav.php'; ?>
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
        <?php $adminTopbarName = $adminName; require __DIR__ . '/admin-topbar-right.php'; ?>
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
              <a href="../admin-archive.php" class="quick-action"><i class="fa-solid fa-box-archive"></i>Archive</a>
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






<script defer src="admin-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>