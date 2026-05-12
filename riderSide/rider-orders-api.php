<?php
// riderSide/rider-orders-api.php
// API endpoint for rider order actions

if (session_status() === PHP_SESSION_NONE) session_start();
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

// ── Fetch rider queue (processing + confirmed) ──
if ($action === 'get_rider_orders' || $action === 'get_confirmed_orders') {
    $stmt = $pdo->prepare("
        SELECT id, address, delivery_latitude, delivery_longitude, status,
               payment_method, total_amount, user_name, user_email,
               notes, eta, created_at
        FROM orders
        WHERE status IN ('processing', 'confirmed')
           OR (status = 'shipped' AND rider_id = ?)
        ORDER BY updated_at DESC
    ");
    $stmt->execute([(string)$riderId]);
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
            'customer_phone' => $notes['payment_details']['codMobile'] ?? 
                               $notes['payment_details']['gcashMobile'] ?? 
                               $notes['payment_details']['mobile'] ?? 
                               $notes['phone'] ?? '—',
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

// ── Fetch MY active orders (shipped) for Chat ──
if ($action === 'get_my_active_chats') {
    $stmt = $pdo->prepare("
        SELECT id, address, delivery_latitude, delivery_longitude, status,
               payment_method, total_amount, user_name, user_email,
               notes, eta, created_at
        FROM orders
        WHERE status = 'shipped' AND rider_id = ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([(string)$riderId]);
    $rows = $stmt->fetchAll();

    $orders = [];
    foreach ($rows as $row) {
        $notes = json_decode($row['notes'] ?? '{}', true) ?: [];
        $orders[] = [
            'id'             => $row['id'],
            'order_num'      => '#ORD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
            'status'         => $row['status'],
            'customer_name'  => $row['user_name'] ?: ($notes['user_name'] ?? 'Customer'),
            'customer_phone' => $notes['phone'] ?? '—',
            'address'        => $row['address'],
        ];
    }
    echo json_encode(['success' => true, 'orders' => $orders]);
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
        // Store rider_id as VARCHAR-compatible string
        $stmt = $pdo->prepare("UPDATE orders SET status = 'shipped', rider_id = ?, updated_at = NOW() WHERE id = ? AND status = 'confirmed'");
        $stmt->execute([(string)$riderId, $orderId]);
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
        $pdo->prepare("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?")
            ->execute([$orderId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Fetch MY delivery history ──
if ($action === 'get_history') {
    // Get total deliveries
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE rider_id = ? AND status = 'delivered'");
    $stmt->execute([(string)$riderId]);
    $totalCount = $stmt->fetch()['count'];

    // Get orders
    $stmt = $pdo->prepare("
        SELECT id, address, total_amount, user_name, notes, created_at, updated_at
        FROM orders
        WHERE rider_id = ? AND status = 'delivered'
        ORDER BY updated_at DESC
    ");
    $stmt->execute([(string)$riderId]);
    $rows = $stmt->fetchAll();

    $history = [];
    foreach ($rows as $row) {
        $notes = json_decode($row['notes'] ?? '{}', true) ?: [];
        $items = $notes['items'] ?? [];
        $history[] = [
            'id'             => $row['id'],
            'order_num'      => '#ORD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
            'customer_name'  => $row['user_name'] ?: ($notes['user_name'] ?? 'Customer'),
            'address'        => $row['address'],
            'total'          => '₱' . number_format((float)($row['total_amount'] ?? 0), 2),
            'items_count'    => count($items),
            'date'           => date('F j, Y', strtotime($row['updated_at'])),
            'time'           => date('g:i A', strtotime($row['updated_at'])),
        ];
    }
    echo json_encode(['success' => true, 'history' => $history, 'total_deliveries' => $totalCount]);
    exit;
}

// ── Fetch specific order details ──
if ($action === 'get_order_details') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT id, address, total_amount, user_name, user_email, notes, status, created_at, updated_at, payment_method
        FROM orders
        WHERE id = ? AND rider_id = ?
    ");
    $stmt->execute([$orderId, (string)$riderId]);
    $order = $stmt->fetch();

    if ($order) {
        $notes = json_decode($order['notes'] ?? '{}', true) ?: [];
        $order['items'] = $notes['items'] ?? [];
        
        // Extract phone from payment_details if it exists
        $paymentDetails = $notes['payment_details'] ?? [];
        $order['phone'] = $paymentDetails['codMobile'] ?? 
                          $paymentDetails['gcashMobile'] ?? 
                          $paymentDetails['mobile'] ?? 
                          $notes['phone'] ?? '—';
                          
        $order['order_num'] = '#ORD-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);
        $order['formatted_date'] = date('F j, Y', strtotime($order['updated_at']));
        $order['formatted_time'] = date('g:i A', strtotime($order['updated_at']));
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
