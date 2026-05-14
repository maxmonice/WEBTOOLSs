<?php
require_once 'Db.php';
$pdo = getDB();
$stmt = $pdo->query("DESCRIBE users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
