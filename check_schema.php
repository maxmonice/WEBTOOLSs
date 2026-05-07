<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=lukes_seafood;charset=utf8mb4','root','');
    $cols = $pdo->query('DESCRIBE orders')->fetchAll(PDO::FETCH_COLUMN);
    echo 'COLUMNS: ' . implode(', ', $cols) . "\n";
    $needed = ['eta','rider_id','total_amount','user_name','user_email','notes','status'];
    foreach($needed as $c) {
        echo (in_array($c,$cols) ? 'OK' : 'MISSING') . ': ' . $c . "\n";
    }
    $info = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch();
    echo 'Status type: ' . $info['Type'] . "\n";
    $counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll();
    echo "Order counts:\n";
    foreach($counts as $r) echo '  ' . $r['status'] . ': ' . $r['cnt'] . "\n";
    echo "DONE\n";
} catch(Exception $e) { echo 'Error: ' . $e->getMessage() . "\n"; }
