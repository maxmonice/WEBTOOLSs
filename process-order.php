<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'activity-logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

header('Content-Type: application/json');

// Get logged-in user from session
if (session_status() === PHP_SESSION_NONE) session_start();
$sessionUserId = $_SESSION['user_id'] ?? null;
$sessionUserName = $_SESSION['user_name'] ?? '';
$sessionUserEmail = $_SESSION['user_email'] ?? '';

$rawInput = file_get_contents('php://input');
$data = [];

// Accept both JSON payloads and classic form-data from checkout.
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $data = $decoded;
    }
}

if (empty($data) && !empty($_POST)) {
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $totalRaw = preg_replace('/[^0-9.\-]/', '', (string)($_POST['total'] ?? '0'));
    $subtotal = is_array($cart)
        ? array_sum(array_map(fn($i) => ((float)($i['rawPrice'] ?? 0)) * ((int)($i['quantity'] ?? 1)), $cart))
        : 0;

    $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
    $paymentDetails = [];
    if ($paymentMethod === 'card') {
        $paymentDetails = [
            'card_name'   => trim((string)($_POST['cardName'] ?? '')),
            'card_number' => trim((string)($_POST['cardNumber'] ?? '')),
            'card_expiry' => trim((string)($_POST['cardExpiry'] ?? '')),
            'card_cvv'    => trim((string)($_POST['cardCvv'] ?? '')),
        ];
    } elseif ($paymentMethod === 'cod') {
        $paymentDetails = [
            'receiver_name' => trim((string)($_POST['codName'] ?? '')),
            'mobile'        => trim((string)($_POST['codMobile'] ?? '')),
        ];
    } elseif ($paymentMethod === 'gcash') {
        $paymentDetails = [
            'reference' => trim((string)($_POST['gcashRef'] ?? '')),
            'mobile'    => trim((string)($_POST['gcashNumber'] ?? '')),
        ];
    }

    $data = [
        'action'         => 'create_order',
        'items'          => is_array($cart) ? $cart : [],
        'address'        => trim((string)($_POST['address'] ?? '')),
        'paymentMethod'  => $paymentMethod,
        'paymentDetails' => $paymentDetails,
        'subtotal'       => $subtotal,
        'tax'            => $subtotal * 0.12,
        'shipping'       => 50,
        'total'          => (float)$totalRaw,
    ];
}

if (($data['action'] ?? 'create_order') !== 'create_order') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    require_once __DIR__ . '/Db.php';
    $pdo = getDB();

    $items          = $data['items']          ?? [];
    $address        = $data['address']        ?? '';
    $paymentMethod  = $data['paymentMethod']  ?? '';
    $paymentDetails = $data['paymentDetails'] ?? [];
    $total          = floatval($data['total'] ?? 0);
    $userEmail      = trim((string)($data['userEmail'] ?? $sessionUserEmail));
    $userName       = trim((string)($data['userName'] ?? $sessionUserName));
    $lat            = $data['lat'] ?? null; // Latitude from customer
    $lng            = $data['lng'] ?? null; // Longitude from customer

    if (empty($items) || empty($address) || empty($paymentMethod)) {
        echo json_encode(['success' => false, 'message' => 'Missing required order information']);
        exit;
    }

    // --- Phone Number Validation ---
    $currentMobile = trim((string)($paymentDetails['mobile'] ?? ''));
    if (!empty($currentMobile)) {
        // Check if this mobile number has been used by a different email in past orders
        $checkStmt = $pdo->prepare("
            SELECT user_email FROM orders 
            WHERE notes LIKE ? AND user_email != ? 
            LIMIT 1
        ");
        $checkStmt->execute(['%' . $currentMobile . '%', $userEmail]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            echo json_encode([
                'success' => false, 
                'message' => 'This phone number is already registered to another customer account. Please use a different number.'
            ]);
            exit;
        }
    }

    $orderNotes = json_encode([
        'items'           => $items,
        'payment_details' => $paymentDetails,
        'user_email'      => $userEmail,
        'user_name'       => $userName,
        'shipping'        => floatval($data['shipping'] ?? 0),
        'subtotal'        => floatval($data['subtotal'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, user_name, user_email, status, subtotal, tax, shipping, total_amount, address, delivery_latitude, delivery_longitude, payment_method, notes, created_at, updated_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $sessionUserId, $userName, $userEmail, 
        floatval($data['subtotal'] ?? 0), 
        floatval($data['tax'] ?? 0), 
        floatval($data['shipping'] ?? 0), 
        $total, $address, $lat, $lng, $paymentMethod, $orderNotes
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemCount = count($items);
    logActivity('order_placed', "Order #{$orderId} placed — {$itemCount} item(s) — ₱{$total}", $userEmail, $userName);

    try {
        require_once __DIR__ . '/Notifications.php';
        (new Notifications($pdo))->autoNotify('new_order', [
            'id' => $orderId,
            'customer_name' => $userName !== '' ? $userName : 'Guest',
            'total' => number_format($total, 2),
            'user_id' => $sessionUserId ? (int) $sessionUserId : null,
        ]);
    } catch (Throwable $e) {
        error_log('process-order new_order notify: ' . $e->getMessage());
    }

    require_once __DIR__ . '/includes/send-receipt.php';
    sendOrderReceiptEmail($userEmail, $userName, $orderId, $items, floatval($data['subtotal'] ?? 0), floatval($data['shipping'] ?? 0), $total, $paymentMethod, floatval($data['tax'] ?? 0));

    echo json_encode(['success' => true, 'order_id' => $orderId, 'message' => 'Order created successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}


