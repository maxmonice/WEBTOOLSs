<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

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
            SELECT id, user_id, status, total_amount, address, payment_method, notes, created_at, updated_at
            FROM orders WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$orderId]);
    } elseif ($sessionUserId) {
        $stmt = $pdo->prepare("
            SELECT id, user_id, status, total_amount, address, payment_method, notes, created_at, updated_at
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
            'payment_method' => $order['payment_method'],
            'items'          => $notes['items'] ?? [],
            'created_at'     => $order['created_at'],
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
