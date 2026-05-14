<?php
require_once 'admin-config.php';
require_once '../activity-logger.php';
requireAdmin();

// Handle order creation from frontend (JSON body)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : null;

    if (isset($data['action']) && $data['action'] === 'create_order') {
        $items         = $data['items']          ?? [];
        $address       = $data['address']        ?? '';
        $paymentMethod = $data['paymentMethod']  ?? '';
        $paymentDetails= $data['paymentDetails'] ?? [];
        $subtotal      = $data['subtotal']       ?? 0;
        $shipping      = $data['shipping']       ?? 0;
        $total         = $data['total']          ?? 0;
        $userEmail     = $data['userEmail']      ?? '';
        $userName      = $data['userName']       ?? '';

        if (empty($items) || empty($address) || empty($paymentMethod)) {
            echo json_encode(['success' => false, 'message' => 'Missing required order information']);
            exit;
        }
        $orderNotes = json_encode([
            'items'           => $items,
            'payment_details' => $paymentDetails,
            'user_email'      => $userEmail,
            'user_name'       => $userName,
            'shipping'        => $shipping,
            'subtotal'        => $subtotal,
            'created_by_admin'=> true,
        ]);
        try {
            $pdo->prepare("
                INSERT INTO orders (user_id, status, total_amount, address, payment_method, notes, created_at, updated_at)
                VALUES (?, 'pending', ?, ?, ?, ?, NOW(), NOW())
            ")->execute([null, $total, $address, $paymentMethod, $orderNotes]);
            echo json_encode(['success' => true, 'message' => 'Order created successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Handle form-POST AJAX actions
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
            logActivity('order_preparing', "Admin started preparing order #{$orderId} — ETA: {$eta}", $_SESSION['admin_user_email'] ?? '', $_SESSION['admin_user_name'] ?? 'Admin');
            echo json_encode(['success' => true, 'message' => 'Order is now being prepared.']);
        } catch (PDOException $e) {
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
            logActivity('order_ready', "Order #{$orderId} marked as ready for rider dispatch", $_SESSION['admin_user_email'] ?? '', $_SESSION['admin_user_name'] ?? 'Admin');
            echo json_encode(['success' => true, 'message' => 'Order is ready for rider!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Generic status update (cancel, etc.) ──
    if ($_POST['action'] === 'update_order_status') {
        $orderId   = $_POST['order_id'] ?? '';
        $newStatus = $_POST['status']   ?? '';
        $validStatuses = ['pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        if (empty($orderId) || !in_array($newStatus, $validStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        try {
            $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $orderId]);
            logActivity('order_status_updated', "Admin updated order #{$orderId} to: {$newStatus}", $_SESSION['admin_user_email'] ?? '', $_SESSION['admin_user_name'] ?? 'Admin');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get orders for display
$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders ORDER BY created_at DESC");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // continue
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Order Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="admin-orders.css?v=<?= time() ?>">
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
      <a href="admin-orders.php" class="nav-item active"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
      <a href="admin-content.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Content Management</a>
      <a href="admin-promos.php" class="nav-item"><i class="fa-solid fa-ticket"></i> Promo Management</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-account.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="../index.php" class="logout-btn" style="background: #22c55e; color: #fff;"><i class="fa-solid fa-home"></i> Home</a>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Order Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Orders</div>
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
        <h1>Order Management</h1>
        <p>View and manage customer orders.</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value"><?= count(array_filter($orders, fn($o) => $o['status'] === 'pending')) ?></div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs attention</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="stat-card-value">₱<?php 
$revenueThisMonth = array_sum(array_map(function($o) { 
    return $o['total_amount'] ?? $o['total'] ?? 0; 
}, array_filter($orders, fn($o) => 
    date('Y-m', strtotime($o['created_at'])) === date('Y-m') && 
    $o['status'] === 'delivered'
)));

if ($revenueThisMonth >= 1000) {
    echo number_format($revenueThisMonth / 1000, 1) . 'K';
} else {
    echo number_format($revenueThisMonth, 2);
}
?></div>
          <div class="stat-card-label">Revenue This Month</div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Orders</span>
          <a href="admin-dashboard.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>ORDER ID</th>
                <th>CUSTOMER</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                  <?php 
                    // Parse order details from notes field
                    $orderDetails = json_decode($order['notes'], true) ?: [];
                    $items = $orderDetails['items'] ?? [];
                    $userName = $orderDetails['user_name'] ?? 'Guest';
                    $userEmail = $orderDetails['user_email'] ?? '';
                    
                    // Build items list text
                    $itemsList = array_map(function($item) {
                        return $item['name'] . ($item['quantity'] > 1 ? ' x' . $item['quantity'] : '');
                    }, $items);
                    $itemsText = implode(', ', $itemsList);
                    
                    // Use total_amount first, then total as fallback
                    $orderTotal = $order['total_amount'] ?? $order['total'] ?? 0;
                  ?>
                  <tr>
                    <td style="color:var(--red);font-weight:700;">#ORD-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                      <div class="flex-gap">
                        <div class="user-avatar"><?= strtoupper(substr($userName, 0, 2)) ?></div>
                        <div>
                          <?= htmlspecialchars($userName) ?>
                          <?php if ($userEmail): ?>
                            <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($userEmail) ?></div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($itemsText) ?></td>
                    <td><?= '₱' . number_format($orderTotal, 2) ?></td>
                    <td>
                      <?php 
$badgeClass = 'yellow'; // default
if ($order['status'] === 'delivered') $badgeClass = 'green';
elseif ($order['status'] === 'cancelled') $badgeClass = 'red';
elseif ($order['status'] === 'shipped' || $order['status'] === 'confirmed') $badgeClass = 'blue';
?>
<span class="badge badge-<?= $badgeClass ?>">
                        <?= ucfirst($order['status']) ?>
                      </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                    <td>
                      <div class="flex-gap">
                        <?php
                        $statusIcons = [
                          'pending'    => ['icon'=>'fa-hourglass-half',  'color'=>'#f39c12', 'label'=>'Pending'],
                          'processing' => ['icon'=>'fa-fire-burner',     'color'=>'#f97316', 'label'=>'Preparing'],
                          'confirmed'  => ['icon'=>'fa-motorcycle',      'color'=>'#3b82f6', 'label'=>'With Rider'],
                          'shipped'    => ['icon'=>'fa-truck',           'color'=>'#a855f7', 'label'=>'Out for Delivery'],
                          'delivered'  => ['icon'=>'fa-circle-check',   'color'=>'#22c55e', 'label'=>'Completed'],
                          'cancelled'  => ['icon'=>'fa-circle-xmark',   'color'=>'#6b7280', 'label'=>'Cancelled'],
                        ];
                        $si = $statusIcons[$order['status']] ?? ['icon'=>'fa-circle', 'color'=>'#6b7280', 'label'=>ucfirst($order['status'])];
                        ?>
                        <span style="color:<?= $si['color'] ?>;font-size:0.8rem;display:inline-flex;align-items:center;gap:5px;">
                          <i class="fa-solid <?= $si['icon'] ?>"></i> <?= $si['label'] ?>
                        </span>
                        <?php if (!empty($order['eta']) && $order['status'] === 'processing'): ?>
                          <span style="font-size:0.7rem;color:#f39c12;">· ETA: <?= htmlspecialchars($order['eta']) ?></span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">
                    <i class="fa-solid fa-bag-shopping" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    No orders yet. Orders will appear here once customers place them.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>






<script src="admin-orders.js?v=<?= time() ?>"></script>
</body>
</html>