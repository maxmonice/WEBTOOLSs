<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['rider_id'])) { header('Location: login.php'); exit; }
?>
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
    <div class="rider-badge" id="header-status-badge">
      <div class="dot" id="header-status-dot"></div>
      <span id="header-status-text">Online</span>
    </div>
  </div>

  <!-- ACCOUNT PAGE -->
  <div class="page-content">
    <div class="account-body">

      <div class="account-section">
        <div class="account-section-title">Personal Info</div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-user"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Full Name</div>
            <div class="account-row-sub">Marco Rivera</div>
          </div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-phone"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Mobile Number</div>
            <div class="account-row-sub">0917 123 4567</div>
          </div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-motorcycle"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Vehicle Plate</div>
            <div class="account-row-sub">ABC 1234</div>
          </div>
        </div>
      </div>

      <div class="account-section">
        <div class="account-section-title">Status</div>
        <div class="account-row">
          <div class="account-row-icon green"><i class="fas fa-circle"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Online Status</div>
            <div class="account-row-sub" id="online-status-sub">You are currently online</div>
          </div>
          <div class="toggle-switch on" id="online-toggle" onclick="toggleSwitch(this)"></div>
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
        <div class="account-section-title">Settings</div>
        <div class="account-row" onclick="window.location.href='admin-chat.php'">
          <div class="account-row-icon"><i class="fas fa-headset"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Admin Support</div>
            <div class="account-row-sub">Chat with Luke's Admin</div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-moon"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Switch Theme</div>
            <div class="account-row-sub">Switch between dark and light theme</div>
          </div>
          <div class="toggle-switch on" id="theme-toggle" onclick="toggleTheme()"></div>
        </div>
      </div>

      <button class="logout-btn" onclick="doLogout()">
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
    <button class="nav-item" onclick="window.location.href='chat.php'">
      <div class="nav-dot" id="chat-badge"></div>
      <i class="fas fa-comment-dots"></i><span>Chat</span>
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
<script src="theme-manager.js"></script>
<script src="account.js?v=<?= time() ?>"></script>
</body>
</html>