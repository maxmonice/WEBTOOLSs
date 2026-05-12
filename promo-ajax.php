<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Db.php';
header('Content-Type: application/json');

$pdo = getDB();
$userId = $_SESSION['user_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $data['action'] ?? '';

if ($action === 'search_promos') {
    $query = $data['query'] ?? '';
    
    // Fetch all promos or search specifically
    $sql = "SELECT p.*, (SELECT COUNT(*) FROM user_promos up WHERE up.promo_id = p.id AND up.user_id = ?) as is_claimed 
            FROM promos p";
    $params = [$userId];
    
    if (!empty($query)) {
        $sql .= " WHERE p.code LIKE ?";
        $params[] = "%$query%";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $promos = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'promos' => $promos]);
    exit;
}

if ($action === 'claim_promo') {
    $promoId = $data['promo_id'] ?? 0;
    
    // Check if already claimed
    $check = $pdo->prepare("SELECT * FROM user_promos WHERE user_id = ? AND promo_id = ?");
    $check->execute([$userId, $promoId]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Promo already claimed']);
        exit;
    }
    
    // Get promo details
    $promoStmt = $pdo->prepare("SELECT * FROM promos WHERE id = ?");
    $promoStmt->execute([$promoId]);
    $promo = $promoStmt->fetch();
    
    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Promo not found']);
        exit;
    }
    
    // Calculate expiry
    $expiresAt = null;
    if ($promo['duration_days']) {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$promo['duration_days']} days"));
    }
    
    // Claim
    $insert = $pdo->prepare("INSERT INTO user_promos (user_id, promo_id, expires_at) VALUES (?, ?, ?)");
    try {
        $insert->execute([$userId, $promoId, $expiresAt]);
        echo json_encode([
            'success' => true, 
            'discount' => $promo['discount_percent'],
            'applicable_category' => $promo['applicable_category']
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Claim failed']);
    }
    exit;
}
