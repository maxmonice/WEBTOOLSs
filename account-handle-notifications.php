<?php
require_once 'Db.php';
require_once 'Notifications.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$notifications = new Notifications($pdo);
$userId = $_SESSION['user_id'] ?? null;

switch ($action) {
    case 'get_notifications':
        $userNotifications = $notifications->getForUser('customer', $userId, 10);
        echo json_encode([
            'success' => true,
            'notifications' => $userNotifications
        ]);
        break;
        
    case 'mark_read':
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $success = $notifications->markAsRead($notificationId, $userId);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        $success = $notifications->markAllAsRead('customer', $userId);
        echo json_encode(['success' => $success]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
?>


