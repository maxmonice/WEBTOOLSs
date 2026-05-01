<?php
// =====================================================
//  staff-config.php
//  Central config included by every staff page.
//  Staff have LIMITED access:
//    - Can view & update bookings/orders (no delete)
//    - Can view customers (read-only)
//    - Cannot access user management, logs, or config
// =====================================================

$host     = 'localhost';
$dbname   = 'lukes_seafood';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
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
    </div></body></html>';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
//  STAFF SESSION GUARD
//  Staff must be logged in. Admins may also access
//  staff pages. Non-authenticated users are redirected.
// =====================================================
function requireStaff(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: account.php');
        exit;
    }
    // Block customers from accessing staff panel
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    if (!in_array($role, ['staff', 'admin'], true) && empty($_SESSION['is_admin'])) {
        header('Location: account.php');
        exit;
    }
}

function isAdmin(): bool {
    return !empty($_SESSION['is_admin']) ||
           in_array($_SESSION['role'] ?? '', ['admin'], true);
}

// =====================================================
//  STAFF-SCOPED STATS (no revenue, no sensitive data)
// =====================================================
function getStaffStats(PDO $pdo): array {
    $stats = [
        'active_bookings'  => 0,
        'pending_bookings' => 0,
        'my_bookings_today'=> 0,
        'pending_orders'   => 0,
        'processing_orders'=> 0,
        'total_orders_today'=> 0,
    ];

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

    try {
        $stats['my_bookings_today'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM bookings WHERE DATE(event_date) = CURDATE()")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['pending_orders'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['processing_orders'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    try {
        $stats['total_orders_today'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")
            ->fetchColumn();
    } catch (\Throwable $_) {}

    return $stats;
}

// =====================================================
//  RECENT BOOKINGS (staff view)
// =====================================================
function getRecentBookings(PDO $pdo, int $limit = 8): array {
    try {
        return $pdo->query(
            "SELECT b.id, b.status, b.event_date, b.created_at,
                    COALESCE(u.name, 'Unknown') AS customer_name
             FROM bookings b
             LEFT JOIN users u ON u.id = b.user_id
             ORDER BY b.created_at DESC
             LIMIT $limit"
        )->fetchAll();
    } catch (\Throwable $_) { return []; }
}

// =====================================================
//  RECENT ORDERS (staff view)
// =====================================================
function getRecentOrders(PDO $pdo, int $limit = 8): array {
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
    } catch (\Throwable $_) { return []; }
}

// =====================================================
//  HELPERS (shared with admin)
// =====================================================
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return (int)($diff / 60) . ' min ago';
    if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
    return (int)($diff / 86400) . 'd ago';
}

function peso(float $amount): string {
    return '₱' . number_format($amount, 0, '.', ',');
}

function statusBadge(string $status): string {
    $map = [
        'pending'    => 'badge-yellow',
        'confirmed'  => 'badge-green',
        'active'     => 'badge-green',
        'processing' => 'badge-blue',
        'shipped'    => 'badge-blue',
        'delivered'  => 'badge-green',
        'cancelled'  => 'badge-red',
        'completed'  => 'badge-green',
    ];
    $cls = $map[strtolower($status)] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}
