<?php
// =====================================================
//  customer-orders-api.php — Customer order history
//  Returns all orders for the logged-in user with
//  items, rider info, and rating status
// =====================================================

require_once 'Db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';
$db = getDB();

function customerTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function customerColumnExists(PDO $db, string $table, string $column): bool {
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

// =====================================================
//  GET: Fetch user's orders
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'my-orders') {
        try {
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $hasAssignedRiderId = customerColumnExists($db, 'orders', 'assigned_rider_id');
            $hasLegacyRiderId = customerColumnExists($db, 'orders', 'rider_id');
            $riderColumn = $hasAssignedRiderId ? 'assigned_rider_id' : ($hasLegacyRiderId ? 'rider_id' : null);
            $riderSelect = $riderColumn ? "o.$riderColumn AS rider_ref," : "NULL AS rider_ref,";
            $totalExpr = customerColumnExists($db, 'orders', 'total_amount') && customerColumnExists($db, 'orders', 'total')
                ? 'COALESCE(o.total_amount, o.total, 0)'
                : (customerColumnExists($db, 'orders', 'total_amount') ? 'COALESCE(o.total_amount, 0)' : 'COALESCE(o.total, 0)');
            $deliveredAtSelect = customerColumnExists($db, 'orders', 'delivered_at') ? 'o.delivered_at,' : 'NULL AS delivered_at,';
            $updatedAtSelect = customerColumnExists($db, 'orders', 'updated_at') ? 'o.updated_at,' : 'NULL AS updated_at,';
            $notesSelect = customerColumnExists($db, 'orders', 'notes') ? 'o.notes,' : 'NULL AS notes,';
            $itemsJsonSelect = customerColumnExists($db, 'orders', 'items') ? 'o.items AS items_json,' : 'NULL AS items_json,';
            $deliveryRatingSelect = customerColumnExists($db, 'orders', 'delivery_rating_given')
                ? 'COALESCE(o.delivery_rating_given, 0) as delivery_rating_given,'
                : '0 as delivery_rating_given,';
            $hasRidersTable = customerTableExists($db, 'riders');
            $riderJoin = ($hasRidersTable && $riderColumn)
                ? "LEFT JOIN riders r ON CAST(o.$riderColumn AS UNSIGNED) = r.id"
                : "";
            $riderFields = $hasRidersTable
                ? "r.name as rider_name, r.phone as rider_phone, r.average_rating as rider_avg_rating"
                : "NULL as rider_name, NULL as rider_phone, NULL as rider_avg_rating";
            $hasUserId = customerColumnExists($db, 'orders', 'user_id');
            $whereClause = $hasUserId ? 'o.user_id = ?' : 'o.user_email = ?';
            $ownerParam = $hasUserId ? $userId : $userEmail;

            // Fetch orders with rider info when available
            $stmt = $db->prepare("
                SELECT 
                    o.id,
                    o.status,
                    $totalExpr AS total_amount,
                    o.subtotal,
                    o.shipping,
                    o.address,
                    o.payment_method,
                    $notesSelect
                    $itemsJsonSelect
                    o.created_at,
                    $updatedAtSelect
                    $deliveredAtSelect
                    $riderSelect
                    $deliveryRatingSelect
                    $riderFields
                FROM orders o
                $riderJoin
                WHERE $whereClause
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$ownerParam, $limit, $offset]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process each order
            $processedOrders = [];
            foreach ($orders as $order) {
                // Parse items from notes JSON
                $notes = json_decode($order['notes'] ?? '{}', true) ?: [];
                $items = $notes['items'] ?? [];
                if (!$items && !empty($order['items_json'])) {
                    $items = json_decode($order['items_json'], true) ?: [];
                }

                if (customerTableExists($db, 'order_items') && customerTableExists($db, 'menu_items')) {
                    $itemStmt = $db->prepare("
                        SELECT
                            oi.menu_item_id,
                            oi.quantity,
                            oi.price_at_purchase,
                            mi.name
                        FROM order_items oi
                        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
                        WHERE oi.order_id = ?
                        ORDER BY oi.id ASC
                    ");
                    $itemStmt->execute([$order['id']]);
                    $dbItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($dbItems) {
                        $items = array_map(function ($item) {
                            return [
                                'id' => (int)$item['menu_item_id'],
                                'menu_item_id' => (int)$item['menu_item_id'],
                                'name' => $item['name'] ?: 'Item #' . (int)$item['menu_item_id'],
                                'quantity' => (int)$item['quantity'],
                                'price' => (float)$item['price_at_purchase']
                            ];
                        }, $dbItems);
                    }
                }

                // Check if user already rated this order's rider
                $riderRated = false;
                if ($order['rider_ref']) {
                    $ratingStmt = $db->prepare("
                        SELECT rating, comment FROM rider_ratings 
                        WHERE order_id = ? AND customer_id = ?
                    ");
                    $ratingStmt->execute([$order['id'], $userId]);
                    $existingRating = $ratingStmt->fetch(PDO::FETCH_ASSOC);
                    $riderRated = $existingRating ? true : false;
                }

                // Get food ratings for this order
                $foodRatingStmt = $db->prepare("
                    SELECT menu_item_id, rating, comment FROM food_ratings
                    WHERE order_id = ? AND customer_id = ?
                ");
                $foodRatingStmt->execute([$order['id'], $userId]);
                $foodRatings = $foodRatingStmt->fetchAll(PDO::FETCH_ASSOC);
                $foodRatingsMap = [];
                foreach ($foodRatings as $fr) {
                    $foodRatingsMap[$fr['menu_item_id']] = [
                        'rating' => (int)$fr['rating'],
                        'comment' => $fr['comment']
                    ];
                }

                $processedOrders[] = [
                    'id' => (int)$order['id'],
                    'status' => $order['status'],
                    'total' => floatval($order['total_amount'] ?? 0),
                    'subtotal' => floatval($order['subtotal'] ?? $notes['subtotal'] ?? 0),
                    'shipping' => floatval($order['shipping'] ?? $notes['shipping'] ?? 0),
                    'address' => $order['address'],
                    'payment_method' => $order['payment_method'],
                    'items' => $items,
                    'created_at' => $order['created_at'],
                    'delivered_at' => $order['delivered_at'],
                    'rider_id' => $order['rider_ref'] ? (int)$order['rider_ref'] : null,
                    'rider_name' => $order['rider_name'] ?: ($order['rider_ref'] ? 'Rider #' . (int)$order['rider_ref'] : null),
                    'rider_phone' => $order['rider_phone'],
                    'rider_avg_rating' => $order['rider_avg_rating'] ? floatval($order['rider_avg_rating']) : null,
                    'delivery_rating_given' => (bool)$order['delivery_rating_given'],
                    'rider_rated' => $riderRated,
                    'food_ratings' => $foodRatingsMap,
                    'can_mark_delivered' => in_array($order['status'], ['on_route', 'shipped'], true),
                    'can_rate' => $order['status'] === 'delivered' && !$riderRated && $order['rider_ref']
                ];
            }

            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $whereClause");
            $countStmt->execute([$ownerParam]);
            $totalCount = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'orders' => $processedOrders,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset
            ]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request']);
