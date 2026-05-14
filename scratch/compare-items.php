<?php
require_once 'Db.php';
$pdo = getDB();

echo "Menu Items Count: " . $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn() . "\n";
echo "Content Items Count: " . $pdo->query("SELECT COUNT(*) FROM content_items")->fetchColumn() . "\n";

echo "\nSample Menu Items:\n";
$s = $pdo->query("SELECT id, name, category_id FROM menu_items LIMIT 3");
while($r = $s->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

echo "\nSample Content Items:\n";
$s = $pdo->query("SELECT id, name, category FROM content_items LIMIT 3");
while($r = $s->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
