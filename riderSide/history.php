<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['rider_id'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — History</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="history.css">
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

  <!-- HISTORY PAGE -->
  <div class="page-content">
    <div class="history-hero">
      <div class="section-title">Delivery History</div>
      <div class="section-sub">Your completed deliveries</div>
      <div class="earnings-card">
        <div class="earning-item">
          <div class="earning-val">47</div>
          <div class="earning-lbl">Deliveries</div>
        </div>
        <div class="earning-item">
          <div class="earning-val" style="color:#fbbf24">4.9 ⭐</div>
          <div class="earning-lbl">Avg Rating</div>
        </div>
      </div>
    </div>

    <!-- SEARCH BAR -->
    <div class="history-search-wrap">
      <div class="search-input-inner">
        <i class="fas fa-search"></i>
        <input type="text" id="historySearch" placeholder="Search customer, address, or order #..." oninput="handleHistorySearch()">
      </div>
    </div>

    <div class="history-body">
      <!-- Dynamically populated by history.js -->
      <div style="text-align:center;padding:40px;opacity:0.5;">
        <i class="fas fa-spinner fa-spin" style="font-size:2rem;margin-bottom:15px;"></i>
        <div>Loading history...</div>
      </div>
    </div>
  </div>

  <!-- ORDER DETAILS MODAL -->
  <div class="history-modal" id="historyModal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="modalOrderNum">#ORD-0000</div>
        <button class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="modalBody">
        <!-- Content injected by JS -->
      </div>
    </div>
  </div>

  <!-- Bottom Nav -->
  <div class="bottom-nav">
    <button class="nav-item" onclick="window.location.href='orders.php'">
      <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </button>
    <button class="nav-item" onclick="window.location.href='map.php'">
      <i class="fas fa-map-marked-alt"></i><span>Map</span>
    </button>
    <button class="nav-item" onclick="window.location.href='chat.php'">
      <div class="nav-dot" id="chat-badge"></div>
      <i class="fas fa-comment-dots"></i><span>Chat</span>
    </button>
    <button class="nav-item active">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item" onclick="window.location.href='account.php'">
      <i class="fas fa-user-circle"></i><span>Account</span>
    </button>
  </div>

</div>

<div class="toast" id="toast"></div>
<script src="theme-manager.js"></script>
<script src="history.js?v=<?= time() ?>"></script>
</body>
</html>