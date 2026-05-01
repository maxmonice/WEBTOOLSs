<?php
require_once 'staff-config.php';
requireStaff();

$stats    = getStaffStats($pdo);
$bookings = getRecentBookings($pdo, 5);
$orders   = getRecentOrders($pdo, 5);
$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — Luke's Staff</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ── Staff role pill in sidebar ── */
.role-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 100px; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    background: rgba(243,156,18,0.15); color: #f39c12;
    border: 1px solid rgba(243,156,18,0.3); margin-top: 6px;
}
.role-pill i { font-size: 0.65rem; }

/* ── Access-denied nav items ── */
.nav-item.locked {
    opacity: 0.38; pointer-events: none; cursor: not-allowed;
    position: relative;
}
.nav-item.locked::after {
    content: '\f023';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    font-size: 0.65rem;
    margin-left: auto;
    color: rgba(255,255,255,0.3);
}

/* ── Permission banner ── */
.access-banner {
    display: flex; align-items: center; gap: 12px;
    background: rgba(243,156,18,0.08);
    border: 1px solid rgba(243,156,18,0.2);
    border-radius: 10px; padding: 12px 18px; margin-bottom: 22px;
    font-size: 0.82rem; color: rgba(255,255,255,0.65);
}
.access-banner i { color: #f39c12; font-size: 1rem; flex-shrink: 0; }
.access-banner strong { color: #f39c12; }

/* ── Activity feed ── */
.activity-item {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
}
.activity-item:last-child { border-bottom: none; }
.activity-dot {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: grid; place-items: center; font-size: 0.8rem; margin-top: 2px;
}
.activity-dot.green  { background: rgba(46,204,113,0.12); color: var(--success); }
.activity-dot.blue   { background: rgba(52,152,219,0.12); color: var(--info); }
.activity-dot.yellow { background: rgba(243,156,18,0.12); color: var(--warning); }
.activity-meta { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }
.activity-text { font-size: 0.84rem; color: rgba(255,255,255,0.82); }

/* ── Quick actions ── */
.quick-action {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; padding: 20px; background: var(--card2);
    border: 1px solid var(--line-w); border-radius: 10px; text-decoration: none;
    color: var(--muted); font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em;
    text-transform: uppercase; text-align: center; transition: all 0.25s;
}
.quick-action i { font-size: 1.4rem; color: var(--red); }
.quick-action:hover { border-color: var(--red); color: #fff; background: rgba(194,38,38,0.08); transform: translateY(-2px); }
.quick-action.disabled {
    opacity: 0.3; pointer-events: none; cursor: not-allowed;
}
.quick-action.disabled i { color: var(--muted); }
.empty-feed { padding: 24px; text-align: center; color: var(--muted); font-size: 0.85rem; }

/* ── Today tasks ── */
.task-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
}
.task-item:last-child { border-bottom: none; }
.task-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.task-dot.urgent { background: #ef4444; }
.task-dot.normal { background: #f39c12; }
.task-dot.done   { background: #22c55e; }
.task-text { font-size: 0.83rem; color: rgba(255,255,255,0.8); flex: 1; }
.task-time { font-size: 0.72rem; color: var(--muted); white-space: nowrap; }
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Staff Panel</span></div>
      <div class="role-pill"><i class="fa-solid fa-id-badge"></i> Staff Access</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="staff-dashboard.php" class="nav-item active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">My Work</div>
      <a href="staff-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Bookings</a>
      <a href="staff-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Orders</a>
      <div class="nav-section-label">View Only</div>
      <a href="staff-customers.php" class="nav-item"><i class="fa-solid fa-users"></i> Customers</a>
      <div class="nav-section-label">Restricted</div>
      <span class="nav-item locked"><i class="fa-solid fa-layer-group"></i> Content Management</span>
      <span class="nav-item locked"><i class="fa-solid fa-shield-halved"></i> Security & Logs</span>
      <span class="nav-item locked"><i class="fa-solid fa-sliders"></i> System Config</span>
    </nav>
    <div class="sidebar-footer">
      <a href="staff-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
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
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i>
          <?php if ($stats['pending_bookings'] > 0 || $stats['pending_orders'] > 0): ?>
          <span class="badge-dot"></span>
          <?php endif; ?>
        </div>
        <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);" title="<?= $staffName ?>">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
        </div>
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
          <div class="stat-card-label">In Progress Orders</div>
          <div class="stat-card-change up"><i class="fa-solid fa-spinner"></i> being processed</div>
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
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
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

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>
