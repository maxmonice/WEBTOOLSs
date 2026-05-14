<?php
// Run once to add rider GPS columns to the orders table
$pdo = new PDO("mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$queries = [
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS rider_lat DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS rider_lng DECIMAL(10,7) DEFAULT NULL",
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ " . $sql . "<br>";
    } catch (Exception $e) {
        echo "⚠️ " . $e->getMessage() . "<br>";
    }
}
echo "<br><strong>Done! You can now delete this file.</strong>";


