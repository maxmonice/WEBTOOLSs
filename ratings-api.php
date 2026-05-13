<?php
// =====================================================
//  ratings-api.php — Rating system for riders and food
//  - Submit rider ratings after delivery
//  - Submit food ratings after delivery
//  - Get average rider ratings
//  - Get average food ratings
// =====================================================

require_once 'Db.php';

header('Content-Type: application/json');

// CORS Headers
$allowed_origins = ['http://localhost', 'http://127.0.0.1', 'http://webtoolss.test'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(204); 
    exit; 
}

session_start();

$db = getDB();

function ratingTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function ratingColumnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function getOrderRiderColumn(PDO $db): ?string {
    if (ratingColumnExists($db, 'orders', 'assigned_rider_id')) {
        return 'assigned_rider_id';
    }
    if (ratingColumnExists($db, 'orders', 'rider_id')) {
        return 'rider_id';
    }
    return null;
}

function getOrderOwnerColumn(PDO $db): string {
    return ratingColumnExists($db, 'orders', 'user_id') ? 'user_id' : 'user_email';
}

function orderBelongsToCurrentUser(array $order, string $ownerColumn, int $userId, string $userEmail): bool {
    if ($ownerColumn === 'user_id') {
        return isset($order['user_id']) && (int)$order['user_id'] === $userId;
    }
    return isset($order['user_email']) && strcasecmp((string)$order['user_email'], $userEmail) === 0;
}

