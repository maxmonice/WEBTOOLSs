<?php
require_once 'admin-config.php';
requireAdmin();

// Handle order creation from frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
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
            
            echo json_encode(['success' => true, 'message' => 'Order created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
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
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i><span class="badge-dot"></span></div>
        <div class="admin-avatar">A</div>
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
          <div class="stat-card-value">87</div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs attention</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="stat-card-value">₱84.2K</div>
          <div class="stat-card-label">Revenue This Month</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +18% vs last month</div>
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
                <th>Order ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                  <?php 
                    $items = json_decode($order['items'], true);
                    $itemsList = array_map(function($item) {
                        return $item['name'] . ($item['quantity'] > 1 ? ' x' . $item['quantity'] : '');
                    }, $items);
                    $itemsText = implode(', ', $itemsList);
                  ?>
                  <tr>
                    <td style="color:var(--red);font-weight:700;">#ORD-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                      <div class="flex-gap">
                        <div class="user-avatar"><?= strtoupper(substr($order['user_name'], 0, 2)) ?></div>
                        <?= htmlspecialchars($order['user_name']) ?>
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
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">
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
</script>
</body>
</html>