<?php
$host = 'localhost';
$dbname = 'lukes_seafood';
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$stmt = $pdo->query("SELECT notes FROM orders ORDER BY id DESC LIMIT 1");
print_r($stmt->fetch());


