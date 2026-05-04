<?php
require_once 'staff-config.php';
requireStaff();

$successMsg = '';
$errorMsg   = '';

// Allowed status transitions for staff (cannot set to refunded or anything financial)
const STAFF_ALLOWED_STATUSES = ['processing', 'shipped', 'delivered', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $oid    = (int)($_POST['order_id'] ?? 0);

    if ($action === 'update_status' && $oid > 0) {
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, STAFF_ALLOWED_STATUSES, true)) {
            try {
                $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')
                    ->execute([$newStatus, $oid]);
                $successMsg = 'Order <strong>#ORD-' . str_pad($oid, 4, '0', STR_PAD_LEFT) . '</strong> updated to ' . ucfirst($newStatus) . '.';
            } catch (\Throwable $e) {
                $errorMsg = 'Error: ' . $e->getMessage();
            }
        } else {
            $errorMsg = 'Invalid status. Staff can only set: Processing, Shipped, Delivered, Cancelled.';
        }
    } elseif ($action === 'delete_order') {
        $errorMsg = 'You do not have permission to delete orders. Please contact an admin.';
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');

$whereClause = 'WHERE 1=1';
$params      = [];
if ($search !== '') {
    $whereClause .= ' AND u.name LIKE :s';
    $params[':s'] = "%$search%";
}
if ($filter !== '') {
    $whereClause .= ' AND o.status = :f';
    $params[':f'] = $filter;
}

try {
    $stmt = $pdo->prepare(
        "SELECT o.id, o.status, o.created_at,
                COALESCE(o.total_amount, o.total, 0) AS total,
                COALESCE(u.name, 'Unknown') AS customer_name,
                COALESCE(u.email, '—') AS customer_email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         $whereClause
         ORDER BY o.created_at DESC"
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (\Throwable $_) {
    $orders = [];
}

$stats = getStaffStats($pdo);
$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Orders — Luke's Staff</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.role-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 100px; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    background: rgba(243,156,18,0.15); color: #f39c12;
    border: 1px solid rgba(243,156,18,0.3); margin-top: 6px;
}
.nav-item.locked {
    opacity: 0.38; pointer-events: none; cursor: not-allowed; position: relative;
}
.nav-item.locked::after {
    content: '\f023'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
    font-size: 0.65rem; margin-left: auto; color: rgba(255,255,255,0.3);
}
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.action-btn {
    width:30px; height:30px; border-radius:6px; border:1px solid var(--line-w);
    background:transparent; color:var(--muted); font-size:0.8rem;
    display:inline-grid; place-items:center; cursor:pointer; transition:all 0.2s;
}
.action-btn:hover { border-color:var(--red); color:#ff6b6b; background:rgba(194,38,38,0.1); }
.alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.86rem; }
.alert-success { background:rgba(46,204,113,0.12); color:#2ecc71; border:1px solid rgba(46,204,113,0.25); }
.alert-error   { background:rgba(194,38,38,0.12);  color:#ff6b6b; border:1px solid rgba(194,38,38,0.25); }
.permission-note {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; color: rgba(243,156,18,0.7);
    background: rgba(243,156,18,0.07); border: 1px solid rgba(243,156,18,0.15);
    border-radius: 6px; padding: 3px 8px;
}
.status-select {
    background: var(--card2); border: 1px solid var(--line-w); color: #fff;
    border-radius: 6px; padding: 4px 8px; font-size: 0.78rem; cursor: pointer;
}
.status-select:focus { outline: none; border-color: var(--red); }
.inline-form { display: inline-flex; align-items: center; gap: 6px; }
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Staff Panel</span></div>
      <div class="role-pill"><i class="fa-solid fa-id-badge"></i> Staff Access</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="staff-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">My Work</div>
      <a href="staff-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Bookings</a>
      <a href="staff-orders.php" class="nav-item active"><i class="fa-solid fa-bag-shopping"></i> Orders</a>
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

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Order Management</div>
          <div class="topbar-breadcrumb">Staff <span>/</span> Orders</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i>
          <?php if ($stats['pending_orders'] > 0): ?><span class="badge-dot"></span><?php endif; ?>
        </div>
        <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
        </div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Order Management</h1>
          <p>Update order status to Processing, Shipped, Delivered, or Cancelled.</p>
        </div>
        <span class="permission-note"><i class="fa-solid fa-lock"></i> Status update only — no delete or refund</span>
      </div>

      <?php if ($successMsg): ?>
      <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
      <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errorMsg ?></div>
      <?php endif; ?>

      <!-- STATS (no revenue) -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value"><?= $stats['pending_orders'] ?></div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change <?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-down"></i> <?= $stats['pending_orders'] > 0 ? 'needs processing' : 'all clear' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-gears"></i></div>
          <div class="stat-card-value"><?= $stats['processing_orders'] ?></div>
          <div class="stat-card-label">In Processing</div>
          <div class="stat-card-change up"><i class="fa-solid fa-spinner"></i> active</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-truck-fast"></i></div>
          <div class="stat-card-value"><?= $stats['total_orders_today'] ?></div>
          <div class="stat-card-label">Orders Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-clock"></i> today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-list-check"></i></div>
          <div class="stat-card-value"><?= count($orders) ?></div>
          <div class="stat-card-label">Total Orders</div>
          <div class="stat-card-change up"><i class="fa-solid fa-database"></i> in system</div>
        </div>
      </div>

      <!-- FILTER TABS -->
      <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <a href="staff-orders.php" class="btn btn-outline btn-sm <?= !$filter ? 'btn-primary' : '' ?>">All</a>
        <a href="staff-orders.php?filter=pending" class="btn btn-outline btn-sm">Pending</a>
        <a href="staff-orders.php?filter=processing" class="btn btn-outline btn-sm">Processing</a>
        <a href="staff-orders.php?filter=shipped" class="btn btn-outline btn-sm">Shipped</a>
        <a href="staff-orders.php?filter=delivered" class="btn btn-outline btn-sm">Delivered</a>
        <a href="staff-orders.php?filter=cancelled" class="btn btn-outline btn-sm">Cancelled</a>
      </div>

      <!-- ORDERS TABLE -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Orders
            <span style="color:var(--muted);font-weight:400;font-size:0.82rem;margin-left:8px;">(<?= count($orders) ?> shown)</span>
          </span>
          <form method="GET" style="display:flex;gap:10px;align-items:center;">
            <div class="search-wrap">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="search-input" name="search" placeholder="Search customer…" value="<?= htmlspecialchars($search) ?>"/>
            </div>
            <?php if ($filter): ?>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
          </form>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Order ID</th><th>Customer</th><th>Total</th><th>Current Status</th><th>Date</th><th>Update Status</th>
            </tr></thead>
            <tbody>
              <?php if (empty($orders)): ?>
              <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--muted);">
                No orders found<?= $filter ? ' with status "' . htmlspecialchars($filter) . '"' : '' ?>.
              </td></tr>
              <?php else: ?>
              <?php foreach ($orders as $o): ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-<?= str_pad($o['id'], 4, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($o['customer_name'], 0, 2)) ?></div>
                    <?= htmlspecialchars($o['customer_name']) ?>
                  </div>
                </td>
                <td><?= peso((float)$o['total']) ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                <td>
                  <?php if (!in_array($o['status'], ['delivered', 'cancelled'])): ?>
                  <form method="POST" class="inline-form">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <select name="new_status" class="status-select">
                      <option value="processing" <?= $o['status']==='processing'?'selected':'' ?>>Processing</option>
                      <option value="shipped"    <?= $o['status']==='shipped'?'selected':'' ?>>Shipped</option>
                      <option value="delivered"  <?= $o['status']==='delivered'?'selected':'' ?>>Delivered</option>
                      <option value="cancelled"  <?= $o['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="action-btn" title="Save" onclick="return confirm('Update this order status?')">
                      <i class="fa-solid fa-floppy-disk"></i>
                    </button>
                  </form>
                  <?php else: ?>
                  <span style="font-size:0.75rem;color:var(--muted);">
                    <i class="fa-solid fa-lock" style="margin-right:4px;"></i>Finalized
                  </span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--line-w);font-size:0.75rem;color:var(--muted);">
          <i class="fa-solid fa-circle-info" style="margin-right:5px;color:#f39c12;"></i>
          Staff can update status to: <strong style="color:rgba(255,255,255,0.5);">Processing → Shipped → Delivered</strong> or <strong style="color:rgba(255,255,255,0.5);">Cancelled</strong>. 
          Delivered and Cancelled orders are locked. Refunds and deletions require admin.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
</script>
</body>
</html>
