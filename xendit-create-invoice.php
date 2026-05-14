<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/env-bootstrap.php';
webtools_load_env(__DIR__);

require_once __DIR__ . '/includes/xendit-checkout-runner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

webtools_run_xendit_checkout($data, [
    'respond_json' => true,
    'success_page' => 'success.php',
    'failure_page' => 'error.php',
]);


