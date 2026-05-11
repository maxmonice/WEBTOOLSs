<?php
session_start();
require_once 'Db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$email = $_SESSION['user_email'];

try {
    $db = new Db();
    $pdo = $db->getConnection();

    // Query for bookings where the notes JSON contains the user's email
    // This is a simple way to find bookings associated with the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE notes LIKE ? ORDER BY created_at DESC");
    $stmt->execute(['%"user_email":"' . $email . '"%']);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedBookings = [];
    foreach ($bookings as $b) {
        $details = json_decode($b['notes'], true) ?: [];
        $formattedBookings[] = [
            'id' => $b['id'],
            'status' => $b['status'],
            'event_date' => $b['event_date'],
            'event_name' => $details['event_name'] ?? 'Event',
            'event_time' => $details['event_time'] ?? '',
            'num_guests' => $details['num_guests'] ?? '',
            'address' => $details['address'] ?? '',
            'created_at' => $b['created_at']
        ];
    }

    echo json_encode(['success' => true, 'bookings' => $formattedBookings]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
