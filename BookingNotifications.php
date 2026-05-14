<?php
require_once __DIR__ . '/Notifications.php';

function booking_notifications_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'bookings'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);
        $cache[$column] = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

function booking_notifications_format_time(?string $time): string
{
    $time = trim((string)$time);
    if ($time === '') {
        return 'N/A';
    }
    $ts = strtotime($time);
    return $ts === false ? $time : date('g:i A', $ts);
}

function booking_notifications_find_user_id(PDO $pdo, array $booking): ?int
{
    if (!empty($booking['user_id'])) {
        return (int)$booking['user_id'];
    }

    $email = trim((string)($booking['email_address'] ?? $booking['user_email'] ?? ''));
    if ($email === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    } catch (Throwable $e) {
        return null;
    }
}

function booking_notifications_fetch_booking(PDO $pdo, int $bookingId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT b.*, u.name AS account_name, u.email AS account_email
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         WHERE b.id = ?
         LIMIT 1"
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    return $booking ?: null;
}

function booking_notifications_after_status_change(PDO $pdo, int $bookingId, string $status): void
{
    if (!in_array($status, ['confirmed', 'cancelled'], true)) {
        return;
    }

    try {
        $booking = booking_notifications_fetch_booking($pdo, $bookingId);
        if (!$booking) {
            return;
        }

        $userId = booking_notifications_find_user_id($pdo, $booking);
        if (!$userId) {
            return;
        }

        $date = trim((string)($booking['event_date'] ?? ''));
        $time = booking_notifications_format_time($booking['event_time'] ?? null);
        $customerName = trim((string)($booking['full_name'] ?? $booking['account_name'] ?? 'Customer'));

        $notifications = new Notifications($pdo);
        $notifications->autoNotify($status === 'confirmed' ? 'booking_confirmed' : 'booking_cancelled', [
            'id' => $bookingId,
            'customer_name' => $customerName !== '' ? $customerName : 'Customer',
            'date' => $date !== '' ? $date : 'N/A',
            'time' => $time,
            'user_id' => $userId,
        ]);
    } catch (Throwable $e) {
        error_log('booking notification failed: ' . $e->getMessage());
    }
}
?>
