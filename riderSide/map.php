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
    $stmt = $pdo->prepare("
        SELECT id, delivery_latitude, delivery_longitude, address
        FROM orders
        WHERE rider_id = ? AND status = 'shipped'
        ORDER BY updated_at DESC
        LIMIT 1
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
    <style>
        .map-page {
            position: relative;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            background: #0f172a;
        }

        #map-view {
            width: 100%;
            height: 100%;
        }

        .delivery-card {
            position: absolute;
            left: 12px;
            right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            background: #1a1a2e;
            color: white;
            padding: 14px;
            border-radius: 12px;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .delivery-card .address {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.95rem;
            line-height: 1.35;
            max-height: 2.7em;
            overflow: hidden;
        }

        .eta-box {
            color: #f39c12;
            font-weight: 700;
        }

        .deliver-btn {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            background: #22c55e;
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .empty-state-map {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 20px;
            text-align: center;
            background: #0f172a;
        }

        .empty-link {
            display: inline-block;
            padding: 10px 14px;
            background: #C22626;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
        }

        @media (max-width: 360px) {
            .delivery-card {
                left: 10px;
                right: 10px;
                padding: 12px;
            }
        }
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

        <div class="page-content map-page">
        <?php if (!$order): ?>
        <div class="empty-state-map">
            <div>
                <div style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">No active delivery to track</div>
                <div style="opacity:.8;font-size:.9rem;margin-bottom:16px;">Accept an order first from the Orders page.</div>
                <a href="orders.php" class="empty-link">Go to Orders</a>
            </div>
        </div>
        <?php else: ?>
        <div id="map-view"></div>
        
        <div class="delivery-card">
            <div style="font-size:0.9rem;opacity:0.8;">Delivering to:</div>
            <div class="address"><?= htmlspecialchars($order['address']) ?></div>
            <div class="eta-box">ETA: <span class="eta-val">--</span> mins</div>
            <button class="deliver-btn" onclick="markDelivered()">Mark as Delivered</button>
        </div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
        <script>
            // Global variables for map.js
            const ACTIVE_ORDER_ID = <?= $orderId ?>;
            const DEST_LAT = <?= $order['delivery_latitude'] ?>;
            const DEST_LNG = <?= $order['delivery_longitude'] ?>;
        </script>
        <script src="map.js"></script>
        <script>
            function markDelivered() {
                const fd = new FormData();
                fd.append('action', 'deliver_order');
                fd.append('order_id', ACTIVE_ORDER_ID);
                fetch('rider-orders-api.php', { method: 'POST', body: fd }).then(() => window.location.href='orders.php');
            }
        </script>
        <?php endif; ?>
        </div>

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
</body>
</html>