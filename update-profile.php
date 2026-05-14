<?php
require_once 'Db.php';
require_once 'session-helper.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');

if (!$name || !$email) {
    echo json_encode(['success' => false, 'message' => 'Name and Email are required']);
    exit;
}

try {
    $pdo = getDB();

    // 1. Check if phone is already used by another account
    if ($phone) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This phone number is already linked to another account.']);
            exit;
        }
    }

    // 2. Check if email is already used by another account
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This email is already in use by another account.']);
        exit;
    }

    // 3. Update the user
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $address, $userId]);

    // 4. Update session
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
