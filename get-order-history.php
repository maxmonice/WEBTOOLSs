<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4",
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = ?");
    $stmt->execute(['user_id']);
    $hasUserIdColumn = (int)$stmt->fetchColumn() > 0;

    $query = "
        SELECT 
            o.id, o.status, o.total_amount, o.total, o.payment_method, o.created_at,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
            (SELECT mi.name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = o.id LIMIT 1) as first_item_name
        FROM orders o 
        WHERE o.user_id = ? AND o.status IN ('delivered', 'cancelled') 
        ORDER BY o.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $history = [];
    foreach ($rows as $row) {
        $count = (int)$row['items_count'];
        $history[] = [
            'id'             => (int) $row['id'],
            'status'         => $row['status'],
            'total_amount'   => floatval($row['total_amount'] ?: ($row['total'] ?? 0)),
            'payment_method' => $row['payment_method'],
            'items_count'    => $count,
            'items_summary'  => $row['first_item_name'] ? ($row['first_item_name'] . ($count > 1 ? ' + others' : '')) : 'Seafood Order',
            'created_at'     => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'history' => $history]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>


