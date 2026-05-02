<?php
// Test the database connection used in process-order.php
$host     = 'localhost';
$dbname   = 'lukes_seafood';
$username = 'root';
$password = '';

echo "Testing database connection for process-order.php...\n";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "Database connection: SUCCESS\n";
    
    // Test if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $tableExists = $stmt->rowCount() > 0;
    echo "Orders table exists: " . ($tableExists ? "YES" : "NO") . "\n";
    
    if (!$tableExists) {
        echo "Creating orders table...\n";
        $createTableSQL = "
            CREATE TABLE orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                items JSON NOT NULL,
                address TEXT NOT NULL,
                payment_method VARCHAR(50) NOT NULL,
                payment_details JSON,
                subtotal DECIMAL(10,2) NOT NULL,
                shipping DECIMAL(10,2) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                user_email VARCHAR(255),
                user_name VARCHAR(255),
                status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($createTableSQL);
        echo "Orders table created: SUCCESS\n";
    }
    
    echo "Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
