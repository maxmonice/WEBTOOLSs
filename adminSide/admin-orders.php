<?php
require_once 'admin-config.php';
require_once '../activity-logger.php';
requireAdmin();

// Handle order creation from frontend (JSON body only — do not read php://input for form posts)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
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
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, status, total_amount, address, payment_method, notes, created_at, updated_at)
                VALUES (?, 'pending', ?, ?, ?, ?, NOW(), NOW())
            ")->execute([null, $total, $address, $paymentMethod, $orderNotes]);
            $orderId = $pdo->lastInsertId();

            if (!empty($items)) {
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
                foreach ($items as $item) {
                    $mid = $item['id'] ?? null;
                    if (!$mid) {
                        $f = $pdo->prepare("SELECT id FROM menu_items WHERE name = ? LIMIT 1");
                        $f->execute([$item['name']]);
                        $mid = $f->fetchColumn();
                    }
                    if ($mid) {
                        $itemStmt->execute([$orderId, $mid, $item['quantity'] ?? 1, $item['price'] ?? 0]);
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => 'Order created successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
        }
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
            logActivity('order_preparing', "Admin started preparing order #{$orderId} — ETA: {$eta}", (int)$_SESSION['user_id']);
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
            logActivity('order_ready', "Order #{$orderId} marked as ready for rider dispatch", (int)$_SESSION['user_id']);
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
            logActivity('order_status_updated', "Admin updated order #{$orderId} to: {$newStatus}", (int)$_SESSION['user_id']);
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
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch items for each order
    $itemStmt = $pdo->prepare("
        SELECT oi.*, mi.name as item_name 
        FROM order_items oi 
        JOIN menu_items mi ON oi.menu_item_id = mi.id 
        WHERE oi.order_id = ?
    ");
    foreach ($orders as &$o) {
        $itemStmt->execute([$o['id']]);
        $o['items_list'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
<?php $adminNavActive = 'orders'; require __DIR__ . '/admin-sidebar-nav.php'; ?>
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
        <?php require __DIR__ . '/admin-topbar-right.php'; ?>
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
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <a href="../report-download.php?type=orders" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-pdf"></i> Order Report</a>
            <a href="../report-download.php?type=sales" class="btn btn-outline btn-sm"><i class="fa-solid fa-chart-line"></i> Sales Report</a>
            <a href="../report-download.php?type=delivery" class="btn btn-outline btn-sm"><i class="fa-solid fa-truck-fast"></i> Delivery Report</a>
            <a href="admin-dashboard.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
          </div>
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
                    $orderDetails = json_decode($order['notes'], true) ?: [];
                    $userName = $order['customer_name'] ?? $orderDetails['user_name'] ?? 'Guest';
                    $userEmail = $order['customer_email'] ?? $orderDetails['user_email'] ?? '';
                    
                    // Build items list text
                    $itemsList = array_map(function($item) {
                        return $item['item_name'] . ($item['quantity'] > 1 ? ' x' . $item['quantity'] : '');
                    }, $order['items_list'] ?? []);
                    
                    if (empty($itemsList)) {
                        $decoded = json_decode($order['items'] ?? '[]', true) ?: [];
                        $notes   = json_decode($order['notes'] ?? '{}', true) ?: [];
                        $jsonItems = is_array($decoded) ? $decoded : [];
                        if (isset($decoded['items']) && is_array($decoded['items'])) {
                            $jsonItems = $decoded['items'];
                        } elseif (isset($notes['items']) && is_array($notes['items'])) {
                            $jsonItems = $notes['items'];
                        }
                        $itemsList = array_map(fn($i) => ($i['name'] ?? 'Item') . (($i['quantity'] ?? 1) > 1 ? ' x' . $i['quantity'] : ''), $jsonItems);
                    }
                    
                    $itemsText = !empty($itemsList) ? implode(', ', $itemsList) : 'No items';
                    
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
                        $badgeClass = 'yellow';
                        if ($order['status'] === 'delivered') {
                            $badgeClass = 'green';
                        } elseif ($order['status'] === 'cancelled') {
                            $badgeClass = 'red';
                        } elseif ($order['status'] === 'shipped' || $order['status'] === 'confirmed') {
                            $badgeClass = 'blue';
                        } elseif ($order['status'] === 'processing' || $order['status'] === 'preparing') {
                            $badgeClass = 'yellow';
                        }
                      ?>
                      <span class="badge badge-<?= $badgeClass ?>">
                        <?= ucfirst(htmlspecialchars($order['status'])) ?>
                      </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                    <td>
                      <div class="order-actions" style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;">
                        <?php
                        $statusIcons = [
                          'pending'    => ['icon'=>'fa-hourglass-half',  'color'=>'#f39c12', 'label'=>'Pending'],
                          'preparing'  => ['icon'=>'fa-fire-burner',     'color'=>'#f97316', 'label'=>'Preparing'],
                          'processing' => ['icon'=>'fa-fire-burner',     'color'=>'#f97316', 'label'=>'Preparing'],
                          'confirmed'  => ['icon'=>'fa-motorcycle',      'color'=>'#3b82f6', 'label'=>'With Rider'],
                          'shipped'    => ['icon'=>'fa-truck',           'color'=>'#a855f7', 'label'=>'Out for Delivery'],
                          'delivered'  => ['icon'=>'fa-circle-check',   'color'=>'#22c55e', 'label'=>'Completed'],
                          'cancelled'  => ['icon'=>'fa-circle-xmark',   'color'=>'#6b7280', 'label'=>'Cancelled'],
                        ];
                        $si = $statusIcons[$order['status']] ?? ['icon'=>'fa-circle', 'color'=>'#6b7280', 'label'=>ucfirst($order['status'])];
                        ?>
                        <span style="color:<?= $si['color'] ?>;font-size:0.78rem;display:inline-flex;align-items:center;gap:5px;">
                          <i class="fa-solid <?= $si['icon'] ?>"></i> <?= htmlspecialchars($si['label']) ?>
                        </span>
                        <?php if (!empty($order['eta']) && ($order['status'] === 'processing' || $order['status'] === 'preparing')): ?>
                          <span style="font-size:0.72rem;color:#f39c12;">ETA: <?= htmlspecialchars($order['eta']) ?></span>
                        <?php endif; ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;">
                          <?php if ($order['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="adminPrepareOrder(<?= (int)$order['id'] ?>)">Start preparing</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="adminSetOrderStatus(<?= (int)$order['id'] ?>,'cancelled')">Cancel</button>
                          <?php elseif ($order['status'] === 'processing' || $order['status'] === 'preparing'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="adminCompletePrepare(<?= (int)$order['id'] ?>)">Ready for rider</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="adminSetOrderStatus(<?= (int)$order['id'] ?>,'cancelled')">Cancel</button>
                          <?php elseif ($order['status'] === 'confirmed'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="adminSetOrderStatus(<?= (int)$order['id'] ?>,'shipped')">Out for delivery</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="adminSetOrderStatus(<?= (int)$order['id'] ?>,'cancelled')">Cancel</button>
                          <?php elseif ($order['status'] === 'shipped'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="adminSetOrderStatus(<?= (int)$order['id'] ?>,'delivered')">Mark delivered</button>
                          <?php else: ?>
                            <span style="font-size:0.75rem;color:var(--muted);">—</span>
                          <?php endif; ?>
                        </div>
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






<script defer src="admin-notifications.js?v=<?= time() ?>"></script>
<script src="admin-orders.js?v=<?= time() ?>"></script>
</body>
</html>


