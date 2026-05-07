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
        'shipping'       => 50,
        'total'          => (float)$totalRaw,
    ];
}

if (($data['action'] ?? 'create_order') !== 'create_order') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4",
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

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

    $orderNotes = json_encode([
        'items'           => $items,
        'payment_details' => $paymentDetails,
        'user_email'      => $userEmail,
        'user_name'       => $userName,
        'shipping'        => floatval($data['shipping'] ?? 0),
        'subtotal'        => floatval($data['subtotal'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, user_name, user_email, status, total_amount, address, delivery_latitude, delivery_longitude, payment_method, notes, created_at, updated_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$sessionUserId, $userName, $userEmail, $total, $address, $lat, $lng, $paymentMethod, $orderNotes]);
    $orderId = (int) $pdo->lastInsertId();

    $itemCount = count($items);
    logActivity('order_placed', "Order #{$orderId} placed — {$itemCount} item(s) — ₱{$total}", $userEmail, $userName);

    echo json_encode(['success' => true, 'order_id' => $orderId, 'message' => 'Order created successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
