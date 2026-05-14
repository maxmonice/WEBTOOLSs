<?php
require_once __DIR__ . '/adminSide/admin-config.php';
require_once __DIR__ . '/Notifications.php';

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

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
    case 'mark_read':
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $success = $notifications->markAsRead($notificationId, $userId, 'admin');
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        $success = $notifications->markAllAsRead('admin', $userId);
        echo json_encode(['success' => $success]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
?>
