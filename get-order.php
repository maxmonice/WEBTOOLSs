<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$sessionUserId = $_SESSION['user_id'] ?? null;
$orderId = intval($_GET['order_id'] ?? 0);

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4",
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Fetch specific order by ID, or latest active for this user
    if ($orderId > 0) {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as rider_name
            FROM orders o
            LEFT JOIN users u ON o.rider_id = u.id
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
    } elseif ($sessionUserId) {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as rider_name
            FROM orders o
            LEFT JOIN users u ON o.rider_id = u.id
            WHERE o.user_id = ? 
              AND (o.status NOT IN ('delivered','cancelled') 
                   OR (o.status = 'delivered' AND o.updated_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)))
            ORDER BY o.created_at DESC LIMIT 1
        ");
        $stmt->execute([$sessionUserId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $order = $stmt->fetch();
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'No active order found']);
        exit;
    }

    // Parse stored JSON notes
    $notes = json_decode($order['notes'] ?? '{}', true) ?: [];

    echo json_encode([
        'success' => true,
        'order'   => [
            'id'             => (int) $order['id'],
            'status'         => $order['status'],
            'total_amount'   => floatval($order['total_amount']),
            'subtotal'       => floatval($notes['subtotal'] ?? 0),
            'shipping'       => floatval($notes['shipping']  ?? 0),
            'address'        => $order['address'],
            'delivery_latitude'  => isset($order['delivery_latitude'])  ? floatval($order['delivery_latitude'])  : null,
            'delivery_longitude' => isset($order['delivery_longitude']) ? floatval($order['delivery_longitude']) : null,
            'rider_lat'      => isset($order['rider_lat']) && $order['rider_lat'] ? floatval($order['rider_lat']) : null,
            'rider_lng'      => isset($order['rider_lng']) && $order['rider_lng'] ? floatval($order['rider_lng']) : null,
            'payment_method' => $order['payment_method'],
            'rider_id'       => $order['rider_id'] ?? null,
            'rider_name'     => $order['rider_name'] ?? 'Your Rider',
            'items'          => $notes['items'] ?? [],
            'created_at'     => $order['created_at'],
            'updated_at'     => $order['updated_at'],
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
