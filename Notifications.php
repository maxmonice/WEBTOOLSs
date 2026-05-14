<?php
// =====================================================
//  Notifications.php - Notification management system
//  Handles role-based notifications for admin, staff, and customers
// =====================================================

class Notifications {
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    // Create new notification
    public function create(string $title, string $message, string $type = 'info', string $targetRole = 'all', ?int $targetUserId = null): bool {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO notifications (title, message, type, target_role, target_user_id) 
                 VALUES (?, ?, ?, ?, ?)'
            );
            return $stmt->execute([$title, $message, $type, $targetRole, $targetUserId]);
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Get notifications for current user based on role
    public function getForUser(string $userRole, ?int $userId = null, int $limit = 10): array {
        try {
            $limit = max(1, min(100, (int)$limit));
            if ($userRole === 'customer') {
                if (!$userId) {
                    return [];
                }
                $sql = "
                    SELECT id, title, message, type, is_read, created_at
                    FROM notifications
                    WHERE target_role = 'customer'
                      AND target_user_id = ?
                    ORDER BY created_at DESC
                    LIMIT $limit
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userId]);
                return $stmt->fetchAll();
            }

            if ($userId) {
                $sql = "
                    SELECT id, title, message, type, is_read, created_at 
                    FROM notifications 
                    WHERE (target_role = 'all' OR target_role = ? OR (target_role = ? AND target_user_id = ?))
                    ORDER BY created_at DESC 
                    LIMIT $limit
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userRole, $userRole, $userId]);
            } else {
                $sql = "
                    SELECT id, title, message, type, is_read, created_at 
                    FROM notifications 
                    WHERE (target_role = 'all' OR target_role = ? OR target_user_id IS NULL)
                    ORDER BY created_at DESC 
                    LIMIT $limit
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userRole]);
            }
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get notifications: " . $e->getMessage());
            return [];
        }
    }
    
    // Mark notification as read
    public function markAsRead(int $notificationId, ?int $userId = null, ?string $userRole = null): bool {
        try {
            $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ?";
            if ($userRole === 'customer') {
                if (!$userId) {
                    return false;
                }
                $sql .= " AND target_role = 'customer' AND target_user_id = ?";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$notificationId, $userId]);
            } elseif ($userId && $userRole) {
                $sql .= " AND (target_role = 'all' OR target_role = ? OR (target_role = ? AND target_user_id = ?))";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$notificationId, $userRole, $userRole, $userId]);
            } elseif ($userId) {
                $sql .= " AND target_user_id = ?";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$notificationId, $userId]);
            } else {
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$notificationId]);
            }
        } catch (Exception $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    // Get unread count for user
    public function getUnreadCount(string $userRole, ?int $userId = null): int {
        try {
            if ($userRole === 'customer') {
                if (!$userId) {
                    return 0;
                }
                $sql = "
                    SELECT COUNT(*) as count
                    FROM notifications
                    WHERE is_read = FALSE
                      AND target_role = 'customer'
                      AND target_user_id = ?
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userId]);
                return (int) $stmt->fetchColumn();
            }

            if ($userId) {
                $sql = "
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE is_read = FALSE 
                    AND (target_role = 'all' OR target_role = ? OR (target_role = ? AND target_user_id = ?))
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userRole, $userRole, $userId]);
            } else {
                $sql = "
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE is_read = FALSE 
                    AND (target_role = 'all' OR target_role = ? OR target_user_id IS NULL)
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$userRole]);
            }
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Failed to get unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    // Mark all notifications as read for user
    public function markAllAsRead(string $userRole, ?int $userId = null): bool {
        try {
            if ($userRole === 'customer') {
                if (!$userId) {
                    return false;
                }
                $sql = "
                    UPDATE notifications
                    SET is_read = TRUE
                    WHERE is_read = FALSE
                      AND target_role = 'customer'
                      AND target_user_id = ?
                ";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$userId]);
            }

            if ($userId) {
                $sql = "
                    UPDATE notifications 
                    SET is_read = TRUE 
                    WHERE is_read = FALSE 
                    AND (target_role = 'all' OR target_role = ? OR (target_role = ? AND target_user_id = ?))
                ";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$userRole, $userRole, $userId]);
            } else {
                $sql = "
                    UPDATE notifications 
                    SET is_read = TRUE 
                    WHERE is_read = FALSE 
                    AND (target_role = 'all' OR target_role = ? OR target_user_id IS NULL)
                ";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$userRole]);
            }
        } catch (Exception $e) {
            error_log("Failed to mark all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    // Auto-create notifications for system events
    public function autoNotify(string $event, array $data = []): void {
        switch ($event) {
            case 'new_user':
                // Admin notification for new user registration
                $this->create(
                    'New User Registered',
                    "User '{$data['name']}' ({$data['email']}) has registered from user side.",
                    'user',
                    'admin'
                );
                break;
                
            case 'admin_created_user':
                // Admin notification for admin-created user
                $this->create(
                    'Admin Created User',
                    "Admin '{$data['admin_name']}' created user '{$data['name']}' ({$data['email']}).",
                    'user',
                    'admin'
                );
                // Customer notification
                $this->create(
                    'Account Created',
                    "Your account has been created by our admin team. Welcome to Luke's Seafood!",
                    'success',
                    'customer',
                    $data['user_id'] ?? null
                );
                break;
                
            case 'new_booking':
                // Admin and staff notification for new booking
                $this->create(
                    'New Booking Received',
                    "New booking #{$data['id']} received from {$data['customer_name']} for {$data['date']} at {$data['time']}.",
                    'booking',
                    'admin'
                );
                $this->create(
                    'New Booking Received',
                    "New booking #{$data['id']} received from {$data['customer_name']}.",
                    'booking',
                    'staff'
                );
                if (!empty($data['user_id'])) {
                    $this->create(
                        'Booking Received',
                        "Your booking #{$data['id']} for {$data['date']} at {$data['time']} has been received and is pending confirmation.",
                        'info',
                        'customer',
                        (int)$data['user_id']
                    );
                }
                break;
                
            case 'booking_confirmed':
                // Admin and staff notification
                $this->create(
                    'Booking Confirmed',
                    "Booking #{$data['id']} for {$data['customer_name']} has been confirmed.",
                    'success',
                    'admin'
                );
                $this->create(
                    'Booking Confirmed',
                    "Booking #{$data['id']} has been confirmed.",
                    'success',
                    'staff'
                );
                if (!empty($data['user_id'])) {
                    $this->create(
                        'Booking Confirmed',
                        "Your booking #{$data['id']} for {$data['date']} at {$data['time']} has been confirmed.",
                        'success',
                        'customer',
                        (int)$data['user_id']
                    );
                }
                break;
                
            case 'booking_cancelled':
                // Admin and staff notification
                $this->create(
                    'Booking Cancelled',
                    "Booking #{$data['id']} for {$data['customer_name']} has been cancelled.",
                    'error',
                    'admin'
                );
                $this->create(
                    'Booking Cancelled',
                    "Booking #{$data['id']} has been cancelled.",
                    'error',
                    'staff'
                );
                if (!empty($data['user_id'])) {
                    $this->create(
                        'Booking Cancelled',
                        "Your booking #{$data['id']} has been cancelled.",
                        'error',
                        'customer',
                        (int)$data['user_id']
                    );
                }
                break;
                
            case 'booking_rescheduled':
                // Admin and staff notification
                $this->create(
                    'Booking Rescheduled',
                    "Booking #{$data['id']} for {$data['customer_name']} rescheduled from {$data['old_date']} to {$data['new_date']}.",
                    'warning',
                    'admin'
                );
                $this->create(
                    'Booking Rescheduled',
                    "Booking #{$data['id']} has been rescheduled.",
                    'warning',
                    'staff'
                );
                if (!empty($data['user_id'])) {
                    $this->create(
                        'Booking Rescheduled',
                        "Your booking #{$data['id']} has been rescheduled to {$data['new_date']} at {$data['new_time']}.",
                        'warning',
                        'customer',
                        (int)$data['user_id']
                    );
                }
                break;
                
            case 'new_order':
                // Admin and staff notification
                $this->create(
                    'New Order Received',
                    "New order #{$data['id']} received from {$data['customer_name']} - Total: ₱{$data['total']}.",
                    'order',
                    'admin'
                );
                $this->create(
                    'New Order Received',
                    "New order #{$data['id']} received for processing.",
                    'order',
                    'staff'
                );
                if (!empty($data['user_id'])) {
                    $this->create(
                        'Order Received',
                        "Your order #{$data['id']} has been received and is being processed.",
                        'info',
                        'customer',
                        (int)$data['user_id']
                    );
                }
                break;
                
            case 'menu_item_added':
                // Admin notification
                $this->create(
                    'Menu Item Added',
                    "New menu item '{$data['name']}' (₱{$data['price']}) has been added to the menu.",
                    'success',
                    'admin'
                );
                break;
                
            case 'gallery_item_added':
                // Admin notification
                $this->create(
                    'Gallery Item Added',
                    "New gallery item '{$data['title']}' has been added to the gallery.",
                    'success',
                    'admin'
                );
                break;
                
            case 'security_alert':
                // Admin notification for security events
                $this->create(
                    'Security Alert',
                    $data['message'],
                    'error',
                    'admin'
                );
                break;
                
            case 'login_attempt':
                // Admin notification for suspicious login attempts
                if ($data['success']) {
                    $this->create(
                        'User Login',
                        "User '{$data['email']}' logged in successfully.",
                        'info',
                        'admin'
                    );
                } else {
                    $this->create(
                        'Failed Login Attempt',
                        "Failed login attempt for email '{$data['email']}' from IP {$data['ip']}.",
                        'warning',
                        'admin'
                    );
                }
                break;
        }
    }
}

// Helper function to get notification icon
function getNotificationIcon(string $type): string {
    $icons = [
        'info' => 'fa-info-circle',
        'success' => 'fa-check-circle',
        'warning' => 'fa-exclamation-triangle',
        'error' => 'fa-times-circle',
        'booking' => 'fa-calendar-days',
        'order' => 'fa-bag-shopping',
        'user' => 'fa-user-plus'
    ];
    return $icons[$type] ?? 'fa-info-circle';
}

// Helper function to get notification color
function getNotificationColor(string $type): string {
    $colors = [
        'info' => '#3498db',
        'success' => '#2ecc71',
        'warning' => '#f39c12',
        'error' => '#e74c3c',
        'booking' => '#9b59b6',
        'order' => '#e67e22',
        'user' => '#1abc9c'
    ];
    return $colors[$type] ?? '#3498db';
}
?>
