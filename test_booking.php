<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$payload = json_encode([
    'action' => 'create',
    'booking_data' => [
        'event_name' => 'Test Event',
        'full_name' => 'John Doe',
        'contact_number' => '09123456789',
        'email_address' => 'john@example.com',
        'event_date' => '2027-01-01',
        'event_dates' => ['2027-01-01'],
        'event_time' => '1:00 PM – 8:00 PM',
        'event_time_start' => '1:00 PM',
        'event_time_end' => '8:00 PM',
        'event_type' => 'wedding',
        'num_guests' => 50,
        'address' => '123 Test St',
        'notes' => 'Test notes'
    ]
]);
file_put_contents('php://input', $payload);

// We simulate php://input by replacing it in booking-api.php but we can just require it if we override file_get_contents. 
// Instead, let's just use curl.


