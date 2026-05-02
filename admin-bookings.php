<?php
require_once 'admin-config.php';
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
        
        // Insert booking into database
        $stmt = $pdo->prepare("
            INSERT INTO bookings (
                event_name, address, event_date, event_time, event_type, 
                num_guests, full_name, contact_number, email_address, 
                notes, user_email, user_name, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        try {
            $result = $stmt->execute([
                $eventName, $address, $formattedDate, $formattedTime, $eventType,
                $numGuests, $fullName, $contactNumber, $emailAddress,
                $notes, $userEmail, $userName
            ]);
            
            if ($result) {
                $bookingId = $pdo->lastInsertId();
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
        
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE event_date = ? ORDER BY event_time");
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
    // Table might not exist, create it
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            num_guests VARCHAR(50) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            email_address VARCHAR(255) NOT NULL,
            notes TEXT,
            user_email VARCHAR(255),
            user_name VARCHAR(255),
            status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableSQL);
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
                $bookingId = '#BK-' . str_pad($booking['id'], 3, '0', STR_PAD_LEFT);
                $customerName = substr($booking['full_name'], 0, 8);
                
                $calendar .= '<div class="cal-event ' . $booking['status'] . '">';
                $calendar .= '<div onclick="event.stopPropagation(); showDayBookings(' . $day . ')" style="cursor: pointer;">' . $bookingId . ' ' . $customerName . '</div>';
                $calendar .= '</div>';
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
  transition: all 0.25s ease;
}
.booking-card:hover { 
  border-color: rgba(194,38,38,0.5); 
  transform: translateY(-3px);
  box-shadow: 0 4px 12px rgba(194, 38, 38, 0.2);
  background: var(--card2);
}
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

/* Booking detail action buttons */
.booking-detail-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  flex-wrap: wrap;
}

.booking-action-btn {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.05));
  border: 1.5px solid rgba(255, 255, 255, 0.25);
  color: #fff;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.25s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-weight: 600;
  white-space: nowrap;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.booking-action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}

.booking-action-btn.confirm {
  background: linear-gradient(135deg, rgba(46, 204, 113, 0.3), rgba(46, 204, 113, 0.15));
  border-color: rgba(46, 204, 113, 0.7);
  color: #2ecc71;
}

.view-layout {
  display: grid;
  grid-template-columns: 1.6fr 0.9fr;
  gap: 20px;
}

.view-layout .main-panel {
  min-width: 0;
}

.side-stack {
  display: grid;
  gap: 20px;
}

@media (max-width: 1100px) {
  .view-layout {
    grid-template-columns: 1fr;
  }
}

.no-results-row td {
  padding: 30px;
  text-align: center;
  color: var(--muted);
}

.booking-action-btn.confirm:hover {
  background: linear-gradient(135deg, rgba(46, 204, 113, 0.5), rgba(46, 204, 113, 0.3));
  border-color: rgba(46, 204, 113, 0.9);
  box-shadow: 0 6px 16px rgba(46, 204, 113, 0.4);
}

.booking-action-btn.cancel {
  background: linear-gradient(135deg, rgba(194, 38, 38, 0.3), rgba(194, 38, 38, 0.15));
  border-color: rgba(194, 38, 38, 0.7);
  color: #ff6b6b;
}

.booking-action-btn.cancel:hover {
  background: linear-gradient(135deg, rgba(194, 38, 38, 0.5), rgba(194, 38, 38, 0.3));
  border-color: rgba(194, 38, 38, 0.9);
  box-shadow: 0 6px 16px rgba(194, 38, 38, 0.4);
}

