<?php
require_once __DIR__ . '/rider-session.php';
require_once __DIR__ . '/../Db.php';
startRiderSession();
if (empty($_SESSION['rider_id'])) { header('Location: login.php'); exit; }

// Fetch rider profile + rating from database
$riderId = (int)$_SESSION['rider_id'];
$riderName = htmlspecialchars($_SESSION['rider_name'] ?? 'Rider');
$riderPhone = '';
$riderPlate = '';
$riderEarnings = 0;
$riderMonthEarnings = 0;
$riderDeliveries = 0;
$riderAvgRating = 0;
$riderRatingCount = 0;
$riderIdDisplay = 'LKS-R-' . str_pad($riderId, 4, '0', STR_PAD_LEFT);

try {
    $db = getDB();
    // Get rider info
    $stmt = $db->prepare("SELECT name, phone, vehicle_plate, average_rating, rating_count FROM riders WHERE id = ?");
    $stmt->execute([$riderId]);
    $riderData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($riderData) {
        $riderName = htmlspecialchars($riderData['name'] ?: $riderName);
        $riderPhone = htmlspecialchars($riderData['phone'] ?? '');
        $riderPlate = htmlspecialchars($riderData['vehicle_plate'] ?? '');
        $riderAvgRating = $riderData['average_rating'] ? round(floatval($riderData['average_rating']), 1) : 0;
        $riderRatingCount = (int)($riderData['rating_count'] ?? 0);
    }

    // Count completed deliveries
    $delStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE assigned_rider_id = ? AND status = 'delivered'");
    $delStmt->execute([$riderId]);
    $riderDeliveries = (int)$delStmt->fetchColumn();

} catch (Exception $e) {
    // Silently continue with defaults
}

// Generate star display
$filledStars = $riderAvgRating > 0 ? round($riderAvgRating) : 0;
$starsDisplay = str_repeat('★', $filledStars) . str_repeat('☆', 5 - $filledStars);
$ratingDisplay = $riderAvgRating > 0 ? number_format($riderAvgRating, 1) : 'N/A';
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
    <div class="rider-badge"><div class="dot"></div>Online</div>
  </div>

  <!-- ACCOUNT PAGE -->
  <div class="page-content">
    <div class="account-hero">
      <div class="avatar">
        🏍️
        <div class="avatar-online"></div>
      </div>
      <div class="account-name"><?= $riderName ?></div>
      <div class="account-id">Rider ID: <?= $riderIdDisplay ?></div>
      <div class="rating-row">
        <div class="rating-stars" id="riderStars"><?= $starsDisplay ?></div>
        <div class="rating-val" id="riderRatingVal"><?= $ratingDisplay ?></div>
        <?php if ($riderRatingCount > 0): ?>
        <div style="font-size:0.7rem;color:rgba(255,255,255,0.4);margin-left:4px;">(<?= $riderRatingCount ?> ratings)</div>
        <?php endif; ?>
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
            <div class="account-row-sub"><?= $riderName ?></div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-phone"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Mobile Number</div>
            <div class="account-row-sub"><?= $riderPhone ?: 'Not set' ?></div>
          </div>
          <div class="account-row-right"><i class="fas fa-chevron-right"></i></div>
        </div>
        <div class="account-row">
          <div class="account-row-icon"><i class="fas fa-motorcycle"></i></div>
          <div class="account-row-text">
            <div class="account-row-label">Vehicle Plate</div>
            <div class="account-row-sub"><?= $riderPlate ?: 'Not set' ?></div>
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
          <div class="account-row-right" style="font-weight:800"><?= $riderDeliveries ?></div>
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
