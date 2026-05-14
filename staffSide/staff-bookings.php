<?php
require_once 'staff-config.php';
requireStaff();

$successMsg = '';
$errorMsg   = '';

$rawInput = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$jsonPayload = null;
if ($rawInput !== '') {
    $jsonPayload = json_decode($rawInput, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($jsonPayload !== null || stripos($contentType, 'application/json') !== false)) {
    header('Content-Type: application/json');
    $data = is_array($jsonPayload) ? $jsonPayload : ($_POST ?: []);
    $action = trim((string)($data['action'] ?? ''));

    if ($action === 'update_status') {
        $bookingId = (int)($data['booking_id'] ?? 0);
        $status = $data['status'] ?? '';
        if ($bookingId <= 0 || !in_array($status, ['confirmed', 'cancelled'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking update.']);
            exit;
        }
        try {
            $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, $bookingId]);
            echo json_encode(['success' => true, 'message' => 'Booking updated to ' . $status . '.']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_booking') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
            exit;
        }
        $stmt = $pdo->prepare(
            'SELECT b.*, COALESCE(NULLIF(b.full_name, ""), u.name, "Guest") AS full_name,
                    COALESCE(NULLIF(b.email_address, ""), u.email, "N/A") AS email_address,
                    u.name AS user_name, u.email AS user_email
             FROM bookings b
             LEFT JOIN users u ON u.id = b.user_id
             WHERE b.id = ?'
        );
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'booking' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'get_day_bookings') {
        $date = trim((string)($data['date'] ?? ''));
        $stmt = $pdo->prepare(
            'SELECT b.*, COALESCE(NULLIF(b.full_name, ""), u.name, "Guest") AS full_name,
                    COALESCE(NULLIF(b.email_address, ""), u.email, "N/A") AS email_address,
                    u.name AS user_name, u.email AS user_email
             FROM bookings b
             LEFT JOIN users u ON u.id = b.user_id
             WHERE b.event_date = ?
             ORDER BY b.event_time ASC, created_at ASC'
        );
        $stmt->execute([$date]);
        echo json_encode(['success' => true, 'bookings' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// Staff can confirm or cancel bookings — but NOT delete them
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bid    = (int)($_POST['booking_id'] ?? 0);

    if ($bid > 0 && in_array($action, ['confirm_booking', 'cancel_booking'], true)) {
        $newStatus = $action === 'confirm_booking' ? 'confirmed' : 'cancelled';
        try {
            $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')
                ->execute([$newStatus, $bid]);
            $successMsg = 'Booking <strong>#BK-' . str_pad($bid, 3, '0', STR_PAD_LEFT) . '</strong> marked as ' . ucfirst($newStatus) . '.';
        } catch (\Throwable $e) {
            $errorMsg = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_booking') {
        // Staff cannot delete — show permission error
        $errorMsg = 'You do not have permission to delete bookings. Please contact an admin.';
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$whereClause = 'WHERE 1=1';
$params      = [];
if ($search !== '') {
    $whereClause .= ' AND (u.name LIKE :s OR b.full_name LIKE :s OR b.event_name LIKE :s)';
    $params[':s'] = "%$search%";
}
if ($status !== '') {
    $whereClause .= ' AND b.status = :st';
    $params[':st'] = $status;
}

try {
    $stmt = $pdo->prepare(
        "SELECT b.*,
                COALESCE(NULLIF(b.full_name, ''), u.name, 'Unknown') AS customer_name,
                COALESCE(NULLIF(b.email_address, ''), u.email, 'N/A') AS customer_email
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         $whereClause
         ORDER BY b.created_at DESC"
    );
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (\Throwable $_) {
    $bookings = [];
}

$stats    = getStaffStats($pdo);
$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');

$viewMode = $_GET['view'] ?? 'calendar';
if (!in_array($viewMode, ['calendar', 'table'], true)) $viewMode = 'calendar';
$currentMonth = (int)($_GET['month'] ?? date('n'));
$currentYear = (int)($_GET['year'] ?? date('Y'));
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = (int)date('n');
if ($currentYear < 2020 || $currentYear > 2030) $currentYear = (int)date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = (int)date('w', strtotime(sprintf('%04d-%02d-01', $currentYear, $currentMonth)));
$today = ($currentYear === (int)date('Y') && $currentMonth === (int)date('n')) ? (int)date('j') : null;

$bookingsByDate = [];
foreach ($bookings as $booking) {
    if (empty($booking['event_date'])) continue;
    $date = new DateTime($booking['event_date']);
    if ((int)$date->format('n') === $currentMonth && (int)$date->format('Y') === $currentYear) {
        $bookingsByDate[(int)$date->format('j')][] = $booking;
    }
}

function staffCalendarHtml(int $daysInMonth, int $firstDayOfWeek, ?int $today, array $bookingsByDate): string {
    $calendar = '';
    for ($i = 0; $i < $firstDayOfWeek; $i++) $calendar .= '<div class="cal-day empty"></div>';
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $cls = ($day === $today) ? 'cal-day today' : 'cal-day';
        $calendar .= '<div class="' . $cls . '" onclick="showDayBookings(' . $day . ')">';
        $calendar .= '<div class="cal-day-num">' . $day . '</div>';
        foreach (($bookingsByDate[$day] ?? []) as $booking) {
            $name = htmlspecialchars(substr($booking['customer_name'] ?? $booking['full_name'] ?? 'Guest', 0, 8));
            $statusClass = htmlspecialchars($booking['status'] ?? 'pending');
            $id = (int)$booking['id'];
            $bookingId = '#BK-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
            $calendar .= '<div class="cal-event ' . $statusClass . '" onclick="event.stopPropagation(); showBookingDetails(' . $id . ')">' . $bookingId . ' ' . $name . '</div>';
        }
        $calendar .= '</div>';
    }
    $totalCells = $firstDayOfWeek + $daysInMonth;
    for ($i = 0; $i < (42 - $totalCells); $i++) $calendar .= '<div class="cal-day empty"></div>';
    return $calendar;
}
$calendar = staffCalendarHtml($daysInMonth, $firstDayOfWeek, $today, $bookingsByDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Bookings — Luke's Staff</title>
<link rel="stylesheet" href="../adminSide/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="staff-bookings.css?v=<?= time() ?>">
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <aside class="sidebar" id="sidebar">
<?php $staffNavActive = 'bookings'; require __DIR__ . '/staff-sidebar-nav.php'; ?>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Booking Management</div>
          <div class="topbar-breadcrumb">Staff <span>/</span> Bookings</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i>
          <?php if ($stats['pending_bookings'] > 0): ?><span class="badge-dot"></span><?php endif; ?>
        </div>
        <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
        </div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Booking Management</h1>
          <p>Confirm or cancel bookings. Contact an admin to delete or create bookings.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <a href="../report-download.php?type=bookings" class="btn btn-outline"><i class="fa-solid fa-file-pdf"></i> Booking Report</a>
          <span class="permission-note"><i class="fa-solid fa-lock"></i> View & update only — no delete</span>
        </div>
      </div>

      <?php if ($successMsg): ?>
      <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
      <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errorMsg ?></div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value"><?= $stats['active_bookings'] ?></div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> confirmed</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= $stats['pending_bookings'] ?></div>
          <div class="stat-card-label">Pending Confirmation</div>
          <div class="stat-card-change <?= $stats['pending_bookings'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-down"></i> <?= $stats['pending_bookings'] > 0 ? 'needs action' : 'all handled' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div class="stat-card-value"><?= $stats['my_bookings_today'] ?></div>
          <div class="stat-card-label">Today's Events</div>
          <div class="stat-card-change up"><i class="fa-solid fa-clock"></i> scheduled today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-list-check"></i></div>
          <div class="stat-card-value"><?= count($bookings) ?></div>
          <div class="stat-card-label">Total Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-database"></i> in system</div>
        </div>
      </div>

      <!-- CALENDAR -->
      <div class="panel">
        <div class="panel-header">
          <div class="flex-between calendar-toolbar">
            <div class="flex-gap" style="align-items:center;flex-wrap:wrap;">
              <span class="panel-title"><i class="fa-solid fa-calendar" style="color:var(--red);margin-right:8px;"></i><?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></span>
              <select id="viewToggle" onchange="toggleView()">
                <option value="calendar" <?= $viewMode === 'calendar' ? 'selected' : '' ?>>Calendar View</option>
                <option value="table" <?= $viewMode === 'table' ? 'selected' : '' ?>>Table View</option>
              </select>
              <select id="yearSelect" onchange="changeYear()">
                <?php for ($year = 2020; $year <= 2030; $year++): ?>
                  <option value="<?= $year ?>" <?= $year === $currentYear ? 'selected' : '' ?>><?= $year ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="calendar-nav-group">
              <button class="btn btn-outline btn-sm" onclick="navigateMonth('prev')" title="Previous Month"><i class="fa-solid fa-chevron-left"></i> Prev</button>
              <button class="btn btn-outline btn-sm" onclick="navigateMonth('next')" title="Next Month">Next <i class="fa-solid fa-chevron-right"></i></button>
              <span class="badge badge-green">Confirmed</span>
              <span class="badge badge-yellow">Pending</span>
              <span class="badge badge-red">Cancelled</span>
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
            <div style="overflow-x:auto;">
              <table class="data-table">
                <thead><tr>
                  <th>ID</th><th>Customer</th><th>Event</th><th>Date</th><th>Time</th><th>Type</th><th>Guests</th><th>Status</th><th>Actions</th>
                </tr></thead>
                <tbody>
                  <?php if (empty($bookings)): ?>
                  <tr><td colspan="9" style="text-align:center;padding:24px;color:var(--muted);">No bookings found.</td></tr>
                  <?php else: ?>
                  <?php foreach ($bookings as $booking): ?>
                  <tr>
                    <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($booking['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($booking['customer_name']) ?></td>
                    <td><?= htmlspecialchars($booking['event_name'] ?? 'N/A') ?></td>
                    <td><?= $booking['event_date'] ? date('M d, Y', strtotime($booking['event_date'])) : 'N/A' ?></td>
                    <td><?= !empty($booking['event_time']) ? date('g:i A', strtotime($booking['event_time'])) : 'N/A' ?></td>
                    <td><?= htmlspecialchars($booking['event_type'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($booking['num_guests'] ?? 'N/A') ?></td>
                    <td><?= statusBadge($booking['status']) ?></td>
                    <td><button class="action-btn" title="View Details" onclick="showBookingDetails(<?= (int)$booking['id'] ?>)"><i class="fa-solid fa-eye"></i></button></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- BOOKINGS TABLE -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Bookings
            <span style="color:var(--muted);font-weight:400;font-size:0.82rem;margin-left:8px;">(<?= count($bookings) ?> shown)</span>
          </span>
          <div class="filter-bar">
            <form method="GET" class="filter-form" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="search-input" name="search" placeholder="Search customer…" value="<?= htmlspecialchars($search) ?>"/>
              </div>
              <select class="form-control" name="status" style="width:auto;padding:8px 12px;font-size:0.82rem;">
                <option value="">All Status</option>
                <option value="pending"   <?= $status==='pending'   ?'selected':'' ?>>Pending</option>
                <option value="confirmed" <?= $status==='confirmed' ?'selected':'' ?>>Confirmed</option>
                <option value="cancelled" <?= $status==='cancelled' ?'selected':'' ?>>Cancelled</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Filter</button>
              <?php if ($search || $status): ?>
              <a href="staff-bookings.php" class="btn btn-outline btn-sm">Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Booking ID</th><th>Customer</th><th>Email</th><th>Event Date</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php if (empty($bookings)): ?>
              <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--muted);">
                <?= $search || $status ? 'No bookings match your filters.' : 'No bookings found.' ?>
              </td></tr>
              <?php else: ?>
              <?php foreach ($bookings as $b): ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($b['customer_name'], 0, 2)) ?></div>
                    <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                  </div>
                </td>
                <td><?= htmlspecialchars($b['customer_email']) ?></td>
                <td><?= $b['event_date'] ? date('M d, Y', strtotime($b['event_date'])) : '—' ?></td>
                <td><?= statusBadge($b['status']) ?></td>
                <td>
                  <div class="flex-gap">
                    <?php if ($b['status'] === 'pending'): ?>
                    <!-- Confirm -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="confirm_booking">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="action-btn confirm" title="Confirm Booking"
                              onclick="return confirm('Confirm this booking?')">
                        <i class="fa-solid fa-check"></i>
                      </button>
                    </form>
                    <!-- Cancel -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="cancel_booking">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="action-btn" title="Cancel Booking"
                              onclick="return confirm('Cancel this booking?')">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    </form>
                    <?php else: ?>
                    <!-- View only for non-pending -->
                    <button class="action-btn" title="View Details" onclick="showBookingDetails(<?= (int)$b['id'] ?>)">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <?php endif; ?>
                    <!-- NO delete button for staff -->
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="bookingDetailModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-info-circle" style="color:var(--red);margin-right:8px;"></i>Booking Details</div>
    <div class="modal-body">
      <div style="margin-bottom:20px;">
        <div class="booking-id" id="bookingDetailId">#BK-001</div>
        <h3 style="color:#fff;margin-bottom:15px;" id="bookingDetailName">Customer Name</h3>
      </div>
      <div class="booking-detail-grid">
        <div><div class="detail-label">Event</div><div class="detail-value" id="bookingDetailEvent">Event Name</div></div>
        <div><div class="detail-label">Date</div><div class="detail-value" id="bookingDetailDate">Date</div></div>
        <div><div class="detail-label">Time</div><div class="detail-value" id="bookingDetailTime">Time</div></div>
        <div><div class="detail-label">Type</div><div class="detail-value" id="bookingDetailType">Event Type</div></div>
        <div><div class="detail-label">Guests</div><div class="detail-value" id="bookingDetailGuests">Number</div></div>
        <div><div class="detail-label">Status</div><span class="badge badge-yellow" id="bookingDetailStatus">Status</span></div>
      </div>
      <div style="margin-bottom:15px;"><div class="detail-label">Address</div><div class="detail-value" id="bookingDetailAddress">Event Address</div></div>
      <div class="booking-detail-grid">
        <div><div class="detail-label">Contact Number</div><div class="detail-value" id="bookingDetailContact">Phone</div></div>
        <div><div class="detail-label">Email</div><div class="detail-value" id="bookingDetailEmail">Email</div></div>
      </div>
      <div><div class="detail-label">Notes</div><div class="detail-value" id="bookingDetailNotes">Notes</div></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('bookingDetailModal')">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="dayBookingsModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-title"><i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for Date</div>
    <div class="modal-body"></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('dayBookingsModal')">Close</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
window.STAFF_BOOKINGS_CONTEXT = {
  month: <?= (int)$currentMonth ?>,
  year: <?= (int)$currentYear ?>,
  view: <?= json_encode($viewMode) ?>
};
</script>
<script src="staff-bookings.js?v=<?= time() ?>"></script>
</body>
</html>
