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
    // For now, we just acknowledge rider ratings. 
    // In a full implementation, you'd update a 'riders' table.
    echo json_encode(['success' => true, 'message' => 'Rider rated successfully']);
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
                $oldRating = (float)$menuItem['rating'];
                $oldCount = (int)$menuItem['rating_count'];
                
                // Calculate new average rating
                // New Rating = ((Old Rating * Old Count) + New Rating) / (Old Count + 1)
                $newCount = $oldCount + 1;
                $newRating = (($oldRating * $oldCount) + $rating) / $newCount;
                
                // Round to 1 decimal place
                $newRating = round($newRating, 1);

                $update = $pdo->prepare("UPDATE menu_items SET rating = ?, rating_count = ? WHERE id = ?");
                $update->execute([$newRating, $newCount, $menuItem['id']]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Food items rated successfully']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save rating: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
