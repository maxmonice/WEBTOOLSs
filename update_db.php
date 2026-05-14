<?php
require_once 'Db.php';

try {
    $db = getDB();
    
    // Check if eta column exists
    $stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'eta'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE orders ADD COLUMN eta VARCHAR(50) DEFAULT NULL AFTER status");
        echo "Added 'eta' column. ";
    }
    
    // Check if rider_id column exists
    $stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'rider_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE orders ADD COLUMN rider_id INT DEFAULT NULL AFTER user_name");
        echo "Added 'rider_id' column. ";
    }
    
    echo "Database updated successfully.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>


