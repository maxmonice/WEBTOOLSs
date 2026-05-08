<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'Db.php';

$sessionUserId = $_SESSION['user_id'] ?? null;
$orderId = intval($_GET['order_id'] ?? 0);

try {
    $pdo = getDB();

    // Fetch specific order by ID, or latest active for this user
    if ($orderId > 0) {
        if (!$sessionUserId) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT id, user_id, status, total_amount, address, payment_method, notes, created_at, updated_at,
                   delivery_latitude, delivery_longitude
            FROM orders WHERE id = ? AND user_id = ? LIMIT 1
        ");
        $stmt->execute([$orderId, $sessionUserId]);
    } elseif ($sessionUserId) {
        $stmt = $pdo->prepare("
            SELECT id, user_id, status, total_amount, address, payment_method, notes, created_at, updated_at,
                   delivery_latitude, delivery_longitude
            FROM orders
            WHERE user_id = ? AND status NOT IN ('delivered','cancelled')
            ORDER BY created_at DESC LIMIT 1
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
            'delivery_latitude'  => isset($order['delivery_latitude']) ? floatval($order['delivery_latitude']) : null,
            'delivery_longitude' => isset($order['delivery_longitude']) ? floatval($order['delivery_longitude']) : null,
            'payment_method' => $order['payment_method'],
            'items'          => $notes['items'] ?? [],
            'created_at'     => $order['created_at'],
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
