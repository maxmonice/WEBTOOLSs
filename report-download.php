<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = getDB();

$role = $_SESSION['role'] ?? '';
$isAdmin = !empty($_SESSION['is_admin']) || $role === 'admin';
$isStaff = !empty($_SESSION['is_staff']) || $role === 'staff';

if (empty($_SESSION['user_id']) || (!$isAdmin && !$isStaff)) {
    http_response_code(403);
    exit('Forbidden');
}

$report = strtolower(trim((string)($_GET['type'] ?? '')));
$allowedForStaff = ['orders', 'bookings', 'customers', 'delivery'];
$allowedForAdmin = ['sales', 'orders', 'bookings', 'customers', 'delivery', 'ratings', 'audit'];

if (!in_array($report, $isAdmin ? $allowedForAdmin : $allowedForStaff, true)) {
    http_response_code(404);
    exit('Report not found');
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function pesoReport(float $amount): string {
    return 'PHP ' . number_format($amount, 2);
}

function scalar(PDO $pdo, string $sql, array $params = []): float {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)($stmt->fetchColumn() ?: 0);
}

function rows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function esc(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logoDataUri(): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">
        <rect width="96" height="96" rx="18" fill="#C22626"/>
        <circle cx="48" cy="48" r="34" fill="#ffffff" opacity=".12"/>
        <text x="48" y="43" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" font-weight="800" fill="#ffffff">L</text>
        <text x="48" y="63" text-anchor="middle" font-family="Arial, sans-serif" font-size="13" font-weight="700" fill="#ffffff">Luke&apos;s</text>
    </svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function reportMeta(string $report): array {
    return match ($report) {
        'sales' => ['Sales Report', 'Daily, weekly, and monthly sales transactions, total revenue, and completed orders.'],
        'orders' => ['Order Report', 'Customer orders, payment methods, delivery details, and order statuses.'],
        'bookings' => ['Booking Report', 'Sushi bar and catering reservations including schedules, event details, and booking status.'],
        'customers' => ['Customer Report', 'Registered customer information, account status, and customer activity.'],
        'delivery' => ['Delivery Report', 'Rider assignments, delivery progress, and completed deliveries.'],
        'ratings' => ['Food Ratings Report', 'Customer ratings, reviews, and feedback for menu items.'],
        'audit' => ['Audit Logs Report', 'System activities, login history, and important actions performed by users and administrators.'],
        default => ['Report', 'Luke\'s Seafood Trading report.'],
    };
}

function buildReportData(PDO $pdo, string $report): array {
    if ($report === 'sales') {
        $summary = [
            ['Metric', 'Value'],
            ['Revenue Today', pesoReport(scalar($pdo, "SELECT COALESCE(SUM(COALESCE(total_amount,total,0)),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status='delivered'"))],
            ['Revenue Last 7 Days', pesoReport(scalar($pdo, "SELECT COALESCE(SUM(COALESCE(total_amount,total,0)),0) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status='delivered'"))],
            ['Revenue This Month', pesoReport(scalar($pdo, "SELECT COALESCE(SUM(COALESCE(total_amount,total,0)),0) FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status='delivered'"))],
            ['Completed Orders', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status='delivered'")],
        ];
        $data = rows($pdo, "SELECT id, COALESCE(user_name, user_email, 'Customer') AS customer, payment_method, COALESCE(total_amount,total,0) AS total, status, created_at FROM orders ORDER BY created_at DESC LIMIT 100");
        $table = [['Order ID', 'Customer', 'Payment', 'Total', 'Status', 'Date']];
        foreach ($data as $r) $table[] = ['ORD-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT), $r['customer'], $r['payment_method'], pesoReport((float)$r['total']), ucfirst((string)$r['status']), (string)$r['created_at']];
        return [$summary, $table];
    }

    if ($report === 'orders') {
        $summary = [
            ['Metric', 'Value'],
            ['Total Orders', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders")],
            ['Pending', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status='pending'")],
            ['Processing / Confirmed', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('processing','confirmed')")],
            ['Delivered', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status='delivered'")],
        ];
        $data = rows($pdo, "SELECT id, COALESCE(user_name,user_email,'Customer') AS customer, payment_method, address, rider_id, status, COALESCE(total_amount,total,0) AS total, created_at FROM orders ORDER BY created_at DESC LIMIT 100");
        $table = [['Order ID', 'Customer', 'Payment', 'Delivery Details', 'Status', 'Total']];
        foreach ($data as $r) $table[] = ['ORD-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT), $r['customer'], $r['payment_method'], trim((string)$r['address']) . ($r['rider_id'] ? ' | Rider: ' . $r['rider_id'] : ''), ucfirst((string)$r['status']), pesoReport((float)$r['total'])];
        return [$summary, $table];
    }

    if ($report === 'bookings') {
        $summary = [
            ['Metric', 'Value'],
            ['Total Bookings', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM bookings")],
            ['Pending', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM bookings WHERE status='pending'")],
            ['Confirmed', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM bookings WHERE status='confirmed'")],
            ['Cancelled', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM bookings WHERE status='cancelled'")],
        ];
        $data = rows($pdo, "SELECT id, full_name, email_address, event_name, event_type, event_date, event_time, event_time_end, num_guests, status FROM bookings ORDER BY event_date DESC, id DESC LIMIT 100");
        $table = [['Booking ID', 'Customer', 'Event', 'Schedule', 'Guests', 'Status']];
        foreach ($data as $r) $table[] = ['BK-' . str_pad((string)$r['id'], 3, '0', STR_PAD_LEFT), $r['full_name'] . ' | ' . $r['email_address'], $r['event_name'] . ' (' . $r['event_type'] . ')', $r['event_date'] . ' ' . $r['event_time'] . ($r['event_time_end'] ? '-' . $r['event_time_end'] : ''), (string)$r['num_guests'], ucfirst((string)$r['status'])];
        return [$summary, $table];
    }

    if ($report === 'customers') {
        $summary = [
            ['Metric', 'Value'],
            ['Registered Customers', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='customer' AND COALESCE(is_archived,0)=0")],
            ['Active Accounts', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='customer' AND status='active' AND COALESCE(is_archived,0)=0")],
            ['Suspended Accounts', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='customer' AND status='suspended' AND COALESCE(is_archived,0)=0")],
            ['New This Month', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='customer' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")],
        ];
        $data = rows($pdo, "SELECT u.id, u.name, u.email, u.status, u.provider, u.created_at, COUNT(o.id) AS order_count FROM users u LEFT JOIN orders o ON o.user_id = u.id WHERE u.role='customer' AND COALESCE(u.is_archived,0)=0 GROUP BY u.id ORDER BY u.created_at DESC LIMIT 100");
        $table = [['Customer', 'Email', 'Status', 'Provider', 'Orders', 'Registered']];
        foreach ($data as $r) $table[] = [$r['name'], $r['email'], ucfirst((string)$r['status']), ucfirst((string)$r['provider']), (string)$r['order_count'], (string)$r['created_at']];
        return [$summary, $table];
    }

    if ($report === 'delivery') {
        $summary = [
            ['Metric', 'Value'],
            ['Ready for Rider', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status='confirmed'")],
            ['Out for Delivery', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status='shipped'")],
            ['Completed Deliveries', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM orders WHERE status='delivered'")],
            ['Assigned Riders', (string)(int)scalar($pdo, "SELECT COUNT(DISTINCT rider_id) FROM orders WHERE rider_id IS NOT NULL AND rider_id <> ''")],
        ];
        $data = rows($pdo, "SELECT id, COALESCE(user_name,user_email,'Customer') AS customer, rider_id, address, status, eta, delivered_at, updated_at FROM orders WHERE status IN ('confirmed','shipped','delivered') OR rider_id IS NOT NULL ORDER BY updated_at DESC LIMIT 100");
        $table = [['Order ID', 'Customer', 'Rider', 'Delivery Address', 'Progress', 'Completed']];
        foreach ($data as $r) $table[] = ['ORD-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT), $r['customer'], $r['rider_id'] ?: 'Unassigned', $r['address'], ucfirst((string)$r['status']) . ($r['eta'] ? ' | ETA: ' . $r['eta'] : ''), $r['delivered_at'] ?: '-'];
        return [$summary, $table];
    }

    if ($report === 'ratings') {
        $summary = [['Metric', 'Value']];
        if (!tableExists($pdo, 'food_ratings')) {
            $summary[] = ['Food Ratings', 'No food ratings table found yet'];
            return [$summary, [['Menu Item', 'Average Rating', 'Reviews', 'Latest Feedback', 'Date']]];
        }
        $summary[] = ['Total Food Reviews', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM food_ratings")];
        $summary[] = ['Average Rating', number_format(scalar($pdo, "SELECT AVG(rating) FROM food_ratings"), 2) . ' / 5'];
        $feedbackColumn = columnExists($pdo, 'food_ratings', 'review') ? 'review' : (columnExists($pdo, 'food_ratings', 'comment') ? 'comment' : null);
        $feedbackExpr = $feedbackColumn ? "MAX(fr.`{$feedbackColumn}`)" : "''";
        $join = tableExists($pdo, 'content_items')
            ? "LEFT JOIN content_items ci ON ci.id = fr.menu_item_id"
            : (tableExists($pdo, 'menu_items') ? "LEFT JOIN menu_items ci ON ci.id = fr.menu_item_id" : "");
        $nameExpr = $join ? "COALESCE(ci.name, CONCAT('Menu Item #', fr.menu_item_id))" : "CONCAT('Menu Item #', fr.menu_item_id)";
        $data = rows($pdo, "SELECT fr.menu_item_id, {$nameExpr} AS item_name, AVG(fr.rating) AS avg_rating, COUNT(*) AS review_count, {$feedbackExpr} AS latest_review, MAX(fr.created_at) AS latest_date FROM food_ratings fr {$join} GROUP BY fr.menu_item_id, item_name ORDER BY review_count DESC, avg_rating DESC LIMIT 100");
        $table = [['Menu Item', 'Average Rating', 'Reviews', 'Latest Feedback', 'Date']];
        foreach ($data as $r) $table[] = [$r['item_name'], number_format((float)$r['avg_rating'], 2) . ' / 5', (string)$r['review_count'], $r['latest_review'] ?: '-', (string)$r['latest_date']];
        return [$summary, $table];
    }

    $summary = [
        ['Metric', 'Value'],
        ['Total Logs', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM audit_logs")],
        ['Activities Today', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")],
        ['Login Events', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%'")],
        ['Security Events', (string)(int)scalar($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%security%' OR action LIKE '%suspend%'")],
    ];
    $data = rows($pdo, "SELECT action, details, user_email, user_name, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 100");
    $table = [['Action', 'Details', 'User', 'IP Address', 'Date']];
    foreach ($data as $r) $table[] = [$r['action'], $r['details'], trim((string)$r['user_name']) . ' | ' . $r['user_email'], $r['ip_address'], (string)$r['created_at']];
    return [$summary, $table];
}

function renderTable(array $table): string {
    if (empty($table)) return '';
    $head = array_shift($table);
    $html = '<table><thead><tr>';
    foreach ($head as $h) $html .= '<th>' . esc((string)$h) . '</th>';
    $html .= '</tr></thead><tbody>';
    if (empty($table)) {
        $html .= '<tr><td colspan="' . count($head) . '" class="empty">No records available for this report.</td></tr>';
    }
    foreach ($table as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) $html .= '<td>' . nl2br(esc((string)$cell)) . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

[$title, $description] = reportMeta($report);
[$summary, $detail] = buildReportData($pdo, $report);
$logo = logoDataUri();
$generatedBy = $_SESSION['user_name'] ?? 'System User';
$generatedAt = date('F j, Y g:i A');

$html = '<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin: 28px 28px 34px; }
body { font-family: DejaVu Sans, Arial, sans-serif; color:#252525; font-size:10.5px; }
.brand { border-bottom: 3px solid #C22626; padding-bottom: 14px; margin-bottom: 18px; }
.brand-table { width:100%; border-collapse:collapse; }
.brand-table td { border:0; padding:0; vertical-align:middle; }
.logo { width:58px; height:58px; object-fit:contain; }
.logo-fallback { width:58px; height:58px; border-radius:12px; background:#C22626; color:#fff; text-align:center; line-height:58px; font-size:26px; font-weight:800; }
.site { font-size:18px; font-weight:800; color:#111; margin-bottom:2px; }
.sub { color:#666; font-size:10px; }
.report-title { font-size:24px; color:#C22626; font-weight:800; margin:8px 0 4px; }
.desc { color:#555; margin-bottom:16px; font-size:11px; }
.meta { text-align:right; color:#666; font-size:9.5px; }
.section-title { font-size:13px; font-weight:800; color:#111; margin:16px 0 8px; }
table { width:100%; border-collapse:collapse; margin-bottom:12px; table-layout:fixed; }
th { background:#C22626; color:#fff; text-align:left; padding:7px 6px; font-size:9.5px; }
td { border:1px solid #e1e1e1; padding:6px; vertical-align:top; word-wrap:break-word; }
tbody tr:nth-child(even) td { background:#fafafa; }
.summary td:first-child { font-weight:700; width:35%; background:#f8eeee; }
.empty { text-align:center; color:#777; padding:18px; }
.footer { position: fixed; bottom: -18px; left:0; right:0; text-align:center; font-size:8.5px; color:#777; border-top:1px solid #ddd; padding-top:6px; }
</style></head><body>';

$html .= '<div class="brand"><table class="brand-table"><tr><td style="width:70px;">' .
    ($logo ? '<img class="logo" src="' . esc($logo) . '">' : '<div class="logo-fallback">L</div>') .
    '</td><td><div class="site">Luke&apos;s Seafood Trading</div><div class="sub">Professional operations report</div><div class="report-title">' . esc($title) . '</div></td><td class="meta">Generated by<br><strong>' . esc((string)$generatedBy) . '</strong><br><br>' . esc($generatedAt) . '</td></tr></table></div>';
$html .= '<div class="desc">' . esc($description) . '</div>';
$html .= '<div class="section-title">Executive Summary</div><div class="summary">' . renderTable($summary) . '</div>';
$html .= '<div class="section-title">Report Details</div>' . renderTable($detail);
$html .= '<div class="footer">Luke&apos;s Seafood Trading • Confidential internal report • Generated ' . esc($generatedAt) . '</div>';
$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream(strtolower(str_replace(' ', '-', $title)) . '.pdf', ['Attachment' => true]);


