<?php
require_once __DIR__ . '/Db.php';
$pdo = getDB();

try {
    $sql = file_get_contents(__DIR__ . '/sqlDatabase/promos.sql');
    $pdo->exec($sql);
    echo "Promo tables created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>


