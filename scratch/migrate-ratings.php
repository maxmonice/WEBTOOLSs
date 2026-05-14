<?php
require_once 'Db.php';
$pdo = getDB();

try {
    // Add rating_count if it doesn't exist
    $check = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'rating_count'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN rating_count INT DEFAULT 0");
        echo "Added rating_count column.\n";
    }

    // Initialize items with 5 stars as requested
    $pdo->exec("UPDATE menu_items SET rating = 5.0, rating_count = 1 WHERE rating_count = 0");
    echo "Initialized empty ratings to 5.0.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
