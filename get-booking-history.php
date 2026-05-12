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

    // Fetch past bookings: completed or cancelled, or date is in the past
    $stmt = $pdo->prepare("
        SELECT id, event_type, status, event_date, guests, total_amount, created_at 
        FROM bookings 
        WHERE user_id = ? 
        AND (status IN ('completed', 'cancelled') OR event_date < CURDATE())
        ORDER BY event_date DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $history = [];
    foreach ($rows as $row) {
        $history[] = [
            'id'           => (int) $row['id'],
            'event_type'   => $row['event_type'],
            'status'       => $row['status'],
            'event_date'   => $row['event_date'],
            'guests'       => (int) $row['guests'],
            'total_amount' => floatval($row['total_amount']),
            'created_at'   => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'history' => $history]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
