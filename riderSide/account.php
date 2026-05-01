<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — Account</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="account.css">
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

  <!-- ACCOUNT PAGE -->
  <div class="page-content">
    <div class="account-hero">
      <div class="avatar">
        🏍️
        <div class="avatar-online"></div>
      </div>
      <div class="account-name">Marco Rivera</div>
      <div class="account-id">Rider ID: LKS-R-0042</div>
      <div class="rating-row">
        <div class="rating-stars">★★★★★</div>
        <div class="rating-val">4.9</div>
      </div>
    </div>

    <div class="account-body">

      <div class="account-section">
        <div class="account-section-title">Status</div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-circle"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Online Status</div>
            <div class="account-row-sub">You are currently online</div>
          </div>
          <div class="toggle-switch on" onclick="toggleSwitch(this)"></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon amber"><i class="fas fa-bell"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Order Notifications</div>
            <div class="account-row-sub">Get alerts for new orders</div>
          </div>
          <div class="toggle-switch on" onclick="toggleSwitch(this)"></div>
        </div>
      </div>

      <div class="account-section">
        <div class="account-section-title">Personal Info</div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-user"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Full Name</div>
            <div class="account-row-sub">Marco Rivera</div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-phone"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Mobile Number</div>
            <div class="account-row-sub">0917 123 4567</div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-motorcycle"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Vehicle Plate</div>
            <div class="account-row-sub">ABC 1234</div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
      </div>

      <div class="account-section">
        <div class="account-section-title">Earnings</div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-peso-sign"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Total Earnings</div>
            <div class="account-row-sub">All time</div>
          </div>
          <div class="account-row-right" style="font-weight:800;color:#4ade80">₱48,240</div>
        </div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-calendar"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">This Month</div>
            <div class="account-row-sub">April 2026</div>
          </div>
          <div class="account-row-right" style="font-weight:800;color:#4ade80">₱12,860</div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-box"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Total Deliveries</div>
            <div class="account-row-sub">Completed orders</div>
          </div>
          <div class="account-row-right" style="font-weight:800">284</div>
        </div>
      </div>

      <div class="account-section">
        <div class="account-section-title">Settings</div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-lock"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Change Password</div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-shield-alt"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Privacy Policy</div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
      </div>

      <button class="logout-btn" onclick="confirmLogout()">
        <i class="fas fa-sign-out-alt"></i> Log Out
      </button>
      <div style="height:8px"></div>
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
    <button class="nav-item" onclick="window.location.href='history.php'">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item active">
      <i class="fas fa-user-circle"></i><span>Account</span>
    </button>
  </div>

</div>

<div class="toast" id="toast"></div>
<script src="account.js"></script>
</body>
</html>