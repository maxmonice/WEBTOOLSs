<?php
$host = '127.0.0.1';
$db   = 'lukes_seafood';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $count = $stmt->fetchColumn();
    echo "Total orders in database: " . $count . "\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, status, user_name, total_amount FROM orders LIMIT 5");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
    } else {
        echo "The 'orders' table is completely empty.\n";
    }
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>


