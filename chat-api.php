<?php
// central chat API for all sides
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'Db.php';

$pdo = getDB();

// Determine requester identity
$userId = $_SESSION['user_id'] ?? null;
$riderId = $_SESSION['rider_id'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);
$isStaff = !empty($_SESSION['is_staff']);

$senderType = 'customer';
$senderId = $userId;

if ($isAdmin || $isStaff) {
    $senderType = 'admin';
    $senderId = $userId;
} elseif ($riderId) {
    $senderType = 'rider';
    $senderId = $riderId;
}

if (!$senderId && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send_message':
        $orderId      = $_POST['order_id'] ?? null;
        $receiverId   = $_POST['receiver_id'] ?? null;
        $receiverType = $_POST['receiver_type'] ?? '';
        $message      = $_POST['message'] ?? '';

        if (!$message) {
            echo json_encode(['success' => false, 'message' => 'Empty message']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages 
                (order_id, sender_id, sender_type, receiver_id, receiver_type, message) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$orderId, $senderId, $senderType, $receiverId, $receiverType, $message]);
            
            // Trigger Socket.io if possible
            $msgId = $pdo->lastInsertId();
            $socketData = [
                'id' => $msgId,
                'orderId' => $orderId,
                'sender' => $senderType,
                'senderId' => $senderId,
                'message' => $message,
                'timestamp' => date('g:i A')
            ];
            
            @file_get_contents("http://localhost:3000/emit?event=new-message&data=" . urlencode(json_encode($socketData)));

            echo json_encode(['success' => true, 'message' => 'Sent']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_history':
        $orderId = $_GET['order_id'] ?? null;
        // For admin, they can see messages for a specific customer or rider if order_id is null
        $otherId = $_GET['other_id'] ?? null;
        $otherType = $_GET['other_type'] ?? '';

        try {
            if ($orderId) {
                // Fetch by order (Rider <-> Customer)
                $stmt = $pdo->prepare("
                    SELECT * FROM chat_messages 
                    WHERE order_id = ? 
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$orderId]);
            } else {
                // Fetch by peer (Admin <-> X)
                $stmt = $pdo->prepare("
                    SELECT * FROM chat_messages 
                    WHERE (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ?)
                       OR (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ?)
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$senderId, $senderType, $otherId, $otherType, $otherId, $otherType, $senderId, $senderType]);
            }
            
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($messages as &$m) {
                $m['timestamp'] = date('g:i A', strtotime($m['created_at']));
                // Map sender type for frontend simplicity
                // If I am the sender, mark as 'outgoing' or similar?
                // Frontend usually handles this by comparing sender_id
            }

            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_active_threads':
        // For admin to see who is chatting
        if (!$isAdmin && !$isStaff) {
            echo json_encode(['success' => false, 'message' => 'Admin only']);
            exit;
        }
        
        try {
            // Get unique pairs of (sender, receiver) excluding admin
            $stmt = $pdo->query("
                SELECT DISTINCT 
                    CASE WHEN sender_type != 'admin' THEN sender_id ELSE receiver_id END as peer_id,
                    CASE WHEN sender_type != 'admin' THEN sender_type ELSE receiver_type END as peer_type
                FROM chat_messages
                WHERE sender_type = 'admin' OR receiver_type = 'admin'
            ");
            $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enrich with names
            foreach ($peers as &$peer) {
                if ($peer['peer_type'] === 'customer') {
                    $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                    $u->execute([$peer['peer_id']]);
                    $peer['name'] = $u->fetchColumn() ?: 'Unknown Customer';
                } else {
                    $u = $pdo->prepare("SELECT name FROM users WHERE id = ?"); // Assuming riders are in users too with role
                    $u->execute([$peer['peer_id']]);
                    $peer['name'] = $u->fetchColumn() ?: 'Unknown Rider';
                }
            }
            
            echo json_encode(['success' => true, 'threads' => $peers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
