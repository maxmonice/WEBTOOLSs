<?php
// Check the actual structure of the orders table
$host     = '127.0.0.1';
$dbname   = 'lukes_seafood';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    echo "Database connection successful\n";
    
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "Orders table exists\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE orders");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Table structure:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
        }
    } else {
        echo "Orders table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
