<?php
require_once 'Db.php';
$pdo = getDB();
$tables = ['menu_items', 'orders', 'order_items'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}
