<?php
require_once 'admin-config.php';
require_once 'activity-logger.php';
requireAdmin();

// Handle booking creation from frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    error_log("Raw input received: " . $rawInput);
    
    if (empty($rawInput)) {
        error_log("No input data received");
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }
    
    $data = json_decode($rawInput, true);
    
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
        
        // Validate required fields - use isset and !empty combination
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
            
            error_log("Missing fields: " . implode(', ', $missing));
            echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
            exit;
        }
        
        // Validate email format
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email format: " . $emailAddress);
            echo json_encode(['success' => false, 'message' => 'Invalid email format: ' . $emailAddress]);
            exit;
        }
        
        // Validate date format - try multiple formats
        $dateObj = DateTime::createFromFormat('Y-m-d', $eventDate);
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('m/d/Y', $eventDate);
        }
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('F j, Y', $eventDate);
        }
        if (!$dateObj) {
            error_log("Invalid date format: " . $eventDate);
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD format.']);
            exit;
        }
        $formattedDate = $dateObj->format('Y-m-d');
        
        // Validate time format
        $timeObj = DateTime::createFromFormat('H:i', $eventTime);
        if (!$timeObj) {
            $timeObj = DateTime::createFromFormat('h:i A', $eventTime);
        }
        if (!$timeObj) {
            error_log("Invalid time format: " . $eventTime);
            echo json_encode(['success' => false, 'message' => 'Invalid time format. Use HH:MM format.']);
            exit;
        }
        $formattedTime = $timeObj->format('H:i');
        
        error_log("Processed data - Date: $formattedDate, Time: $formattedTime");
        
        // Insert booking into database using existing table structure
        $stmt = $pdo->prepare("
            INSERT INTO bookings (
                user_id, status, event_date, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        // Store all booking details in notes field as JSON
        $bookingNotes = json_encode([
            'event_name' => $eventName,
            'address' => $address,
            'event_time' => $formattedTime,
            'event_type' => $eventType,
            'num_guests' => $numGuests,
            'full_name' => $fullName,
            'contact_number' => $contactNumber,
            'email_address' => $emailAddress,
            'user_email' => $userEmail,
            'user_name' => $userName,
            'original_notes' => $notes
        ]);
        
        try {
            $result = $stmt->execute([
                null, // user_id (null for guest bookings)
                'pending',
                $formattedDate,
                $bookingNotes
            ]);
            
            if ($result) {
                $bookingId = $pdo->lastInsertId();
                
                // Log booking creation activity
                logActivity('booking_created', "Customer created booking for {$eventName} on {$formattedDate} with {$numGuests} guests", $userEmail, $userName);
                
                echo json_encode(['success' => true, 'message' => 'Booking created successfully', 'booking_id' => $bookingId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to insert booking']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'update_status') {
        $bookingId = $data['booking_id'] ?? 0;
        $status = $data['status'] ?? 'pending';
        
        if (!in_array($status, ['confirmed', 'cancelled'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        try {
            $stmt->execute([$status, $bookingId]);
            echo json_encode(['success' => true, 'message' => "Booking $status successfully"]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'clear_bookings') {
        try {
            $stmt = $pdo->prepare("DELETE FROM bookings");
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'All bookings cleared successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'get_booking') {
        $id = $data['id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        try {
            $stmt->execute([$id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'booking' => $booking]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'get_day_bookings') {
        $date = $data['date'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE event_date = ? ORDER BY created_at");
        try {
            $stmt->execute([$date]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'bookings' => $bookings]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get bookings for display
$bookings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM bookings ORDER BY created_at DESC");
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table already exists with different structure, just continue
}

$viewMode = isset($_GET['view']) ? $_GET['view'] : 'calendar'; // calendar or table
$requestedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$requestedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month/year
if ($requestedMonth < 1 || $requestedMonth > 12) $requestedMonth = date('n');
if ($requestedYear < 2020 || $requestedYear > 2030) $requestedYear = date('Y');

$currentMonth = $requestedMonth;
$currentYear = $requestedYear;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = date('w', strtotime("$currentYear-$currentMonth-01"));
$today = ($currentYear == date('Y') && $currentMonth == date('n')) ? date('j') : null;

// Get previous/next month for navigation
$prevMonth = $currentMonth - 1;
$nextMonth = $currentMonth + 1;
$prevYear = $currentYear;
$nextYear = $currentYear;

if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear = $currentYear - 1;
}
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear = $currentYear + 1;
}

// Group bookings by date - only for selected month/year
$bookingsByDate = [];
foreach ($bookings as $booking) {
    $bookingDate = new DateTime($booking['event_date']);
    $bookingMonth = (int)$bookingDate->format('n');
    $bookingYear = (int)$bookingDate->format('Y');
    
    // Only include bookings for the selected month/year
    if ($bookingMonth == $currentMonth && $bookingYear == $currentYear) {
        $date = $bookingDate->format('j');
        if (!isset($bookingsByDate[$date])) {
            $bookingsByDate[$date] = [];
        }
        $bookingsByDate[$date][] = $booking;
    }
}

// Calendar generation function
function generateCalendar($currentMonth, $currentYear, $daysInMonth, $firstDayOfWeek, $today, $bookingsByDate) {
    $calendar = '';
    $day = 1;
    
    // Add empty cells for days before month starts
    for ($i = 0; $i < $firstDayOfWeek; $i++) {
        $calendar .= '<div class="cal-day empty"></div>';
    }
    
    // Add days of the month
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $isToday = ($day == $today);
        $dayClass = $isToday ? 'cal-day today' : 'cal-day';
        $calendar .= '<div class="' . $dayClass . '" onclick="showDayBookings(' . $day . ')">';
        $calendar .= '<div class="cal-day-num">' . $day . '</div>';
        
        // Add bookings for this day
        if (isset($bookingsByDate[$day])) {
            foreach ($bookingsByDate[$day] as $booking) {
                // Parse booking details from notes field
                $bookingDetails = json_decode($booking['notes'], true) ?: [];
                $fullName = $bookingDetails['full_name'] ?? 'Guest';
                
                $bookingId = '#BK-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT);
                $customerName = substr($fullName, 0, 8);
                $calendar .= '<div class="cal-event ' . $booking['status'] . '" onclick="event.stopPropagation(); showBookingDetails(' . $booking['id'] . ')">' . $bookingId . ' ' . $customerName . '</div>';
            }
        }
        
        $calendar .= '</div>';
    }
    
    // Add empty cells for days after month ends
    $totalCells = $firstDayOfWeek + $daysInMonth;
    $remainingCells = 42 - $totalCells; // 6 rows * 7 days
    for ($i = 0; $i < $remainingCells; $i++) {
        $calendar .= '<div class="cal-day empty"></div>';
    }
    
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
<style>
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
  margin-top: 10px;
}
.cal-header {
  text-align: center;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--muted);
  padding: 6px 0;
}
.cal-day {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 8px;
  padding: 8px 6px;
  min-height: 70px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: border-color 0.2s;
  position: relative;
}
.cal-day:hover { border-color: rgba(194,38,38,0.4); }
.cal-day.today { border-color: var(--red); background: rgba(194,38,38,0.08); }
.cal-day.empty { background: transparent; border-color: transparent; cursor: default; }
.cal-day-num { font-weight: 700; color: rgba(255,255,255,0.6); margin-bottom: 4px; font-size: 0.8rem; }
.cal-day.today .cal-day-num { color: var(--red); }
.cal-event {
  font-size: 0.62rem;
  padding: 2px 5px;
  border-radius: 4px;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 600;
}
.cal-event.confirmed { background: rgba(46,204,113,0.2); color: #2ecc71; }
.cal-event.pending   { background: rgba(243,156,18,0.2); color: #f39c12; }
.cal-event.cancelled { background: rgba(194,38,38,0.2); color: #ff6b6b; }

.booking-card {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 10px;
  padding: 16px;
  transition: border-color 0.2s, transform 0.2s;
}
.booking-card:hover { border-color: rgba(194,38,38,0.3); transform: translateY(-2px); }
.booking-id { font-size: 0.7rem; color: var(--red); font-weight: 700; letter-spacing: 0.1em; margin-bottom: 4px; }
.booking-name { font-size: 0.9rem; color: #fff; font-weight: 600; margin-bottom: 6px; }
.booking-detail { font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
.booking-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; }

.resource-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--line-w); }
.resource-item:last-child { border-bottom: none; }
.resource-name { font-size: 0.84rem; color: #fff; font-weight: 500; }
.resource-sub { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }

/* Dropdown styling */
select.form-control,
#viewToggle,
#yearSelect {
  color: #fff !important;
  background: var(--dark) !important;
  border: 1px solid var(--line-w) !important;
}
select.form-control option,
#viewToggle option,
#yearSelect option {
  color: #fff;
  background: var(--card);
}

/* Navigation buttons group */
.calendar-nav-group {
  display: flex;
  align-items: center;
  gap: 10px;
}
</style>
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
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-account.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="index.php" class="logout-btn" style="background: #22c55e; color: #fff;"><i class="fa-solid fa-home"></i> Home</a>
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
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function showToast(msg, type='') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function updateBookingStatus(bookingId, status) {
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'update_status',
      booking_id: bookingId,
      status: status
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(`Booking ${status === 'confirmed' ? 'confirmed' : 'cancelled'} successfully!`, 'success');
      location.reload();
    } else {
      showToast(data.message || 'Failed to update booking', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to update booking. Please try again.', 'error');
  });
}

function showBookingDetails(bookingId) {
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_booking', id: bookingId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const booking = data.booking;
      // Parse booking details from notes field
      const bookingDetails = booking.notes ? JSON.parse(booking.notes) : {};
      
      document.getElementById('bookingDetailId').textContent = '#BK-' + booking.id;
      document.getElementById('bookingDetailName').textContent = bookingDetails.full_name || 'Guest';
      document.getElementById('bookingDetailEvent').textContent = bookingDetails.event_name || 'N/A';
      document.getElementById('bookingDetailDate').textContent = new Date(booking.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      document.getElementById('bookingDetailTime').textContent = bookingDetails.event_time || 'N/A';
      document.getElementById('bookingDetailType').textContent = bookingDetails.event_type || 'N/A';
      document.getElementById('bookingDetailGuests').textContent = bookingDetails.num_guests || 'N/A';
      document.getElementById('bookingDetailAddress').textContent = bookingDetails.address || 'N/A';
      document.getElementById('bookingDetailContact').textContent = bookingDetails.contact_number || 'N/A';
      document.getElementById('bookingDetailEmail').textContent = bookingDetails.email_address || 'N/A';
      document.getElementById('bookingDetailNotes').textContent = bookingDetails.original_notes || 'No notes';
      document.getElementById('bookingDetailStatus').className = 'badge badge-' + (booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow'));
      document.getElementById('bookingDetailStatus').textContent = booking.status;
      
      openModal('bookingDetailModal');
    } else {
      showToast(data.message || 'Failed to load booking details', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to load booking details. Please try again.', 'error');
  });
}

function toggleView() {
  const viewMode = document.getElementById('viewToggle').value;
  const urlParams = new URLSearchParams(window.location.search);
  const currentMonth = urlParams.get('month') || new Date().getMonth() + 1;
  const currentYear = urlParams.get('year') || new Date().getFullYear();
  window.location.href = `admin-bookings.php?month=${currentMonth}&year=${currentYear}&view=${viewMode}`;
}

function changeYear() {
  const selectedYear = document.getElementById('yearSelect').value;
  const urlParams = new URLSearchParams(window.location.search);
  const currentMonth = urlParams.get('month') || new Date().getMonth() + 1;
  window.location.href = `admin-bookings.php?month=${currentMonth}&year=${selectedYear}&view=${urlParams.get('view') || 'calendar'}`;
}

function navigateMonth(direction) {
  const urlParams = new URLSearchParams(window.location.search);
  let currentMonth = parseInt(urlParams.get('month')) || new Date().getMonth() + 1;
  let currentYear = parseInt(urlParams.get('year')) || new Date().getFullYear();
  
  let newMonth = currentMonth;
  let newYear = currentYear;
  
  if (direction === 'prev') {
    newMonth = currentMonth - 1;
    if (newMonth < 1) {
      newMonth = 12;
      newYear = currentYear - 1;
    }
  } else if (direction === 'next') {
    newMonth = currentMonth + 1;
    if (newMonth > 12) {
      newMonth = 1;
      newYear = currentYear + 1;
    }
  }
  
  // Navigate to new month
  window.location.href = `admin-bookings.php?month=${newMonth}&year=${newYear}&view=<?= $viewMode ?>`;
}

function showDayBookings(day) {
  // Use the same month/year that PHP is using to display the calendar
  // These are embedded by PHP to ensure they match what's displayed
  const month = <?= $currentMonth ?>;
  const year = <?= $currentYear ?>;
  const dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
  
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_day_bookings', date: dateStr })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const bookings = data.bookings;
      const modal = document.getElementById('dayBookingsModal');
      const title = modal.querySelector('.modal-title');
      const content = modal.querySelector('.modal-body');
      
      // Create date object properly to avoid timezone issues
      const [year, month, day] = dateStr.split('-').map(Number);
      const dateObj = new Date(year, month - 1, day); // month-1 because JS months are 0-indexed
      
      title.innerHTML = `<i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for ${dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}>`;
      
      if (bookings.length === 0) {
        content.innerHTML = '<p style="text-align: center; color: var(--muted); padding: 20px;">No bookings for this day.</p>';
      } else {
        content.innerHTML = bookings.map(booking => {
          // Parse booking details from notes field
          const bookingDetails = booking.notes ? JSON.parse(booking.notes) : {};
          const fullName = bookingDetails.full_name || 'Guest';
          const eventTime = bookingDetails.event_time || 'N/A';
          const eventType = bookingDetails.event_type || 'N/A';
          const numGuests = bookingDetails.num_guests || 'N/A';
          const address = bookingDetails.address || 'N/A';
          
          return `
          <div class="booking-card">
            <div class="booking-id">#BK-${booking.id}</div>
            <div class="booking-name">${fullName}</div>
            <div class="booking-detail"><i class="fa-solid fa-clock"></i> ${eventTime} · ${eventType}</div>
            <div class="booking-detail"><i class="fa-solid fa-users"></i> ${numGuests} guests</div>
            <div class="booking-detail"><i class="fa-solid fa-location-dot"></i> ${address}</div>
            <div class="booking-footer">
              <span class="badge badge-${booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow')}">${booking.status}</span>
              <button class="btn btn-outline btn-sm" onclick="showBookingDetails(${booking.id})">View Details</button>
            </div>
          </div>
        `;
        }).join('');
      }
      
      openModal('dayBookingsModal');
    } else {
      showToast(data.message || 'Failed to load day bookings', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to load day bookings. Please try again.', 'error');
  });
}

function clearAllBookings() {
  if (confirm('Are you sure you want to delete ALL bookings? This action cannot be undone.')) {
    fetch('admin-bookings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'clear_bookings' })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('All bookings cleared successfully!', 'success');
        location.reload();
      } else {
        showToast(data.message || 'Failed to clear bookings', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('Failed to clear bookings. Please try again.', 'error');
    });
  }
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});

document.getElementById('newBookingForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Get form values
  const eventName = document.getElementById('newEventName').value.trim();
  const fullName = document.getElementById('newFullName').value.trim();
  const contactNumber = document.getElementById('newContactNumber').value.trim();
  const emailAddress = document.getElementById('newEmailAddress').value.trim();
  const eventDate = document.getElementById('newEventDate').value.trim();
  const eventTime = document.getElementById('newEventTime').value.trim();
  const eventType = document.getElementById('newEventType').value.trim();
  const numGuests = document.getElementById('newNumGuests').value.trim();
  const address = document.getElementById('newAddress').value.trim();
  const notes = document.getElementById('newNotes').value.trim() || 'N/A';
  
  // Client-side validation
  if (!eventName || !fullName || !contactNumber || !emailAddress || !eventDate || !eventTime || !eventType || !numGuests || !address) {
    showToast('Please fill in all required fields', 'error');
    return;
  }
  
  // Validate email format
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(emailAddress)) {
    showToast('Please enter a valid email address', 'error');
    return;
  }
  
  // Validate phone number (basic check)
  if (contactNumber.length < 10) {
    showToast('Please enter a valid contact number', 'error');
    return;
  }
  
  const formData = {
    action: 'create_booking',
    eventName: eventName,
    fullName: fullName,
    contactNumber: contactNumber,
    emailAddress: emailAddress,
    eventDate: eventDate,
    eventTime: eventTime,
    eventType: eventType,
    numGuests: numGuests,
    address: address,
    notes: notes,
    userEmail: 'admin@lukesseafood.com',
    userName: 'Admin'
  };
  
  console.log('Submitting booking data:', formData);
  
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(formData)
  })
  .then(response => {
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    return response.json();
  })
  .then(data => {
    console.log('Response data:', data);
    if (data.success) {
      showToast('Booking created successfully!', 'success');
      closeModal('newBookingModal');
      document.getElementById('newBookingForm').reset();
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      showToast(data.message || 'Failed to create booking', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to create booking. Please try again.', 'error');
  });
});

</script>

<style>
/* Notification Dropdown Styles */
.notification-dropdown {
  position: relative;
}

.notification-menu {
  position: absolute;
  top: 100%;
  right: 0;
  width: 380px;
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  z-index: 1000;
  display: none;
  margin-top: 10px;
}

.notification-menu.show {
  display: block;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--line-w);
}

.notification-header h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
}

.mark-all-read {
  background: none;
  border: none;
  color: var(--red);
  font-size: 0.8rem;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.mark-all-read:hover {
  background: rgba(194, 38, 38, 0.1);
}

.notification-list {
  max-height: 400px;
  overflow-y: auto;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  padding: 16px 20px;
  border-bottom: 1px solid var(--line-w);
  transition: background-color 0.2s;
  cursor: pointer;
}

.notification-item:hover {
  background: rgba(255, 255, 255, 0.05);
}

.notification-item.unread {
  background: rgba(194, 38, 38, 0.05);
}

.notification-item.unread:hover {
  background: rgba(194, 38, 38, 0.1);
}

.notification-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
  flex-shrink: 0;
}

.notification-icon i {
  font-size: 1rem;
}

.notification-item:nth-child(1) .notification-icon {
  background: rgba(34, 197, 94, 0.2);
  color: #22c55e;
}

.notification-item:nth-child(2) .notification-icon {
  background: rgba(59, 130, 246, 0.2);
  color: #3b82f6;
}

.notification-item:nth-child(3) .notification-icon {
  background: rgba(249, 115, 22, 0.2);
  color: #f97316;
}

.notification-item:nth-child(4) .notification-icon {
  background: rgba(168, 85, 247, 0.2);
  color: #a855f7;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-weight: 600;
  color: #fff;
  font-size: 0.9rem;
  margin-bottom: 4px;
}

.notification-message {
  color: var(--muted);
  font-size: 0.85rem;
  margin-bottom: 4px;
  line-height: 1.3;
}

.notification-time {
  color: var(--muted);
  font-size: 0.75rem;
}

.notification-close {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s;
  margin-left: 8px;
  flex-shrink: 0;
}

.notification-close:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

.notification-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--line-w);
  text-align: center;
}

.view-all-link {
  color: var(--red);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: opacity 0.2s;
}

.view-all-link:hover {
  opacity: 0.8;
}

/* Badge dot for unread notifications */
.badge-dot {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 8px;
  height: 8px;
  background: var(--red);
  border-radius: 50%;
  border: 2px solid var(--dark);
}

/* Scrollbar styling */
.notification-list::-webkit-scrollbar {
  width: 6px;
}

.notification-list::-webkit-scrollbar-track {
  background: transparent;
}

.notification-list::-webkit-scrollbar-thumb {
  background: var(--line-w);
  border-radius: 3px;
}

.notification-list::-webkit-scrollbar-thumb:hover {
  background: var(--muted);
}
</style>

<script>
function toggleNotifications() {
  const menu = document.getElementById('notificationMenu');
  menu.classList.toggle('show');
  
  // Close when clicking outside
  document.addEventListener('click', function closeNotifications(e) {
    if (!e.target.closest('.notification-dropdown')) {
      menu.classList.remove('show');
      document.removeEventListener('click', closeNotifications);
    }
  });
}

function removeNotification(element) {
  const item = element.closest('.notification-item');
  item.style.transform = 'translateX(100%)';
  item.style.opacity = '0';
  setTimeout(() => item.remove(), 300);
}

function markAllAsRead() {
  const unreadItems = document.querySelectorAll('.notification-item.unread');
  unreadItems.forEach(item => {
    item.classList.remove('unread');
  });
  
  // Remove badge dot
  const badgeDot = document.querySelector('.badge-dot');
  if (badgeDot) {
    badgeDot.style.display = 'none';
  }
}
</script>
</body>
</html>