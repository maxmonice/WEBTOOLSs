<?php
require_once 'db.php';
$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE role = ?');
$stmt->execute(['staff']);
$users = $stmt->fetchAll();
echo 'Staff users found: ' . count($users) . PHP_EOL;
foreach ($users as $user) {
    echo 'ID: ' . $user['id'] . ', Name: ' . $user['name'] . ', Email: ' . $user['email'] . ', Role: ' . $user['role'] . PHP_EOL;
}
?>