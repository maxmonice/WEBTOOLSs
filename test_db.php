<?php
require_once 'Db.php';
$db = getDB();
$stmt = $db->prepare("SELECT DATABASE()");
$stmt->execute();
echo "DATABASE(): " . $stmt->fetchColumn() . "\n";

$stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'event_time_end'");
$stmt->execute();
echo "event_time_end count: " . $stmt->fetchColumn() . "\n";
