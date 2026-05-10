<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=lukes_seafood;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT id, status, user_id, updated_at FROM orders ORDER BY id DESC LIMIT 5');
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
