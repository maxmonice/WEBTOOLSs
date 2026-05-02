<?php
// Test suspend functionality
require_once 'admin-config.php';

// Test suspending user ID 1
$uid = 1;

try {
    // First, add status column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
        echo "Status column added successfully\n";
    } catch (\Throwable $_) {
        echo "Status column already exists\n";
    }
    
    // Check current status
    $stmt = $pdo->prepare('SELECT name, status FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    
    if ($u) {
        echo "Current user data:\n";
        echo "Name: " . $u['name'] . "\n";
        echo "Status: " . ($u['status'] ?? 'NULL') . "\n";
        
        $currentStatus = $u['status'] ?? 'active';
        $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
        echo "New status will be: $newStatus\n";
        
        $updateStmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
        $result = $updateStmt->execute([$newStatus, $uid]);
        
        if ($result) {
            echo "Update: SUCCESS\n";
            
            // Verify the update
            $stmt2 = $pdo->prepare('SELECT status FROM users WHERE id = ?');
            $stmt2->execute([$uid]);
            $updated = $stmt2->fetch();
            echo "Updated status: " . $updated['status'] . "\n";
        } else {
            echo "Update: FAILED\n";
        }
    } else {
        echo "User not found\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
