<?php
require_once 'Db.php';
$pdo = getDB();
$stmt = $pdo->query("DESCRIBE menu_items");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . "\n";
}
