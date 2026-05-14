<?php
require_once 'Db.php';
$pdo = getDB();

echo "Starting Database Normalization...\n";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. audit_logs — Burahin ang Redundancy
    echo "Normalizing audit_logs...\n";
    // Siguraduhin na may user_id column
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'user_id'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE audit_logs ADD COLUMN user_id INT UNSIGNED NULL AFTER id");
    }

    // Map existing names/emails to IDs if columns still exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'user_email'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->exec("UPDATE audit_logs al JOIN users u ON al.user_email = u.email SET al.user_id = u.id WHERE al.user_id IS NULL");
    }
    
    // Burahin ang columns na user_email at user_name
    foreach(['user_email', 'user_name'] as $col) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE audit_logs DROP COLUMN $col");
        }
    }
    
    // Add Foreign Key for user_id
    try { $pdo->exec("ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL"); } catch (Exception $e) {}

    // 2. Gawing Foreign Key ang mga ID sa orders
    echo "Adding Foreign Keys to orders...\n";
    try { $pdo->exec("ALTER TABLE orders MODIFY COLUMN user_id INT UNSIGNED NOT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_order_staff FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_order_rider FOREIGN KEY (assigned_rider_id) REFERENCES users(id) ON DELETE SET NULL"); } catch (Exception $e) {}

    // 3. Pagsamahin ang messages at chat_messages sa communications
    echo "Merging messages and chat_messages into communications...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS communications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED DEFAULT NULL,
        order_id INT UNSIGNED DEFAULT NULL,
        sender_id INT UNSIGNED DEFAULT NULL,
        sender_type ENUM('customer','rider','admin','staff','support') NOT NULL,
        receiver_id INT UNSIGNED DEFAULT NULL,
        receiver_type ENUM('customer','rider','admin','staff','support') NOT NULL,
        subject VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        status ENUM('open', 'replied', 'closed', 'read', 'unread') DEFAULT 'unread',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES communications(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Migrate chat_messages
    $pdo->exec("INSERT INTO communications (order_id, sender_id, sender_type, receiver_id, receiver_type, message, is_read, created_at)
                SELECT order_id, sender_id, sender_type, receiver_id, receiver_type, message, is_read, created_at FROM chat_messages");

    // Migrate messages
    $pdo->exec("INSERT INTO communications (parent_id, sender_id, sender_type, receiver_id, receiver_type, subject, message, status, created_at, updated_at)
                SELECT parent_id, sender_id, sender_role, recipient_id, recipient_role, subject, message, status, created_at, updated_at FROM messages");

    // 4. Pagsamahin ang rider_ratings at food_ratings sa reviews
    echo "Merging rider_ratings and food_ratings into reviews...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        review_type ENUM('food', 'rider') NOT NULL,
        target_id INT UNSIGNED NOT NULL, -- menu_item_id or rider_id (user_id)
        rating TINYINT UNSIGNED NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_review (order_id, review_type, target_id),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Migrate rider_ratings
    $pdo->exec("INSERT INTO reviews (order_id, user_id, review_type, target_id, rating, comment, created_at)
                SELECT order_id, customer_id, 'rider', rider_id, rating, comment, created_at FROM rider_ratings");

    // Migrate food_ratings
    $pdo->exec("INSERT INTO reviews (order_id, user_id, review_type, target_id, rating, comment, created_at)
                SELECT order_id, customer_id, 'food', menu_item_id, rating, comment, created_at FROM food_ratings");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // Rename old tables instead of dropping, for safety
    echo "Renaming old tables to _old...\n";
    foreach(['audit_logs', 'messages', 'chat_messages', 'rider_ratings', 'food_ratings'] as $tbl) {
        // We actually want to keep audit_logs but normalized.
        // For messages/chat/ratings, we can rename.
        if ($tbl === 'audit_logs') continue;
        try { $pdo->exec("RENAME TABLE $tbl TO {$tbl}_old"); } catch(Exception $e) {}
    }

    echo "Normalization successful!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
