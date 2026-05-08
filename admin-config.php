<?php
// =====================================================
//  admin-config.php
//  Central config included by every admin page.
//  Provides:
//    - DB connection ($pdo)
//    - requireAdmin() session guard
//    - getAdminStats() real-time dashboard figures
//    - getRecentUsers() for user management
//    - logAdminActivity() for audit trail
// =====================================================

// ── Database connection ────────────────────────────
// Matches the credentials in admin-users.php (XAMPP defaults).
// Update $dbname if your DB is named differently.
$host     = 'localhost';
$dbname   = 'lukes_seafood';   // ← change to match your DB name
$username = 'root';
$password = '';                // ← XAMPP default (no password)

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    // Pretty error page instead of raw crash
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><title>DB Error</title>
    <style>body{font-family:Arial;background:#191919;color:#fff;display:flex;align-items:center;
    justify-content:center;min-height:100vh;margin:0;}
    .box{background:#222;border:1px solid rgba(194,38,38,0.4);border-radius:12px;padding:32px;
    max-width:520px;text-align:center;}
    h2{color:#ff6b6b;margin-bottom:12px;}p{color:rgba(255,255,255,0.6);line-height:1.6;}
    code{background:#2a2a2a;padding:4px 8px;border-radius:4px;font-size:0.85rem;}</style>
    </head><body><div class="box">
    <h2>⚠️ Database Connection Error</h2>
    <p>Could not connect to <code>' . htmlspecialchars($dbname) . '</code>.<br>
    Make sure XAMPP is running and the database exists.</p>
    <p style="font-size:0.8rem;color:rgba(255,255,255,0.35);">' . htmlspecialchars($e->getMessage()) . '</p>
    </div></body></html>';
    exit;
}

function adminColumnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (\Throwable $_) {
        return false;
    }
}

function ensureAdminSchema(PDO $pdo): void {
    try {
        if (!adminColumnExists($pdo, 'users', 'status')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER role");
        }
    } catch (\Throwable $_) {}

    try {
        if (!adminColumnExists($pdo, 'content_items', 'updated_at')) {
            $pdo->exec("ALTER TABLE content_items ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS content_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_id INT NOT NULL,
            variation_name VARCHAR(255) NOT NULL,
            variation_price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Throwable $_) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info','success','warning','error','booking','order','user') NOT NULL DEFAULT 'info',
            target_role ENUM('all','admin','staff','customer') NOT NULL DEFAULT 'all',
            target_user_id INT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_target (target_role, target_user_id),
            INDEX idx_read (is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Throwable $_) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_resources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            resource_type ENUM('staff','equipment') NOT NULL,
            role_label VARCHAR(120) DEFAULT NULL,
            status ENUM('available','inactive') NOT NULL DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_resource_type (resource_type),
            INDEX idx_resource_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS booking_resource_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            resource_id INT NOT NULL,
            assigned_by INT DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_booking_resource (booking_id, resource_id),
            INDEX idx_assignment_booking (booking_id),
            INDEX idx_assignment_resource (resource_id),
            CONSTRAINT fk_assignment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_assignment_resource FOREIGN KEY (resource_id) REFERENCES booking_resources(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $count = (int)$pdo->query("SELECT COUNT(*) FROM booking_resources")->fetchColumn();
        if ($count === 0) {
            $seed = $pdo->prepare("INSERT INTO booking_resources (name, resource_type, role_label, status) VALUES (?, ?, ?, 'available')");
            foreach ([
                ['Carlos Mendoza', 'staff', 'Head Fishmonger'],
                ['Lita Navarro', 'staff', 'Chef'],
                ['Ben Aquino', 'staff', 'Event Staff'],
                ['Delivery Van 1', 'equipment', 'Vehicle'],
                ['Delivery Van 2', 'equipment', 'Vehicle'],
                ['Ice Box Set A', 'equipment', 'Cold Storage'],
            ] as $row) {
                $seed->execute($row);
            }
        }
    } catch (\Throwable $_) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) DEFAULT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            subject VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            admin_reply TEXT DEFAULT NULL,
            status ENUM('new','reviewed','replied','archived') NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_feedback_status (status),
            INDEX idx_feedback_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Throwable $_) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT DEFAULT NULL,
            sender_role ENUM('admin','staff','customer') NOT NULL,
            sender_id INT DEFAULT NULL,
            sender_name VARCHAR(255) NOT NULL,
            sender_email VARCHAR(255) DEFAULT NULL,
            recipient_role ENUM('admin','staff','customer') NOT NULL,
            recipient_id INT DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('open','replied','closed') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_message_parent (parent_id),
            INDEX idx_message_recipient (recipient_role, recipient_id),
            INDEX idx_message_sender (sender_role, sender_id),
            INDEX idx_message_status (status),
            CONSTRAINT fk_message_parent FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Throwable $_) {}
}

