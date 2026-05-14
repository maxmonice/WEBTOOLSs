<?php
// Activity Logger - Centralized logging system for all website activities

require_once 'db.php';

/**
 * Log an activity to the audit_logs table
 * 
 * @param string $action The action type (login, signup, order_placed, booking_created, etc.)
 * @param string $details Detailed description of the activity
 * @param string $userEmail User's email (optional)
 * @param string $userName User's name (optional)
 * @param string $ipAddress User's IP address (optional)
 * @param string $userAgent User's browser info (optional)
 * @return bool Success status
 */
function logActivity($action, $details, $userId = null, $ipAddress = '', $userAgent = '') {
    try {
        $pdo = getDB();
        
        // Auto-detect IP and user agent if not provided
        $ipAddress = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Get user info from session if not provided
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                action, details, user_id, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$action, $details, $userId, $ipAddress, $userAgent]);
        
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent activities for display
 * 
 * @param int $limit Number of activities to retrieve
 * @param string $action Filter by action type (optional)
 * @return array Array of activities
 */
function getRecentActivities($limit = 100, $action = '') {
    try {
        $pdo = getDB();
        
        $sql = "SELECT al.*, u.name as user_name, u.email as user_email 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id";
        $params = [];
        
        if (!empty($action)) {
            $sql .= " WHERE al.action = ?";
            $params[] = $action;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Failed to get recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get activity statistics
 * 
 * @return array Statistics about activities
 */
function getActivityStats() {
    try {
        $pdo = getDB();
        
        // Total activities
        $total = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        
        // Activities today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $today = (int) $stmt->fetchColumn();
        
        // Unique users today
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL");
        $stmt->execute();
        $usersToday = (int) $stmt->fetchColumn();
        
        // Security events
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%' OR action LIKE '%security%'");
        $stmt->execute();
        $security = (int) $stmt->fetchColumn();
        
        return [
            'total' => $total,
            'today' => $today,
            'users_today' => $usersToday,
            'security' => $security
        ];
        
    } catch (PDOException $e) {
        error_log("Failed to get activity stats: " . $e->getMessage());
        return ['total' => 0, 'today' => 0, 'users_today' => 0, 'security' => 0];
    }
}

?>


