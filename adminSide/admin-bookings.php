<?php
require_once 'admin-config.php';
require_once '../activity-logger.php';
$data = null;

/** Whether the live `bookings` table has a given column (cached per request). */
function admin_bookings_has_column(PDO $pdo, string $column): bool
{
    $cache = &$GLOBALS['__admin_bookings_col_cache'];
    if (!is_array($cache)) {
        $cache = [];
    }
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    try {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute(['bookings', $column]);
        $cache[$column] = (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

/** Add `event_time_end` when DB was never migrated via getDB() (e.g. admin-only traffic). */
function admin_bookings_ensure_event_time_end(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (admin_bookings_has_column($pdo, 'event_time_end')) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN event_time_end TIME NULL DEFAULT NULL AFTER event_time');
        unset($GLOBALS['__admin_bookings_col_cache']['event_time_end']);
    } catch (Throwable $e) {
        error_log('admin_bookings_ensure_event_time_end: ' . $e->getMessage());
    }
}

function admin_bookings_parse_time_to_his(string $t): ?string
{
    $t = trim($t);
    foreach (['H:i:s', 'H:i', 'g:i A', 'h:i A', 'g:i a', 'h:i a'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $t);
        if ($d instanceof DateTime) {
            return $d->format('H:i:s');
        }
    }
    $ts = strtotime($t);
    if ($ts !== false) {
        return date('H:i:s', $ts);
    }

    return null;
}

/** @return array{0:?string,1:?string} start H:i:s, end H:i:s */
function admin_bookings_parse_event_time_field(string $field): array
{
    $field = trim($field);
    if ($field === '') {
        return [null, null];
    }
    if (preg_match('/^(.+?)\s*[\x{2013}\x{2014}-]\s*(.+)$/u', $field, $m)) {
        $a = admin_bookings_parse_time_to_his(trim($m[1]));
        $b = admin_bookings_parse_time_to_his(trim($m[2]));

        return [$a, $b];
    }
    $a = admin_bookings_parse_time_to_his($field);

    return [$a, $a];
}

function admin_bookings_his_to_ampm(string $sqlTime): string
{
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', trim($sqlTime), $m)) {
        return $sqlTime;
    }
    $h = (int) $m[1];
    $min = $m[2];
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12 ?: 12;

    return sprintf('%d:%s %s', $h12, $min, $ampm);
}

function admin_bookings_format_time_cell(array $b): string
{
    $st = isset($b['event_time']) ? trim((string) $b['event_time']) : '';
    $en = isset($b['event_time_end']) ? trim((string) $b['event_time_end']) : '';
    if ($st === '') {
        return 'N/A';
    }
    $startShow = admin_bookings_his_to_ampm($st);
    if ($en !== '' && $en !== '00:00:00' && $en !== $st) {
        return htmlspecialchars($startShow . ' – ' . admin_bookings_his_to_ampm($en), ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars($startShow, ENT_QUOTES, 'UTF-8');
}

admin_bookings_ensure_event_time_end($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // If it's a booking creation, allow it without admin session
    // Public/unauthenticated: customer booking create + account dashboard edits only.
    // get_booking / get_day_bookings require admin (see first branch → requireAdmin).
    $publicActions = ['create_booking', 'update_booking', 'update_status'];
    if ($data && isset($data['action']) && in_array($data['action'], $publicActions)) {
        // Safe to proceed to specific action validation
    } else {
        requireAdmin();
    }

} else {
    requireAdmin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (empty($rawInput) && empty($data)) {
        error_log("No input data received");
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    error_log("Decoded data: " . print_r($data, true));
    
    if (empty($data['action'])) {
        error_log("No action specified in request");
        echo json_encode(['success' => false, 'message' => 'No action specified']);
        exit;
    }
    
    if ($data['action'] === 'create_booking') {
        // Debug: Log received data
        error_log("Received booking data: " . print_r($data, true));
        
        // Get and sanitize input data
        $eventName = trim($data['eventName'] ?? '');
        $address = trim($data['address'] ?? '');
        $eventDate = trim($data['eventDate'] ?? '');
        $eventTime = trim($data['eventTime'] ?? '');
        $eventType = trim($data['eventType'] ?? '');
        $numGuests = trim($data['numGuests'] ?? '');
        $fullName = trim($data['fullName'] ?? '');
        $contactNumber = trim($data['contactNumber'] ?? '');
        $emailAddress = trim($data['emailAddress'] ?? '');
        $notes = trim($data['notes'] ?? '');
        $userEmail = trim($data['userEmail'] ?? '');
        $userName = trim($data['userName'] ?? '');
        
        // Validate required fields
        if (empty($eventName) || empty($fullName) || empty($contactNumber) || empty($emailAddress) || 
            empty($eventDate) || empty($eventTime) || empty($eventType) || empty($numGuests) || empty($address)) {
            
            $missing = [];
            if (empty($eventName)) $missing[] = 'eventName';
            if (empty($fullName)) $missing[] = 'fullName';
            if (empty($contactNumber)) $missing[] = 'contactNumber';
            if (empty($emailAddress)) $missing[] = 'emailAddress';
            if (empty($eventDate)) $missing[] = 'eventDate';
            if (empty($eventTime)) $missing[] = 'eventTime';
            if (empty($eventType)) $missing[] = 'eventType';
            if (empty($numGuests)) $missing[] = 'numGuests';
            if (empty($address)) $missing[] = 'address';
            
            echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
            exit;
        }
        
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        $dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
        if (!$dateObj) $dateObj = DateTime::createFromFormat('m/d/Y', $eventDate);
        if (!$dateObj) $dateObj = DateTime::createFromFormat('F j, Y', $eventDate);
        
        if (!$dateObj) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }
        $formattedDate = $dateObj->format('Y-m-d');

        [$tStart, $tEnd] = admin_bookings_parse_event_time_field($eventTime);
        if (!$tStart || !$tEnd) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format (use start – end, e.g. 1:00 PM – 8:00 PM)']);
            exit;
        }

        $columns = [
            'event_name', 'address', 'event_date', 'event_time', 'event_type', 'num_guests',
            'full_name', 'contact_number', 'email_address', 'notes'
        ];
        $placeholders = array_fill(0, count($columns), '?');
        $execValues = [
            $eventName, $address, $formattedDate, $tStart, $eventType, $numGuests,
            $fullName, $contactNumber, $emailAddress, $notes
        ];
        if (admin_bookings_has_column($pdo, 'event_time_end')) {
            $ti = array_search('event_time', $columns, true);
            if ($ti !== false) {
                array_splice($columns, $ti + 1, 0, ['event_time_end']);
                array_splice($placeholders, $ti + 1, 0, ['?']);
                array_splice($execValues, $ti + 1, 0, [$tEnd]);
            }
        }
        if (admin_bookings_has_column($pdo, 'user_id')) {
            array_unshift($columns, 'user_id');
            array_unshift($placeholders, '?');
            array_unshift($execValues, null);
        }
        $columns[] = 'status';
        $placeholders[] = "'pending'";
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
        if (admin_bookings_has_column($pdo, 'updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = 'NOW()';
        }

        $sql = 'INSERT INTO bookings (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($execValues);
            $bookingId = $pdo->lastInsertId();
            logActivity('booking_created', "Customer created booking for {$eventName} on {$formattedDate}", $userEmail, $userName);
            echo json_encode(['success' => true, 'message' => 'Booking created successfully', 'booking_id' => $bookingId]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'update_booking') {
        $bookingId = $data['booking_id'] ?? 0;
        
        // Fetch booking to check existence, ownership, and status
        $check = $pdo->prepare(
            "SELECT user_id, email_address, event_date, status, full_name, contact_number FROM bookings WHERE id = ?"
        );
        $check->execute([$bookingId]);
        $b = $check->fetch();

        if (!$b) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }

        // Ownership check: If not admin, must be the owner
        if (!isset($_SESSION['is_admin'])) {
            $ownerId = $_SESSION['user_id'] ?? -1;
            $ownerEmail = $_SESSION['user_email'] ?? '';
            if ($b['user_id'] != $ownerId && $b['email_address'] != $ownerEmail) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }
            
        $eventName = trim($data['eventName'] ?? '');
        $address = trim($data['address'] ?? '');
        $eventDate = trim($data['eventDate'] ?? '');
        $eventTime = trim($data['eventTime'] ?? '');
        $eventType = trim($data['eventType'] ?? '');
        $numGuests = trim($data['numGuests'] ?? '');
        $fullName = trim($data['fullName'] ?? '');
        $contactNumber = trim($data['contactNumber'] ?? '');
        $emailAddress = trim($data['emailAddress'] ?? '');
        if ($fullName === '') {
            $fullName = trim($b['full_name'] ?? '');
        }
        if ($contactNumber === '') {
            $contactNumber = trim($b['contact_number'] ?? '');
        }
        if ($emailAddress === '') {
            $emailAddress = trim($b['email_address'] ?? '');
        }
        $notes = trim($data['notes'] ?? '');

        // Format Date
        $dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
        if (!$dateObj) $dateObj = DateTime::createFromFormat('m/d/Y', $eventDate);
        if (!$dateObj) $dateObj = DateTime::createFromFormat('F j, Y', $eventDate);
        if (!$dateObj) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }
        $formattedDate = $dateObj->format('Y-m-d');

        [$tStart, $tEnd] = admin_bookings_parse_event_time_field($eventTime);
        if (!$tStart || !$tEnd) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format (use start – end, e.g. 1:00 PM – 8:00 PM)']);
            exit;
        }

        $rawTimeInput = trim((string) ($data['eventTime'] ?? ''));
        $hadRangeInInput = (bool) preg_match('/\s[\x{2013}\x{2014}-]\s/u', $rawTimeInput);
        if (!$hadRangeInInput && $tStart === $tEnd && admin_bookings_has_column($pdo, 'event_time_end')) {
            $cur = $pdo->prepare('SELECT event_time_end FROM bookings WHERE id = ?');
            $cur->execute([$bookingId]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            $prevEnd = isset($row['event_time_end']) ? trim((string) $row['event_time_end']) : '';
            if ($prevEnd !== '' && $prevEnd !== '00:00:00' && $prevEnd !== $tStart) {
                $tEnd = $prevEnd;
            }
        }

        // 3-day lead time check: New date must be at least 3 days from now
        $now = new DateTime();
        $newDateObj = new DateTime($formattedDate);
        $interval = $now->diff($newDateObj);
        $daysLeft = $interval->days;

        if (!isset($_SESSION['is_admin']) && ($interval->invert || $daysLeft < 3) && $b['status'] !== 'cancelled') {
            echo json_encode(['success' => false, 'message' => 'New event date must be at least 3 days from today.']);
            exit;
        }

        if ($fullName === '' || $contactNumber === '' || $emailAddress === '') {
            echo json_encode(['success' => false, 'message' => 'Customer name, contact, and email are required.']);
            exit;
        }
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        $setSql = "
            UPDATE bookings SET
                event_name = ?, address = ?, event_date = ?,
                event_time = ?, event_type = ?, num_guests = ?,
                full_name = ?, contact_number = ?, email_address = ?,
                notes = ?
        ";
        $exec = [
            $eventName, $address, $formattedDate, $tStart,
            $eventType, $numGuests, $fullName, $contactNumber, $emailAddress, $notes,
        ];
        if (admin_bookings_has_column($pdo, 'event_time_end')) {
            $setSql = "
            UPDATE bookings SET
                event_name = ?, address = ?, event_date = ?,
                event_time = ?, event_time_end = ?, event_type = ?, num_guests = ?,
                full_name = ?, contact_number = ?, email_address = ?,
                notes = ?
        ";
            $exec = [
                $eventName, $address, $formattedDate, $tStart, $tEnd,
                $eventType, $numGuests, $fullName, $contactNumber, $emailAddress, $notes,
            ];
        }
        if (admin_bookings_has_column($pdo, 'updated_at')) {
            $setSql .= ', updated_at = NOW()';
        }
        $setSql .= ' WHERE id = ?';
        $exec[] = $bookingId;

        $stmt = $pdo->prepare($setSql);

        try {
            $stmt->execute($exec);
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully']);
            exit;

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'update_status') {
        $bookingId = $data['booking_id'] ?? 0;
        $status = $data['status'] ?? 'pending';

        // Ownership check if not admin (only allow cancellation)
        if (!isset($_SESSION['is_admin'])) {
            if ($status !== 'cancelled') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized status update']);
                exit;
            }
            $check = $pdo->prepare("SELECT user_id, email_address, event_date FROM bookings WHERE id = ?");
            $check->execute([$bookingId]);
            $b = $check->fetch();
            if (!$b || ($b['user_id'] != ($_SESSION['user_id'] ?? -1) && $b['email_address'] != ($_SESSION['user_email'] ?? ''))) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }


        }


        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        try {
            $stmt->execute([$status, $bookingId]);
            echo json_encode(['success' => true, 'message' => "Booking updated to $status"]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'clear_bookings') {
        try {
            $pdo->exec("DELETE FROM bookings");
            echo json_encode(['success' => true, 'message' => 'All bookings cleared']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'get_booking') {
        $id = (int)($data['id'] ?? 0);
        if ($id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking id']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT b.*, u.email AS user_email, u.name AS user_name 
            FROM bookings b 
            LEFT JOIN users u ON b.user_id = u.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }

        // Multi-day submissions create one row per date with the same metadata; surface sibling rows.
        $related = [];
        try {
            $relStmt = $pdo->prepare("
                SELECT id, event_date, event_time, event_time_end, status
                FROM bookings
                WHERE id != ?
                  AND full_name <=> ?
                  AND email_address <=> ?
                  AND event_name <=> ?
                  AND contact_number <=> ?
                  AND created_at = ?
                ORDER BY event_date ASC, id ASC
            ");
            $relStmt->execute([
                $id,
                $booking['full_name'] ?? '',
                $booking['email_address'] ?? '',
                $booking['event_name'] ?? '',
                $booking['contact_number'] ?? '',
                $booking['created_at'] ?? null,
            ]);
            $related = $relStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $related = [];
        }

        echo json_encode(
            ['success' => true, 'booking' => $booking, 'related_bookings' => $related],
            JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
        );
        exit;
    }
    
    if ($data['action'] === 'get_day_bookings') {
        $date = $data['date'] ?? '';
        $stmt = $pdo->prepare("
            SELECT b.*, u.email AS user_email, u.name AS user_name 
            FROM bookings b 
            LEFT JOIN users u ON b.user_id = u.id 
            WHERE b.event_date = ? 
            ORDER BY b.created_at
        ");
        $stmt->execute([$date]);
        echo json_encode(
            ['success' => true, 'bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
            JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)
        );
        exit;
    }
}

// Get bookings for display
$bookings = [];
try {
    $bookings = $pdo->query("
        SELECT b.*, u.email AS user_email, u.name AS user_name 
        FROM bookings b 
        LEFT JOIN users u ON b.user_id = u.id 
        ORDER BY b.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {}

$viewMode = $_GET['view'] ?? 'calendar';
$requestedMonth = (int)($_GET['month'] ?? date('n'));
$requestedYear = (int)($_GET['year'] ?? date('Y'));
if ($requestedMonth < 1 || $requestedMonth > 12) $requestedMonth = date('n');
$currentMonth = $requestedMonth;
$currentYear = $requestedYear;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = date('w', strtotime("$currentYear-$currentMonth-01"));
$today = ($currentYear == date('Y') && $currentMonth == date('n')) ? date('j') : null;

$prevMonth = $currentMonth - 1; $nextMonth = $currentMonth + 1;
$prevYear = $currentYear; $nextYear = $currentYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$bookingsByDate = [];
foreach ($bookings as $booking) {
    $d = new DateTime($booking['event_date']);
    if ((int)$d->format('n') == $currentMonth && (int)$d->format('Y') == $currentYear) {
        $day = $d->format('j');
        $bookingsByDate[$day][] = $booking;
    }
}

function generateCalendar($currentMonth, $currentYear, $daysInMonth, $firstDayOfWeek, $today, $bookingsByDate) {
    $calendar = '';
    for ($i = 0; $i < $firstDayOfWeek; $i++) $calendar .= '<div class="cal-day empty"></div>';
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $cls = ($day == $today) ? 'cal-day today' : 'cal-day';
        $calendar .= '<div class="' . $cls . '" onclick="showDayBookings(' . $day . ')">';
        $calendar .= '<div class="cal-day-num">' . $day . '</div>';
        if (isset($bookingsByDate[$day])) {
            foreach ($bookingsByDate[$day] as $b) {
                $bid = '#BK-' . str_pad($b['id'], 3, '0', STR_PAD_LEFT);
                $name = substr($b['full_name'], 0, 8);
                $calendar .= '<div class="cal-event ' . $b['status'] . '" onclick="event.stopPropagation(); showBookingDetails(' . $b['id'] . ')">' . $bid . ' ' . $name . '</div>';
            }
        }
        $calendar .= '</div>';
    }
    $totalCells = $firstDayOfWeek + $daysInMonth;
    for ($i = 0; $i < (42 - $totalCells); $i++) $calendar .= '<div class="cal-day empty"></div>';
    return $calendar;
}
$calendar = generateCalendar($currentMonth, $currentYear, $daysInMonth, $firstDayOfWeek, $today, $bookingsByDate);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Booking Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<link rel="stylesheet" href="admin-bookings.css?v=<?= time() ?>">
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <aside class="sidebar" id="sidebar">
<?php $adminNavActive = 'bookings'; require __DIR__ . '/admin-sidebar-nav.php'; ?>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Booking Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Bookings</div>
        </div>
      </div>
      <div class="topbar-right">
        <?php require __DIR__ . '/admin-topbar-right.php'; ?>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Booking Management</h1>
          <p>Monitor bookings, assign staff & equipment, and prevent double-booking.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <a href="../report-download.php?type=bookings" class="btn btn-outline"><i class="fa-solid fa-file-pdf"></i> Booking Report</a>
          <button class="btn btn-primary" onclick="openModal('newBookingModal')"><i class="fa-solid fa-calendar-plus"></i> New Booking</button>
        </div>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value"><?= count(array_filter($bookings, fn($b) => $b['status'] !== 'cancelled')) ?></div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +<?= count(array_filter($bookings, fn($b) => $b['status'] !== 'cancelled' && date('Y-m-d', strtotime($b['created_at'])) === date('Y-m-d'))) ?> today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'pending')) ?></div>
          <div class="stat-card-label">Pending Confirmation</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs action</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-circle-xmark"></i></div>
          <div class="stat-card-value"><?= count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled' && date('Y-m', strtotime($b['created_at'])) === date('Y-m'))) ?></div>
          <div class="stat-card-label">Cancelled This Month</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> <?= count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled')) ?> total</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div class="stat-card-value"><?= count(array_filter($bookings, fn($b) => $b['event_date'] === date('Y-m-d'))) ?></div>
          <div class="stat-card-label">Today's Bookings</div>
          <div class="stat-card-change"><?= date('M j') ?></div>
        </div>
      </div>

      <div class="grid-2">
        <!-- CALENDAR -->
        <div class="panel" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div class="flex-between" style="width: 100%;">
              <div class="flex-gap" style="align-items: center;">
                <span class="panel-title"><i class="fa-solid fa-calendar" style="color:var(--red);margin-right:8px;"></i><?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></span>
                <select id="viewToggle" onchange="toggleView()" style="background: var(--dark); border: 1px solid var(--line-w); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                  <option value="calendar" <?= $viewMode === 'calendar' ? 'selected' : '' ?>>Calendar View</option>
                  <option value="table" <?= $viewMode === 'table' ? 'selected' : '' ?>>Table View</option>
                </select>
                <select id="yearSelect" onchange="changeYear()" style="background: var(--dark); border: 1px solid var(--line-w); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                  <?php 
                  $startYear = (int) date('Y') - 5;
                  $endYear = (int) date('Y') + 8;
                  for ($year = $startYear; $year <= $endYear; $year++) {
                    echo '<option value="' . $year . '"' . ($year == $currentYear ? ' selected' : '') . '>' . $year . '</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="calendar-nav-group">
                <button class="btn btn-outline btn-sm" onclick="navigateMonth('prev')" title="Previous Month">
                  <i class="fa-solid fa-chevron-left"></i> Prev
                </button>
                <button class="btn btn-outline btn-sm" onclick="navigateMonth('next')" title="Next Month">
                  Next <i class="fa-solid fa-chevron-right"></i>
                </button>
                <div style="border-left: 1px solid var(--line-w); padding-left: 10px; display: flex; gap: 8px;">
                  <span class="badge badge-green">● Confirmed</span>
                  <span class="badge badge-yellow">● Pending</span>
                  <span class="badge badge-red">● Cancelled</span>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="clearAllBookings()" title="Clear All Bookings">
                  <i class="fa-solid fa-trash"></i> Clear All
                </button>
                <?php if ($viewMode === 'table'): ?>
                <div class="search-wrap" style="min-width: 200px; max-width: 280px;">
                  <i class="fa-solid fa-magnifying-glass"></i>
                  <input type="search" id="bookingsTableSearch" class="search-input" placeholder="Filter table…" autocomplete="off" aria-label="Filter bookings table">
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="panel-body">
            <?php if ($viewMode === 'calendar'): ?>
              <div class="calendar-grid">
                <div class="cal-header">Sun</div><div class="cal-header">Mon</div><div class="cal-header">Tue</div>
                <div class="cal-header">Wed</div><div class="cal-header">Thu</div><div class="cal-header">Fri</div><div class="cal-header">Sat</div>
                <?= $calendar ?>
              </div>
            <?php else: ?>
              <div id="bookingsTableWrap" style="overflow-x:auto;">
                <table class="data-table" id="bookingsDataTable">
                  <thead>
                    <tr>
                      <th>ID</th><th>Customer</th><th>Event Name</th><th>Date</th><th>Time</th><th>Type</th><th>Guests</th><th>Status</th><th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($bookings)): ?>
                      <?php foreach ($bookings as $booking): ?>
                        <tr onclick="showBookingDetails(<?= $booking['id'] ?>)" style="cursor: pointer;" title="Click to view details">
                          <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($booking['id'], 3, '0', STR_PAD_LEFT) ?></td>
                          <td>
                            <div class="flex-gap">
                              <div class="user-avatar"><?= strtoupper(substr($booking['full_name'], 0, 2)) ?></div>
                              <?= htmlspecialchars($booking['full_name'] ?? 'Guest') ?>
                            </div>
                          </td>
                          <td><?= htmlspecialchars($booking['event_name'] ?? 'N/A') ?></td>
                          <td><?= date('M d, Y', strtotime($booking['event_date'])) ?></td>
                          <td><?= admin_bookings_format_time_cell($booking) ?></td>
                          <td><?= htmlspecialchars($booking['event_type'] ?? 'N/A') ?></td>
                          <td><?= htmlspecialchars($booking['num_guests'] ?? 'N/A') ?></td>
                          <td>
                            <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'green' : ($booking['status'] === 'cancelled' ? 'red' : 'yellow') ?>">
                              <?= ucfirst($booking['status']) ?>
                            </span>
                          </td>
                          <td onclick="event.stopPropagation();">
                            <div class="flex-gap">
                              <button type="button" class="action-btn" title="View full client details" onclick="showBookingDetails(<?= (int)$booking['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                            <?php if ($booking['status'] === 'pending'): ?>
                                <button type="button" class="action-btn edit" title="Confirm" onclick="updateBookingStatus(<?= $booking['id'] ?>, 'confirmed')">
                                  <i class="fa-solid fa-check"></i>
                                </button>
                                <button type="button" class="action-btn" title="Cancel" onclick="updateBookingStatus(<?= $booking['id'] ?>, 'cancelled')">
                                  <i class="fa-solid fa-x" style="color: rgb(0, 0, 0);"></i>
                                </button>
                            <?php else: ?>
                              <button type="button" class="action-btn edit" title="Edit" onclick="editBooking(<?= $booking['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                            <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--muted);">
                          <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                          No bookings yet. Bookings will appear here once customers submit them.
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="panel" style="margin-top: 1rem;">
        <div class="panel-header flex-between" style="flex-wrap: wrap; gap: 12px;">
          <span class="panel-title"><i class="fa-solid fa-lightbulb" style="color:var(--red);margin-right:8px;"></i>Using this page</span>
        </div>
        <div class="panel-body">
          <p style="font-size:0.85rem;color:var(--muted);margin:0;line-height:1.55;">
            <strong>Calendar</strong> shows this month’s events; click a day for that day’s list, or click a coloured chip for full client details.
            Switch to <strong>Table view</strong> and use the search box to filter the list. Pending bookings can be confirmed or cancelled from the Actions column.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- New Booking Modal -->
<div class="modal-overlay" id="newBookingModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--red);margin-right:8px;"></i>New Booking</div>
    <form id="newBookingForm">
      <div class="form-group">
        <label class="form-label">Event Name *</label>
        <input type="text" class="form-control" id="newEventName" placeholder="e.g. Birthday Party" required>
      </div>
      <div class="form-group">
        <label class="form-label">Customer Name *</label>
        <input type="text" class="form-control" id="newFullName" placeholder="e.g. Maria Santos" required>
      </div>
      <div class="form-group">
        <label class="form-label">Contact Number *</label>
        <input type="tel" class="form-control" id="newContactNumber" placeholder="e.g. 09123456789" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address *</label>
        <input type="email" class="form-control" id="newEmailAddress" placeholder="e.g. maria@example.com" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Event Date *</label>
          <input type="date" class="form-control" id="newEventDate" required>
        </div>
        <div class="form-group">
          <label class="form-label">Event time (range) *</label>
          <input type="text" class="form-control" id="newEventTime" placeholder="e.g. 1:00 PM – 8:00 PM" required>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Event Type *</label>
          <select class="form-control" id="newEventType" required>
            <option value="">Select event type</option>
            <option value="Birthday">Birthday Party</option>
            <option value="Wedding">Wedding Reception</option>
            <option value="Corporate">Corporate Event</option>
            <option value="Family">Family Gathering</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Number of Guests *</label>
          <input type="number" class="form-control" id="newNumGuests" placeholder="e.g. 50" min="1" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Event Address *</label>
        <input type="text" class="form-control" id="newAddress" placeholder="e.g. 123 Main St, City" required>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" id="newNotes" rows="3" placeholder="Special instructions or notes..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('newBookingModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Create Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- Booking Details Modal -->
<div class="modal-overlay" id="bookingDetailModal">
  <div class="modal modal-booking-detail">
    <div class="modal-title"><i class="fa-solid fa-info-circle" style="color:var(--red);margin-right:8px;"></i>Booking Details</div>
    <div class="modal-body">
      <div style="margin-bottom: 20px;">
        <div class="booking-id" id="bookingDetailId">#BK-001</div>
        <h3 style="color: #fff; margin-bottom: 15px;" id="bookingDetailName">Customer Name</h3>
      </div>
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Event</div>
          <div style="font-weight: 600;" id="bookingDetailEvent">Event Name</div>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Date</div>
          <div style="font-weight: 600;" id="bookingDetailDate">Date</div>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Time</div>
          <div style="font-weight: 600;" id="bookingDetailTime">Time</div>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Type</div>
          <div style="font-weight: 600;" id="bookingDetailType">Event Type</div>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Guests</div>
          <div style="font-weight: 600;" id="bookingDetailGuests">Number</div>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Status</div>
          <span class="badge badge-yellow" id="bookingDetailStatus">Status</span>
        </div>
      </div>
      
      <div style="margin-bottom: 15px;">
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Address</div>
        <div style="font-weight: 600; white-space: pre-wrap; word-break: break-word;" id="bookingDetailAddress">Event Address</div>
      </div>

      <div id="bookingDetailRelatedWrap" style="display: none; margin-bottom: 15px; padding: 12px; background: rgba(194,38,38,0.08); border: 1px solid rgba(194,38,38,0.25); border-radius: 8px;">
        <div style="font-size: 0.7rem; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em;">Other dates from the same submission</div>
        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.9);" id="bookingDetailRelatedList"></div>
      </div>
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Contact Number</div>
          <div style="font-weight: 600;" id="bookingDetailContact">Phone</div>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Email</div>
          <div style="font-weight: 600;" id="bookingDetailEmail">Email</div>
        </div>
      </div>
      
      <div>
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Notes / special requests</div>
        <div style="font-weight: 600; white-space: pre-wrap; word-break: break-word;" id="bookingDetailNotes">Notes</div>
      </div>
      
      <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--line-w);">
        <div style="font-size: 0.7rem; color: var(--muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;">Account Information</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">User Name</div>
            <div style="font-weight: 600;" id="bookingDetailUserName">-</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">User Email</div>
            <div style="font-weight: 600;" id="bookingDetailUserEmail">-</div>
          </div>
        </div>
        <div id="bookingDetailUserIdRow" style="display: none; margin-top: 12px;">
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Linked user ID</div>
          <div style="font-weight: 600;" id="bookingDetailUserId">-</div>
        </div>
      </div>
      
      <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--line-w);">
        <div style="font-size: 0.7rem; color: var(--muted); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.05em;">Timestamps</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Created</div>
            <div style="font-weight: 600;" id="bookingDetailCreated">-</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Last Updated</div>
            <div style="font-weight: 600;" id="bookingDetailUpdated">-</div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px;">
      <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" id="bookingDetailBtnEdit"><i class="fa-solid fa-pen"></i> Edit</button>
        <button type="button" class="btn btn-primary btn-sm" id="bookingDetailBtnConfirm" style="display:none;"><i class="fa-solid fa-check"></i> Confirm</button>
        <button type="button" class="btn btn-danger btn-sm" id="bookingDetailBtnCancelBk" style="display:none;"><i class="fa-solid fa-ban"></i> Cancel booking</button>
      </div>
      <button type="button" class="btn btn-outline" onclick="closeModal('bookingDetailModal')">Close</button>
    </div>
  </div>
</div>

<!-- Edit Booking Modal -->
<div class="modal-overlay" id="editBookingModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-pen" style="color:var(--red);margin-right:8px;"></i>Edit Booking</div>
    <form id="editBookingForm">
      <input type="hidden" id="editBookingId">
      <div class="form-group">
        <label class="form-label">Event Name *</label>
        <input type="text" class="form-control" id="editEventName" required>
      </div>
      <div class="form-group">
        <label class="form-label">Customer Name *</label>
        <input type="text" class="form-control" id="editFullName" required>
      </div>
      <div class="form-group">
        <label class="form-label">Contact Number *</label>
        <input type="tel" class="form-control" id="editContactNumber" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address *</label>
        <input type="email" class="form-control" id="editEmailAddress" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Event Date *</label>
          <input type="date" class="form-control" id="editEventDate" required>
        </div>
        <div class="form-group">
          <label class="form-label">Event time (range) *</label>
          <input type="text" class="form-control" id="editEventTime" placeholder="e.g. 1:00 PM – 8:00 PM" required>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Event Type *</label>
          <select class="form-control" id="editEventType" required>
            <option value="">Select event type</option>
            <option value="Birthday">Birthday Party</option>
            <option value="Wedding">Wedding Reception</option>
            <option value="Corporate">Corporate Event</option>
            <option value="Family">Family Gathering</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Number of Guests *</label>
          <input type="number" class="form-control" id="editNumGuests" min="1" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Event Address *</label>
        <input type="text" class="form-control" id="editAddress" required>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" id="editNotes" rows="3" placeholder="Special instructions or notes..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editBookingModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- Day Bookings Modal -->
<div class="modal-overlay" id="dayBookingsModal">
  <div class="modal" style="max-width: 600px;">
    <div class="modal-title"><i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for Date</div>
    <div class="modal-body">
      <!-- Content will be dynamically populated -->
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('dayBookingsModal')">Close</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>





<script defer src="admin-notifications.js?v=<?= time() ?>"></script>
<script type="application/json" id="adminBookingsPageConfig"><?= json_encode([
    'calendarMonth' => (int) $currentMonth,
    'calendarYear' => (int) $currentYear,
], JSON_UNESCAPED_UNICODE) ?></script>
<script src="admin-bookings.js?v=<?= time() ?>"></script>
</body>
</html>