ensureAdminSchema($pdo);

// Ensure a PHP session is running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
//  ADMIN SESSION GUARD
//  Call requireAdmin() at the top of every admin page.
//  Redirects to account.php if the visitor is not an
//  authenticated admin.
// =====================================================
function requireAdmin(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        header('Location: account.php');
        exit;
    }
    
    // Check if user has admin role
    if ($_SESSION['user_role'] !== 'admin') {
        // If user is staff, redirect to staff page
        if ($_SESSION['user_role'] === 'staff') {
            header('Location: staff.php');
            exit;
        }
        // Otherwise redirect to account
        header('Location: account.php');
        exit;
    }
}

// =====================================================
//  AUDIT LOG HELPER
//  Records admin actions to the audit_logs table.
//  Call logAdminActivity($pdo, 'action_name', 'details')
//  after any significant admin operation.
// =====================================================
function logAdminActivity(PDO $pdo, string $action, string $details = ''): void {
    try {
        // Ensure audit_logs table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            user_email VARCHAR(255),
            user_name VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $pdo->prepare("INSERT INTO audit_logs (action, details, user_email, user_name, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $action,
            $details,
            $_SESSION['user_email'] ?? ($_SESSION['email'] ?? 'unknown'),
            $_SESSION['user_name'] ?? 'System',
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (\Throwable $_) {
        // Silently fail so admin operations never break
    }
}

// =====================================================
//  REAL-TIME DASHBOARD STATS
//  Returns an array of live counts from the DB.
//  Wraps every query so a missing table never crashes
//  the page — it just shows 0 instead.
// =====================================================
function getAdminStats(PDO $pdo): array {
    $stats = [
        'total_users'      => 0,
        'new_users_week'   => 0,
        'active_bookings'  => 0,
        'pending_bookings' => 0,
        'pending_orders'   => 0,
        'revenue_month'    => 0,
        'total_orders'     => 0,
    ];

    // ── Users ─────────────────────────────────────
    try {
        $stats['total_users'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND email != 'admin@gmail.com'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['new_users_week'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM users
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       AND role = 'customer'
                       AND email != 'admin@gmail.com'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    // ── Bookings ───────────────────────────────────
    try {
        $stats['active_bookings'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed','active')")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['pending_bookings'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    // ── Orders ────────────────────────────────────
    try {
        $stats['pending_orders'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['total_orders'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM orders")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    // ── Revenue (current month) ───────────────────
    try {
        $rev = $pdo
            ->query("SELECT SUM(total_amount) FROM orders
                     WHERE MONTH(created_at) = MONTH(NOW())
                       AND YEAR(created_at)  = YEAR(NOW())
                       AND status != 'cancelled'")
            ->fetchColumn();
        $stats['revenue_month'] = (float) ($rev ?? 0);
    } catch (\Throwable $_) {
        // Try alternate column name
        try {
            $rev = $pdo
                ->query("SELECT SUM(total) FROM orders
                         WHERE MONTH(created_at) = MONTH(NOW())
                           AND YEAR(created_at)  = YEAR(NOW())
                           AND status != 'cancelled'")
                ->fetchColumn();
            $stats['revenue_month'] = (float) ($rev ?? 0);
        } catch (\Throwable $_) {}
    }

    return $stats;
}

// =====================================================
//  RECENT ACTIVITY LOG
//  Pulls the latest events across users / orders /
//  bookings and merges them into a unified feed.
// =====================================================
function getRecentActivity(PDO $pdo, int $limit = 8): array {
    $events = [];

    // Recent user registrations
    try {
        $rows = $pdo->query(
            "SELECT name, email, created_at FROM users
             WHERE email != 'admin@gmail.com'
             ORDER BY created_at DESC LIMIT 5"
        )->fetchAll();
        foreach ($rows as $r) {
            $events[] = [
                'type'  => 'user',
                'icon'  => 'fa-user-plus',
                'color' => 'green',
                'text'  => 'New customer <strong>' . htmlspecialchars($r['name']) . '</strong> registered',
                'time'  => $r['created_at'],
            ];
        }
    } catch (\Throwable $_) {}

    // Recent orders
    try {
        $rows = $pdo->query(
            "SELECT id, status, created_at FROM orders
             ORDER BY created_at DESC LIMIT 5"
        )->fetchAll();
        foreach ($rows as $r) {
            $oid   = '#ORD-' . str_pad($r['id'], 4, '0', STR_PAD_LEFT);
            $label = ucfirst($r['status']);
            $events[] = [
                'type'  => 'order',
                'icon'  => 'fa-bag-shopping',
                'color' => 'blue',
                'text'  => 'Order <strong>' . $oid . '</strong> marked as ' . htmlspecialchars($label),
                'time'  => $r['created_at'],
            ];
        }
    } catch (\Throwable $_) {}

    // Recent bookings
    try {
        $rows = $pdo->query(
            "SELECT id, status, event_date, created_at FROM bookings
             ORDER BY created_at DESC LIMIT 5"
        )->fetchAll();
        foreach ($rows as $r) {
            $bid  = '#BK-' . str_pad($r['id'], 3, '0', STR_PAD_LEFT);
            $date = $r['event_date'] ? date('M j', strtotime($r['event_date'])) : 'TBD';
            $events[] = [
                'type'  => 'booking',
                'icon'  => 'fa-calendar-days',
                'color' => 'yellow',
                'text'  => 'Booking <strong>' . $bid . '</strong> ' . htmlspecialchars(ucfirst($r['status'])) . ' for ' . $date,
                'time'  => $r['created_at'],
            ];
        }
    } catch (\Throwable $_) {}

    // Sort by time descending and trim
    usort($events, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
    return array_slice($events, 0, $limit);
}

// =====================================================
//  RECENT ORDERS (for dashboard table)
// =====================================================
function getRecentOrders(PDO $pdo, int $limit = 5): array {
    try {
        return $pdo->query(
            "SELECT o.id, o.status, o.created_at,
                    o.total AS total,
                    COALESCE(NULLIF(o.user_name, ''), u.name, 'Guest') AS customer_name
             FROM orders o
             LEFT JOIN users u ON u.email = o.user_email
             ORDER BY o.created_at DESC
             LIMIT $limit"
        )->fetchAll();
    } catch (\Throwable $_) {
        return [];
    }
}

// =====================================================
//  HELPERS
// =====================================================

/** Human-readable time-ago string */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return (int)($diff / 60) . ' min ago';
    if ($diff < 86400) return (int)($diff / 3600) . ' hour' . ((int)($diff / 3600) > 1 ? 's' : '') . ' ago';
    return (int)($diff / 86400) . ' day' . ((int)($diff / 86400) > 1 ? 's' : '') . ' ago';
}

/** Format peso */
function peso(float $amount): string {
    return '₱' . number_format($amount, 0, '.', ',');
}

/** Badge HTML by status */
function statusBadge(string $status): string {
    $map = [
        'pending'   => 'badge-yellow',
        'processing' => 'badge-blue',
        'confirmed' => 'badge-green',
        'active'    => 'badge-green',
        'shipped'   => 'badge-blue',
        'delivered' => 'badge-green',
        'cancelled' => 'badge-red',
        'suspended' => 'badge-red',
        'available' => 'badge-green',
        'inactive'  => 'badge-gray',
        'new'       => 'badge-yellow',
        'reviewed'  => 'badge-blue',
        'replied'   => 'badge-green',
        'archived'  => 'badge-gray',
        'completed' => 'badge-green',
    ];
    $cls = $map[strtolower($status)] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}
