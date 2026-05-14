<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../staffSide/staff-config.php'; // Reuse DB connection

if (empty($_SESSION['rider_id'])) {
    header('Location: login.php');
    exit;
}

$riderId = (string)($_SESSION['rider_id'] ?? '');
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, delivery_latitude, delivery_longitude, address
        FROM orders
        WHERE id = ? AND rider_id = ? AND status = 'shipped'
        LIMIT 1
    ");
    $stmt->execute([$orderId, $riderId]);
    $order = $stmt->fetch();
} else {
    // Auto-fetch the active shipping order for this rider if none provided
    $stmt = $pdo->prepare("
        SELECT id, delivery_latitude, delivery_longitude, address
        FROM orders
        WHERE rider_id = ? AND status = 'shipped'
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$riderId]);
    $order = $stmt->fetch();
}

if ($order) {
    $orderId = (int)$order['id'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rider Delivery Map</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="shared.css">
    <link rel="stylesheet" href="map.css">
    <!-- Leaflet Routing Machine -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

    
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

        <div class="page-content map-page">
            <div id="map-view"></div>

            <?php if ($order): ?>
            <div class="delivery-card" id="deliveryCard">
                <div class="card-header-toggle" onclick="toggleDeliveryCard()">
                    <div class="card-title-text">
                        <i class="fas fa-route"></i> Active Delivery
                    </div>
                    <i class="fas fa-chevron-down" id="toggleIcon"></i>
                </div>
                <div class="card-expandable-content" id="cardContent">
                    <div class="card-label-text">Delivering to:</div>
                    <div class="address"><?= htmlspecialchars($order['address']) ?></div>
                    <div class="eta-box">ETA: <span class="eta-val">--</span> mins</div>
                    <button class="deliver-btn" onclick="triggerDeliveryConfirm()">Mark as Delivered</button>
                </div>
            </div>
            <script>
                function toggleDeliveryCard() {
                    const card = document.getElementById('deliveryCard');
                    const content = document.getElementById('cardContent');
                    const icon = document.getElementById('toggleIcon');
                    
                    if (content.style.display === 'none') {
                        content.style.display = 'block';
                        icon.className = 'fas fa-chevron-down';
                        card.style.bottom = 'calc(12px + env(safe-area-inset-bottom, 0px))';
                    } else {
                        content.style.display = 'none';
                        icon.className = 'fas fa-chevron-up';
                        card.style.bottom = 'calc(12px + env(safe-area-inset-bottom, 0px))';
                    }
                }
                // Start minimized if you want, but user said "if im on route", 
                // so let's keep it expanded if they are on route, but allow minimizing.
            </script>
            <?php endif; ?>
        </div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
        <script>
            // Global variables for map.js
            const ACTIVE_ORDER_ID = <?= (int)($orderId ?? 0) ?>;
            const DEST_LAT = <?= (float)($order['delivery_latitude'] ?? 0) ?>;
            const DEST_LNG = <?= (float)($order['delivery_longitude'] ?? 0) ?>;
        </script>
        <div class="toast" id="toast"></div>
        <script src="theme-manager.js"></script>
        <script src="map.js?v=<?= time() ?>"></script>
        <script>
            // Note: handleDeliverySuccess and other modal functions are managed in map.js
        </script>

        <div class="bottom-nav">
            <button class="nav-item" onclick="window.location.href='orders.php'">
                <i class="fas fa-clipboard-list"></i><span>Orders</span>
            </button>
            <button class="nav-item active">
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

        <!-- CUSTOM CONFIRMATION MODAL -->
        <div class="custom-modal-overlay" id="confirmModal">
            <div class="custom-modal-card">
                <div class="modal-icon"><i class="fas fa-box-open"></i></div>
                <div class="modal-title">Mark as Delivered?</div>
                <div class="modal-msg">Are you sure you have successfully delivered this order to the customer's address?</div>
                <div class="modal-actions">
                    <button class="modal-btn modal-btn-confirm" onclick="handleDeliverySuccess()">Yes, Delivered</button>
                    <button class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>