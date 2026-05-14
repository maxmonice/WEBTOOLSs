<?php
$host = 'localhost';
$dbname = 'lukes_seafood';
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll());
