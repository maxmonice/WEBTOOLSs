<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=lukes_seafood;charset=utf8mb4','root','',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $steps = [
        'Make id auto increment' => 'ALTER TABLE `orders` MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'Add user_name'  => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `user_name` VARCHAR(255) DEFAULT NULL',
        'Add user_email' => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `user_email` VARCHAR(255) DEFAULT NULL',
        'Add notes'      => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `notes` LONGTEXT DEFAULT NULL',
        'Add total_amount'=> 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `total_amount` DECIMAL(10,2) DEFAULT NULL',
        'Add eta'        => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `eta` VARCHAR(100) DEFAULT NULL',
        'Add rider_id'   => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `rider_id` VARCHAR(50) DEFAULT NULL',
        'Set rider_id type' => 'ALTER TABLE `orders` MODIFY COLUMN `rider_id` VARCHAR(50) DEFAULT NULL',
        'Add lat'        => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `delivery_latitude` DECIMAL(10, 8) DEFAULT NULL',
        'Add lng'        => 'ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `delivery_longitude` DECIMAL(11, 8) DEFAULT NULL',
        'Legacy items nullable' => 'ALTER TABLE `orders` MODIFY COLUMN `items` LONGTEXT NULL',
        'Legacy subtotal nullable' => 'ALTER TABLE `orders` MODIFY COLUMN `subtotal` DECIMAL(10,2) NULL DEFAULT NULL',
        'Legacy shipping nullable' => 'ALTER TABLE `orders` MODIFY COLUMN `shipping` DECIMAL(10,2) NULL DEFAULT NULL',
        'Legacy total nullable' => 'ALTER TABLE `orders` MODIFY COLUMN `total` DECIMAL(10,2) NULL DEFAULT NULL',
        'Expand status enum' => "ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','processing','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'",
    ];

    foreach ($steps as $label => $sql) {
        $pdo->exec($sql);
        echo "OK: $label\n";
    }

    // Ensure primary key exists on id
    try {
        $pdo->exec('ALTER TABLE `orders` ADD PRIMARY KEY (`id`)');
        echo "OK: Added primary key on id\n";
    } catch (Exception $e) {
        echo "OK: Primary key already exists or not needed\n";
    }

    // Verify final schema
    $cols = $pdo->query('DESCRIBE orders')->fetchAll(PDO::FETCH_ASSOC);
    echo "\nFinal table columns:\n";
    foreach ($cols as $c) {
        echo '  ' . str_pad($c['Field'], 20) . $c['Type'] . "\n";
    }
    echo "\n=== MIGRATION COMPLETE ===\n";

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
