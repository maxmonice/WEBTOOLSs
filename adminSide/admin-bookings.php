<?php
require_once 'admin-config.php';
require_once '../activity-logger.php';
$data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // If it's a booking creation, allow it without admin session
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
        
        $timeObj = DateTime::createFromFormat('H:i', $eventTime);
        if (!$timeObj) $timeObj = DateTime::createFromFormat('h:i A', $eventTime);
        
        if (!$timeObj) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            exit;
        }
        $formattedTime = $timeObj->format('H:i');
        
        $stmt = $pdo->prepare("
            INSERT INTO bookings (
                user_id, event_name, address, event_date, event_time, 
                event_type, num_guests, full_name, contact_number, 
                email_address, notes, user_email, user_name, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        try {
            $stmt->execute([
                null, $eventName, $address, $formattedDate, $formattedTime,
                $eventType, $numGuests, $fullName, $contactNumber,
                $emailAddress, $notes, $userEmail, $userName
            ]);
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
        $check = $pdo->prepare("SELECT user_id, email_address, event_date, status FROM bookings WHERE id = ?");
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

        // Format Time
        $timeObj = DateTime::createFromFormat('H:i', $eventTime);
        if (!$timeObj) $timeObj = DateTime::createFromFormat('h:i A', $eventTime);
        if (!$timeObj) $timeObj = DateTime::createFromFormat('h:i K', $eventTime);
        if (!$timeObj) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            exit;
        }
        $formattedTime = $timeObj->format('H:i:s');

        // 3-day lead time check: New date must be at least 3 days from now
        $now = new DateTime();
        $newDateObj = new DateTime($formattedDate);
        $interval = $now->diff($newDateObj);
        $daysLeft = $interval->days;

        if (($interval->invert || $daysLeft < 3) && $b['status'] !== 'cancelled') {
            echo json_encode(['success' => false, 'message' => 'New event date must be at least 3 days from today.']);
            exit;
        }



        $stmt = $pdo->prepare("
            UPDATE bookings SET 
                event_name = ?, address = ?, event_date = ?, 
                event_time = ?, event_type = ?, num_guests = ?, 
                notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        try {
            $stmt->execute([
                $eventName, $address, $formattedDate, $formattedTime, 
                $eventType, $numGuests, $notes, $bookingId
            ]);
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
        $id = $data['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'booking' => $stmt->fetch()]);
        exit;
    }
    
    if ($data['action'] === 'get_day_bookings') {
        $date = $data['date'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE event_date = ? ORDER BY created_at");
        $stmt->execute([$date]);
        echo json_encode(['success' => true, 'bookings' => $stmt->fetchAll()]);
        exit;
    }
}

// Get bookings for display
$bookings = [];
try {
    $bookings = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC")->fetchAll();
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
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Admin Panel</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="admin-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="admin-users.php" class="nav-item"><i class="fa-solid fa-users"></i> User Management</a>
      <a href="admin-bookings.php" class="nav-item active"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
      <a href="admin-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
      <a href="admin-content.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Content Management</a>
      <a href="admin-promos.php" class="nav-item"><i class="fa-solid fa-ticket"></i> Promo Management</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-account.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="../index.php" class="logout-btn" style="background: #22c55e; color: #fff;"><i class="fa-solid fa-home"></i> Home</a>
    </div>
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
        <div class="notification-dropdown">
          <div class="topbar-badge" onclick="toggleNotifications()">
            <i class="fa-regular fa-bell"></i>
            <span class="badge-dot"></span>
          </div>
          <div class="notification-menu" id="notificationMenu">
            <div class="notification-header">
              <h4>Notifications</h4>
              <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
            </div>
            <div class="notification-list">
              <div class="notification-item unread">
                <div class="notification-icon">
                  <i class="fa-solid fa-shopping-cart"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">New Order Received</div>
                  <div class="notification-message">Order #ORD-0001 has been placed</div>
                  <div class="notification-time">2 minutes ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
              <div class="notification-item unread">
                <div class="notification-icon">
                  <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">New Booking Confirmed</div>
                  <div class="notification-message">Event booking for May 15, 2025</div>
                  <div class="notification-time">15 minutes ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
              <div class="notification-item">
                <div class="notification-icon">
                  <i class="fa-solid fa-user-plus"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">New User Registered</div>
                  <div class="notification-message">John Doe joined the platform</div>
                  <div class="notification-time">1 hour ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
              <div class="notification-item">
                <div class="notification-icon">
                  <i class="fa-solid fa-truck"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">Order Shipped</div>
                  <div class="notification-message">Order #ORD-0002 has been shipped</div>
                  <div class="notification-time">2 hours ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
            </div>
            <div class="notification-footer">
              <a href="admin-logs.php" class="view-all-link">View all notifications</a>
            </div>
          </div>
        </div>
        <a href="admin-account.php" class="admin-avatar">A</a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Booking Management</h1>
          <p>Monitor bookings, assign staff & equipment, and prevent double-booking.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('newBookingModal')"><i class="fa-solid fa-calendar-plus"></i> New Booking</button>
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
                  $startYear = 2020;
                  $endYear = 2030;
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
                <button class="btn btn-danger btn-sm" onclick="clearAllBookings()" title="Clear All Bookings">
                  <i class="fa-solid fa-trash"></i> Clear All
                </button>
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
                  <thead>
                    <tr>
                      <th>ID</th><th>Customer</th><th>Event Name</th><th>Date</th><th>Time</th><th>Type</th><th>Guests</th><th>Status</th><th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($bookings)): ?>
                      <?php foreach ($bookings as $booking): ?>
                        <?php 
                          // Parse booking details from notes field
                          $bookingDetails = json_decode($booking['notes'], true) ?: [];
                          $fullName = $bookingDetails['full_name'] ?? 'Guest';
                          $eventName = $bookingDetails['event_name'] ?? 'N/A';
                          $eventTime = $bookingDetails['event_time'] ?? 'N/A';
                          $eventType = $bookingDetails['event_type'] ?? 'N/A';
                          $numGuests = $bookingDetails['num_guests'] ?? 'N/A';
                        ?>
                        <tr>
                          <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($booking['id'], 3, '0', STR_PAD_LEFT) ?></td>
                          <td>
                            <div class="flex-gap">
                              <div class="user-avatar"><?= strtoupper(substr($fullName, 0, 2)) ?></div>
                              <?= htmlspecialchars($fullName) ?>
                            </div>
                          </td>
                          <td><?= htmlspecialchars($eventName) ?></td>
                          <td><?= date('M d, Y', strtotime($booking['event_date'])) ?></td>
                          <td><?= $eventTime ?></td>
                          <td><?= htmlspecialchars($eventType) ?></td>
                          <td><?= $numGuests ?></td>
                          <td>
                            <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'green' : ($booking['status'] === 'cancelled' ? 'red' : 'yellow') ?>">
                              <?= ucfirst($booking['status']) ?>
                            </span>
                          </td>
                          <td>
                            <?php if ($booking['status'] === 'pending'): ?>
                              <div class="flex-gap">
                                <button class="action-btn edit" title="Confirm" onclick="updateBookingStatus(<?= $booking['id'] ?>, 'confirmed')">
                                  <i class="fa-solid fa-check"></i>
                                </button>
                                <button class="action-btn" title="Cancel" onclick="updateBookingStatus(<?= $booking['id'] ?>, 'cancelled')">
                                  <i class="fa-solid fa-xmark"></i>
                                </button>
                              </div>
                            <?php else: ?>
                              <button class="action-btn edit"><i class="fa-solid fa-pen"></i></button>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--muted);">
                          <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                          No bookings yet. Bookings will appear here once customers submit them.
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- BOOKINGS TABLE + RESOURCES -->
      <div class="grid-2" style="margin-top:0;">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Bookings</span>
            <div class="search-wrap">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="search-input" placeholder="Search..." style="max-width:160px;"/>
            </div>
          </div>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th><th>Customer</th><th>Date</th><th>Status</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($bookings)): ?>
                  <?php foreach ($bookings as $booking): ?>
                    <?php 
                      // Parse booking details from notes field
                      $bookingDetails = json_decode($booking['notes'], true) ?: [];
                      $fullName = $bookingDetails['full_name'] ?? 'Guest';
                    ?>
                    <tr>
                      <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($booking['id'], 3, '0', STR_PAD_LEFT) ?></td>
                      <td>
                        <div class="flex-gap">
                          <div class="user-avatar"><?= strtoupper(substr($fullName, 0, 2)) ?></div>
                          <?= htmlspecialchars($fullName) ?>
                        </div>
                      </td>
                      <td><?= date('M d, Y', strtotime($booking['event_date'])) ?></td>
                      <td>
                        <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'green' : ($booking['status'] === 'cancelled' ? 'red' : 'yellow') ?>">
                          <?= ucfirst($booking['status']) ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($booking['status'] === 'pending'): ?>
                          <div class="flex-gap">
                            <button class="action-btn edit" title="Confirm" onclick="updateBookingStatus(<?= $booking['id'] ?>, 'confirmed')">
                              <i class="fa-solid fa-check"></i>
                            </button>
                            <button class="action-btn" title="Cancel" onclick="updateBookingStatus(<?= $booking['id'] ?>, 'cancelled')">
                              <i class="fa-solid fa-xmark"></i>
                            </button>
                          </div>
                        <?php else: ?>
                          <button class="action-btn edit"><i class="fa-solid fa-pen"></i></button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--muted);">
                      <i class="fa-solid fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                      No bookings yet. Bookings will appear here once customers submit them.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- STAFF & EQUIPMENT -->
        <div class="panel">
          <div class="panel-header"><span class="panel-title">Staff & Equipment</span><span class="badge badge-gray">Resource Tracker</span></div>
          <div class="panel-body">
            <p style="font-size:0.75rem;color:var(--muted);margin-bottom:14px;">Assigned resources for active bookings. Prevents double-booking.</p>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>No staff assigned</div>
                <div class="resource-sub">Staff management will be implemented</div>
              </div>
              <span class="badge badge-gray">N/A</span>
            </div>
          </div>
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
          <label class="form-label">Event Time *</label>
          <input type="time" class="form-control" id="newEventTime" required>
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
  <div class="modal">
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
        <div style="font-weight: 600;" id="bookingDetailAddress">Event Address</div>
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
        <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Notes</div>
        <div style="font-weight: 600;" id="bookingDetailNotes">Notes</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('bookingDetailModal')">Close</button>
    </div>
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





<script src="admin-bookings.js?v=<?= time() ?>"></script>
</body>
</html>
