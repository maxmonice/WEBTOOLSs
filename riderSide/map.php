<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../staffSide/staff-config.php'; // Reuse DB connection

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) die("Order ID missing");

$stmt = $pdo->prepare("SELECT delivery_latitude, delivery_longitude, address FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) die("Order not found");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rider Delivery Map</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        #map-view { height: 100vh; width: 100%; }
        .delivery-card { position: fixed; bottom: 20px; left: 10px; right: 10px; background: #1a1a2e; color: white; padding: 15px; border-radius: 12px; z-index: 1000; }
        .eta-box { color: #f39c12; font-weight: bold; }
    </style>
</head>
<body>
    <div id="map-view"></div>
    
    <div class="delivery-card">
        <div style="font-size: 0.9rem; opacity: 0.8;">Delivering to:</div>
        <div style="font-weight: bold; margin-bottom: 8px;"><?= htmlspecialchars($order['address']) ?></div>
        <div class="eta-box">ETA: <span class="eta-val">--</span> mins</div>
        <button onclick="markDelivered()" style="width:100%; margin-top:10px; padding:10px; background:#22c55e; border:none; border-radius:8px; color:white; font-weight:bold;">
            Mark as Delivered
        </button>
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
</body>
</html>