<?php
// =====================================================
//  riderSide/rider-auth.php
//  Simple rider authentication endpoint
// =====================================================
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

// ── Hardcoded rider accounts (extend as needed) ──
$RIDERS = [
    'rider1@gmail.com' => [
        'password' => 'password123',
        'name'     => 'Rider One',
        'id'       => 1,
    ],
];

switch ($action) {

    case 'login':
        $email    = strtolower(trim($body['email']    ?? ''));
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
            exit;
        }

        if (!isset($RIDERS[$email])) {
            echo json_encode(['success' => false, 'message' => 'No rider account found with this email.']);
            exit;
        }

        $rider = $RIDERS[$email];

        if ($password !== $rider['password']) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
            exit;
        }

        // Set rider session
        session_regenerate_id(true);
        $_SESSION['rider_id']    = $rider['id'];
        $_SESSION['rider_name']  = $rider['name'];
        $_SESSION['rider_email'] = $email;

        echo json_encode([
            'success'  => true,
            'message'  => 'Login successful.',
            'name'     => $rider['name'],
            'rider_id' => $rider['id'],
        ]);
        break;

    case 'check_session':
        if (!empty($_SESSION['rider_id'])) {
            echo json_encode([
                'success'  => true,
                'name'     => $_SESSION['rider_name']  ?? '',
                'email'    => $_SESSION['rider_email'] ?? '',
                'rider_id' => $_SESSION['rider_id'],
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not logged in.']);
        }
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}


