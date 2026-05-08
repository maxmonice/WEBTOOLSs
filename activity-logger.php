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
function logActivity($action, $details, $userEmail = '', $userName = '', $ipAddress = '', $userAgent = '') {
    try {
        $pdo = getDB();
        
        // Auto-detect IP and user agent if not provided
        $ipAddress = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Get user info from session if not provided
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($userEmail) && isset($_SESSION['user_email'])) {
            $userEmail = $_SESSION['user_email'];
        }
        if (empty($userName) && isset($_SESSION['user_name'])) {
            $userName = $_SESSION['user_name'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                action, details, user_email, user_name, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$action, $details, $userEmail, $userName, $ipAddress, $userAgent]);
        
    } catch (PDOException $e) {
        // Log error but don't break the application
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
        
        $sql = "SELECT * FROM audit_logs";
        $params = [];
        
        if (!empty($action)) {
            $sql .= " WHERE action = ?";
            $params[] = $action;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
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
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs");
        $total = $stmt->fetch()['total'];
        
        // Activities today
        $stmt = $pdo->prepare("SELECT COUNT(*) as today FROM audit_logs WHERE DATE(created_at) = CURDATE()");
        $today = $stmt->fetch()['today'];
        
        // Unique users today
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_email) as users_today FROM audit_logs WHERE DATE(created_at) = CURDATE() AND user_email != ''");
        $usersToday = $stmt->fetch()['users_today'];
        
        // Security events
        $stmt = $pdo->prepare("SELECT COUNT(*) as security FROM audit_logs WHERE action LIKE '%login%' OR action LIKE '%security%'");
        $security = $stmt->fetch()['security'];
        
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
