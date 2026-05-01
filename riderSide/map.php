<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — Map</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="map.css">
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
    <div class="map-container">
      <div class="map-grid"></div>

      <!-- Roads -->
      <div class="map-road-h" style="top:30%;height:18px"></div>
      <div class="map-road-h" style="top:55%;height:14px"></div>
      <div class="map-road-h" style="top:75%;height:10px"></div>
      <div class="map-road-v" style="left:35%;width:16px"></div>
      <div class="map-road-v" style="left:65%;width:12px"></div>
      <div class="map-road-v" style="left:20%;width:10px"></div>

      <!-- Blocks -->
      <div class="map-block" style="top:8%;left:8%;width:22%;height:20%"></div>
      <div class="map-block" style="top:8%;left:42%;width:18%;height:20%"></div>
      <div class="map-block" style="top:8%;left:72%;width:20%;height:20%"></div>
      <div class="map-block" style="top:38%;left:8%;width:20%;height:14%"></div>
      <div class="map-block" style="top:38%;left:42%;width:18%;height:14%"></div>
      <div class="map-block" style="top:38%;left:72%;width:20%;height:14%"></div>
      <div class="map-block" style="top:62%;left:8%;width:22%;height:10%"></div>
      <div class="map-block" style="top:62%;left:42%;width:18%;height:10%"></div>
      <div class="map-block" style="top:62%;left:72%;width:20%;height:10%"></div>

      <div class="route-line"></div>

      <!-- Rider pin -->
      <div class="rider-pin">
        <div class="rider-pin-pulse"></div>
        <div class="rider-pin-inner"><i class="fas fa-motorcycle"></i></div>
      </div>

      <!-- Destination pin -->
      <div class="dest-pin">
        <div class="dest-pin-inner"><i class="fas fa-home"></i></div>
      </div>

      <!-- Map controls -->
      <div class="map-controls">
        <button class="map-ctrl-btn"><i class="fas fa-plus"></i></button>
        <button class="map-ctrl-btn"><i class="fas fa-minus"></i></button>
        <button class="map-ctrl-btn"><i class="fas fa-crosshairs"></i></button>
        <button class="map-ctrl-btn"><i class="fas fa-layer-group"></i></button>
      </div>

      <!-- Map labels -->
      <div style="position:absolute;top:22%;left:36%;font-size:.6rem;color:rgba(255,255,255,.5);font-weight:600;z-index:6">Mabini St.</div>
      <div style="position:absolute;top:48%;left:12%;font-size:.6rem;color:rgba(255,255,255,.5);font-weight:600;z-index:6">Rizal Ave.</div>
      <div style="position:absolute;top:24%;left:67%;background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.4);border-radius:6px;padding:2px 6px;font-size:.62rem;color:#4ade80;font-weight:700;z-index:8">Destination</div>
      <div style="position:absolute;top:52%;left:44%;background:rgba(194,38,38,.2);border:1px solid rgba(194,38,38,.4);border-radius:6px;padding:2px 6px;font-size:.62rem;color:#ff8080;font-weight:700;z-index:8">You</div>
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
<script src="map.js"></script>
</body>
</html>