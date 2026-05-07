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

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$data = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

if (($data['action'] ?? '') !== 'create_order') {
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
    $userEmail      = $data['userEmail']      ?? '';
    $userName       = $data['userName']       ?? '';

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
        INSERT INTO orders (user_id, user_name, user_email, status, total_amount, address, payment_method, notes, created_at, updated_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$sessionUserId, $userName, $userEmail, $total, $address, $paymentMethod, $orderNotes]);
    $orderId = (int) $pdo->lastInsertId();

    $itemCount = count($items);
    logActivity('order_placed', "Order #{$orderId} placed — {$itemCount} item(s) — ₱{$total}", $userEmail, $userName);

    echo json_encode(['success' => true, 'order_id' => $orderId, 'message' => 'Order created successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
