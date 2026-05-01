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
          <div class="earning-val" style="color:#4ade80">₱3,420</div>
          <div class="earning-lbl">This Week</div>
        </div>
        <div class="earning-item">
          <div class="earning-val">₱12,860</div>
          <div class="earning-lbl">This Month</div>
        </div>
        <div class="earning-divider"></div>
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

    <div class="history-body">
      <div class="history-date-label">Today</div>

      <div class="history-card" onclick="viewDelivery('ORD-2830')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info">
          <div class="history-name">Ana Gabutin — #ORD-2830</div>
          <div class="history-meta">3 items · Brgy. Batasan Hills</div>
        </div>
        <div class="history-right">
          <div class="history-amount">+₱420</div>
          <div class="history-time">2:30 PM</div>
        </div>
      </div>

      <div class="history-card" onclick="viewDelivery('ORD-2825')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info">
          <div class="history-name">Benz Cruz — #ORD-2825</div>
          <div class="history-meta">2 items · Brgy. Payatas</div>
        </div>
        <div class="history-right">
          <div class="history-amount">+₱280</div>
          <div class="history-time">1:15 PM</div>
        </div>
      </div>

      <div class="history-date-label">Yesterday</div>

      <div class="history-card" onclick="viewDelivery('ORD-2818')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info">
          <div class="history-name">Clara Orias — #ORD-2818</div>
          <div class="history-meta">5 items · Brgy. Commonwealth</div>
        </div>
        <div class="history-right">
          <div class="history-amount">+₱875</div>
          <div class="history-time">6:45 PM</div>
        </div>
      </div>

      <div class="history-card" onclick="viewDelivery('ORD-2810')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info">
          <div class="history-name">Diego Tan — #ORD-2810</div>
          <div class="history-meta">1 item · Brgy. Novaliches</div>
        </div>
        <div class="history-right">
          <div class="history-amount">+₱195</div>
          <div class="history-time">4:20 PM</div>
        </div>
      </div>

      <div class="history-card" onclick="viewDelivery('ORD-2804')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info">
          <div class="history-name">Eva Manalo — #ORD-2804</div>
          <div class="history-meta">4 items · Brgy. Gulod</div>
        </div>
        <div class="history-right">
          <div class="history-amount">+₱560</div>
          <div class="history-time">11:00 AM</div>
        </div>
      </div>

      <div style="height:16px"></div>
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
    <button class="nav-item active">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item" onclick="window.location.href='account.php'">
      <i class="fas fa-user-circle"></i><span>Account</span>
    </button>
  </div>

</div>

<div class="toast" id="toast"></div>
<script src="history.js"></script>
</body>
</html>