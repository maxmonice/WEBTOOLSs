<?php
session_start();
require_once 'Db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$email = $_SESSION['user_email'] ?? '';

try {
    $pdo = getDB();


    // Query for bookings by user_id OR by email in notes (for legacy or guest bookings matched after login)
    $stmt = $pdo->prepare("
        SELECT * FROM bookings 
        WHERE user_id = ? 
        OR (user_id IS NULL AND (email_address = ? OR notes LIKE ?))
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId, $email, '%"user_email":"' . $email . '"%']);
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
