<?php
require_once 'admin-config.php';
require_once 'Notifications.php';
requireAdmin();

// Initialize notifications
$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// Handle order actions from frontend/admin UI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    if (empty($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
        exit;
    }
    
    if ($data['action'] === 'create_order') {
        // Create orders table if it doesn't exist
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                items JSON NOT NULL,
                address TEXT NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                payment_details JSON,
                subtotal DECIMAL(10,2) NOT NULL,
                shipping DECIMAL(10,2) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                user_email VARCHAR(255),
                user_name VARCHAR(255),
                status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($createTableSQL);
        
        $items = $data['items'] ?? [];
        $address = $data['address'] ?? '';
        $paymentMethod = $data['paymentMethod'] ?? '';
        $paymentDetails = $data['paymentDetails'] ?? [];
        $subtotal = $data['subtotal'] ?? 0;
        $shipping = $data['shipping'] ?? 0;
        $total = $data['total'] ?? 0;
        $userEmail = $data['userEmail'] ?? '';
        $userName = $data['userName'] ?? '';
        
        // Validate required fields
        if (empty($items) || empty($address) || empty($paymentMethod)) {
            echo json_encode(['success' => false, 'message' => 'Missing required order information']);
            exit;
        }
        
        // Insert order into database
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                items, address, payment_method, payment_details, 
                subtotal, shipping, total, user_email, user_name, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        try {
            $stmt->execute([
                json_encode($items), $address, $paymentMethod, json_encode($paymentDetails),
                $subtotal, $shipping, $total, $userEmail, $userName
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Get user ID for customer notification
            $userId = null;
            if (!empty($userEmail)) {
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $userStmt->execute([$userEmail]);
                $userData = $userStmt->fetch();
                $userId = $userData['id'] ?? null;
            }
            
            // Create comprehensive notifications for new order
            $notifications = new Notifications($pdo);
            $notifications->autoNotify('new_order', [
                'id' => $orderId,
                'customer_name' => $userName,
                'customer_email' => $userEmail,
                'total' => $total,
                'items_count' => count($items),
                'payment_method' => $paymentMethod,
                'user_id' => $userId
            ]);
            
            // Audit log
            logAdminActivity($pdo, 'order_created', "Created order #{$orderId} for {$userName} ({$userEmail}) with total ₱{$total}");

            echo json_encode(['success' => true, 'message' => 'Order created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($data['action'] === 'update_status') {
        $orderId = (int)($data['order_id'] ?? 0);
        $status = $data['status'] ?? '';
        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

        if ($orderId <= 0 || !in_array($status, $allowedStatuses, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid order or status']);
            exit;
        }

        try {
            $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                exit;
            }

            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);

            $userId = null;
            if (!empty($order['user_email'])) {
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $userStmt->execute([$order['user_email']]);
                $userData = $userStmt->fetch();
                $userId = $userData['id'] ?? null;
            }

            if ($userId) {
                $notifications->create(
                    'Order Status Updated',
                    "Order #{$orderId} is now " . ucfirst($status) . ".",
                    $status === 'cancelled' ? 'error' : 'order',
                    'customer',
                    (int)$userId
                );
            }

            $notifications->create(
                'Order Status Updated',
                "Admin updated order #{$orderId} to " . ucfirst($status) . ".",
                'order',
                'admin'
            );

            logAdminActivity($pdo, 'order_status_updated', "Updated order #{$orderId} status to {$status}");
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Get orders for display
$orders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders ORDER BY created_at DESC");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist, create it
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            items JSON NOT NULL,
            address TEXT NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_details JSON,
            subtotal DECIMAL(10,2) NOT NULL,
            shipping DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            user_email VARCHAR(255),
            user_name VARCHAR(255),
            status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableSQL);
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lukes-seafood-transactions-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order ID', 'Customer', 'Email', 'Items', 'Subtotal', 'Shipping', 'Total', 'Payment Method', 'Status', 'Date']);
    foreach ($orders as $order) {
        $items = json_decode($order['items'] ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }
        $itemsText = implode('; ', array_map(function ($item) {
            $name = $item['name'] ?? 'Item';
            $quantity = (int)($item['quantity'] ?? 1);
            return $name . ' x' . $quantity;
        }, $items));
        fputcsv($out, [
            '#ORD-' . str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT),
            $order['user_name'] ?: 'Guest',
            $order['user_email'] ?: '',
            $itemsText,
            $order['subtotal'],
            $order['shipping'],
            $order['total'],
            $order['payment_method'],
            ucfirst($order['status']),
            $order['created_at'],
        ]);
    }
    fclose($out);
    exit;
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
<style>
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
      <a href="admin-orders.php" class="nav-item active"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
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
        <h1>Order Management</h1>
        <p>View and manage customer orders.</p>
      </div>

      <?php
      $orderStats = [
        'pending' => 0,
        'revenue_month' => 0,
        'revenue_last_month' => 0,
        'total' => 0,
        'month_orders' => 0,
        'delivered' => 0
      ];
      try {
        $orderStats['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
      } catch (\Throwable $_) {}
      try {
        $rev = $pdo->query("SELECT SUM(total) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status != 'cancelled'")->fetchColumn();
        $orderStats['revenue_month'] = (float)($rev ?? 0);
      } catch (\Throwable $_) {}
      try {
        $rev = $pdo->query("SELECT SUM(total) FROM orders WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND status != 'cancelled'")->fetchColumn();
        $orderStats['revenue_last_month'] = (float)($rev ?? 0);
      } catch (\Throwable $_) {}
      try {
        $orderStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
      } catch (\Throwable $_) {}
      try {
        $orderStats['month_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();
      } catch (\Throwable $_) {}
      try {
        $orderStats['delivered'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
      } catch (\Throwable $_) {}
      
      $revenueChange = 0;
      if ($orderStats['revenue_last_month'] > 0) {
        $revenueChange = (($orderStats['revenue_month'] - $orderStats['revenue_last_month']) / $orderStats['revenue_last_month']) * 100;
      }
      $avgOrderValue = $orderStats['month_orders'] > 0 ? $orderStats['revenue_month'] / $orderStats['month_orders'] : 0;
      ?>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value"><?= number_format($orderStats['pending']) ?></div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change <?= $orderStats['pending'] > 0 ? 'down' : 'up' ?>"><i class="fa-solid fa-arrow-<?= $orderStats['pending'] > 0 ? 'down' : 'up' ?>"></i> <?= $orderStats['pending'] > 0 ? 'needs attention' : 'all clear' ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="stat-card-value"><?= $orderStats['revenue_month'] >= 1000 ? '₱' . number_format($orderStats['revenue_month'] / 1000, 1) . 'K' : '₱' . number_format($orderStats['revenue_month'], 0) ?></div>
          <div class="stat-card-label">Revenue This Month</div>
          <div class="stat-card-change <?= $revenueChange >= 0 ? 'up' : 'down' ?>"><i class="fa-solid fa-arrow-<?= $revenueChange >= 0 ? 'up' : 'down' ?>"></i> <?= $revenueChange >= 0 ? '+' : '' ?><?= number_format($revenueChange, 1) ?>% vs last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-receipt"></i></div>
          <div class="stat-card-value"><?= number_format($orderStats['total']) ?></div>
          <div class="stat-card-label">Total Transactions</div>
          <div class="stat-card-change up"><i class="fa-solid fa-file-lines"></i> report-ready</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-chart-line"></i></div>
          <div class="stat-card-value">₱<?= number_format($avgOrderValue, 0) ?></div>
          <div class="stat-card-label">Avg. Order Value</div>
          <div class="stat-card-change up"><i class="fa-solid fa-check"></i> <?= number_format($orderStats['delivered']) ?> delivered</div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Orders</span>
          <div class="flex-gap">
            <a href="admin-orders.php?export=csv" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-arrow-down"></i> Export Report</a>
            <a href="admin-dashboard.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
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
                    $items = json_decode($order['items'] ?? '[]', true);
                    if (!is_array($items)) {
                        $items = [];
                    }
                    $itemsList = array_map(function($item) {
                        $name = $item['name'] ?? 'Item';
                        $quantity = (int)($item['quantity'] ?? 1);
                        return $name . ($quantity > 1 ? ' x' . $quantity : '');
                    }, $items);
                    $itemsText = implode(', ', $itemsList);
                  ?>
                  <tr>
                    <td style="color:var(--red);font-weight:700;">#ORD-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                      <div class="flex-gap">
                        <?php $customerName = $order['user_name'] ?: 'Guest'; ?>
                        <div class="user-avatar"><?= strtoupper(substr($customerName, 0, 2)) ?></div>
                        <?= htmlspecialchars($customerName) ?>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($itemsText) ?></td>
                    <td><?= '₱' . number_format($order['total'], 2) ?></td>
                    <td>
                      <span class="badge badge-<?= $order['status'] === 'delivered' ? 'green' : ($order['status'] === 'cancelled' ? 'red' : ($order['status'] === 'shipped' ? 'blue' : 'yellow')) ?>">
                        <?= ucfirst($order['status']) ?>
                      </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                    <td>
                      <div class="flex-gap">
                        <select class="form-control" id="orderStatus<?= (int)$order['id'] ?>" style="width:auto;padding:7px 10px;font-size:0.78rem;">
                          <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $statusOption): ?>
                            <option value="<?= $statusOption ?>" <?= $order['status'] === $statusOption ? 'selected' : '' ?>><?= ucfirst($statusOption) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline btn-sm" title="Save Status" onclick="updateOrderStatus(<?= (int)$order['id'] ?>)">
                          <i class="fa-solid fa-floppy-disk"></i>
                        </button>
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

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

function updateOrderStatus(orderId) {
  const status = document.getElementById(`orderStatus${orderId}`).value;

  fetch('admin-orders.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'update_status',
      order_id: orderId,
      status
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Failed to update order status');
    }
  })
  .catch(() => alert('Failed to update order status'));
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
</script>
</body>
</html>
