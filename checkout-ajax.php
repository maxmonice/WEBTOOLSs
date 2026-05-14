<?php
declare(strict_types=1);

require_once __DIR__ . '/env-bootstrap.php';
webtools_load_env(__DIR__);

require_once __DIR__ . '/includes/xendit-checkout-runner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$rawInput = file_get_contents('php://input');
$decoded = json_decode($rawInput, true);

if (!is_array($decoded)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Map the AJAX payload into the structure expected by the runner
$payload = [
    'paymentMethod' => trim((string)($decoded['paymentMethod'] ?? '')),
    'items'         => is_array($decoded['items'] ?? null) ? $decoded['items'] : [],
    'address'       => trim((string)($decoded['address'] ?? '')),
    'subtotal'      => (float)($decoded['subtotal'] ?? 0),
    'tax'           => (float)($decoded['tax'] ?? 0),
    'shipping'      => (float)($decoded['shipping'] ?? 50),
    'total'         => (float)($decoded['total'] ?? 0),
    'payer_email'   => trim((string)($decoded['payerEmail'] ?? '')),
    'xendit_token'  => trim((string)($decoded['xenditToken'] ?? '')),
    'gcash_mobile'  => trim((string)($decoded['gcashMobile'] ?? '')),
    'lat'           => $decoded['lat'] ?? null,
    'lng'           => $decoded['lng'] ?? null,
];

// Run the checkout with JSON response mode
webtools_run_xendit_checkout($payload, [
    'respond_json' => true,
    'success_page' => 'success.php',
    'failure_page' => 'error.php',
]);


