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

function normalizeBookingDates($value): array {
    if (is_array($value)) {
        $dates = $value;
    } else {
        $dates = preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
    }

    $normalized = [];
    foreach ($dates as $date) {
        $ts = strtotime((string)$date);
        if ($ts !== false) {
            $normalized[] = date('Y-m-d', $ts);
        }
    }

    return array_values(array_unique($normalized));
}

/** Parse common time strings from flatpickr (12h / 24h). */
function bookingParseTime(string $t): ?DateTime
{
    $t = trim($t);
    foreach (['h:i A', 'h:i a', 'g:i A', 'g:i a', 'H:i:s', 'H:i'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $t);
        if ($d instanceof DateTime) {
            return $d;
        }
    }
    return null;
}

/** Start time only, for `bookings.event_time` when the column is MySQL TIME. */
function bookingTimeToSql(string $startLabel): string
{
    $d = bookingParseTime($startLabel);
    return $d instanceof DateTime ? $d->format('H:i:s') : '00:00:00';
}

/** Validate end after start (same calendar day). */
function bookingValidateTimeOrder(string $startLabel, string $endLabel): ?string
{
    $a = bookingParseTime($startLabel);
    $b = bookingParseTime($endLabel);
    if (!$a || !$b) {
        return 'Invalid start or end time.';
    }
    $aMin = (int)$a->format('H') * 60 + (int)$a->format('i');
    $bMin = (int)$b->format('H') * 60 + (int)$b->format('i');
    if ($bMin <= $aMin) {
        return 'End time must be after start time.';
    }
    return null;
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
            $eventDates = normalizeBookingDates($data['event_dates'] ?? $data['event_date'] ?? null);
            $eventDate = $eventDates[0] ?? null;
            $eventTimeLegacy = trim((string)($data['event_time'] ?? ''));
            $eventTimeStart = trim((string)($data['event_time_start'] ?? ''));
            $eventTimeEnd = trim((string)($data['event_time_end'] ?? ''));
            if ($eventTimeStart === '' && $eventTimeEnd === '' && $eventTimeLegacy !== '') {
                if (preg_match('/^(.+?)\s*[\x{2013}\x{2014}-]\s*(.+)$/u', $eventTimeLegacy, $m)) {
                    $eventTimeStart = trim($m[1]);
                    $eventTimeEnd = trim($m[2]);
                } else {
                    $eventTimeStart = $eventTimeLegacy;
                    $eventTimeEnd = $eventTimeLegacy;
                }
            }

            $sameTimeAll = $data['same_time_all_days'] ?? true;
            if (is_string($sameTimeAll)) {
                $sameTimeAll = !in_array(strtolower($sameTimeAll), ['0', 'false', 'no'], true);
            } else {
                $sameTimeAll = (bool)$sameTimeAll;
            }
            $eventTimesByDate = $data['event_times_by_date'] ?? null;
            if (!is_array($eventTimesByDate)) {
                $eventTimesByDate = null;
            }

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
            $today = new DateTime(date('Y-m-d'));
            foreach ($eventDates as $date) {
                $eventDateObj = new DateTime($date);
                if ($eventDateObj < $today) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Cannot book dates in the past']);
                    exit;
                }
            }

            if ($eventTimeStart === '' || $eventTimeEnd === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Event start and end time are required']);
                exit;
            }
            $orderErr = bookingValidateTimeOrder($eventTimeStart, $eventTimeEnd);
            if ($orderErr !== null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $orderErr]);
                exit;
            }
            $defaultTimeSql = bookingTimeToSql($eventTimeStart);
            $defaultTimeEndSql = bookingTimeToSql($eventTimeEnd);

            if (count($eventDates) > 1 && !$sameTimeAll) {
                foreach ($eventDates as $dCheck) {
                    if (!$eventTimesByDate || empty($eventTimesByDate[$dCheck]) || !is_array($eventTimesByDate[$dCheck])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Please provide a time range for each selected date.']);
                        exit;
                    }
                    $ps = trim((string)($eventTimesByDate[$dCheck]['start'] ?? ''));
                    $pe = trim((string)($eventTimesByDate[$dCheck]['end'] ?? ''));
                    if ($ps === '' || $pe === '') {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Each day needs a start and end time.']);
                        exit;
                    }
                    $eMsg = bookingValidateTimeOrder($ps, $pe);
                    if ($eMsg !== null) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => $eMsg . ' (' . $dCheck . ')']);
                        exit;
                    }
                }
            }

            $availabilityStmt = $db->prepare("
                SELECT COUNT(*) as booking_count
                FROM bookings
                WHERE DATE(event_date) = ?
                AND status IN ('pending', 'confirmed')
            ");
            foreach ($eventDates as $date) {
                $availabilityStmt->execute([$date]);
                $bookingCount = (int)$availabilityStmt->fetchColumn();

                if ($bookingCount >= 2) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => date('M j, Y', strtotime($date)) . ' is fully booked. Please remove it or select another date.',
                        'full_booked' => true
                    ]);
                    exit;
                }
            }

            $userName = trim($data['user_name'] ?? $data['userName'] ?? $_SESSION['user_name'] ?? $fullName);
            $userEmail = trim($data['user_email'] ?? $data['userEmail'] ?? $_SESSION['user_email'] ?? $emailAddress);

            $columns = [
                'event_name', 'address', 'event_date', 'event_time', 'event_type',
                'num_guests', 'full_name', 'contact_number', 'email_address',
                'notes', 'status', 'created_at'
            ];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', "'pending'", 'NOW()'];
            $values = [
                $eventName, $address, date('Y-m-d', strtotime($eventDate)), $defaultTimeSql, $eventType,
                $numGuests, $fullName, $contactNumber, $emailAddress, $notes
            ];

            if (bookingColumnExists($db, 'event_time_end')) {
                $insertIdx = array_search('event_time', $columns, true);
                if ($insertIdx !== false) {
                    array_splice($columns, $insertIdx + 1, 0, ['event_time_end']);
                    array_splice($placeholders, $insertIdx + 1, 0, ['?']);
                    array_splice($values, $insertIdx + 1, 0, [$defaultTimeEndSql]);
                }
            }

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
            $bookingIds = [];
            $db->beginTransaction();

            $baseAssoc = [];
            $vi = 0;
            foreach ($columns as $col) {
                if ($col === 'status' || $col === 'created_at' || $col === 'updated_at') {
                    continue;
                }
                $baseAssoc[$col] = $values[$vi++];
            }

            foreach ($eventDates as $date) {
                $assoc = $baseAssoc;
                $assoc['event_date'] = $date;
                if (count($eventDates) > 1 && !$sameTimeAll && $eventTimesByDate && !empty($eventTimesByDate[$date])) {
                    $pair = $eventTimesByDate[$date];
                    $assoc['event_time'] = bookingTimeToSql(trim((string)($pair['start'] ?? '')));
                    if (isset($assoc['event_time_end'])) {
                        $assoc['event_time_end'] = bookingTimeToSql(trim((string)($pair['end'] ?? '')));
                    }
                } else {
                    $assoc['event_time'] = $defaultTimeSql;
                    if (isset($assoc['event_time_end'])) {
                        $assoc['event_time_end'] = $defaultTimeEndSql;
                    }
                }
                $rowValues = [];
                foreach ($columns as $col) {
                    if ($col === 'status' || $col === 'created_at' || $col === 'updated_at') {
                        continue;
                    }
                    $rowValues[] = $assoc[$col];
                }
                $stmt->execute($rowValues);
                $bookingIds[] = (int)$db->lastInsertId();
            }
            $db->commit();

            try {
                require_once __DIR__ . '/Notifications.php';
                $notif = new Notifications($db);
                $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $timeLbl = trim($eventTimeStart) . ' – ' . trim($eventTimeEnd);
                $dateLbl = count($eventDates) > 1 ? implode(', ', $eventDates) : ($eventDates[0] ?? '');
                $notif->autoNotify('new_booking', [
                    'id' => $bookingIds[0] ?? 0,
                    'customer_name' => $fullName,
                    'date' => $dateLbl,
                    'time' => $timeLbl,
                    'user_id' => $uid ?: null,
                ]);
            } catch (Throwable $e) {
                error_log('booking-api new_booking notify: ' . $e->getMessage());
            }

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => count($bookingIds) > 1 ? 'Bookings created successfully! Pending admin confirmation.' : 'Booking created successfully! Pending admin confirmation.',
                'booking_id' => $bookingIds[0] ?? null,
                'booking_ids' => $bookingIds
            ]);
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}


