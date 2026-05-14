<?php
declare(strict_types=1);

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=lukes_seafood;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $dbName = 'lukes_seafood';

    // If id is not primary key yet, make it primary first.
    $pkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'id' AND CONSTRAINT_NAME = 'PRIMARY'
    ");
    $pkStmt->execute([$dbName]);
    $hasPrimaryOnId = (int)$pkStmt->fetchColumn() > 0;

    if (!$hasPrimaryOnId) {
        // Drop existing primary key (if any), then add on id.
        try {
            $pdo->exec("ALTER TABLE `orders` DROP PRIMARY KEY");
            echo "Dropped old primary key\n";
        } catch (Throwable $e) {
            echo "No existing primary key to drop\n";
        }
        $pdo->exec("ALTER TABLE `orders` ADD PRIMARY KEY (`id`)");
        echo "Added primary key on id\n";
    } else {
        echo "Primary key on id already exists\n";
    }

    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    echo "Set id as AUTO_INCREMENT\n";

    // Compatibility changes for current app code.
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `rider_id` VARCHAR(50) DEFAULT NULL");
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `items` LONGTEXT NULL");
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `subtotal` DECIMAL(10,2) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `shipping` DECIMAL(10,2) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `total` DECIMAL(10,2) NULL DEFAULT NULL");

    echo "Compatibility columns updated\n";
    echo "Done\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}


