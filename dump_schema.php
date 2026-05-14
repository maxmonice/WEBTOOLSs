<?php
require_once 'Db.php';
$pdo = getDB();
$tables = ['users', 'categories', 'menu_items', 'orders', 'order_items', 'bookings', 'communications', 'reviews', 'audit_logs', 'messages_old', 'chat_messages_old', 'rider_ratings_old', 'food_ratings_old'];
$output = '';

foreach($tables as $t) {
    $output .= "Table: $t\n";
    try {
        $res = $pdo->query("DESCRIBE $t");
        while($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $output .= "  Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}\n";
        }
    } catch(Exception $e) {
        $output .= "  Error: " . $e->getMessage() . "\n";
    }
    $output .= "\n";
}

file_put_contents('schema_dump.txt', $output);
echo "Dumped to schema_dump.txt\n";
