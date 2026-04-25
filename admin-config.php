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
    if (empty($_SESSION['is_admin']) || empty($_SESSION['user_id'])) {
        header('Location: account.php');
        exit;
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
            ->query("SELECT COUNT(*) FROM users WHERE email != 'admin@gmail.com'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['new_users_week'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM users
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
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
                    COALESCE(o.total_amount, o.total, 0) AS total,
                    COALESCE(u.name, 'Unknown') AS customer_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
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
        'confirmed' => 'badge-green',
        'active'    => 'badge-green',
        'shipped'   => 'badge-blue',
        'delivered' => 'badge-green',
        'cancelled' => 'badge-red',
        'suspended' => 'badge-red',
        'completed' => 'badge-green',
    ];
    $cls = $map[strtolower($status)] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}