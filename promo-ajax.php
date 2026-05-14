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

function ensurePromoStorage(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promos (
            id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            discount_percent INT NOT NULL DEFAULT 0,
            duration_days INT NULL,
            applicable_category VARCHAR(100) DEFAULT 'All Items',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_promos (
            user_id INT(10) UNSIGNED NOT NULL,
            promo_id INT(10) UNSIGNED NOT NULL,
            claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (user_id, promo_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (promo_id) REFERENCES promos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    try {
        $columnCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promos' AND COLUMN_NAME = 'applicable_category'"
        );
        $columnCheck->execute();
        if ((int)$columnCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE promos ADD COLUMN applicable_category VARCHAR(100) DEFAULT 'All Items'");
        }

        $columnCheck2 = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_promos' AND COLUMN_NAME = 'is_active'"
        );
        $columnCheck2->execute();
        if ((int)$columnCheck2->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE user_promos ADD COLUMN is_active BOOLEAN DEFAULT FALSE");
        }
    } catch (Throwable $_) {}

    $stmt = $pdo->prepare(
        "INSERT INTO promos (code, discount_percent, duration_days, applicable_category)
         VALUES ('N3WUS3R', 30, NULL, 'All Items')
         ON DUPLICATE KEY UPDATE
            discount_percent = VALUES(discount_percent),
            duration_days = VALUES(duration_days),
            applicable_category = COALESCE(applicable_category, VALUES(applicable_category))"
    );
    $stmt->execute();
}

if ($action === 'search_promos') {
    ensurePromoStorage($pdo);
    
    $query = $data['query'] ?? '';
    
    // Fetch all promos or search specifically
    // Now also fetching is_active from user_promos
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM user_promos up WHERE up.promo_id = p.id AND up.user_id = ?) as is_claimed,
            (SELECT is_active FROM user_promos up WHERE up.promo_id = p.id AND up.user_id = ? LIMIT 1) as is_active
            FROM promos p";
    $params = [$userId, $userId];
    
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
    ensurePromoStorage($pdo);
    
    $promoId = (int)($data['promo_id'] ?? 0);
    $promoCode = strtoupper(trim((string)($data['promo_code'] ?? '')));

    if ($promoId <= 0 && $promoCode !== '') {
        $promoIdStmt = $pdo->prepare("SELECT id FROM promos WHERE UPPER(code) = ? LIMIT 1");
        $promoIdStmt->execute([$promoCode]);
        $promoId = (int)($promoIdStmt->fetchColumn() ?: 0);
    }
    
    if ($promoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid promo']);
        exit;
    }

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
    
    try {
        $pdo->beginTransaction();
        
        // Deactivate all other promos for this user (Ensure one use at a time)
        $deactivate = $pdo->prepare("UPDATE user_promos SET is_active = FALSE WHERE user_id = ?");
        $deactivate->execute([$userId]);
        
        // Claim and set as active
        $insert = $pdo->prepare("INSERT INTO user_promos (user_id, promo_id, expires_at, is_active) VALUES (?, ?, ?, TRUE)");
        $insert->execute([$userId, $promoId, $expiresAt]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'discount' => $promo['discount_percent'],
            'applicable_category' => $promo['applicable_category']
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Claim failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'use_promo') {
    $promoId = (int)($data['promo_id'] ?? 0);
    if ($promoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid promo ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // Check if claimed
        $check = $pdo->prepare("SELECT up.*, p.discount_percent, p.applicable_category, p.code 
                               FROM user_promos up 
                               JOIN promos p ON up.promo_id = p.id
                               WHERE up.user_id = ? AND up.promo_id = ?");
        $check->execute([$userId, $promoId]);
        $claimed = $check->fetch();
        
        if (!$claimed) {
            echo json_encode(['success' => false, 'message' => 'You have not claimed this promo yet']);
            $pdo->rollBack();
            exit;
        }

        // Deactivate all others
        $deactivate = $pdo->prepare("UPDATE user_promos SET is_active = FALSE WHERE user_id = ?");
        $deactivate->execute([$userId]);
        
        // Activate this one
        $activate = $pdo->prepare("UPDATE user_promos SET is_active = TRUE WHERE user_id = ? AND promo_id = ?");
        $activate->execute([$userId, $promoId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Promo activated',
            'code' => $claimed['code'],
            'discount' => $claimed['discount_percent'],
            'applicable_category' => $claimed['applicable_category']
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to activate promo']);
    }
    exit;
}


