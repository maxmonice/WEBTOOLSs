<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['rider_id'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — Map</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="map.css">
<style>
  #map-view { width: 100%; height: 100%; background: #1a1a2e; }
  .leaflet-routing-container { display: none !important; /* Hide default OSRM routing UI */ }
</style>
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

  <!-- MAP PAGE -->
  <div class="page-content map-page">
    <div class="map-container" style="padding:0;">
      <div id="map-view"></div>
    </div>

    <!-- Delivery info card -->
    <div class="delivery-card">
      <div class="delivery-card-top">
        <div>
          <div class="delivery-dest">Delivering to</div>
          <div class="delivery-addr">123 Mabini St., Brgy. Bagumbayan</div>
        </div>
        <div class="delivery-eta">
          <div class="eta-val">12</div>
          <div class="eta-lbl">mins</div>
        </div>
      </div>

      <div class="delivery-progress">
        <div class="progress-step">
          <div class="progress-dot done"></div>
          <div class="progress-lbl">Order<br>Accepted</div>
        </div>
        <div class="progress-line done"></div>
        <div class="progress-step">
          <div class="progress-dot active"></div>
          <div class="progress-lbl">On the<br>Way</div>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step">
          <div class="progress-dot"></div>
          <div class="progress-lbl">Delivered</div>
        </div>
      </div>

      <button class="delivered-btn" onclick="markDelivered()">
        <i class="fas fa-check-circle"></i> Mark as Delivered
      </button>
    </div>
  </div>

  <!-- Bottom Nav -->
  <div class="bottom-nav">
    <button class="nav-item" onclick="window.location.href='orders.php'">
      <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </button>
    <button class="nav-item active">
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

<div class="toast" id="toast"></div>

<!-- Leaflet, Routing, and Socket.io JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
<script src="http://localhost:3000/socket.io/socket.io.js"></script>

<script>
  // Pass active order data from backend if available (mocked for now, assumes rider accepted order 12345)
  // In a real flow, this comes from the active order session/db.
  const ACTIVE_ORDER_ID = localStorage.getItem('rider_active_order') || 12345;
  const DEST_LAT = 14.545; // Default Brgy Bagumbayan demo coords
  const DEST_LNG = 121.050;
</script>
<script src="map.js"></script>
</body>
</html>