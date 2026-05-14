<?php
require_once 'staffSide/staff-config.php';
$orderId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT status, delivery_latitude, delivery_longitude FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Track Your Order - Luke's Seafood</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: sans-serif; background: #0f172a; color: white; margin: 0; text-align: center; }
        #map { height: 400px; width: 90%; margin: 20px auto; border-radius: 15px; display: none; }
        .status-container { padding: 50px 20px; }
        .status-badge { padding: 10px 20px; border-radius: 50px; background: #334155; font-weight: bold; }
        .on-route { background: #16a34a; }
    </style>
</head>
<body>
    <div class="status-container">
        <h2>Order #<?= $orderId ?></h2>
        <div id="status-text" class="status-badge">
            <?= ucfirst($order['status']) == 'Shipped' ? 'On Route' : ucfirst($order['status']) ?>
        </div>
        
        <p id="instruction">
            <?php if($order['status'] == 'shipped'): ?>
                Rider is on the way! Watch the map below.
            <?php else: ?>
                We are preparing your fresh seafood.
            <?php endif; ?>
        </p>
    </div>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script>
        const orderId = <?= $orderId ?>;
        let currentStatus = '<?= $order['status'] ?>';
        let map, riderMarker;

        const socket = io('http://localhost:3000');
        socket.emit('join-order', orderId);

        // Custom Rider Icon
        const riderIcon = L.icon({
            iconUrl: 'https://cdn-icons-png.flaticon.com/512/71/71422.png',
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        function initCustomerMap(lat, lng) {
            document.getElementById('map').style.display = 'block';
            document.getElementById('status-text').classList.add('on-route');
            document.getElementById('status-text').innerText = 'ON ROUTE';
            document.getElementById('instruction').innerText = 'Rider is on the way!';

            map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(map);
            
            // Customer Home Marker
            L.marker([<?= $order['delivery_latitude'] ?>, <?= $order['delivery_longitude'] ?>]).addTo(map)
                .bindPopup('Your Location').openPopup();

            riderMarker = L.marker([lat, lng], { icon: riderIcon }).addTo(map);
        }

        // Listen for rider movement
        socket.on('receive-location', (data) => {
            if (!map) {
                initCustomerMap(data.lat, data.lng);
            } else {
                riderMarker.setLatLng([data.lat, data.lng]);
                map.panTo([data.lat, data.lng]);
            }
        });

        // Auto-refresh status checker
        setInterval(() => {
            if (currentStatus !== 'shipped') {
                fetch(`riderSide/rider-orders-api.php?action=get_confirmed_orders`) // Simplified check
                .then(r => location.reload()); // Reload to trigger map if status changed to shipped
            }
        }, 10000);

        // If already shipped on load
        if (currentStatus === 'shipped') {
            initCustomerMap(14.52625, 121.055375); // Start at store location
        }
    </script>
</body>
</html>