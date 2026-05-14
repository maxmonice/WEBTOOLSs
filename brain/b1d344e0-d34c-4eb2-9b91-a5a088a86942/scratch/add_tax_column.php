<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=lukes_seafood', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0 AFTER subtotal");
    echo "SUCCESS: Tax column added/verified.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}


