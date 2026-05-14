<?php
require_once 'Db.php';
$pdo = getDB();

$sql = "
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT DEFAULT NULL,
    sender_id INT DEFAULT NULL,
    sender_type ENUM('customer', 'rider', 'admin', 'staff', 'support') NOT NULL,
    receiver_id INT DEFAULT NULL,
    receiver_type ENUM('customer', 'rider', 'admin', 'staff', 'support') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);";

try {
    $pdo->exec($sql);
    echo "chat_messages table created successfully.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
