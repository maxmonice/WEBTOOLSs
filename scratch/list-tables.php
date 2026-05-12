<?php
require_once 'Db.php';
$pdo = getDB();
$stmt = $pdo->query("SHOW TABLES");
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
    echo $table . "\n";
}
