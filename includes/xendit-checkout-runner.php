<?php

declare(strict_types=1);

/**
 * Shared Lukes Seafood Xendit checkout — used by checkout.php (form POST → redirect)
 * and xendit-create-invoice.php (fetch JSON → response).
 */

function webtools_notify_new_order_from_checkout(PDO $pdo, int $orderId, string $customerName, float $total, ?int $userId): void
{
    try {
        require_once dirname(__DIR__) . '/Notifications.php';
        (new Notifications($pdo))->autoNotify('new_order', [
            'id' => $orderId,
            'customer_name' => $customerName !== '' ? $customerName : 'Guest',
            'total' => number_format($total, 2),
            'user_id' => $userId,
        ]);
    } catch (Throwable $e) {
        error_log('webtools_notify_new_order_from_checkout: ' . $e->getMessage());
    }
}

/**
 * @param array<string,mixed> $data Same shape as JSON body: paymentMethod, items, address, subtotal, shipping, total, payer_email?, lat?, lng?
 * @param array{
 *   respond_json?: bool,
 *   success_page?: string,
 *   failure_page?: string,
 * } $options
 *
 * Always exits via redirect (non-JSON), JSON encode (JSON API), or error output.
 */
function webtools_run_xendit_checkout(array $data, array $options = []): void {
    require_once dirname(__DIR__) . '/activity-logger.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once dirname(__DIR__) . '/env-bootstrap.php';
    webtools_load_env(dirname(__DIR__));

    $respondJson = (bool)($options['respond_json'] ?? false);
    $successPage = (string)($options['success_page'] ?? 'success.php');
    $failurePage = (string)($options['failure_page'] ?? 'error.php');

    $demoPayments = webtools_truthy_env(getenv('PAYMENTS_DEMO') ?: null)
        || webtools_truthy_env(getenv('XENDIT_DEMO') ?: null);

    $secretKey = trim((string)(getenv('XENDIT_SECRET_KEY') ?: ''));
    $baseUrl = rtrim((string)(getenv('SITE_BASE_URL') ?: ''), '/');
    if ($baseUrl === '') {
        $baseUrl = webtools_infer_base_url();
    }

    if (!$demoPayments && $secretKey === '') {
        webtools_send_checkout_failure(
            $respondJson,
            'Missing XENDIT_SECRET_KEY — or set PAYMENTS_DEMO=1 for a simulated checkout.',
            "{$baseUrl}/{$failurePage}"
        );

        return;
    }

    if ($baseUrl === '') {
        webtools_send_checkout_failure(
            $respondJson,
            'Set SITE_BASE_URL in .env, or open the site via your Laragon URL so checkout can infer the link.',
            ''
        );

        return;
    }

    $sessionUserId = $_SESSION['user_id'] ?? null;
    $sessionUserName = trim((string)($_SESSION['user_name'] ?? ''));
    $sessionUserEmail = trim((string)($_SESSION['user_email'] ?? ''));

    if ($sessionUserId === null) {
        webtools_send_checkout_failure(
            $respondJson,
            'Sign in required to pay online.',
            "{$baseUrl}/{$failurePage}"
        );

        return;
    }

    $paymentMethod = trim((string)($data['paymentMethod'] ?? ''));
    if (!in_array($paymentMethod, ['card', 'gcash'], true)) {
        webtools_send_checkout_failure(
            $respondJson,
            'Use COD for cash on delivery, or Card/GCash for online pay.',
            "{$baseUrl}/{$failurePage}"
        );

        return;
    }

    $items = $data['items'] ?? [];
    $address = trim((string)($data['address'] ?? ''));
    $subtotal = (float)($data['subtotal'] ?? 0);
    $tax = (float)($data['tax'] ?? 0);
    $shipping = (float)($data['shipping'] ?? 50);
    $total = (float)($data['total'] ?? 0);

    if (!is_array($items) || $items === [] || $address === '') {
        webtools_send_checkout_failure(
            $respondJson,
            'Missing cart items or delivery address.',
            "{$baseUrl}/{$failurePage}"
        );

        return;
    }

    $total = round($total, 2);
    if ($total <= 0) {
        webtools_send_checkout_failure(
            $respondJson,
            'Invalid total amount.',
            "{$baseUrl}/{$failurePage}"
        );

        return;
    }

    $payerEmail = trim((string)($data['payer_email'] ?? ''));
    if ($payerEmail === '') {
        $payerEmail = $sessionUserEmail;
    }

    $lat = $data['lat'] ?? null;
    $lng = $data['lng'] ?? null;
    $xenditToken = trim((string)($data['xendit_token'] ?? ''));

    $orderNotesStruct = [
        'items'            => $items,
        'payment_details' => [
            'channel'     => $demoPayments ? 'demo_xendit' : ($xenditToken !== '' ? 'xendit_charge' : 'xendit_invoice'),
            'preference'  => $paymentMethod === 'gcash' ? 'ewallet_gcash' : 'card',
            'payer_email' => $payerEmail,
        ],
        'user_email'      => $payerEmail !== '' ? $payerEmail : $sessionUserEmail,
        'user_name'       => $sessionUserName,
        'shipping'        => $shipping,
        'subtotal'        => $subtotal,
        'tax'             => $tax,
    ];

    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Exception $e) {
        webtools_send_checkout_failure(
            $respondJson,
            'Database unavailable.',
            "{$baseUrl}/{$failurePage}"
        );

        return;
    }

    $pdo->beginTransaction();
    try {
        $notesStub = json_encode($orderNotesStruct, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare('
            INSERT INTO orders (
                user_id, user_name, user_email, status, subtotal, tax, shipping, total_amount, address,
                delivery_latitude, delivery_longitude, payment_method, notes,
                created_at, updated_at
            ) VALUES (?, ?, ?, \'pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->execute([
            $sessionUserId,
            $sessionUserName,
            $sessionUserEmail !== '' ? $sessionUserEmail : null,
            $subtotal,
            $tax,
            $shipping,
            $total,
            $address,
            $lat !== null ? (string)$lat : null,
            $lng !== null ? (string)$lng : null,
            $paymentMethod,
            $notesStub,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $externalId = sprintf('INV-ORDER-%d-%s', $orderId, substr(bin2hex(random_bytes(6)), 0, 12));
        $orderNotesStruct['xendit'] = [
            'external_id'          => $externalId,
            'invoice_id'          => null,
            'charge_id'           => null,
            'checkout_preference' => $paymentMethod,
            'paid'                => false,
        ];

        $stmt = $pdo->prepare('UPDATE orders SET notes = ? WHERE id = ?');
        $stmt->execute([json_encode($orderNotesStruct, JSON_UNESCAPED_UNICODE), $orderId]);

        $successQuery = ['order_id' => $orderId];
        $failQuery = ['order_id' => $orderId, 'payment' => 'failed'];

        if ($demoPayments) {
            $orderNotesStruct['xendit']['demo'] = true;
            $orderNotesStruct['xendit']['invoice_id'] = 'demo_no_live_api';
            $orderNotesStruct['xendit']['paid'] = true;

            $stmt = $pdo->prepare('UPDATE orders SET notes = ? WHERE id = ?');
            $stmt->execute([json_encode($orderNotesStruct, JSON_UNESCAPED_UNICODE), $orderId]);

            $pdo->commit();

            webtools_notify_new_order_from_checkout(
                $pdo,
                $orderId,
                $sessionUserName,
                $total,
                $sessionUserId !== null ? (int) $sessionUserId : null
            );

            logActivity(
                'xendit_demo_checkout',
                "Demo checkout order #{$orderId} — ₱{$total} ({$paymentMethod}, simulated)",
                (string)$sessionUserEmail,
                $sessionUserName
            );

            require_once __DIR__ . '/send-receipt.php';
            sendOrderReceiptEmail($payerEmail, $sessionUserName, $orderId, $items, $subtotal, $shipping, $total, $paymentMethod, $tax);

            $successQuery['demo'] = '1';
            $landing = "{$baseUrl}/{$successPage}?" . http_build_query($successQuery);
            if ($respondJson) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success'     => true,
                    'demo'        => true,
                    'order_id'    => $orderId,
                    'invoice_url' => $landing,
                    'external_id' => $externalId,
                ]);
            } else {
                header('Location: ' . $landing);
            }
            exit;
        }

        $itemSummary = implode(', ', array_map(static function ($i) {
            $qty = (int)($i['quantity'] ?? 1);
            $name = trim((string)($i['name'] ?? ''));

            return "{$name} ×{$qty}";
        }, $items));
        if (strlen($itemSummary) > 230) {
            $itemSummary = substr($itemSummary, 0, 227) . '…';
        }

        $invoiceSuccess = "{$baseUrl}/{$successPage}?" . http_build_query($successQuery);
        $invoiceFailure = "{$baseUrl}/{$failurePage}?" . http_build_query($failQuery);

        $gcashMobile = trim((string)($data['gcash_mobile'] ?? ''));

        if ($xenditToken !== '') {
            // SEAMLESS FLOW: Create Card Charge
            $chargeBody = [
                'external_id' => $externalId,
                'token_id'    => $xenditToken,
                'amount'      => $total,
                'currency'    => 'PHP',
            ];
            
            $endpoint = 'https://api.xendit.co/credit_card_charges';
            $logTag = 'xendit_charge_created';
        } elseif ($paymentMethod === 'gcash' && $respondJson && $gcashMobile !== '') {
            // SEAMLESS FLOW: Create E-wallet Charge (GCash)
            $formattedMobile = str_starts_with($gcashMobile, '0') ? '+63' . substr($gcashMobile, 1) : $gcashMobile;
            $chargeBody = [
                'reference_id'    => $externalId,
                'currency'        => 'PHP',
                'amount'          => $total,
                'checkout_method' => 'ONE_TIME_PAYMENT',
                'channel_code'    => 'PH_GCASH',
                'channel_properties' => [
                    'success_return_url' => "{$baseUrl}/payment-success-callback.php?order_id={$orderId}",
                    'failure_return_url' => $invoiceFailure,
                    'mobile_number'      => $formattedMobile,
                ],
            ];
            $endpoint = 'https://api.xendit.co/ewallets/charges';
            $logTag = 'xendit_ewallet_charge_created';
        } else {
            // REDIRECT FLOW: Create Invoice
            $finalSuccessUrl = $invoiceSuccess;
            if ($respondJson && $paymentMethod === 'gcash') {
                $finalSuccessUrl = "{$baseUrl}/payment-success-callback.php?order_id={$orderId}";
            }

            $chargeBody = [
                'external_id'          => $externalId,
                'amount'               => $total,
                'currency'             => 'PHP',
                'description'          => "Luke's Seafood order #{$orderId} — {$itemSummary}",
                'invoice_duration'      => 86400,
                'success_redirect_url' => $finalSuccessUrl,
                'failure_redirect_url' => $invoiceFailure,
            ];

            if ($paymentMethod === 'gcash') {
                $chargeBody['payment_methods'] = ['GCASH'];
            }

            if ($payerEmail !== '') {
                $chargeBody['customer'] = [
                    'given_names' => $sessionUserName !== '' ? $sessionUserName : 'Customer',
                    'email'       => $payerEmail,
                ];
            }
            
            $endpoint = 'https://api.xendit.co/v2/invoices';
            $logTag = 'xendit_invoice_created';
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialise HTTP client');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
            CURLOPT_POSTFIELDS     => json_encode($chargeBody),
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErr !== '') {
            throw new RuntimeException('Xendit request failed: ' . ($curlErr ?: 'empty response'));
        }

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode((string)$response, true);
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            $reason = '';
            if (isset($decoded['message'])) {
                $reason = ' — ' . (is_string($decoded['message']) ? $decoded['message'] : json_encode($decoded['message']));
            }
            throw new RuntimeException("Xendit returned HTTP {$httpCode}{$reason}");
        }

        $invoiceUrl = $decoded['invoice_url'] ?? null;
        $invoiceId = $decoded['id'] ?? null;
        
        if ($xenditToken !== '') {
            // Charge response
            if (($decoded['status'] ?? '') === 'CAPTURED') {
                $orderNotesStruct['xendit']['paid'] = true;
                $orderNotesStruct['xendit']['charge_id'] = $invoiceId;
                $redirectUrl = $invoiceSuccess;
            } elseif (($decoded['status'] ?? '') === 'IN_REVIEW' && isset($decoded['actions']['authentication_url'])) {
                $redirectUrl = $decoded['actions']['authentication_url'];
            } else {
                throw new RuntimeException('Charge failed: ' . ($decoded['status'] ?? 'unknown'));
            }
        } elseif ($paymentMethod === 'gcash' && $respondJson && $gcashMobile !== '') {
            // E-wallet response
            $invoiceId = $decoded['id'] ?? null;
            $redirectUrl = $decoded['actions']['desktop_web_checkout_url'] 
                        ?? $decoded['actions']['mobile_web_checkout_url'] 
                        ?? null;
            
            if (!$redirectUrl) {
                throw new RuntimeException('E-wallet response missing checkout URL');
            }
            $orderNotesStruct['xendit']['charge_id'] = $invoiceId;
        } else {
            // Invoice response
            if (!$invoiceUrl || !is_string($invoiceUrl)) {
                throw new RuntimeException('Xendit response missing invoice_url');
            }
            $orderNotesStruct['xendit']['invoice_id'] = $invoiceId;
            $redirectUrl = $invoiceUrl;
        }

        $stmt = $pdo->prepare('UPDATE orders SET notes = ? WHERE id = ?');
        $stmt->execute([json_encode($orderNotesStruct, JSON_UNESCAPED_UNICODE), $orderId]);

        $pdo->commit();

        webtools_notify_new_order_from_checkout(
            $pdo,
            $orderId,
            $sessionUserName,
            $total,
            $sessionUserId !== null ? (int) $sessionUserId : null
        );

        logActivity(
            $logTag,
            "{$logTag} for order #{$orderId} — ₱{$total} ({$paymentMethod})",
            (string)$sessionUserEmail,
            $sessionUserName
        );

        if ($xenditToken !== '') {
            require_once __DIR__ . '/send-receipt.php';
            sendOrderReceiptEmail($payerEmail, $sessionUserName, $orderId, $items, $subtotal, $shipping, $total, $paymentMethod, $tax);
        }

        if ($respondJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success'     => true,
                'order_id'    => $orderId,
                'invoice_url' => $redirectUrl,
                'external_id' => $externalId,
            ]);
        } else {
            header('Location: ' . $redirectUrl);
        }
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        webtools_send_checkout_failure(
            $respondJson,
            'Could not create payment link: ' . $e->getMessage(),
            "{$baseUrl}/{$failurePage}?reason=" . rawurlencode('api')
        );
    }
}

/**
 * @internal
 *
 * @return never
 */
function webtools_send_checkout_failure(bool $respondJson, string $message, string $redirectFailure): void {
    if ($respondJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    if ($redirectFailure !== '') {
        $sep = str_contains($redirectFailure, '?') ? '&' : '?';
        header('Location: ' . $redirectFailure . $sep . 'msg=' . rawurlencode($message));
        exit;
    }

    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';

    exit;
}
