<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$sessionUserId = $_SESSION['user_id'] ?? null;
if (!$sessionUserId) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$orderId = (int)($data['order_id'] ?? 0);

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Verify order ownership and status
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $sessionUserId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Restriction: Cannot cancel if shipped or delivered
    if (in_array($order['status'], ['shipped', 'delivered'])) {
        echo json_encode(['success' => false, 'message' => 'Order is already on the way and cannot be cancelled.']);
        exit;
    }

    // Update status to cancelled
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$orderId]);

    // Notify all connected clients via Socket.io
    @file_get_contents("http://localhost:3000/emit?event=order-status-update&orderId={$orderId}&status=cancelled");

    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
