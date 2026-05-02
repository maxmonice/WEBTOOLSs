<?php
// Check the actual ENUM values in the orders table
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
    
    // Get the ENUM definition for the status column
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "Status column ENUM definition:\n";
        echo $column['Type'] . "\n";
        
        // Extract ENUM values
        preg_match("/enum\((.*)\)/", $column['Type'], $matches);
        if (isset($matches[1])) {
            $enumValues = str_getcsv($matches[1], ',', "'");
            echo "Allowed values:\n";
            foreach ($enumValues as $value) {
                echo "- $value\n";
            }
        }
    } else {
        echo "Status column not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
