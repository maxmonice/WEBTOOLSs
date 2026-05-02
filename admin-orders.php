<?php
require_once 'admin-config.php';
require_once 'activity-logger.php';
requireAdmin();

// Handle order creation from frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data['action'] === 'create_order') {
        
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
        
        // Insert order into database using existing table structure
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, status, total_amount, address, payment_method, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        // Store items and payment details in notes field as JSON
        $orderNotes = json_encode([
            'items' => $items,
            'payment_details' => $paymentDetails,
            'user_email' => $userEmail,
            'user_name' => $userName,
            'shipping' => $shipping,
            'subtotal' => $subtotal,
            'created_by_admin' => true
        ]);
        
        try {
            $stmt->execute([
                null, // user_id (null for admin-created orders)
                'pending',
                $total,
                $address,
                $paymentMethod,
                $orderNotes
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Order created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $orderId = $_POST['order_id'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    
    if (empty($orderId) || empty($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Missing order ID or status']);
        exit;
    }
    
    $validStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $newStatus]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatus, $orderId]);
        
        if ($result) {
            // Log order status update
            logActivity('order_status_updated', "Admin updated order #{$orderId} status to: {$newStatus}", $_SESSION['user_email'], $_SESSION['user_name']);
            
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
        }
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
    // Table already exists with different structure, just continue
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
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
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
                        <?php if ($order['status'] === 'pending'): ?>
                          <button class="action-btn edit" title="Mark as Confirmed" onclick="updateOrderStatus(<?= $order['id'] ?>, 'confirmed')">
                            <i class="fa-solid fa-check"></i>
                          </button>
                          <button class="action-btn" title="Cancel Order" onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancelled')">
                            <i class="fa-solid fa-xmark"></i>
                          </button>
                        <?php elseif ($order['status'] === 'confirmed'): ?>
                          <button class="action-btn edit" title="Mark as Shipped" onclick="updateOrderStatus(<?= $order['id'] ?>, 'shipped')">
                            <i class="fa-solid fa-truck"></i>
                          </button>
                          <button class="action-btn" title="Cancel Order" onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancelled')">
                            <i class="fa-solid fa-xmark"></i>
                          </button>
                        <?php elseif ($order['status'] === 'shipped'): ?>
                          <button class="action-btn edit" title="Mark as Delivered" onclick="updateOrderStatus(<?= $order['id'] ?>, 'delivered')">
                            <i class="fa-solid fa-check"></i>
                          </button>
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                          <span style="color: var(--muted); font-size: 0.8rem;">Cancelled</span>
                        <?php elseif ($order['status'] === 'delivered'): ?>
                          <span style="color: var(--green); font-size: 0.8rem;">Completed</span>
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

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

function updateOrderStatus(orderId, newStatus) {
    if (!confirm('Are you sure you want to update this order status to ' + newStatus + '?')) {
        return;
    }
    
    // Use form data instead of JSON
    const formData = new FormData();
    formData.append('action', 'update_order_status');
    formData.append('order_id', orderId);
    formData.append('status', newStatus);
    
    fetch('admin-orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message and reload page
            alert('Order status updated successfully!');
            location.reload();
        } else {
            alert('Failed to update order status: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update order status. Please try again.');
    });
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