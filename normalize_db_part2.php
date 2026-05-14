<?php
require_once 'Db.php';
$pdo = getDB();

try {
    echo "<h1>Database Normalization Part 2</h1>";
    echo "<pre>";

    // 1. Add user_id to bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'user_id'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        echo "Adding user_id column to bookings table...\n";
        $pdo->exec("ALTER TABLE bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER id");
    }

    // 2. Map existing user_email to user_id
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'user_email'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) {
        echo "Mapping existing user emails to user IDs in bookings...\n";
        $pdo->exec("UPDATE bookings b JOIN users u ON b.user_email = u.email SET b.user_id = u.id WHERE b.user_id IS NULL");
    }

    // 3. Drop user_email and user_name from bookings
    $columnsToDrop = [];
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME IN ('user_email', 'user_name')");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($cols)) {
        echo "Dropping redundant columns from bookings...\n";
        foreach ($cols as $col) {
            $pdo->exec("ALTER TABLE bookings DROP COLUMN {$col}");
        }
    }

    // 4. Add FK constraint
    // Check if the foreign key exists first
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND CONSTRAINT_NAME = 'fk_bookings_user'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        echo "Adding Foreign Key constraint for bookings.user_id...\n";
        $pdo->exec("ALTER TABLE bookings ADD CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    }

    echo "\nNormalization Part 2 successful!\n";
    echo "</pre>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
