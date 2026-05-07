<?php
declare(strict_types=1);

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=lukes_seafood;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "=== orders schema ===\n";
    $describe = $pdo->query("DESCRIBE orders")->fetchAll();
    foreach ($describe as $col) {
        echo $col['Field'] . " | " . $col['Type'] . " | Null=" . $col['Null'] . " | Default=" . ($col['Default'] ?? 'NULL') . "\n";
    }

    $notes = json_encode([
        'items' => [
            ['name' => 'Debug Item', 'price' => 120, 'quantity' => 1],
        ],
        'user_name' => 'Debug User',
        'user_email' => 'debug@example.com',
        'shipping' => 50,
        'subtotal' => 120,
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, user_name, user_email, status, total_amount,
            address, delivery_latitude, delivery_longitude, payment_method,
            notes, created_at, updated_at
        )
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        null,
        'Debug User',
        'debug@example.com',
        170,
        'Debug Address, Taguig',
        14.55,
        121.05,
        'cod',
        $notes
    ]);

    $id = (int)$pdo->lastInsertId();
    echo "\nInserted test order ID: {$id}\n";

    $count = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    echo "Total orders in table: {$count}\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
