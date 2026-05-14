<?php
// central chat API for all sides
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'Db.php';

$pdo = getDB();

function ensureChatTable(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("CREATE TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT DEFAULT NULL,
            sender_id INT DEFAULT NULL,
            sender_type ENUM('customer','rider','admin','staff','support') NOT NULL,
            receiver_id INT DEFAULT NULL,
            receiver_type ENUM('customer','rider','admin','staff','support') NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");
    } else {
        foreach (['sender_type', 'receiver_type'] as $column) {
            $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            $columnType = $stmt->fetchColumn();
            if ($columnType && (strpos($columnType, "'staff'") === false || strpos($columnType, "'support'") === false)) {
                $pdo->exec("ALTER TABLE chat_messages MODIFY {$column} ENUM('customer','rider','admin','staff','support') NOT NULL");
            }
        }
    }
}

function firstUserIdByRole(PDO $pdo, string $role): ?int {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$role]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

ensureChatTable($pdo);

// Determine requester identity
$userId = $_SESSION['user_id'] ?? null;
$riderId = $_SESSION['rider_id'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);
$isStaff = !empty($_SESSION['is_staff']);

$senderType = 'customer';
$senderId = $userId;

if ($userId) {
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$userId]);
    $actualRole = $roleStmt->fetchColumn() ?: 'customer';
    $isAdmin = $actualRole === 'admin';
    $isStaff = $actualRole === 'staff';
    if (in_array($actualRole, ['admin', 'staff'], true)) {
        $senderType = $actualRole;
    }
} elseif ($riderId) {
    $senderType = 'rider';
    $senderId = $riderId;
}

