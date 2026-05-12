<?php
require_once 'Db.php';
$pdo = getDB();

// Create a rider user if not exists
$riderEmail = 'rider@gmail.com';
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$riderEmail]);
if (!$stmt->fetch()) {
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)")
        ->execute(['Test Rider', $riderEmail, $hash, 'rider', 'active']);
    echo "Rider user created: rider@gmail.com / password123\n";
} else {
    // Ensure existing user has rider role
    $pdo->prepare("UPDATE users SET role = 'rider' WHERE email = ?")->execute([$riderEmail]);
    echo "Rider role updated for rider@gmail.com\n";
}
