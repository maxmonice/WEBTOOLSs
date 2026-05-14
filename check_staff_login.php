<?php
require_once 'db.php';
$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
$stmt->execute(['staff@gmail.com']);
$user = $stmt->fetch();
if ($user) {
    echo 'User found: ID=' . $user['id'] . ', Name=' . $user['name'] . ', Email=' . $user['email'] . ', Role=' . $user['role'] . PHP_EOL;
    echo 'Password hash exists: ' . (!empty($user['password_hash']) ? 'YES' : 'NO') . PHP_EOL;
} else {
    echo 'Staff user not found' . PHP_EOL;
}
?>

