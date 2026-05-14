<?php
require_once 'Db.php';
$pdo = getDB();

echo "Starting Database Normalization...\n";

try {
    // 1. Update Users Roles
    echo "Updating user roles...\n";
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'staff', 'admin', 'rider', 'support') DEFAULT 'customer'");

    // 2. Prepare Menu Items Table
    echo "Updating menu_items table structure...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM menu_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_archived', $columns)) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
    }
    if (!in_array('archived_at', $columns)) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN archived_at TIMESTAMP NULL");
    }

    // 3. Create menu_item_variations Table if not exists
    echo "Creating menu_item_variations table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_item_variations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_item_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Create order_items table if not exists (already exists? let's check)
    echo "Checking order_items table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        menu_item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price_at_purchase DECIMAL(10,2) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Migrate variations from JSON to table
    echo "Migrating variations from menu_items.variations (JSON) to menu_item_variations table...\n";
    $stmt = $pdo->query("SELECT id, variations, price FROM menu_items WHERE variations IS NOT NULL AND variations != ''");
    $insertVar = $pdo->prepare("INSERT INTO menu_item_variations (menu_item_id, name, price) VALUES (?, ?, ?)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vars = json_decode($row['variations'], true);
        if (is_array($vars)) {
            foreach ($vars as $v) {
                // Check if already exists to avoid duplicates if re-run
                $check = $pdo->prepare("SELECT id FROM menu_item_variations WHERE menu_item_id = ? AND name = ?");
                $check->execute([$row['id'], $v]);
                if (!$check->fetch()) {
                    $insertVar->execute([$row['id'], $v, $row['price']]);
                }
            }
        }
    }

    // 6. Migrate from content_items to menu_items (if not already there)
    echo "Merging content_items into menu_items...\n";
    // First, ensure categories exist for content_items categories
    $contentCats = $pdo->query("SELECT DISTINCT category FROM content_items")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($contentCats as $catName) {
        $slug = strtolower(str_replace(' ', '-', $catName));
        $check = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
        $check->execute([$catName, $slug]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO categories (name, slug, display_order) VALUES (?, ?, 0)")->execute([$catName, $slug]);
        }
    }

    $stmt = $pdo->query("SELECT * FROM content_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Find category ID
        $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $catStmt->execute([$row['category']]);
        $catId = $catStmt->fetchColumn();

        // Check if item already exists in menu_items by name
        $check = $pdo->prepare("SELECT id FROM menu_items WHERE name = ? LIMIT 1");
        $check->execute([$row['name']]);
        $existingId = $check->fetchColumn();

        if (!$existingId) {
            $ins = $pdo->prepare("INSERT INTO menu_items (category_id, name, description, price, image_path, is_available, is_archived, archived_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
            $ins->execute([
                $catId,
                $row['name'],
                $row['description'],
                $row['price'],
                $row['image'],
                $row['is_archived'],
                $row['archived_at']
            ]);
            $newItemId = $pdo->lastInsertId();

            // Migrate content_variations to menu_item_variations
            $varStmt = $pdo->prepare("SELECT * FROM content_variations WHERE content_id = ?");
            $varStmt->execute([$row['id']]);
            while ($vRow = $varStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("INSERT INTO menu_item_variations (menu_item_id, name, price) VALUES (?, ?, ?)")
                    ->execute([$newItemId, $vRow['variation_name'], $vRow['variation_price']]);
            }
        }
    }

    // 7. Add Missing User IDs to Bookings
    echo "Connecting bookings to users...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('user_id', $columns)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN user_id INT UNSIGNED NULL");
    }
    // Attempt to match by email
    $pdo->exec("UPDATE bookings b JOIN users u ON b.user_email = u.email SET b.user_id = u.id WHERE b.user_id IS NULL");

    // 8. Add Foreign Keys (This might fail if data is inconsistent, so we use try/catch per FK)
    echo "Applying Foreign Key constraints...\n";

    $fks = [
        ["menu_items", "category_id", "categories", "id", "SET NULL"],
        ["menu_item_variations", "menu_item_id", "menu_items", "id", "CASCADE"],
        ["order_items", "order_id", "orders", "id", "CASCADE"],
        ["order_items", "menu_item_id", "menu_items", "id", "RESTRICT"],
        ["orders", "user_id", "users", "id", "CASCADE"],
        ["bookings", "user_id", "users", "id", "SET NULL"],
        ["user_promos", "user_id", "users", "id", "CASCADE"],
        ["user_promos", "promo_id", "promos", "id", "CASCADE"],
        ["notifications", "target_user_id", "users", "id", "CASCADE"],
        ["chat_messages", "order_id", "orders", "id", "CASCADE"],
    ];

    foreach ($fks as $fk) {
        list($table, $col, $refTable, $refCol, $onDelete) = $fk;
        echo "Adding FK: $table($col) -> $refTable($refCol)...\n";
        try {
            // First ensure types match
            if ($table === 'orders' && $col === 'user_id') {
                // Already int unsigned
            } elseif ($table === 'menu_items' && $col === 'category_id') {
                // Already int
            } else {
                // Attempt to match types if possible
            }
            
            // Clean up orphaned data first to avoid FK errors
            if ($onDelete !== 'SET NULL') {
                $pdo->exec("DELETE t FROM $table t LEFT JOIN $refTable r ON t.$col = r.$refCol WHERE r.$refCol IS NULL AND t.$col IS NOT NULL");
            } else {
                $pdo->exec("UPDATE $table SET $col = NULL WHERE $col NOT IN (SELECT $refCol FROM $refTable) AND $col IS NOT NULL");
            }

            $pdo->exec("ALTER TABLE $table ADD CONSTRAINT fk_{$table}_{$col} FOREIGN KEY ($col) REFERENCES $refTable($refCol) ON DELETE $onDelete");
        } catch (PDOException $e) {
            echo "  Error adding FK: " . $e->getMessage() . "\n";
        }
    }

    echo "\nNormalization completed successfully.\n";

} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
}
