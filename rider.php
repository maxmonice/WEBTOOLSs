<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — Rider</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="rider.css">
</head>
<body>
<div class="phone-shell">

  <!-- Status Bar -->
  <div class="status-bar">
    <span class="time">9:41</span>
    <div class="status-icons">
      <i class="fas fa-signal" style="font-size:.75rem"></i>
      <i class="fas fa-wifi" style="font-size:.75rem"></i>
      <i class="fas fa-battery-three-quarters" style="font-size:.75rem"></i>
    </div>
  </div>

  <!-- Header -->
  <div class="app-header">
    <div class="header-left">
      <div class="app-logo">Luke's Seafood</div>
      <div class="app-subtitle">Rider Dashboard</div>
    </div>
    <div class="rider-badge"><div class="dot"></div>Online</div>
  </div>

  <!-- ORDERS PAGE -->
  <div class="page active" id="page-orders">
    <div class="orders-hero">
      <div class="hero-greeting">Good morning,</div>
      <div class="hero-name">Rider Marco 🏍️</div>
      <div class="hero-stats">
        <div class="stat-card"><div class="stat-val">3</div><div class="stat-lbl">Incoming</div></div>
        <div class="stat-card"><div class="stat-val">12</div><div class="stat-lbl">Today</div></div>
        <div class="stat-card"><div class="stat-val">₱840</div><div class="stat-lbl">Earned</div></div>
      </div>
    </div>
    <div class="orders-body">
      <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterOrders(this,'all')">All Orders</button>
        <button class="filter-tab" onclick="filterOrders(this,'new')">New</button>
        <button class="filter-tab" onclick="filterOrders(this,'pickup')">For Pickup</button>
        <button class="filter-tab" onclick="filterOrders(this,'delivering')">On the Way</button>
      </div>

      <div class="order-card" data-status="new" onclick="openOrderDetail('ORD-2841','Juan dela Cruz','Blk 5 Lot 3 Sampaguita St., Barangay San Jose, Quezon City','Crazy Maki x2, Salmon Sashimi x1','₱585','GCash','0917 123 4567','new')">
        <div class="order-card-header">
          <div><div class="order-id">#ORD-2841</div><div class="order-name">Juan dela Cruz</div></div>
          <div class="order-status status-new">New</div>
        </div>
        <div class="order-card-body">
          <div class="order-info-row"><i class="fas fa-map-marker-alt"></i>Blk 5 Lot 3 Sampaguita St., Brgy. San Jose</div>
          <div class="order-info-row"><i class="fas fa-box"></i>3 items · Crazy Maki, Salmon Sashimi...</div>
          <div class="order-info-row"><i class="fas fa-clock"></i>Received 2 mins ago</div>
        </div>
        <div class="order-card-footer">
          <div><div class="order-amount">₱585</div><div class="order-payment">GCash</div></div>
          <button class="accept-btn" onclick="event.stopPropagation();acceptOrder(this)">Accept</button>
        </div>
      </div>

      <div class="order-card" data-status="pickup" onclick="openOrderDetail('ORD-2839','Maria Santos','123 Mabini St., Brgy. Bagumbayan, QC','Tuna Roll x3, Ebi Tempura x1','₱720','COD','0928 123 4567','pickup')">
        <div class="order-card-header">
          <div><div class="order-id">#ORD-2839</div><div class="order-name">Maria Santos</div></div>
          <div class="order-status status-pickup">For Pickup</div>
        </div>
        <div class="order-card-body">
          <div class="order-info-row"><i class="fas fa-map-marker-alt"></i>123 Mabini St., Brgy. Bagumbayan, QC</div>
          <div class="order-info-row"><i class="fas fa-box"></i>4 items · Tuna Roll, Ebi Tempura...</div>
          <div class="order-info-row"><i class="fas fa-clock"></i>Waiting 8 mins</div>
        </div>
        <div class="order-card-footer">
          <div><div class="order-amount">₱720</div><div class="order-payment">Cash on Delivery</div></div>
          <button class="accept-btn secondary" onclick="event.stopPropagation();navigateToMap()">Navigate</button>
        </div>
      </div>

      <div class="order-card" data-status="delivering" onclick="openOrderDetail('ORD-2835','Carlo Reyes','456 Rizal Ave., Brgy. Tandang Sora, QC','Seafood Platter x1','₱1,250','GCash','0939 123 4567','delivering')">
        <div class="order-card-header">
          <div><div class="order-id">#ORD-2835</div><div class="order-name">Carlo Reyes</div></div>
          <div class="order-status status-delivering">On the Way</div>
        </div>
        <div class="order-card-body">
          <div class="order-info-row"><i class="fas fa-map-marker-alt"></i>456 Rizal Ave., Brgy. Tandang Sora, QC</div>
          <div class="order-info-row"><i class="fas fa-box"></i>1 item · Seafood Platter</div>
          <div class="order-info-row"><i class="fas fa-clock"></i>En route · ETA 12 mins</div>
        </div>
        <div class="order-card-footer">
          <div><div class="order-amount">₱1,250</div><div class="order-payment">GCash</div></div>
          <button class="accept-btn secondary" onclick="event.stopPropagation();navigateToMap()"><i class="fas fa-map"></i> Map</button>
        </div>
      </div>

      <div style="height:16px"></div>
    </div>
  </div>

  <!-- MAP PAGE -->
  <div class="page" id="page-map">
    <div class="map-container">
      <div class="map-grid"></div>
      <div class="map-road-h" style="top:30%;height:18px"></div>
      <div class="map-road-h" style="top:55%;height:14px"></div>
      <div class="map-road-h" style="top:75%;height:10px"></div>
      <div class="map-road-v" style="left:35%;width:16px"></div>
      <div class="map-road-v" style="left:65%;width:12px"></div>
      <div class="map-road-v" style="left:20%;width:10px"></div>
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
      <div class="rider-pin">
        <div class="rider-pin-pulse"></div>
        <div class="rider-pin-inner"><i class="fas fa-motorcycle"></i></div>
      </div>
      <div class="dest-pin"><div class="dest-pin-inner"><i class="fas fa-home"></i></div></div>
      <div class="map-controls">
        <button class="map-ctrl-btn"><i class="fas fa-plus"></i></button>
        <button class="map-ctrl-btn"><i class="fas fa-minus"></i></button>
        <button class="map-ctrl-btn"><i class="fas fa-crosshairs"></i></button>
        <button class="map-ctrl-btn"><i class="fas fa-layer-group"></i></button>
      </div>
      <div style="position:absolute;top:22%;left:36%;font-size:.6rem;color:rgba(255,255,255,.5);font-weight:600;z-index:6">Mabini St.</div>
      <div style="position:absolute;top:48%;left:12%;font-size:.6rem;color:rgba(255,255,255,.5);font-weight:600;z-index:6">Rizal Ave.</div>
      <div style="position:absolute;top:24%;left:67%;background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.4);border-radius:6px;padding:2px 6px;font-size:.62rem;color:#4ade80;font-weight:700;z-index:8">Destination</div>
      <div style="position:absolute;top:52%;left:44%;background:rgba(194,38,38,.2);border:1px solid rgba(194,38,38,.4);border-radius:6px;padding:2px 6px;font-size:.62rem;color:#ff8080;font-weight:700;z-index:8">You</div>
    </div>
    <div class="delivery-card">
      <div class="delivery-card-top">
        <div>
          <div class="delivery-dest">Delivering to</div>
          <div class="delivery-addr">123 Mabini St., Brgy. Bagumbayan</div>
        </div>
        <div class="delivery-eta"><div class="eta-val">12</div><div class="eta-lbl">mins</div></div>
      </div>
      <div class="delivery-progress">
        <div class="progress-step"><div class="progress-dot done"></div><div class="progress-lbl">Order<br>Accepted</div></div>
        <div class="progress-line done"></div>
        <div class="progress-step"><div class="progress-dot active"></div><div class="progress-lbl">On the<br>Way</div></div>
        <div class="progress-line"></div>
        <div class="progress-step"><div class="progress-dot"></div><div class="progress-lbl">Delivered</div></div>
      </div>
      <button class="delivered-btn" onclick="markDelivered()"><i class="fas fa-check-circle"></i> Mark as Delivered</button>
    </div>
  </div>

  <!-- HISTORY PAGE -->
  <div class="page" id="page-history">
    <div class="history-hero">
      <div class="section-title">Delivery History</div>
      <div class="section-sub">Your completed deliveries</div>
      <div class="earnings-card">
        <div class="earning-item"><div class="earning-val" style="color:#4ade80">₱3,420</div><div class="earning-lbl">This Week</div></div>
        <div class="earning-item"><div class="earning-val">₱12,860</div><div class="earning-lbl">This Month</div></div>
        <div class="earning-divider"></div>
        <div class="earning-item"><div class="earning-val">47</div><div class="earning-lbl">Deliveries</div></div>
        <div class="earning-item"><div class="earning-val" style="color:#fbbf24">4.9 ⭐</div><div class="earning-lbl">Avg Rating</div></div>
      </div>
    </div>
    <div class="history-body">
      <div class="history-date-label">Today</div>
      <div class="history-card" onclick="showToast('📋 ORD-2830 details')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info"><div class="history-name">Ana Reyes — #ORD-2830</div><div class="history-meta">3 items · Brgy. Batasan Hills</div></div>
        <div class="history-right"><div class="history-amount">+₱420</div><div class="history-time">2:30 PM</div></div>
      </div>
      <div class="history-card" onclick="showToast('📋 ORD-2825 details')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info"><div class="history-name">Ben Cruz — #ORD-2825</div><div class="history-meta">2 items · Brgy. Payatas</div></div>
        <div class="history-right"><div class="history-amount">+₱280</div><div class="history-time">1:15 PM</div></div>
      </div>
      <div class="history-date-label">Yesterday</div>
      <div class="history-card" onclick="showToast('📋 ORD-2818 details')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info"><div class="history-name">Clara Lim — #ORD-2818</div><div class="history-meta">5 items · Brgy. Commonwealth</div></div>
        <div class="history-right"><div class="history-amount">+₱875</div><div class="history-time">6:45 PM</div></div>
      </div>
      <div class="history-card" onclick="showToast('📋 ORD-2810 details')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info"><div class="history-name">Diego Tan — #ORD-2810</div><div class="history-meta">1 item · Brgy. Novaliches</div></div>
        <div class="history-right"><div class="history-amount">+₱195</div><div class="history-time">4:20 PM</div></div>
      </div>
      <div class="history-card" onclick="showToast('📋 ORD-2804 details')">
        <div class="history-icon green"><i class="fas fa-check"></i></div>
        <div class="history-info"><div class="history-name">Eva Ramos — #ORD-2804</div><div class="history-meta">4 items · Brgy. Gulod</div></div>
        <div class="history-right"><div class="history-amount">+₱560</div><div class="history-time">11:00 AM</div></div>
      </div>
      <div style="height:16px"></div>
    </div>
  </div>

  <!-- ACCOUNT PAGE -->
  <div class="page" id="page-account">
    <div class="account-hero">
      <div class="avatar">🏍️<div class="avatar-online"></div></div>
      <div class="account-name">Marco Rivera</div>
      <div class="account-id">Rider ID: LKS-R-0042</div>
      <div class="rating-row"><div class="rating-stars">★★★★★</div><div class="rating-val">4.9</div></div>
    </div>
    <div class="account-body">
      <div class="account-section">
        <div class="account-section-title">Status</div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-circle"></i></div>
          <div class="account-row-text"><div class="account-row-label">Online Status</div><div class="account-row-sub">You are currently online</div></div>
          <div class="toggle-switch on" onclick="this.classList.toggle('on')"></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon amber"><i class="fas fa-bell"></i></div>
          <div class="account-row-text"><div class="account-row-label">Order Notifications</div><div class="account-row-sub">Get alerts for new orders</div></div>
          <div class="toggle-switch on" onclick="this.classList.toggle('on')"></div>
        </div>
      </div>
      <div class="account-section">
        <div class="account-section-title">Personal Info</div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-user"></i></div>
          <div class="account-row-text"><div class="account-row-label">Full Name</div><div class="account-row-sub">Marco Rivera</div></div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-phone"></i></div>
          <div class="account-row-text"><div class="account-row-label">Mobile Number</div><div class="account-row-sub">0917 123 4567</div></div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-motorcycle"></i></div>
          <div class="account-row-text"><div class="account-row-label">Vehicle Plate</div><div class="account-row-sub">ABC 1234</div></div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
      </div>
      <div class="account-section">
        <div class="account-section-title">Earnings</div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-peso-sign"></i></div>
          <div class="account-row-text"><div class="account-row-label">Total Earnings</div><div class="account-row-sub">All time</div></div>
          <div class="account-row-right" style="font-weight:800;color:#4ade80">₱48,240</div>
        </div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-calendar"></i></div>
          <div class="account-row-text"><div class="account-row-label">This Month</div><div class="account-row-sub">April 2026</div></div>
          <div class="account-row-right" style="font-weight:800;color:#4ade80">₱12,860</div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-box"></i></div>
          <div class="account-row-text"><div class="account-row-label">Total Deliveries</div><div class="account-row-sub">Completed orders</div></div>
          <div class="account-row-right" style="font-weight:800">284</div>
        </div>
      </div>
      <div class="account-section">
        <div class="account-section-title">Settings</div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-lock"></i></div>
          <div class="account-row-text"><div class="account-row-label">Change Password</div></div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-shield-alt"></i></div>
          <div class="account-row-text"><div class="account-row-label">Privacy Policy</div></div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
      </div>
      <button class="logout-btn"><i class="fas fa-sign-out-alt"></i> Log Out</button>
      <div style="height:8px"></div>
    </div>
  </div>

  <!-- Bottom Nav -->
  <div class="bottom-nav">
    <button class="nav-item active" onclick="switchPage('orders',this)">
      <div class="nav-badge" id="order-badge">3</div>
      <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </button>
    <button class="nav-item" onclick="switchPage('map',this)">
      <i class="fas fa-map-marked-alt"></i><span>Map</span>
    </button>
    <button class="nav-item" onclick="switchPage('history',this)">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item" onclick="switchPage('account',this)">
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
        <div class="detail-row"><span class="detail-val" id="detailAddress" style="max-width:100%;text-align:left">—</span></div>
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
        <button class="btn-primary" id="detailMainBtn" onclick="acceptFromDetail()"><i class="fas fa-check"></i> Accept Order</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="rider.js"></script>
</body>
</html>