/* Booking actions styling */
.booking-actions {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.btn-xs {
  padding: 2px 6px;
  font-size: 0.7rem;
  border-radius: 3px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-xs:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Navigation buttons group */
.calendar-nav-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Table details button */
.table-details-btn {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.03));
  border: 1.5px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  width: 36px;
  height: 36px;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.25s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.table-details-btn:hover {
  background: linear-gradient(135deg, rgba(194, 38, 38, 0.3), rgba(194, 38, 38, 0.1));
  border-color: rgba(194, 38, 38, 0.6);
  color: #ff6b6b;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(194, 38, 38, 0.3);
}

.page-content {
  padding: 28px 48px 28px 28px;
  max-width: calc(100vw - 320px);
  margin-right: auto;
}

@media (max-width: 1100px) {
  .page-content {
    padding-right: 28px;
    max-width: 100%;
  }
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
    </nav>
    <div class="sidebar-footer">
      <a href="admin-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i><span class="badge-dot"></span></div>
        <div class="admin-avatar">A</div>
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
          <div class="stat-card-value">34</div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +5 today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value">8</div>
          <div class="stat-card-label">Pending Confirmation</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs action</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-circle-xmark"></i></div>
          <div class="stat-card-value">3</div>
          <div class="stat-card-label">Cancelled This Month</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> low cancellation</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div class="stat-card-value">6</div>
          <div class="stat-card-label">Today's Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> on schedule</div>
        </div>
      </div>

      <div class="panel main-panel">
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
              <select id="statusFilter" onchange="filterBookingsByStatus()" style="background: var(--dark); border: 1px solid var(--line-w); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                <option value="all">All Bookings</option>
                <option value="pending">Pending Only</option>
                <option value="confirmed">Confirmed Only</option>
                <option value="cancelled">Cancelled Only</option>
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
                      <tr data-status="<?= $booking['status'] ?>">
                        <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($booking['id'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td>
                          <div class="flex-gap">
                            <div class="user-avatar"><?= strtoupper(substr($booking['full_name'], 0, 2)) ?></div>
                            <?= htmlspecialchars($booking['full_name']) ?>
                          </div>
                        </td>
                        <td><?= htmlspecialchars($booking['event_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($booking['event_date'])) ?></td>
                        <td><?= $booking['event_time'] ?></td>
                        <td><?= htmlspecialchars($booking['event_type']) ?></td>
                        <td><?= $booking['num_guests'] ?></td>
                        <td>
                          <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'green' : ($booking['status'] === 'cancelled' ? 'red' : 'yellow') ?>">
                            <?= ucfirst($booking['status']) ?>
                          </span>
                        </td>
                        <td>
                          <button class="table-details-btn" title="View Booking Details" onclick="showBookingDetails(<?= $booking['id'] ?>)">
                            <i class="fa-solid fa-circle-info"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <tr class="no-results-row" style="display:none;">
                      <td colspan="9" style="text-align:center; padding:30px; color:var(--muted);">
                        <i class="fa-solid fa-search-minus" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                        No records found for this filter.
                      </td>
                    </tr>
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
                  <tr data-status="<?= $booking['status'] ?>">
                    <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($booking['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td>
                      <div class="flex-gap">
                        <div class="user-avatar"><?= strtoupper(substr($booking['full_name'], 0, 2)) ?></div>
                        <?= htmlspecialchars($booking['full_name']) ?>
                      </div>
                    </td>
                    <td><?= date('M d, Y', strtotime($booking['event_date'])) ?></td>
                    <td>
                      <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'green' : ($booking['status'] === 'cancelled' ? 'red' : 'yellow') ?>">
                        <?= ucfirst($booking['status']) ?>
                      </span>
                    </td>
                    <td>
                      <button class="table-details-btn" title="View Booking Details" onclick="showBookingDetails(<?= $booking['id'] ?>)">
                        <i class="fa-solid fa-circle-info"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr class="no-results-row" style="display:none;">
                  <td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">
                    <i class="fa-solid fa-search-minus" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                    No records found for this filter.
                  </td>
                </tr>
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

      <div class="panel">
        <div class="panel-header"><span class="panel-title">Staff & Equipment</span><span class="badge badge-gray">Resource Tracker</span></div>
        <div class="panel-body">
          <p style="font-size:0.75rem;color:var(--muted);margin-bottom:14px;">Assigned resources for active bookings. Prevents double-booking.</p>
          <div class="resource-item">
            <div>
              <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>Carlos Mendoza</div>
              <div class="resource-sub">Head Fishmonger · Assigned to BK-044</div>
            </div>
            <span class="badge badge-red">Busy</span>
          </div>
          <div class="resource-item">
            <div>
              <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>Lita Navarro</div>
              <div class="resource-sub">Chef · Assigned to BK-045</div>
            </div>
            <span class="badge badge-yellow">Pending</span>
          </div>
          <div class="resource-item">
            <div>
              <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>Ben Aquino</div>
              <div class="resource-sub">Staff · Available</div>
            </div>
            <span class="badge badge-green">Free</span>
          </div>
          <div class="resource-item">
            <div>
              <div class="resource-name"><i class="fa-solid fa-truck" style="color:var(--info);margin-right:6px;"></i>Delivery Van 1</div>
              <div class="resource-sub">Equipment · Assigned to BK-044</div>
            </div>
            <span class="badge badge-red">Busy</span>
          </div>
          <div class="resource-item">
            <div>
              <div class="resource-name"><i class="fa-solid fa-truck" style="color:var(--info);margin-right:6px;"></i>Delivery Van 2</div>
              <div class="resource-sub">Equipment · Available</div>
            </div>
            <span class="badge badge-green">Free</span>
          </div>
          <div class="resource-item">
            <div>
              <div class="resource-name"><i class="fa-solid fa-box" style="color:var(--warning);margin-right:6px;"></i>Ice Box Set A</div>
              <div class="resource-sub">Equipment · Assigned to BK-046</div>
            </div>
            <span class="badge badge-yellow">Pending</span>
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
      
      <div class="booking-detail-actions" id="bookingDetailActions">
        <!-- Action buttons will be populated here -->
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
let currentStatusFilter = 'all';

function filterBookingsByStatus() {
  const statusFilter = document.getElementById('statusFilter').value;
  currentStatusFilter = statusFilter;
  
  const viewMode = document.getElementById('viewToggle').value;
  
  if (viewMode === 'calendar') {
    const calendarEvents = document.querySelectorAll('.cal-event');
    calendarEvents.forEach(event => {
      const eventClass = event.className;
      const hasStatus = eventClass.includes('confirmed') || eventClass.includes('pending') || eventClass.includes('cancelled');
      if (!hasStatus) return;
      
      let eventStatus = '';
      if (eventClass.includes('confirmed')) eventStatus = 'confirmed';
      else if (eventClass.includes('pending')) eventStatus = 'pending';
      else if (eventClass.includes('cancelled')) eventStatus = 'cancelled';
      
      event.style.display = statusFilter === 'all' || eventStatus === statusFilter ? '' : 'none';
    });
  }
  
  const tableRows = Array.from(document.querySelectorAll('table tbody tr'));
  const dataRows = tableRows.filter(row => row.dataset.status !== undefined);
  const matchingRows = dataRows.filter(row => statusFilter === 'all' || row.dataset.status.toLowerCase() === statusFilter);
  
  tableRows.forEach(row => {
    if (row.classList.contains('no-results-row')) {
      row.style.display = matchingRows.length === 0 && dataRows.length > 0 ? '' : 'none';
      return;
    }
    
    if (!row.dataset.status) {
      row.style.display = dataRows.length === 0 ? '' : 'none';
      return;
    }
    
    row.style.display = statusFilter === 'all' || row.dataset.status.toLowerCase() === statusFilter ? '' : 'none';
  });
}
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
      document.getElementById('bookingDetailId').textContent = '#BK-' + booking.id;
      document.getElementById('bookingDetailName').textContent = booking.full_name;
      document.getElementById('bookingDetailEvent').textContent = booking.event_name;
      document.getElementById('bookingDetailDate').textContent = new Date(booking.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      document.getElementById('bookingDetailTime').textContent = booking.event_time;
      document.getElementById('bookingDetailType').textContent = booking.event_type;
      document.getElementById('bookingDetailGuests').textContent = booking.num_guests;
      document.getElementById('bookingDetailAddress').textContent = booking.address;
      document.getElementById('bookingDetailContact').textContent = booking.contact_number;
      document.getElementById('bookingDetailEmail').textContent = booking.email_address;
      document.getElementById('bookingDetailNotes').textContent = booking.notes || 'No notes';
      document.getElementById('bookingDetailStatus').className = 'badge badge-' + (booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow'));
      document.getElementById('bookingDetailStatus').textContent = booking.status;
      
      // Populate action buttons based on status
      const actionsDiv = document.getElementById('bookingDetailActions');
      if (booking.status === 'pending') {
        actionsDiv.innerHTML = `
          <button class="booking-action-btn confirm" onclick="updateBookingStatus(${booking.id}, 'confirmed')">
            <i class="fa-solid fa-check"></i> Confirm Booking
          </button>
          <button class="booking-action-btn cancel" onclick="updateBookingStatus(${booking.id}, 'cancelled')">
            <i class="fa-solid fa-xmark"></i> Cancel Booking
          </button>
        `;
      } else if (booking.status === 'confirmed') {
        actionsDiv.innerHTML = `
          <button class="booking-action-btn cancel" onclick="updateBookingStatus(${booking.id}, 'cancelled')">
            <i class="fa-solid fa-xmark"></i> Cancel Booking
          </button>
        `;
      } else {
        actionsDiv.innerHTML = '';
      }
      
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
  document.getElementById('statusFilter').value = 'all';
  currentStatusFilter = 'all';
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
  // Store the day for back navigation
  window.lastDayClicked = day;
  
  const urlParams = new URLSearchParams(window.location.search);
  const month = parseInt(urlParams.get('month')) || new Date().getMonth() + 1;
  const year = parseInt(urlParams.get('year')) || new Date().getFullYear();
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
      
      title.innerHTML = `<i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for ${new Date(dateStr).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
      
      if (bookings.length === 0) {
        content.innerHTML = '<p style="text-align: center; color: var(--muted); padding: 20px;">No bookings for this day.</p>';
      } else {
        content.innerHTML = bookings.map(booking => `
          <div class="booking-card" style="cursor: pointer;" onclick="showBookingDetailsFromDay(${booking.id})">
            <div class="booking-id">#BK-${String(booking.id).padStart(3, '0')}</div>
            <div class="booking-name">${booking.full_name}</div>
            <div class="booking-detail"><i class="fa-solid fa-clock"></i> ${booking.event_time} · ${booking.event_type}</div>
            <div class="booking-detail"><i class="fa-solid fa-users"></i> ${booking.num_guests} guests</div>
            <div class="booking-detail"><i class="fa-solid fa-location-dot"></i> ${booking.address}</div>
            <div class="booking-footer">
              <span class="badge badge-${booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow')}">${booking.status}</span>
              <i class="fa-solid fa-arrow-right" style="color: var(--muted); margin-left: auto;"></i>
            </div>
          </div>
        `).join('');
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

function showBookingDetailsFromDay(bookingId) {
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_booking', id: bookingId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const booking = data.booking;
      const modal = document.getElementById('dayBookingsModal');
      const title = modal.querySelector('.modal-title');
      const content = modal.querySelector('.modal-body');
      
      // Show booking details in the day bookings modal
      title.innerHTML = `<i class="fa-solid fa-arrow-left" style="color:var(--red);margin-right:8px;cursor:pointer;font-size:1.2rem;" onclick="goBackToDayBookings()"></i>#BK-${String(booking.id).padStart(3, '0')} · ${booking.full_name}`;
      
      // Store the current day for going back
      window.currentDayBookingsDay = bookingId;
      
      content.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Event</div>
            <div style="font-weight: 600;">${booking.event_name}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Date</div>
            <div style="font-weight: 600;">${new Date(booking.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Time</div>
            <div style="font-weight: 600;">${booking.event_time}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Type</div>
            <div style="font-weight: 600;">${booking.event_type}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Guests</div>
            <div style="font-weight: 600;">${booking.num_guests}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Status</div>
            <span class="badge badge-${booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow')}">${booking.status}</span>
          </div>
        </div>
        
        <div style="margin-bottom: 15px;">
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Address</div>
          <div style="font-weight: 600;">${booking.address}</div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Contact Number</div>
            <div style="font-weight: 600;">${booking.contact_number}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Email</div>
            <div style="font-weight: 600;">${booking.email_address}</div>
          </div>
        </div>
        
        <div>
          <div style="font-size: 0.75rem; color: var(--muted); margin-bottom: 4px;">Notes</div>
          <div style="font-weight: 600;">${booking.notes || 'No notes'}</div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
          ${booking.status === 'pending' ? `
            <button class="booking-action-btn confirm" onclick="updateBookingStatus(${booking.id}, 'confirmed')">
              <i class="fa-solid fa-check"></i> Confirm Booking
            </button>
            <button class="booking-action-btn cancel" onclick="updateBookingStatus(${booking.id}, 'cancelled')">
              <i class="fa-solid fa-xmark"></i> Cancel Booking
            </button>
          ` : booking.status === 'confirmed' ? `
            <button class="booking-action-btn cancel" onclick="updateBookingStatus(${booking.id}, 'cancelled')">
              <i class="fa-solid fa-xmark"></i> Cancel Booking
            </button>
          ` : ''}
        </div>
      `;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to load booking details. Please try again.', 'error');
  });
}

function goBackToDayBookings() {
  // Get stored day bookings
  const urlParams = new URLSearchParams(window.location.search);
  const month = parseInt(urlParams.get('month')) || new Date().getMonth() + 1;
  const year = parseInt(urlParams.get('year')) || new Date().getFullYear();
  const dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(window.lastDayClicked).padStart(2, '0');
  
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
      
      title.innerHTML = `<i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for ${new Date(dateStr).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;
      
      if (bookings.length === 0) {
        content.innerHTML = '<p style="text-align: center; color: var(--muted); padding: 20px;">No bookings for this day.</p>';
      } else {
        content.innerHTML = bookings.map(booking => `
          <div class="booking-card" style="cursor: pointer;" onclick="showBookingDetailsFromDay(${booking.id})">
            <div class="booking-id">#BK-${String(booking.id).padStart(3, '0')}</div>
            <div class="booking-name">${booking.full_name}</div>
            <div class="booking-detail"><i class="fa-solid fa-clock"></i> ${booking.event_time} · ${booking.event_type}</div>
            <div class="booking-detail"><i class="fa-solid fa-users"></i> ${booking.num_guests} guests</div>
            <div class="booking-detail"><i class="fa-solid fa-location-dot"></i> ${booking.address}</div>
            <div class="booking-footer">
              <span class="badge badge-${booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow')}">${booking.status}</span>
              <i class="fa-solid fa-arrow-right" style="color: var(--muted); margin-left: auto;"></i>
            </div>
          </div>
        `).join('');
      }
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

document.querySelectorAll('.cal-day:not(.empty)').forEach(d => {
  d.addEventListener('click', () => {
    const num = d.querySelector('.cal-day-num')?.textContent;
    if(num) showToast(`Selected June ${num}, 2025`);
  });
});
</script>
</body>
</html>