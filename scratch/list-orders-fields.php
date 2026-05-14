<?php
require_once 'Db.php';
$pdo = getDB();
$stmt = $pdo->query("DESCRIBE orders");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . ": " . $row['Type'] . "\n";
}


