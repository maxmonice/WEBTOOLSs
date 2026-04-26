<?php
// =====================================================
//  Auth-new.php — Clean, working authentication system
//  Simple email signup/login + Google/Facebook OAuth
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'Db.php';

session_start();

function createSession($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_admin'] = $user['email'] === 'admin@gmail.com';
    $_SESSION['login_time'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && time() - $_SESSION['login_time'] < 86400;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'signup') {
        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        
        if (!$name || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }
        
        $db = getDB();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An account with this email already exists']);
            exit;
        }
        
        // Create user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, provider, email_verified) VALUES (?, ?, ?, 'email', 1)");
        $stmt->execute([$name, $email, $hash]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Account created successfully! You can now log in.',
            'name' => $name,
            'email' => $email
        ]);
        
    } elseif ($action === 'login') {
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        
        if (!$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? AND provider = 'email'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
            exit;
        }
        
        createSession($user);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful!',
            'name' => $user['name'],
            'email' => $user['email'],
            'redirect' => $email === 'admin@gmail.com' ? 'admin-dashboard.php' : 'account-dashboard.php'
        ]);
        
    } elseif ($action === 'google_auth') {
        $idToken = $data['id_token'] ?? '';
        
        if (!$idToken) {
            echo json_encode(['success' => false, 'message' => 'Google authentication failed']);
            exit;
        }
        
        // Simple Google token verification (for production, use Google API client)
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
            exit;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (!$payload || !isset($payload['email'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
            exit;
        }
        
        $email = strtolower($payload['email']);
        $name = $payload['name'] ?? 'Google User';
        $googleId = $payload['sub'] ?? '';
        
        $db = getDB();
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND provider = 'google'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Create new Google user
            $stmt = $db->prepare("INSERT INTO users (name, email, provider, provider_id, email_verified) VALUES (?, ?, 'google', ?, 1)");
            $stmt->execute([$name, $email, $googleId]);
            
            $user = [
                'id' => $db->lastInsertId(),
                'name' => $name,
                'email' => $email
            ];
        }
        
        createSession($user);
        
        echo json_encode([
            'success' => true,
            'message' => 'Google login successful!',
            'name' => $user['name'],
            'email' => $user['email'],
            'redirect' => $email === 'admin@gmail.com' ? 'admin-dashboard.php' : 'account-dashboard.php'
        ]);
        
    } elseif ($action === 'facebook_auth') {
        $accessToken = $data['access_token'] ?? '';
        
        if (!$accessToken) {
            echo json_encode(['success' => false, 'message' => 'Facebook authentication failed']);
            exit;
        }
        
        // Verify Facebook token
        $response = @file_get_contents("https://graph.facebook.com/me?fields=id,name,email&access_token={$accessToken}");
        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'Facebook API error']);
            exit;
        }
        
        $fbData = json_decode($response, true);
        
        if (!$fbData || !isset($fbData['email'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid Facebook token']);
            exit;
        }
        
        $email = strtolower($fbData['email']);
        $name = $fbData['name'];
        $facebookId = $fbData['id'];
        
        $db = getDB();
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND provider = 'facebook'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Create new Facebook user
            $stmt = $db->prepare("INSERT INTO users (name, email, provider, provider_id, email_verified) VALUES (?, ?, 'facebook', ?, 1)");
            $stmt->execute([$name, $email, $facebookId]);
            
            $user = [
                'id' => $db->lastInsertId(),
                'name' => $name,
                'email' => $email
            ];
        }
        
        createSession($user);
        
        echo json_encode([
            'success' => true,
            'message' => 'Facebook login successful!',
            'name' => $user['name'],
            'email' => $user['email'],
            'redirect' => $email === 'admin@gmail.com' ? 'admin-dashboard.php' : 'account-dashboard.php'
        ]);
        
    } elseif ($action === 'check_session') {
        if (isLoggedIn()) {
            echo json_encode([
                'success' => true,
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'is_admin' => $_SESSION['is_admin'],
                'redirect' => $_SESSION['is_admin'] ? 'admin-dashboard.php' : 'account-dashboard.php'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
        }
        
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
