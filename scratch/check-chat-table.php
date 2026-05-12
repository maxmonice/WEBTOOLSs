<?php
require_once 'Db.php';
$pdo = getDB();
try {
    $stmt = $pdo->query("DESCRIBE chat_messages");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "MISSING\n";
}
