<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('DESCRIBE users');
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
