<?php

declare(strict_types=1);

/**
 * Tutorial-style Xendit entry: your “Pay Now” posts here (same idea as `<form action="checkout.php">`).
 *
 * 1. Secret API key lives in `.env` as `XENDIT_SECRET_KEY` (starts with `xnd_development_…`).
 *    The real CURL call happens in includes/xendit-checkout-runner.php — keep keys out of source.
 * 2. Order totals and cart arrive as POST (`cart_json`, `total_price`, etc.) from the cart form.
 * 3. Runner creates `orders`, calls `https://api.xendit.co/v2/invoices`, then redirects the browser.
 * 4. `success_redirect_url` / `failure_redirect_url` are `success.php` and `error.php` (aliases to order-xendit-return).
 * 5. Set `PAYMENTS_DEMO=1` for class demos without hitting Xendit.
 */

require_once __DIR__ . '/env-bootstrap.php';
webtools_load_env(__DIR__);

require_once __DIR__ . '/includes/xendit-checkout-runner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: menu.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cartJsonRaw = isset($_POST['cart_json']) ? (string)$_POST['cart_json'] : '';

/** @var array<int,array<string,mixed>> */
$items = json_decode($cartJsonRaw ?: '[]', true);
if (!is_array($items)) {
    $items = [];
}

// Shape must match JSON API for shared runner (`paymentMethod` camelCase).
$payload = [
    'paymentMethod' => trim((string)($_POST['payment_method'] ?? '')),
    'items'          => $items,
    'address'        => trim((string)($_POST['address'] ?? '')),
    'subtotal'       => (float)($_POST['subtotal'] ?? 0),
    'shipping'       => (float)($_POST['shipping'] ?? 50),
    'total'          => (float)(
        $_POST['total_price']
        ?? $_POST['total']
        ?? 0
    ),
    'payer_email'    => trim((string)($_POST['payer_email'] ?? '')),
    'xendit_token'   => trim((string)($_POST['xendit_token'] ?? '')),
];

webtools_run_xendit_checkout($payload, [
    'respond_json'  => false,
    'success_page'  => 'success.php',
    'failure_page'  => 'error.php',
]);


