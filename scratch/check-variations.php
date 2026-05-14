<?php
require_once 'Db.php';
$pdo = getDB();
$s = $pdo->query('SELECT id, name, variations FROM menu_items WHERE variations IS NOT NULL AND variations != "" LIMIT 5');
while($r = $s->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | Name: {$r['name']}\n";
    echo "Variations: " . $r['variations'] . "\n---\n";
}
