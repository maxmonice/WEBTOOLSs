<?php
require_once 'staff-config.php';
requireStaff();

$successMsg = '';
$errorMsg   = '';

// Handle AJAX POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // ── Start Preparing: status → processing, save ETA ──
    if ($_POST['action'] === 'prepare_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $eta     = trim($_POST['eta'] ?? '');
        if (!$orderId || !$eta) {
            echo json_encode(['success' => false, 'message' => 'Missing order ID or ETA']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE orders SET status = 'processing', eta = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$eta, $orderId]);
            echo json_encode(['success' => true, 'message' => 'Order is now being prepared.']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Done Preparing: status → confirmed (ready for rider) ──
    if ($_POST['action'] === 'complete_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Missing order ID']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE orders SET status = 'confirmed', updated_at = NOW() WHERE id = ?")
                ->execute([$orderId]);
            echo json_encode(['success' => true, 'message' => 'Order is ready for rider!']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Cancel order ──
    if ($_POST['action'] === 'cancel_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Missing order ID']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?")
                ->execute([$orderId]);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');

$whereClause = 'WHERE 1=1';
$params      = [];
if ($search !== '') {
    $whereClause .= ' AND (u.name LIKE :s OR u.email LIKE :s2)';
    $params[':s']  = "%$search%";
    $params[':s2'] = "%$search%";
}
if ($filter !== '') {
    $whereClause .= ' AND o.status = :f';
    $params[':f'] = $filter;
}

try {
    $stmt = $pdo->prepare(
        "SELECT o.id, o.status, o.created_at, o.eta,
                COALESCE(o.total_amount, o.total, 0) AS total,
                COALESCE(u.name, 'Customer') AS customer_name,
                COALESCE(u.email, '—') AS customer_email,
                o.address, o.payment_method,
                o.items AS items_json,
                o.notes
         
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         $whereClause
         ORDER BY o.created_at DESC"
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (\Throwable $e) {
    $orders = [];
    $errorMsg = "Query Error: " . $e->getMessage();
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
<link rel="stylesheet" href="../adminSide/admin.css"/>
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
.alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.86rem; }
.alert-success { background:rgba(46,204,113,0.12); color:#2ecc71; border:1px solid rgba(46,204,113,0.25); }
.alert-error   { background:rgba(194,38,38,0.12);  color:#ff6b6b; border:1px solid rgba(194,38,38,0.25); }
.permission-note {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; color: rgba(243,156,18,0.7);
    background: rgba(243,156,18,0.07); border: 1px solid rgba(243,156,18,0.15);
    border-radius: 6px; padding: 3px 8px;
}
.action-btn {
    width:30px; height:30px; border-radius:6px; border:1px solid var(--line-w);
    background:transparent; color:var(--muted); font-size:0.8rem;
    display:inline-grid; place-items:center; cursor:pointer; transition:all 0.2s;
}
.action-btn:hover { border-color:#3498db; color:#3498db; background:rgba(52,152,219,0.1); }
/* Status action buttons */
.action-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 8px; font-size: 0.78rem;
    font-weight: 700; cursor: pointer; border: 1px solid; transition: all .2s;
}
.action-pill:hover { transform: translateY(-1px); }
.pill-prepare  { background: rgba(243,156,18,.15); color: #f39c12; border-color: rgba(243,156,18,.3); }
.pill-done     { background: rgba(34,197,94,.15);  color: #22c55e; border-color: rgba(34,197,94,.3); }
.pill-cancel   { background: rgba(239,68,68,.1);   color: #ef4444; border-color: rgba(239,68,68,.25); }

/* Rider confirmation modal */
.confirm-overlay {
    position: fixed; inset: 0; z-index: 10000;
    background: rgba(0,0,0,0.68); backdrop-filter: blur(3px);
    display: none; align-items: center; justify-content: center;
}
.confirm-modal {
    width: 420px; max-width: 92vw;
    background: #1a1a2e;
    border: 1px solid rgba(194,38,38,0.35);
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(0,0,0,0.5);
    padding: 24px;
}
.confirm-icon {
    width: 52px; height: 52px; border-radius: 12px;
    display: grid; place-items: center;
    background: rgba(194,38,38,0.16);
    color: #ef4444; font-size: 1.1rem;
    margin-bottom: 14px;
}
.confirm-title {
    margin: 0 0 8px; color: #fff; font-size: 1.05rem; font-weight: 800;
}
.confirm-text {
    margin: 0; color: rgba(255,255,255,0.62); font-size: 0.86rem; line-height: 1.5;
}
.confirm-order-pill {
    display: inline-flex; margin-top: 12px;
    padding: 6px 10px; border-radius: 8px;
    background: rgba(255,255,255,0.06); color: #fff;
    border: 1px solid rgba(255,255,255,0.12); font-size: 0.78rem; font-weight: 700;
}
.confirm-actions {
    display: flex; gap: 10px; margin-top: 20px;
}
.confirm-btn {
    flex: 1; border-radius: 8px; font-size: 0.88rem; font-weight: 700;
    padding: 10px 12px; cursor: pointer; border: 1px solid transparent;
}
.confirm-btn-cancel {
    background: rgba(255,255,255,0.06); color: #fff;
    border-color: rgba(255,255,255,0.14);
}
.confirm-btn-send {
    background: linear-gradient(135deg,#c22626,#8b0a1e);
    color: #fff; border: none;
}
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
      <span class="nav-item locked"><i class="fa-solid fa-shield-halved"></i> Security &amp; Logs</span>
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
          <p>Prepare incoming orders and dispatch them to riders.</p>
        </div>
        <span class="permission-note"><i class="fa-solid fa-lock"></i> No delete or refund access</span>
      </div>

      <?php if ($errorMsg): ?>
        <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value"><?= $stats['pending_orders'] ?></div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change <?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-down"></i> <?= $stats['pending_orders'] > 0 ? 'needs attention' : 'all clear' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-fire-burner"></i></div>
          <div class="stat-card-value"><?= $stats['processing_orders'] ?></div>
          <div class="stat-card-label">Being Prepared</div>
          <div class="stat-card-change up"><i class="fa-solid fa-spinner"></i> in kitchen</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-motorcycle"></i></div>
          <div class="stat-card-value"><?php
            try { echo $pdo->query("SELECT COUNT(*) FROM orders WHERE status='confirmed'")->fetchColumn(); }
            catch(\Throwable $_) { echo '0'; }
          ?></div>
          <div class="stat-card-label">Ready for Rider</div>
          <div class="stat-card-change up"><i class="fa-solid fa-check"></i> awaiting pickup</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-truck-fast"></i></div>
          <div class="stat-card-value"><?= $stats['total_orders_today'] ?></div>
          <div class="stat-card-label">Orders Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-clock"></i> today</div>
        </div>
      </div>

      <!-- FILTER TABS -->
      <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <a href="staff-orders.php" class="btn btn-outline btn-sm <?= !$filter ? 'btn-primary' : '' ?>">All</a>
        <a href="staff-orders.php?filter=pending" class="btn btn-outline btn-sm <?= $filter==='pending' ? 'btn-primary' : '' ?>">Pending</a>
        <a href="staff-orders.php?filter=processing" class="btn btn-outline btn-sm <?= $filter==='processing' ? 'btn-primary' : '' ?>">Processing</a>
        <a href="staff-orders.php?filter=confirmed" class="btn btn-outline btn-sm <?= $filter==='confirmed' ? 'btn-primary' : '' ?>">Ready for Rider</a>
        <a href="staff-orders.php?filter=shipped" class="btn btn-outline btn-sm <?= $filter==='shipped' ? 'btn-primary' : '' ?>">Shipped</a>
        <a href="staff-orders.php?filter=delivered" class="btn btn-outline btn-sm <?= $filter==='delivered' ? 'btn-primary' : '' ?>">Delivered</a>
      </div>

      <!-- ORDERS TABLE -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Orders
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
              <th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Status / ETA</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php if (empty($orders)): ?>
              <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--muted);">
                <i class="fa-solid fa-bag-shopping" style="font-size:1.8rem;display:block;margin-bottom:8px;"></i>
                No orders found<?= $filter ? ' with status "' . htmlspecialchars($filter) . '"' : '' ?>.
              </td></tr>
              <?php else: ?>
              <?php foreach ($orders as $o):
                // Parse items from either orders.items JSON column or notes->items payload.
                $decoded  = json_decode($o['items_json'] ?? '[]', true) ?: [];
                $notes    = json_decode($o['notes'] ?? '{}', true) ?: [];
                $items    = is_array($decoded) ? $decoded : [];

                if (isset($decoded['items']) && is_array($decoded['items'])) {
                    $items = $decoded['items'];
                } elseif (isset($notes['items']) && is_array($notes['items'])) {
                    $items = $notes['items'];
                }

                
                $itemsText = implode(', ', array_map(fn($i) => ($i['name'] ?? 'Item') . (($i['quantity'] ?? 1) > 1 ? ' x'.$i['quantity'] : ''), $items));
                if (!$itemsText) $itemsText = '—';
              ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-<?= str_pad($o['id'], 4, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($o['customer_name'], 0, 2)) ?></div>
                    <div>
                      <?= htmlspecialchars($o['customer_name']) ?>
                      <div style="font-size:0.72rem;color:var(--muted);"><?= htmlspecialchars($o['customer_email']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="max-width:180px;font-size:0.8rem;"><?= htmlspecialchars($itemsText) ?></td>
                <td><?= peso((float)$o['total']) ?></td>
                <td>
                  <?= statusBadge($o['status']) ?>
                  <?php if ($o['status'] === 'processing' && !empty($o['eta'])): ?>
                    <div style="font-size:0.7rem;color:#f39c12;margin-top:4px;"><i class="fa-solid fa-clock"></i> ETA: <?= htmlspecialchars($o['eta']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                <td>
                  <div class="flex-gap" style="flex-wrap:wrap;gap:6px;">
                    <button class="action-btn" title="View Details" onclick='openViewModal(<?= htmlspecialchars(json_encode($o), ENT_QUOTES, "UTF-8") ?>)'>
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <?php if ($o['status'] === 'pending'): ?>
                      <button class="action-pill pill-prepare" onclick="openPrepareModal(<?= $o['id'] ?>)">
                        <i class="fa-solid fa-fire-burner"></i> Prepare
                      </button>
                      <button class="action-pill pill-cancel" onclick="cancelOrder(<?= $o['id'] ?>)">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    <?php elseif ($o['status'] === 'processing'): ?>
                      <button class="action-pill pill-done" onclick="completeOrder(<?= $o['id'] ?>)">
                        <i class="fa-solid fa-check-circle"></i> Done!
                      </button>
                    <?php elseif ($o['status'] === 'confirmed'): ?>
                      <span style="color:#3b82f6;font-size:0.78rem;"><i class="fa-solid fa-motorcycle"></i> Sent to Rider</span>
                    <?php elseif ($o['status'] === 'shipped'): ?>
                      <span style="color:#a855f7;font-size:0.78rem;"><i class="fa-solid fa-truck"></i> Out for Delivery</span>
                    <?php elseif ($o['status'] === 'delivered'): ?>
                      <span style="color:#22c55e;font-size:0.78rem;"><i class="fa-solid fa-circle-check"></i> Completed</span>
                    <?php elseif ($o['status'] === 'cancelled'): ?>
                      <span style="color:var(--muted);font-size:0.78rem;">Cancelled</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--line-w);font-size:0.75rem;color:var(--muted);">
          <i class="fa-solid fa-circle-info" style="margin-right:5px;color:#f39c12;"></i>
          Workflow: <strong style="color:rgba(255,255,255,0.5);">Pending → Prepare (set ETA) → Processing → Done! → Rider</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SEND TO RIDER CONFIRMATION MODAL -->
<div id="sendRiderConfirm" class="confirm-overlay">
  <div class="confirm-modal">
    <div class="confirm-icon"><i class="fa-solid fa-motorcycle"></i></div>
    <h3 class="confirm-title">Send this order to rider?</h3>
    <p class="confirm-text">This will mark the order as <strong style="color:#ef4444;">Ready for Rider</strong> and make it visible on the rider orders screen.</p>
    <span class="confirm-order-pill" id="sendRiderOrderLabel">Order #ORD-0000</span>
    <div class="confirm-actions">
      <button class="confirm-btn confirm-btn-cancel" onclick="closeSendRiderConfirm()">Cancel</button>
      <button class="confirm-btn confirm-btn-send" onclick="submitSendToRider()"><i class="fa-solid fa-paper-plane"></i> Send to Rider</button>
    </div>
  </div>
</div>

<!-- ETA MODAL -->
<div id="etaModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#1a1a2e;border:1px solid rgba(243,156,18,0.3);border-radius:16px;padding:32px;width:380px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
    <h3 style="margin:0 0 6px;color:#fff;font-size:1.1rem;"><i class="fa-solid fa-fire-burner" style="color:#f39c12;margin-right:8px;"></i>Start Preparing Order</h3>
    <p style="color:rgba(255,255,255,0.5);font-size:0.82rem;margin:0 0 22px;">Set an estimated preparation time.</p>
    <input type="hidden" id="etaOrderId">
    <label style="font-size:0.8rem;color:rgba(255,255,255,0.6);display:block;margin-bottom:8px;">Estimated Time</label>
    <select id="etaSelect" style="width:100%;padding:10px 14px;background:#0f0f1a;border:1px solid rgba(255,255,255,0.15);border-radius:8px;color:#fff;font-size:0.9rem;margin-bottom:8px;">
      <option value="10-15 mins">10–15 minutes</option>
      <option value="15-20 mins">15–20 minutes</option>
      <option value="20-30 mins" selected>20–30 minutes</option>
      <option value="30-45 mins">30–45 minutes</option>
      <option value="45-60 mins">45–60 minutes</option>
      <option value="custom">Custom…</option>
    </select>
    <input type="text" id="etaCustom" placeholder="e.g. 25 mins" style="width:100%;padding:10px 14px;background:#0f0f1a;border:1px solid rgba(255,255,255,0.15);border-radius:8px;color:#fff;font-size:0.9rem;margin-bottom:20px;display:none;box-sizing:border-box;">
    <div style="display:flex;gap:10px;">
      <button onclick="closeEtaModal()" style="flex:1;padding:10px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:#fff;cursor:pointer;font-size:0.9rem;">Cancel</button>
      <button onclick="submitPrepare()" style="flex:2;padding:10px;background:linear-gradient(135deg,#f39c12,#e67e22);border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:0.9rem;font-weight:600;"><i class="fa-solid fa-fire-burner"></i> Start Preparing</button>
    </div>
  </div>
</div>

<!-- VIEW ORDER MODAL -->
<div id="viewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
  <div style="background:#1a1a2e;border:1px solid rgba(52,152,219,0.3);border-radius:16px;padding:32px;width:550px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:15px;">
        <h3 style="margin:0;color:#fff;font-size:1.2rem;"><i class="fa-solid fa-file-invoice" style="color:#3498db;margin-right:8px;"></i>Order Details <span id="viewOrderIdLabel" style="color:var(--red);margin-left:10px;"></span></h3>
        <button onclick="closeViewModal()" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1.2rem;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <div id="viewOrderContent" style="color:rgba(255,255,255,0.9);font-size:0.9rem;">
        <!-- Content injected by JS -->
    </div>

    <div style="margin-top:30px;display:flex;justify-content:flex-end;gap:12px;">
      <button onclick="window.print()" style="padding:10px 18px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;cursor:pointer;font-size:0.85rem;">
        <i class="fa-solid fa-print" style="margin-right:5px;"></i> Print
      </button>
      <button onclick="closeViewModal()" style="padding:10px 24px;background:linear-gradient(135deg,#334155,#1e293b);border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:0.9rem;font-weight:600;">
        Close
      </button>
    </div>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
let pendingCompleteOrderId = null;

// ── ETA Modal ──
function openPrepareModal(orderId) {
  document.getElementById('etaOrderId').value = orderId;
  document.getElementById('etaSelect').value = '20-30 mins';
  document.getElementById('etaCustom').style.display = 'none';
  document.getElementById('etaModal').style.display = 'flex';
}
function closeEtaModal() {
  document.getElementById('etaModal').style.display = 'none';
}
document.getElementById('etaSelect').addEventListener('change', function() {
  document.getElementById('etaCustom').style.display = this.value === 'custom' ? 'block' : 'none';
});
function submitPrepare() {
  const orderId = document.getElementById('etaOrderId').value;
  const sel = document.getElementById('etaSelect');
  const eta = sel.value === 'custom' ? document.getElementById('etaCustom').value.trim() : sel.value;
  if (!eta) { alert('Please enter an estimated time.'); return; }
  const fd = new FormData();
  fd.append('action', 'prepare_order');
  fd.append('order_id', orderId);
  fd.append('eta', eta);
  fetch('staff-orders.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) { closeEtaModal(); showToast('🍳 Order is being prepared!'); setTimeout(() => location.reload(), 1200); }
      else alert('Error: ' + d.message);
    });
}

// ── Done Preparing ──
function completeOrder(orderId) {
  pendingCompleteOrderId = orderId;
  const label = '#ORD-' + String(orderId).padStart(4, '0');
  document.getElementById('sendRiderOrderLabel').textContent = 'Order ' + label;
  document.getElementById('sendRiderConfirm').style.display = 'flex';
}

function closeSendRiderConfirm() {
  pendingCompleteOrderId = null;
  document.getElementById('sendRiderConfirm').style.display = 'none';
}

function submitSendToRider() {
  if (!pendingCompleteOrderId) return;
  const fd = new FormData();
  fd.append('action', 'complete_order');
  fd.append('order_id', pendingCompleteOrderId);
  fetch('staff-orders.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        closeSendRiderConfirm();
        showToast('🏍️ Order sent to rider!');
        setTimeout(() => location.reload(), 1200);
      }
      else alert('Error: ' + d.message);
    });
}

// ── Cancel ──
function cancelOrder(orderId) {
  if (!confirm('Cancel this order?')) return;
  const fd = new FormData();
  fd.append('action', 'cancel_order');
  fd.append('order_id', orderId);
  fetch('staff-orders.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) { showToast('Order cancelled.'); setTimeout(() => location.reload(), 1000); }
      else alert('Error: ' + d.message);
    });
}

// ── View Details Modal ──
function openViewModal(order) {
    const modal = document.getElementById('viewModal');
    document.getElementById('viewOrderIdLabel').textContent = '#ORD-' + order.id.toString().padStart(4, '0');
    
    // Use orders.items (option 1) from the staff row payload
    // items_json can be stored as JSON array OR as an object wrapper
    let parsed = [];
    try {
        parsed = JSON.parse(order.items_json || '[]');
    } catch (e) {
        parsed = [];
    }

    // If items are wrapped like {"items": [...]}
    const wrapped = (parsed && parsed.items) ? parsed.items : parsed;
    const safeItems = Array.isArray(wrapped) ? wrapped : [];

    let itemsHtml = safeItems.map(item => `
        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
            <span>${item.name} <span style="color:rgba(255,255,255,0.4);">x${item.quantity || 1}</span></span>
            <span style="font-weight:600;">₱${parseFloat(item.price || 0).toLocaleString()}</span>
        </div>
    `).join('');

    const content = `
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
            <div>
                <p style="margin:0 0 5px; color:rgba(255,255,255,0.4); font-size:0.75rem; text-transform:uppercase;">Customer</p>
                <p style="margin:0; font-weight:700;">${order.customer_name}</p>
                <p style="margin:2px 0 0; font-size:0.8rem; color:rgba(255,255,255,0.6);">${order.customer_email}</p>
            </div>
            <div>
                <p style="margin:0 0 5px; color:rgba(255,255,255,0.4); font-size:0.75rem; text-transform:uppercase;">Payment Method</p>
                <p style="margin:0; font-weight:700;">${order.payment_method.toUpperCase()}</p>
            </div>
        </div>
        <div style="margin-bottom:20px;">
            <p style="margin:0 0 5px; color:rgba(255,255,255,0.4); font-size:0.75rem; text-transform:uppercase;">Delivery Address</p>
            <p style="margin:0; font-style:italic; line-height:1.4;">${order.address}</p>
        </div>
        <div style="background:rgba(255,255,255,0.03); border-radius:10px; padding:15px; border:1px solid rgba(255,255,255,0.05);">
            <p style="margin:0 0 10px; color:#3498db; font-size:0.75rem; font-weight:800; text-transform:uppercase;">Items Summary</p>
            ${itemsHtml || '<p style="color:rgba(255,255,255,0.4);">No items found</p>'}
            <div style="display:flex; justify-content:space-between; margin-top:15px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.1);">
                <span style="font-weight:700; color:#fff;">Total Amount</span>
                <span style="font-weight:800; color:var(--red); font-size:1.1rem;">₱${parseFloat(order.total).toLocaleString()}</span>
            </div>
        </div>
        ${order.eta ? `
            <div style="margin-top:15px; padding:10px; background:rgba(243,156,18,0.1); border-left:3px solid #f39c12; border-radius:4px;">
                <span style="font-size:0.8rem; color:#f39c12;"><i class="fa-solid fa-clock"></i> Current ETA: <strong>${order.eta}</strong></span>
            </div>
        ` : ''}
    `;

    document.getElementById('viewOrderContent').innerHTML = content;
    modal.style.display = 'flex';
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// ── Toast ──
function showToast(msg) {
  const t = document.createElement('div');
  t.textContent = msg;
  t.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:99999;padding:12px 22px;border-radius:10px;font-size:0.9rem;font-weight:600;color:#fff;background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 8px 30px rgba(0,0,0,0.4);';
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ── Auto-Refresh for Incoming Orders ──
// Refresh the page every 30 seconds to show new orders automatically
setInterval(() => {
    // Only refresh if no modal is open to avoid interrupting the staff
    const etaModal = document.getElementById('etaModal');
    const viewModal = document.getElementById('viewModal');
    if (etaModal.style.display === 'none' && viewModal.style.display === 'none') {
        location.reload();
    }
}, 30000);
</script>
</body>
</html>
