<?php
require_once 'Db.php';
$pdo = getDB();
$stmt = $pdo->query("DESCRIBE orders");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
