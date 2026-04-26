<?php
// Add missing tables (orders, bookings, etc.) to database
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Missing Tables</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .info { color: #666; }
    </style>
</head>
<body>
    <h1>📋 Add Missing Tables</h1>
    
    <?php
    if ($_POST['add'] === 'yes') {
        try {
            require_once 'Db.php';
            $db = getDB();
            
            echo "<div class='info'><h3>🔧 Adding missing tables...</h3>";
            
            // Disable foreign key checks temporarily
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Orders table
            $db->exec("
                CREATE TABLE IF NOT EXISTS orders (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED,
                    status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
                    total_amount DECIMAL(10,2) DEFAULT NULL,
                    total DECIMAL(10,2) DEFAULT NULL,
                    address TEXT,
                    payment_method VARCHAR(50) DEFAULT NULL,
                    notes TEXT,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "<div class='success'>✅ Orders table created</div>";
            
            // Order items table
            $db->exec("
                CREATE TABLE IF NOT EXISTS order_items (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id INT UNSIGNED NOT NULL,
                    item_id INT UNSIGNED DEFAULT NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 1,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "<div class='success'>✅ Order items table created</div>";
            
            // Bookings table (with proper foreign key and CASCADE)
            $db->exec("
                CREATE TABLE IF NOT EXISTS bookings (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED,
                    status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
                    event_date DATE,
                    notes TEXT,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "<div class='success'>✅ Bookings table created with CASCADE delete</div>";
            
            // Content items table (for menu management)
            $db->exec("
                CREATE TABLE IF NOT EXISTS content_items (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    category VARCHAR(80) NOT NULL,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    image_url VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "<div class='success'>✅ Content items table created</div>";
            
            // Re-enable foreign key checks
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Add some sample menu items
            $sampleItems = [
                ['Grilled Salmon', 'Seafood', 'Fresh Atlantic salmon grilled to perfection', 24.99, 'images/salmon.jpg'],
                ['Lobster Roll', 'Seafood', 'Maine lobster with butter on toasted bun', 18.99, 'images/lobster.jpg'],
                ['Fish & Chips', 'Seafood', 'Beer-battered cod with crispy fries', 16.99, 'images/fishchips.jpg'],
                ['Shrimp Scampi', 'Seafood', 'Garlic butter shrimp over pasta', 19.99, 'images/shrimp.jpg'],
                ['Clam Chowder', 'Soup', 'New England style creamy clam chowder', 8.99, 'images/chowder.jpg']
            ];
            
            foreach ($sampleItems as $item) {
                $stmt = $db->prepare("INSERT IGNORE INTO content_items (name, category, description, price, image_url) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute($item);
            }
            echo "<div class='success'>✅ Sample menu items added</div>";
            
            echo "<div class='success'><h3>🎉 All tables added successfully!</h3></div>";
            echo "<p><strong>Now you can:</strong></p>";
            echo "<ul>";
            echo "<li>✅ View menu items without database errors</li>";
            echo "<li>✅ Create orders and bookings</li>";
            echo "<li>✅ Delete users without foreign key errors (CASCADE works)</li>";
            echo "<li>✅ Test Google/Facebook OAuth</li>";
            echo "</ul>";
            echo "<p><strong>Test pages:</strong></p>";
            echo "<p><a href='menu.php'>🍽️ Menu Page</a> | <a href='account.php'>🔐 Login</a> | <a href='bookbar.php'>📅 Bookings</a></p>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
        }
    } else {
    ?>
    
    <div class='info'>
        <h3>Missing Tables Detected:</h3>
        <p>The menu.php is showing database errors because the orders, bookings, and content_items tables are missing after the database cleanup.</p>
    </div>
    
    <p>This will add all the required tables for your website to work properly:</p>
    <ul>
        <li>Orders table (for shopping cart)</li>
        <li>Order items table (for individual order items)</li>
        <li>Bookings table (with CASCADE delete to fix user deletion)</li>
        <li>Content items table (for menu items)</li>
        <li>Sample menu items (so menu.php shows content)</li>
    </ul>
    
    <form method="post">
        <input type="hidden" name="add" value="yes">
        <button type="submit">📋 Add All Tables (5 seconds)</button>
    </form>
    
    <?php } ?>
</body>
</html>
