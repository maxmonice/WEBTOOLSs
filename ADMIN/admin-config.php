<?php
// Database configuration
$host = 'localhost';
$dbname = 'luke_seafood_trading';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

// Start the session
session_start();

// Helper functions
function flash($message) {
    $_SESSION['flash'] = $message;
}

function old($key, $default = '') {
    return isset($_SESSION['old'][$key]) ? $_SESSION['old'][$key] : $default;
}
?>