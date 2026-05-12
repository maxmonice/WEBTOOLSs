<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['rider_id'])) {
    header('Location: login.php');
    exit;
}
$riderName = htmlspecialchars($_SESSION['rider_name'] ?? 'Rider');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — Orders</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="orders.css">
</head>
<body>
<div class="phone-shell">

<div class="app-header">
    <div class="header-left">
      <div class="app-logo">Luke's Seafood</div>
      <div class="app-subtitle">Rider Dashboard</div>
    </div>
    <div class="rider-badge"><div class="dot"></div>Online</div>
  </div>

  <!-- ORDERS PAGE -->
  <div class="page-content">
    <div class="orders-hero">
      <div class="hero-greeting" style="color:rgba(255,255,255,0.8)">Good day,</div>
      <div class="hero-name" style="color:#fff"><?= $riderName ?> 🏍️</div>
      <div class="hero-stats">
        <div class="stat-card"><div class="stat-val" id="stat-incoming" style="color:#fff">0</div><div class="stat-lbl" style="color:rgba(255,255,255,0.7)">Incoming</div></div>
        <div class="stat-card"><div class="stat-val" id="stat-today" style="color:#fff">0</div><div class="stat-lbl" style="color:rgba(255,255,255,0.7)">Today</div></div>
      </div>
    </div>

    <!-- Order list (populated by JS) -->
    <div id="orders-list"></div>

    <!-- Empty state (shown when no orders) -->
    <div class="empty-state" id="empty-state" style="display:none;">
      <i class="fa-solid fa-motorcycle"></i>
      <div class="empty-text">No incoming orders</div>
      <div class="empty-sub">Orders will appear here when they are ready</div>
    </div>

    <!-- Loading state -->
    <div id="loading-state" class="loading-orders">
      <i class="fa-solid fa-spinner fa-spin"></i>
      <span>Loading orders…</span>
    </div>
  </div>

  <!-- Bottom Nav -->
  <div class="bottom-nav">
    <button class="nav-item active">
      <div class="nav-badge" id="order-badge" style="display:none;">0</div>
      <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </button>
    <button class="nav-item" onclick="window.location.href='map.php'">
      <i class="fas fa-map-marked-alt"></i><span>Map</span>
    </button>
    <button class="nav-item" onclick="window.location.href='chat.php'">
      <div class="nav-dot" id="chat-badge"></div>
      <i class="fas fa-comment-dots"></i><span>Chat</span>
    </button>
    <button class="nav-item" onclick="window.location.href='history.php'">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item" onclick="window.location.href='account.php'">
      <i class="fas fa-user-circle"></i><span>Account</span>
    </button>
  </div>

</div>

<!-- ORDER DETAIL MODAL -->
<div class="modal-overlay" id="orderDetailModal" onclick="if(event.target===this)closeOrderDetail()">
  <div class="order-detail-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
      <div class="sheet-title" id="detailTitle">Order Details</div>
      <button class="sheet-close" onclick="closeOrderDetail()"><i class="fas fa-times"></i></button>
    </div>
    <div class="sheet-body">
      <div class="detail-section">
        <div class="detail-section-header"><i class="fas fa-user"></i> Customer Details</div>
        <div class="detail-row"><span class="detail-label">Name</span><span class="detail-val" id="detailName">—</span></div>
        <div class="detail-row"><span class="detail-label">Mobile</span><span class="detail-val" id="detailPhone">—</span></div>
        <div class="detail-row"><span class="detail-label">Payment</span><span class="detail-val" id="detailPayment">—</span></div>
      </div>
      <div class="detail-section">
        <div class="detail-section-header"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
        <div class="detail-row">
          <span class="detail-val" id="detailAddress" style="max-width:100%;text-align:left">—</span>
        </div>
      </div>
      <div class="detail-section">
        <div class="detail-section-header"><i class="fas fa-shopping-bag"></i> Order Items</div>
        <div id="detailItems"></div>
        <div class="detail-row total-row">
          <span class="detail-label">Total</span>
          <span class="detail-val" id="detailTotal">₱0.00</span>
        </div>
      </div>
      <div class="action-row">
        <button class="btn-secondary" onclick="closeOrderDetail()"><i class="fas fa-times"></i> Close</button>
        <button class="btn-primary" id="detailMainBtn" onclick="acceptFromDetail()">
          <i class="fas fa-check"></i> Accept Order
        </button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  // Pass rider session data to JS
  const RIDER_ID = <?= (int)$_SESSION['rider_id'] ?>;
</script>
<script src="theme-manager.js"></script>
<script src="orders.js?v=<?= time() ?>"></script>
</body>
</html>