function syncRiderAverage(PDO $db, int $riderId): array {
    $ratingStmt = $db->prepare("
        SELECT AVG(rating) as average_rating, COUNT(*) as rating_count
        FROM rider_ratings
        WHERE rider_id = ?
    ");
    $ratingStmt->execute([$riderId]);
    $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);

    $average = $ratingData['average_rating'] ? round($ratingData['average_rating'], 2) : null;
    $count = (int)$ratingData['rating_count'];

    if (
        ratingTableExists($db, 'riders') &&
        ratingColumnExists($db, 'riders', 'average_rating') &&
        ratingColumnExists($db, 'riders', 'rating_count')
    ) {
        $riderUpdateStmt = $db->prepare("
            UPDATE riders
            SET average_rating = ?, rating_count = ?
            WHERE id = ?
        ");
        $riderUpdateStmt->execute([$average, $count, $riderId]);
    }

    return [$average, $count];
}

// =====================================================
//  GET RIDER AVERAGE RATING
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'rider-rating') {
    try {
        $riderId = isset($_GET['rider_id']) ? (int)$_GET['rider_id'] : null;

        if (!$riderId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rider ID required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT 
                AVG(rating) as average_rating,
                COUNT(*) as rating_count,
                MIN(rating) as min_rating,
                MAX(rating) as max_rating
            FROM rider_ratings
            WHERE rider_id = ?
        ");
        $stmt->execute([$riderId]);
        $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);

        $average = $ratingData['average_rating'] ? round($ratingData['average_rating'], 2) : null;
        $count = (int)$ratingData['rating_count'];

        if (
            ratingTableExists($db, 'riders') &&
            ratingColumnExists($db, 'riders', 'average_rating') &&
            ratingColumnExists($db, 'riders', 'rating_count')
        ) {
            $updateStmt = $db->prepare("
                UPDATE riders
                SET average_rating = ?, rating_count = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$average, $count, $riderId]);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'rider_id' => $riderId,
            'average_rating' => $average,
            'rating_count' => $count,
            'min_rating' => $ratingData['min_rating'],
            'max_rating' => $ratingData['max_rating']
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// =====================================================
//  GET FOOD AVERAGE RATING
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'food-rating') {
    try {
        $menuItemId = isset($_GET['menu_item_id']) ? (int)$_GET['menu_item_id'] : null;

        if (!$menuItemId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Menu item ID required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT 
                AVG(rating) as average_rating,
                COUNT(*) as rating_count,
                MIN(rating) as min_rating,
                MAX(rating) as max_rating
            FROM food_ratings
            WHERE menu_item_id = ?
        ");
        $stmt->execute([$menuItemId]);
        $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);

        $average = $ratingData['average_rating'] ? round($ratingData['average_rating'], 2) : null;
        $count = (int)$ratingData['rating_count'];

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'menu_item_id' => $menuItemId,
            'average_rating' => $average,
            'rating_count' => $count,
            'min_rating' => $ratingData['min_rating'],
            'max_rating' => $ratingData['max_rating']
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// =====================================================
//  POST: SUBMIT RATINGS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $userEmail = $_SESSION['user_email'] ?? '';

    // ── SUBMIT RIDER RATING ──
    if ($action === 'rate-rider') {
        try {
            $orderId = isset($body['order_id']) ? (int)$body['order_id'] : null;
            $riderId = isset($body['rider_id']) ? (int)$body['rider_id'] : null;
            $rating = isset($body['rating']) ? (int)$body['rating'] : null;
            $comment = trim($body['comment'] ?? '');

            // Validation
            if (!$orderId || !$riderId || !$rating) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields (order_id, rider_id, rating)']);
                exit;
            }

            if ($rating < 1 || $rating > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
                exit;
            }

            $riderColumn = getOrderRiderColumn($db);
            $riderSelect = $riderColumn ? ", $riderColumn AS rider_ref" : ", NULL AS rider_ref";
            $ownerColumn = getOrderOwnerColumn($db);

            // Verify order belongs to user and has been delivered.
            $stmt = $db->prepare("SELECT $ownerColumn, status $riderSelect FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || !orderBelongsToCurrentUser($order, $ownerColumn, $userId, $userEmail)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Order not found or does not belong to user']);
                exit;
            }

            if ($order['status'] !== 'delivered') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Order must be delivered before rating']);
                exit;
            }

            if ($order['rider_ref'] !== null && (int)$order['rider_ref'] !== $riderId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Rider does not match this order']);
                exit;
            }

            // Insert or update rider rating
            $stmt = $db->prepare("
                INSERT INTO rider_ratings (order_id, rider_id, customer_id, rating, comment)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    rating = VALUES(rating),
                    comment = VALUES(comment),
                    updated_at = NOW()
            ");
            $stmt->execute([$orderId, $riderId, $userId, $rating, $comment]);

            if (ratingColumnExists($db, 'orders', 'delivery_rating_given')) {
                $updateStmt = $db->prepare("
                    UPDATE orders
                    SET delivery_rating_given = 1
                    WHERE id = ?
                ");
                $updateStmt->execute([$orderId]);
            }

            [$average, $count] = syncRiderAverage($db, $riderId);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Rider rating submitted successfully',
                'rider_id' => $riderId,
                'average_rating' => $average,
                'rating_count' => $count
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ── SUBMIT FOOD RATING ──
    if ($action === 'rate-food') {
        try {
            $orderId = isset($body['order_id']) ? (int)$body['order_id'] : null;
            $menuItemId = isset($body['menu_item_id']) ? (int)$body['menu_item_id'] : null;
            $rating = isset($body['rating']) ? (int)$body['rating'] : null;
            $comment = trim($body['comment'] ?? '');

            // Validation
            if (!$orderId || !$menuItemId || !$rating) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields (order_id, menu_item_id, rating)']);
                exit;
            }

            if ($rating < 1 || $rating > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
                exit;
            }

            $ownerColumn = getOrderOwnerColumn($db);

            // Verify order belongs to user and has been delivered.
            $stmt = $db->prepare("SELECT $ownerColumn, status FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || !orderBelongsToCurrentUser($order, $ownerColumn, $userId, $userEmail)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Order not found or does not belong to user']);
                exit;
            }

            if ($order['status'] !== 'delivered') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Order must be delivered before rating food']);
                exit;
            }

            if (ratingTableExists($db, 'order_items')) {
                $itemStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM order_items
                    WHERE order_id = ? AND menu_item_id = ?
                ");
                $itemStmt->execute([$orderId, $menuItemId]);
                if ((int)$itemStmt->fetchColumn() === 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Food item does not belong to this order']);
                    exit;
                }
            }

            // Insert or update food rating
            $stmt = $db->prepare("
                INSERT INTO food_ratings (order_id, menu_item_id, customer_id, rating, comment)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    rating = VALUES(rating),
                    comment = VALUES(comment),
                    updated_at = NOW()
            ");
            $stmt->execute([$orderId, $menuItemId, $userId, $rating, $comment]);

            // Get updated average rating
            $ratingStmt = $db->prepare("
                SELECT 
                    AVG(rating) as average_rating,
                    COUNT(*) as rating_count
                FROM food_ratings
                WHERE menu_item_id = ?
            ");
            $ratingStmt->execute([$menuItemId]);
            $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);

            $average = $ratingData['average_rating'] ? round($ratingData['average_rating'], 2) : null;
            $count = (int)$ratingData['rating_count'];

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Food rating submitted successfully',
                'menu_item_id' => $menuItemId,
                'average_rating' => $average,
                'rating_count' => $count
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ── MARK DELIVERY AS COMPLETED ──
    if ($action === 'complete-delivery') {
        try {
            $orderId = isset($body['order_id']) ? (int)$body['order_id'] : null;

            if (!$orderId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Order ID required']);
                exit;
            }

            $riderColumn = getOrderRiderColumn($db);
            $riderSelect = $riderColumn ? ", $riderColumn AS rider_ref" : ", NULL AS rider_ref";
            $ownerColumn = getOrderOwnerColumn($db);

            // Verify order belongs to the logged-in customer.
            $stmt = $db->prepare("SELECT id, $ownerColumn, status $riderSelect FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit;
            }

            if (!orderBelongsToCurrentUser($order, $ownerColumn, $userId, $userEmail)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Order does not belong to user']);
                exit;
            }

            if (!in_array($order['status'], ['on_route', 'shipped', 'delivered'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'This order cannot be marked as delivered yet']);
                exit;
            }

            // Update order status to delivered
            $setParts = ["status = 'delivered'"];
            if (ratingColumnExists($db, 'orders', 'delivered_at')) {
                $setParts[] = 'delivered_at = NOW()';
            }
            if (ratingColumnExists($db, 'orders', 'updated_at')) {
                $setParts[] = 'updated_at = NOW()';
            }

            $updateStmt = $db->prepare("
                UPDATE orders
                SET " . implode(', ', $setParts) . "
                WHERE id = ?
            ");
            $updateStmt->execute([$orderId]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Order marked as delivered',
                'order_id' => $orderId,
                'rider_id' => $order['rider_ref'] ? (int)$order['rider_ref'] : null,
                'requires_rating' => !empty($order['rider_ref'])
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
exit;
