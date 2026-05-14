<?php
require_once 'Db.php';
require_once 'Notifications.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$pdo = getDB();
$userId = $_SESSION['user_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $data['type'] ?? ''; // 'rider' or 'food'
$rating = (int)($data['rating'] ?? 0);
$orderId = (int)($data['order_id'] ?? 0);

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

if ($type === 'rider') {
    try {
        // Find the rider_id for this order
        $stmt = $pdo->prepare("SELECT rider_id FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $riderId = $stmt->fetchColumn();

        if (!$riderId) {
            echo json_encode(['success' => false, 'message' => 'No rider assigned to this order']);
            exit;
        }

        $insert = $pdo->prepare("INSERT INTO reviews (order_id, user_id, review_type, target_id, rating, created_at) VALUES (?, ?, 'rider', ?, ?, NOW())");
        $insert->execute([$orderId, $userId, $riderId, $rating]);

        echo json_encode(['success' => true, 'message' => 'Rider rated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save rider rating: ' . $e->getMessage()]);
    }
    exit;
}

if ($type === 'food') {
    $items = $data['items'] ?? [];
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items to rate']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        foreach ($items as $item) {
            $itemName = $item['name'] ?? '';
            if (!$itemName) continue;

            // Find the item in menu_items
            $stmt = $pdo->prepare("SELECT id, rating, rating_count FROM menu_items WHERE name = ? LIMIT 1");
            $stmt->execute([$itemName]);
            $menuItem = $stmt->fetch();

            if ($menuItem) {
                // Insert into reviews table
                $ins = $pdo->prepare("INSERT INTO reviews (order_id, user_id, review_type, target_id, rating, created_at) VALUES (?, ?, 'food', ?, ?, NOW())");
                $ins->execute([$orderId, $userId, $menuItem['id'], $rating]);

                // Legacy update for menu_items table
                $oldRating = (float)$menuItem['rating'];
                $oldCount = (int)$menuItem['rating_count'];
                $newCount = $oldCount + 1;
                $newRating = (($oldRating * $oldCount) + $rating) / $newCount;
                $newRating = round($newRating, 1);

                $update = $pdo->prepare("UPDATE menu_items SET rating = ?, rating_count = ? WHERE id = ?");
                $update->execute([$newRating, $newCount, $menuItem['id']]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Food items rated successfully']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save food rating: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
