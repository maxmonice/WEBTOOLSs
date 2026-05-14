<?php
require_once 'Db.php';
$pdo = getDB();
try {
    $pdo->exec("ALTER TABLE promos ADD COLUMN applicable_category VARCHAR(100) DEFAULT 'All Items'");
    echo "Column added successfully.\n";
} catch (Exception $e) {
    echo "Column might already exist: " . $e->getMessage() . "\n";
}

$stmt = $pdo->query("SELECT * FROM promos");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));


