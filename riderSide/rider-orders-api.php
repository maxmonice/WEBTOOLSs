<?php
// riderSide/rider-orders-api.php
// API endpoint for rider order actions

require_once __DIR__ . '/rider-session.php';
startRiderSession();
if (empty($_SESSION['rider_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$riderId = $_SESSION['rider_id']; // may be a string like 'LKS-R-0001'

$host   = 'localhost';
$dbname = 'lukes_seafood';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function orderColumnExists(PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$column]);
    return (int)$stmt->fetchColumn() > 0;
}

// ── Fetch rider queue (processing + confirmed) ──
if ($action === 'get_rider_orders' || $action === 'get_confirmed_orders') {
    $stmt = $pdo->prepare("
        SELECT id, address, delivery_latitude, delivery_longitude, status,
               payment_method, total_amount, user_name, user_email,
               notes, eta, created_at
        FROM orders
        WHERE status IN ('processing', 'confirmed')
        ORDER BY updated_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        // Parse items from notes JSON
        $notes = json_decode($row['notes'] ?? '{}', true) ?: [];
        $items = $notes['items'] ?? [];

        // Build items summary
        $itemsSummary = implode(', ', array_map(function($i) {
            return ($i['name'] ?? 'Item') . (isset($i['quantity']) && $i['quantity'] > 1 ? ' x' . $i['quantity'] : '');
        }, $items));

        $orders[] = [
            'id'             => $row['id'],
            'order_num'      => '#ORD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
            'status'         => $row['status'],
            'customer_name'  => $row['user_name'] ?: ($notes['user_name'] ?? 'Customer'),
            'customer_phone' => $notes['phone'] ?? '—',
            'address'        => $row['address'],
            'payment'        => $row['payment_method'],
            'lat'            => $row['delivery_latitude'],
            'lng'            => $row['delivery_longitude'],
            'total'          => '₱' . number_format((float)($row['total_amount'] ?? $row['total'] ?? 0), 2),
            'eta'            => $row['eta'] ?? '',
            'items'          => $items,
            'items_summary'  => $itemsSummary,
            'created_at'     => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'orders' => $orders, 'count' => count($orders)]);
    exit;
}

// ── Accept order: status → shipped, set rider_id ──
if ($action === 'accept_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Missing order ID']);
        exit;
    }
    try {
        $setParts = ["status = 'shipped'"];
        $values = [];
        if (orderColumnExists($pdo, 'rider_id')) {
            $setParts[] = 'rider_id = ?';
            $values[] = (string)$riderId;
        }
        if (orderColumnExists($pdo, 'assigned_rider_id')) {
            $setParts[] = 'assigned_rider_id = ?';
            $values[] = (int)$riderId;
        }
        if (orderColumnExists($pdo, 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        $values[] = $orderId;

        $stmt = $pdo->prepare("UPDATE orders SET " . implode(', ', $setParts) . " WHERE id = ? AND status = 'confirmed'");
        $stmt->execute($values);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Order is not sent to rider yet.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Order accepted! Head to the pickup.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Mark as delivered ──
if ($action === 'deliver_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Missing order ID']);
        exit;
    }
    try {
        $setParts = ["status = 'delivered'"];
        if (orderColumnExists($pdo, 'delivered_at')) {
            $setParts[] = 'delivered_at = NOW()';
        }
        if (orderColumnExists($pdo, 'updated_at')) {
            $setParts[] = 'updated_at = NOW()';
        }
        $pdo->prepare("UPDATE orders SET " . implode(', ', $setParts) . " WHERE id = ?")
            ->execute([$orderId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
