<?php
session_start();
require_once 'Db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$email = $_SESSION['user_email'] ?? '';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = ?");
    $stmt->execute(['user_id']);
    $hasUserIdColumn = (int)$stmt->fetchColumn() > 0;

    if ($hasUserIdColumn) {
        $query = "SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC";
        $params = [$userId];
    } else {
        $query = "SELECT * FROM bookings WHERE email_address = ? ORDER BY created_at DESC";
        $params = [$email];
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedBookings = [];
    foreach ($bookings as $b) {
        $formattedBookings[] = [
            'id' => $b['id'],
            'status' => $b['status'],
            'event_date' => $b['event_date'],
            'event_name' => $b['event_name'] ?? 'Event',
            'event_time' => $b['event_time'] ?? '',
            'num_guests' => $b['num_guests'] ?? '',
            'address' => $b['address'] ?? '',
            'event_type' => $b['event_type'] ?? '',
            'contact_number' => $b['contact_number'] ?? '',
            'email_address' => $b['email_address'] ?? '',
            'notes' => $b['notes'] ?? '',
            'created_at' => $b['created_at'],
            'updated_at' => $b['updated_at']
        ];
    }

    echo json_encode(['success' => true, 'bookings' => $formattedBookings]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
