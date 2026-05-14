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

    if ($hasUserIdColumn) {
        $query = "SELECT id, status, total_amount, total, payment_method, notes, created_at FROM orders WHERE user_id = ? AND status IN ('delivered', 'cancelled') ORDER BY created_at DESC";
        $params = [$userId];
    } else {
        $query = "SELECT id, status, total_amount, total, payment_method, notes, created_at FROM orders WHERE user_email = ? AND status IN ('delivered', 'cancelled') ORDER BY created_at DESC";
        $params = [$_SESSION['user_email'] ?? ''];
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $history = [];
    foreach ($rows as $row) {
        $notes = json_decode($row['notes'] ?? '{}', true) ?: [];
        $history[] = [
            'id'             => (int) $row['id'],
            'status'         => $row['status'],
            'total_amount'   => floatval($row['total_amount'] ?: ($row['total'] ?? 0)),
            'payment_method' => $row['payment_method'],
            'items_count'    => isset($notes['items']) ? count($notes['items']) : 0,
            'items_summary'  => isset($notes['items'][0]['name']) ? $notes['items'][0]['name'] . (count($notes['items']) > 1 ? ' + others' : '') : 'Seafood Order',
            'created_at'     => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'history' => $history]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>


