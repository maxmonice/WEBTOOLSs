<?php
// =====================================================
//  booking-api.php — Booking management with calendar
//  - Get booking availability for dates
//  - Create bookings with availability limits
//  - Check 2-booking-per-day constraint
// =====================================================

require_once 'Db.php';

header('Content-Type: application/json');

// CORS Headers
$allowed_origins = ['http://localhost', 'http://127.0.0.1', 'http://webtoolss.test'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(204); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

$db = getDB();

function bookingColumnExists(PDO $db, string $column): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'bookings'
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$column]);
    return (int)$stmt->fetchColumn() > 0;
}

// =====================================================
//  GET BOOKING AVAILABILITY FOR A DATE RANGE
//  Returns: { year-month-day: { available: true/false, count: 0-2 } }
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'availability') {
    try {
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+30 days'));

        // Validate dates
        $startDate = date('Y-m-d', strtotime($startDate));
        $endDate = date('Y-m-d', strtotime($endDate));

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Get booking counts for each date in the range
        $stmt = $db->prepare("
            SELECT 
                DATE(event_date) as booking_date,
                COUNT(*) as booking_count
            FROM bookings
            WHERE DATE(event_date) BETWEEN ? AND ?
            AND status IN ('pending', 'confirmed')
            GROUP BY DATE(event_date)
        ");
        $stmt->execute([$startDate, $endDate]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create availability map
        $availability = [];
        $currentDate = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        
        while ($currentDate <= $endDateObj) {
            $dateStr = $currentDate->format('Y-m-d');
            $availability[$dateStr] = [
                'available' => true,
                'count' => 0,
                'color' => 'green'
            ];
            $currentDate->modify('+1 day');
        }

        // Update availability based on booking counts
        foreach ($bookings as $booking) {
            $dateStr = $booking['booking_date'];
            $count = (int)$booking['booking_count'];
            
            $availability[$dateStr] = [
                'available' => $count < 2,
                'count' => $count,
                'color' => $count >= 2 ? 'red' : 'green'
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'availability' => $availability,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// =====================================================
//  CREATE BOOKING WITH AVAILABILITY CHECK
//  POST: { action: 'create', booking_data: {...} }
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'create') {
        try {
            $data = $body['booking_data'] ?? [];
            
            // Extract and validate data. Accept snake_case from booking-api
            // and camelCase from older frontend code.
            $eventDate = $data['event_date'] ?? null;
            $eventTime = $data['event_time'] ?? '12:00';
            $eventName = trim($data['event_name'] ?? $data['eventName'] ?? '');
            $eventType = trim($data['event_type'] ?? $data['eventType'] ?? '');
            $numGuests = $data['num_guests'] ?? $data['numGuests'] ?? 0;
            $fullName = trim($data['full_name'] ?? $data['fullName'] ?? '');
            $contactNumber = trim($data['contact_number'] ?? $data['contactNumber'] ?? '');
            $emailAddress = trim($data['email_address'] ?? $data['emailAddress'] ?? '');
            $address = trim($data['address'] ?? '');
            $notes = trim($data['notes'] ?? '');

            // Validation
            if (!$eventDate || !$eventName || !$numGuests || !$fullName || !$contactNumber || !$emailAddress) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Validate date is not in the past
            $eventDateObj = new DateTime($eventDate);
            $today = new DateTime(date('Y-m-d'));
            if ($eventDateObj < $today) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot book dates in the past']);
                exit;
            }

            $timeObj = DateTime::createFromFormat('H:i', $eventTime)
                ?: DateTime::createFromFormat('H:i:s', $eventTime)
                ?: DateTime::createFromFormat('h:i A', $eventTime);
            if (!$timeObj) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid event time']);
                exit;
            }
            $eventTime = $timeObj->format('H:i:s');

            // Check if date already has 2 bookings
            $stmt = $db->prepare("
                SELECT COUNT(*) as booking_count
                FROM bookings
                WHERE DATE(event_date) = ?
                AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([date('Y-m-d', strtotime($eventDate))]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $bookingCount = (int)$result['booking_count'];

            if ($bookingCount >= 2) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'This date is fully booked. Please select another date.',
                    'full_booked' => true
                ]);
                exit;
            }

            $userName = trim($data['user_name'] ?? $data['userName'] ?? $_SESSION['user_name'] ?? $fullName);
            $userEmail = trim($data['user_email'] ?? $data['userEmail'] ?? $_SESSION['user_email'] ?? $emailAddress);

            $columns = [
                'event_name', 'address', 'event_date', 'event_time', 'event_type',
                'num_guests', 'full_name', 'contact_number', 'email_address',
                'notes', 'user_email', 'user_name', 'status', 'created_at'
            ];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', "'pending'", 'NOW()'];
            $values = [
                $eventName, $address, date('Y-m-d', strtotime($eventDate)), $eventTime, $eventType,
                $numGuests, $fullName, $contactNumber, $emailAddress, $notes,
                $userEmail, $userName
            ];

            if (isset($_SESSION['user_id']) && bookingColumnExists($db, 'user_id')) {
                array_unshift($columns, 'user_id');
                array_unshift($placeholders, '?');
                array_unshift($values, (int)$_SESSION['user_id']);
            }

            if (bookingColumnExists($db, 'updated_at')) {
                $columns[] = 'updated_at';
                $placeholders[] = 'NOW()';
            }

            $stmt = $db->prepare("
                INSERT INTO bookings (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
            ");
            $stmt->execute($values);

            $bookingId = (int)$db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Booking created successfully! Pending admin confirmation.',
                'booking_id' => $bookingId
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}
