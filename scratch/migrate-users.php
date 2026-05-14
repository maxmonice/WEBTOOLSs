<?php
require_once 'Db.php';
$pdo = getDB();

try {
    // Add phone column if it doesn't exist
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        echo "Added phone column.\n";
    }

    // Add address column if it doesn't exist
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'address'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL");
        echo "Added address column.\n";
    }

    // Add unique index to phone? 
    // Actually, I'll handle the "rejection" in PHP to give a better error message.
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
