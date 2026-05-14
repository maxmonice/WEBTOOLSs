<?php

declare(strict_types=1);

require_once __DIR__ . '/env-bootstrap.php';

webtools_load_env(__DIR__);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$callbackToken = trim((string)(getenv('XENDIT_WEBHOOK_TOKEN') ?: ''));

if ($callbackToken !== '') {
    $header = '';
    foreach ($_SERVER as $key => $value) {
        if ($key === 'HTTP_X_CALLBACK_TOKEN') {
            $header = trim((string)$value);
            break;
        }
    }

    if ($header !== $callbackToken) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$raw = file_get_contents('php://input');
$evt = json_decode((string)$raw, true);

if (!is_array($evt)) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

// Xendit may wrap the invoice in `data`; merge for consistent reads.
foreach (['data', 'invoice', 'payload'] as $nestedKey) {
    if (isset($evt[$nestedKey]) && is_array($evt[$nestedKey])) {
        foreach ($evt[$nestedKey] as $nk => $nv) {
            if (!isset($evt[$nk])) {
                $evt[$nk] = $nv;
            }
        }
    }
}

$id = isset($evt['id']) ? (string)$evt['id'] : '';
$statusFromRoot = strtolower((string)($evt['status'] ?? ''));

$possibleExternal = (string)($evt['external_id'] ?? '');
if ($possibleExternal === '' && (($evt['invoice_id'] ?? '') !== '')) {
    $possibleExternal = (string)$evt['invoice_id'];
}

if (($evt['invoice'] ?? null) !== null && is_array($evt['invoice'])) {
    /** @var array<string,mixed> $inv */
    $inv = $evt['invoice'];
    if (($inv['external_id'] ?? '') !== '') {
        $possibleExternal = (string)$inv['external_id'];
    }
}

$effectiveStatus = $statusFromRoot;

if (($evt['invoice'] ?? null) !== null && is_array($evt['invoice'])) {
    $invSt = strtolower((string)($evt['invoice']['status'] ?? ''));
    if ($invSt !== '') {
        $effectiveStatus = $invSt;
    }
}

foreach (['paid', 'settled'] as $ok) {
    if ($effectiveStatus !== $ok) {
        continue;
    }

    if ($possibleExternal === '' || strpos($possibleExternal, 'INV-ORDER-') !== 0) {
        break;
    }

    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $possibleExternal) . '%';

        $stmt = $pdo->prepare(
            'SELECT id, notes FROM orders WHERE notes LIKE ? ORDER BY id DESC LIMIT 20'
        );
        $stmt->execute([$like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $notes = json_decode((string)$row['notes'], true);
            if (!is_array($notes)) {
                continue;
            }

            $extStored = isset($notes['xendit']['external_id']) ? (string)$notes['xendit']['external_id'] : '';
            if ($extStored !== $possibleExternal) {
                continue;
            }

            $notes['xendit']['paid']               = true;
            $notes['xendit']['webhook_invoice_id'] = $id ?: ($evt['invoice_id'] ?? null);
            $notes['xendit']['webhook_received_at'] = gmdate('c');

            // FIX: Also update the status column so the order moves out of 'pending'
            $upd = $pdo->prepare(
                'UPDATE orders SET status = ?, notes = ?, updated_at = NOW() WHERE id = ? LIMIT 1'
            );
            $upd->execute(['confirmed', json_encode($notes, JSON_UNESCAPED_UNICODE), (int)$row['id']]);

            break;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'DB error';
        exit;
    }

    break;
}

http_response_code(200);
echo 'OK';

