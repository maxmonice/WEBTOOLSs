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
      <div class="hero-greeting">Good morning,</div>
      <div class="hero-name">Rider Marco 🏍️</div>
      <div class="hero-stats">
        <div class="stat-card"><div class="stat-val">0</div><div class="stat-lbl">Incoming</div></div>
        <div class="stat-card"><div class="stat-val">0</div><div class="stat-lbl">Today</div></div>
        <div class="stat-card"><div class="stat-val">₱0</div><div class="stat-lbl">Earned</div></div>
      </div>
    </div>

    <div class="empty-state">
      <i class="fa-solid fa-motorcycle" style="color: rgb(255, 255, 255);"></i>
      <div class="empty-text">No incoming orders</div>
    </div>


  </div>

  <!-- Bottom Nav -->
  <div class="bottom-nav">
    <button class="nav-item active">
      <div class="nav-badge" id="order-badge">3</div>
      <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </button>
    <button class="nav-item" onclick="window.location.href='map.php'">
      <i class="fas fa-map-marked-alt"></i><span>Map</span>
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
        <div class="detail-section-header"><i class="fas fa-box"></i> Order Items</div>
        <div id="detailItems"></div>
        <div class="detail-row" style="border-top:1px solid rgba(255,255,255,.08)">
          <span class="detail-label" style="font-weight:800;color:#fff">Total</span>
          <span class="detail-val" id="detailTotal" style="color:var(--red);font-size:1rem">—</span>
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

<script src="orders.js"></script>
</body>
</html>