if (!$senderId) {
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
        if ($senderType === 'customer' && ($receiverType === 'admin' || $receiverType === 'support') && !$receiverId) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','staff')");
            if ((int)$stmt->fetchColumn() === 0) {
                echo json_encode(['success' => false, 'message' => 'No support agents are available']);
                exit;
            }
            $receiverId = null;
            $receiverType = 'support';
        }
        if ($receiverType === 'support' && $senderType !== 'customer') {
            echo json_encode(['success' => false, 'message' => 'Choose a customer conversation before replying']);
            exit;
        }

        if ((!$receiverId && $receiverType !== 'support') || !$receiverType) {
            echo json_encode(['success' => false, 'message' => 'Receiver is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO chat_messages 
                (order_id, sender_id, sender_type, receiver_id, receiver_type, message) 
                VALUES (?, ?, ?, ?, ?, ?)");

            $stmt->execute([$orderId, $senderId, $senderType, $receiverId, $receiverType, $message]);
            $msgId = $pdo->lastInsertId();
            
            // Trigger Socket.io if possible
            $threadType = null;
            $roomId = $orderId;
            $customerId = null;
            if ($receiverType === 'support' && $senderType === 'customer') {
                $threadType = 'support';
                $customerId = $senderId;
                $roomId = 'support_' . $senderId;
            } elseif (($senderType === 'admin' || $senderType === 'staff') && $receiverType === 'customer') {
                $threadType = 'support';
                $customerId = $receiverId;
                $roomId = 'support_' . $receiverId;
            }

            $socketData = [
                'id' => $msgId,
                'orderId' => $orderId,
                'roomId' => $roomId,
                'threadType' => $threadType,
                'customerId' => $customerId,
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
            $isSharedSupportHistory = false;
            if ($orderId) {
                // Fetch by order (Rider <-> Customer)
                $stmt = $pdo->prepare("
                    SELECT * FROM chat_messages 
                    WHERE order_id = ? 
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$orderId]);
            } elseif ($senderType === 'customer' && ($otherType === 'admin' || $otherType === 'support')) {
                $isSharedSupportHistory = true;
                $stmt = $pdo->prepare("
                    SELECT * FROM chat_messages
                    WHERE ((sender_id = ? AND sender_type = 'customer' AND receiver_type IN ('admin','staff','support'))
                       OR (receiver_id = ? AND receiver_type = 'customer' AND sender_type IN ('admin','staff')))
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$senderId, $senderId]);
            } elseif (($senderType === 'admin' || $senderType === 'staff') && $otherType === 'customer') {
                $isSharedSupportHistory = true;
                $stmt = $pdo->prepare("
                    SELECT * FROM chat_messages
                    WHERE ((sender_id = ? AND sender_type = 'customer' AND receiver_type IN ('admin','staff','support'))
                       OR (receiver_id = ? AND receiver_type = 'customer' AND sender_type IN ('admin','staff')))
                    ORDER BY created_at ASC
                ");
                $stmt->execute([$otherId, $otherId]);
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
            if ($isSharedSupportHistory) {
                $seenMessages = [];
                $messages = array_values(array_filter($messages, function ($m) use (&$seenMessages) {
                    if ($m['sender_type'] !== 'customer') return true;
                    $key = $m['sender_id'] . '|' . $m['message'] . '|' . $m['created_at'];
                    if (isset($seenMessages[$key])) return false;
                    $seenMessages[$key] = true;
                    return true;
                }));
            }
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
        if (!$isAdmin && !$isStaff) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        try {
            if ($senderType === 'admin' || $senderType === 'staff') {
                $threadRows = [];
                $supportStmt = $pdo->query("SELECT DISTINCT
                    CASE WHEN sender_type = 'customer' THEN sender_id ELSE receiver_id END AS peer_id,
                    'customer' AS peer_type
                FROM chat_messages
                WHERE (sender_type = 'customer' AND receiver_type IN ('admin','staff','support'))
                   OR (receiver_type = 'customer' AND sender_type IN ('admin','staff'))");
                $threadRows = array_merge($threadRows, $supportStmt->fetchAll(PDO::FETCH_ASSOC));

                if ($senderType === 'admin') {
                    $stmt = $pdo->query("SELECT DISTINCT
                    CASE WHEN sender_type = 'staff' THEN sender_id ELSE receiver_id END AS peer_id,
                    CASE WHEN sender_type = 'staff' THEN sender_type ELSE receiver_type END AS peer_type
                FROM chat_messages
                WHERE (sender_type = 'staff' AND receiver_type = 'admin')
                   OR (sender_type = 'admin' AND receiver_type = 'staff')");
                    $threadRows = array_merge($threadRows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                } else {
                    $stmt = $pdo->prepare("SELECT DISTINCT
                    CASE WHEN sender_type = 'admin' THEN sender_id ELSE receiver_id END AS peer_id,
                    CASE WHEN sender_type = 'admin' THEN sender_type ELSE receiver_type END AS peer_type
                FROM chat_messages
                WHERE (sender_type = 'staff' AND receiver_type = 'admin' AND sender_id = ?)
                   OR (sender_type = 'admin' AND receiver_type = 'staff' AND receiver_id = ?)");
                    $stmt->execute([$senderId, $senderId]);
                    $threadRows = array_merge($threadRows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }

                $seen = [];
                $peers = [];
                foreach ($threadRows as $row) {
                    $key = $row['peer_type'] . ':' . $row['peer_id'];
                    if (!isset($seen[$key]) && $row['peer_id']) {
                        $seen[$key] = true;
                        $peers[] = $row;
                    }
                }
            } else {
                $stmt = $pdo->prepare("SELECT DISTINCT
                    CASE WHEN sender_type != ? THEN sender_id ELSE receiver_id END as peer_id,
                    CASE WHEN sender_type != ? THEN sender_type ELSE receiver_type END as peer_type
                FROM chat_messages
                WHERE sender_type = ? OR receiver_type = ?");
                $stmt->execute([$senderType, $senderType, $senderType, $senderType]);
                $peers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($peers as &$peer) {
                if ($peer['peer_type'] === 'customer') {
                    $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                    $u->execute([$peer['peer_id']]);
                    $peer['name'] = $u->fetchColumn() ?: 'Unknown Customer';
                } elseif ($peer['peer_type'] === 'staff') {
                    $u = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'staff'");
                    $u->execute([$peer['peer_id']]);
                    $peer['name'] = $u->fetchColumn() ?: 'Unknown Staff';
                } else {
                    $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                    $u->execute([$peer['peer_id']]);
                    $peer['name'] = $u->fetchColumn() ?: 'Unknown';
                }
            }

            echo json_encode(['success' => true, 'threads' => $peers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    case 'get_staff_list':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        try {
            $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'staff' ORDER BY name ASC");
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'staff' => $staff]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    case 'get_admin_list':
        if (!$isStaff) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        try {
            $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'admin' ORDER BY name ASC");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'admins' => $admins]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}


