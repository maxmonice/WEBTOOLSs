<?php
// =====================================================
//  auth.php — All authentication actions in one file
//  Accepts POST requests with JSON body
//  Returns JSON responses
// =====================================================

require_once 'db.php';

// --- CORS & headers ---
header('Content-Type: application/json');

$allowed_origins = ['https://localhost', 'https://127.0.0.1', 'https://webtoolss.test'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://localhost');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { respond(false, 'Method not allowed.'); }

// --- Session setup ---
session_start();

// --- Read JSON body ---
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

// =====================================================
//  ROUTE
// =====================================================
switch ($action) {
    case 'signup':         handleSignup($body);        break;
    case 'login':          handleLogin($body);          break;
    case 'google_auth':    handleGoogleAuth($body);     break;
    case 'facebook_auth':  handleFacebookAuth($body);   break;
    case 'logout':         handleLogout();              break;
    case 'check_session':  handleCheckSession();        break;
    default:               respond(false, 'Unknown action.');
}

// =====================================================
//  SIGNUP — email + password
// =====================================================
function handleSignup(array $data): void {
    $name     = trim($data['name']     ?? '');
    $email    = strtolower(trim($data['email']    ?? ''));
    $password = $data['password'] ?? '';

    if (!$name)                                          respond(false, 'Name is required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      respond(false, 'Invalid email address.');
    if (strlen($password) < 8)                           respond(false, 'Password must be at least 8 characters.');

    $db = getDB();

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) respond(false, 'An account with this email already exists.');

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, provider) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, 'email']);
    $userId = (int) $db->lastInsertId();

    startUserSession($userId, $name, $email);
    respond(true, 'Account created successfully.', ['name' => $name, 'email' => $email]);
}

// =====================================================
//  LOGIN — email + password
// =====================================================
function handleLogin(array $data): void {
    $email    = strtolower(trim($data['email']    ?? ''));
    $password = $data['password'] ?? '';
    $remember = !empty($data['remember']);

    if (!$email || !$password) respond(false, 'Email and password are required.');

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, password_hash, provider FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['provider'] !== 'email') {
        respond(false, 'No account found with this email.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        respond(false, 'Incorrect password.');
    }

    startUserSession($user['id'], $user['name'], $user['email']);

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $db->prepare('UPDATE users SET remember_token = ? WHERE id = ?')->execute([$token, $user['id']]);
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }

    respond(true, 'Logged in successfully.', ['name' => $user['name'], 'email' => $user['email']]);
}

// =====================================================
//  GOOGLE OAUTH — verify ID token server-side
// =====================================================
function handleGoogleAuth(array $data): void {
    $idToken = trim($data['id_token'] ?? '');
    if (!$idToken) respond(false, 'Missing Google ID token.');

    $url      = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $response = @file_get_contents($url);
    if (!$response) respond(false, 'Could not verify Google token. Check that allow_url_fopen is enabled in php.ini.');

    $payload = json_decode($response, true);

    $expectedClientId = '694050007372-2crn9q3ek8jav88iduut5ddf50ecgj0a.apps.googleusercontent.com';
    if (($payload['aud'] ?? '') !== $expectedClientId) {
        respond(false, 'Google token audience mismatch.');
    }

    $googleId = $payload['sub']     ?? '';
    $email    = strtolower($payload['email']   ?? '');
    $name     = $payload['name']    ?? 'Google User';
    $avatar   = $payload['picture'] ?? null;

    if (!$googleId || !$email) respond(false, 'Invalid Google token payload.');

    $db = getDB();

    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE provider = "google" AND provider_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) respond(false, 'This email is already registered. Please sign in with email/password.');

        $stmt = $db->prepare('INSERT INTO users (name, email, provider, provider_id, avatar_url) VALUES (?, ?, "google", ?, ?)');
        $stmt->execute([$name, $email, $googleId, $avatar]);
        $userId = (int) $db->lastInsertId();
    } else {
        $userId = $user['id'];
        $name   = $user['name'];
        $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatar, $userId]);
    }

    startUserSession($userId, $name, $email);
    respond(true, 'Signed in with Google.', ['name' => $name, 'email' => $email]);
}

// =====================================================
//  FACEBOOK OAUTH — verify access token server-side
// =====================================================
function handleFacebookAuth(array $data): void {
    $accessToken       = trim($data['access_token'] ?? '');
    $facebookAppId     = '1282425887392045';
    $facebookAppSecret = '3914094653c7aa77bc2879c77f5d9fa3';

    if (!$accessToken) respond(false, 'Missing Facebook access token.');

    $appToken  = $facebookAppId . '|' . $facebookAppSecret;
    $verifyUrl = 'https://graph.facebook.com/debug_token?input_token=' . urlencode($accessToken)
               . '&access_token=' . urlencode($appToken);
    $verifyResponse = @file_get_contents($verifyUrl);
    if (!$verifyResponse) respond(false, 'Could not verify Facebook token.');

    $verifyData = json_decode($verifyResponse, true);
    if (empty($verifyData['data']['is_valid'])) respond(false, 'Invalid Facebook token.');

    $userUrl  = 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . urlencode($accessToken);
    $userResp = @file_get_contents($userUrl);
    if (!$userResp) respond(false, 'Could not fetch Facebook user info.');

    $fbUser     = json_decode($userResp, true);
    $facebookId = $fbUser['id']   ?? '';
    $name       = $fbUser['name'] ?? 'Facebook User';
    $email      = strtolower($fbUser['email'] ?? '');
    $avatar     = $fbUser['picture']['data']['url'] ?? null;

    if (!$facebookId) respond(false, 'Invalid Facebook user data.');

    $db = getDB();

    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE provider = "facebook" AND provider_id = ? LIMIT 1');
    $stmt->execute([$facebookId]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($email) {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) respond(false, 'This email is already registered. Please sign in with email/password.');
        }

        $stmt = $db->prepare('INSERT INTO users (name, email, provider, provider_id, avatar_url) VALUES (?, ?, "facebook", ?, ?)');
        $stmt->execute([$name, $email ?: null, $facebookId, $avatar]);
        $userId = (int) $db->lastInsertId();
    } else {
        $userId = $user['id'];
        $name   = $user['name'];
        $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatar, $userId]);
    }

    startUserSession($userId, $name, $email ?: '');
    respond(true, 'Signed in with Facebook.', ['name' => $name, 'email' => $email]);
}

// =====================================================
//  LOGOUT
// =====================================================
function handleLogout(): void {
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    respond(true, 'Logged out.');
}

// =====================================================
//  CHECK SESSION
// =====================================================
function handleCheckSession(): void {
    if (!empty($_SESSION['user_id'])) {
        respond(true, 'Session active.', [
            'name'  => $_SESSION['user_name']  ?? '',
            'email' => $_SESSION['user_email'] ?? '',
        ]);
    }

    $token = $_COOKIE['remember_token'] ?? '';
    if ($token) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, email FROM users WHERE remember_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            startUserSession($user['id'], $user['name'], $user['email']);
            respond(true, 'Session restored.', ['name' => $user['name'], 'email' => $user['email']]);
        }
    }

    respond(false, 'Not logged in.');
}

// =====================================================
//  HELPERS
// =====================================================
function startUserSession(int $id, string $name, string $email): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $id;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;
}

function respond(bool $success, string $message, array $data = []): never {
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ));
    exit;
}