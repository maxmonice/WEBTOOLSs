<?php
// =====================================================
//  auth.php — All authentication actions in one file
//  Accepts POST requests with JSON body
//  Returns JSON responses
// =====================================================

require_once 'db.php';
require_once 'activity-logger.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
require_once 'session-helper.php';
loadLocalEnv(__DIR__ . '/.env');

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

// --- Read JSON body ---
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');
$email  = strtolower(trim($body['email'] ?? ''));
$runId  = (string)($body['runId'] ?? 'unknown');
$side   = $body['side'] ?? 'customer'; 
$GLOBALS['DEBUG_RUN_ID'] = $runId;

// --- Session setup ---
startSiloSession(); // Use unified session

debugLog($runId, 'H6', 'Auth.php:input', 'Raw input received', [
    'action' => $action,
    'email' => $email,
    'emailLength' => strlen($email),
    'emailBytes' => bin2hex($email),
]);

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    if (!in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    debugLog((string)($GLOBALS['DEBUG_RUN_ID'] ?? 'unknown'), 'H8', 'Auth.php:shutdown', 'Fatal PHP error detected', [
        'type' => $err['type'] ?? null,
        'message' => $err['message'] ?? '',
        'file' => $err['file'] ?? '',
        'line' => $err['line'] ?? null,
    ]);
});

debugLog($runId, 'H6', 'Auth.php:route', 'Incoming auth action', [
    'action' => $action,
    'sessionId' => session_id(),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
]);

// =====================================================
//  ROUTE
// =====================================================
switch ($action) {
    case 'signup':           handleSignup($body);          break;
    case 'login':            handleLogin($body);            break;
    case 'verify_otp':       handleVerifyOtp($body);        break;
    case 'resend_otp':       handleResendOtp();             break;
    case 'google_auth':      handleGoogleAuth($body);       break;
    case 'facebook_auth':    handleFacebookAuth($body);     break;
    case 'logout':           handleLogout();                break;
    case 'check_session':    handleCheckSession();          break;
    case 'change_password':  handleChangePassword($body);   break;
    default:                 respond(false, 'Unknown action.');
}

// =====================================================
//  ADMIN ACCOUNT SEEDER
//  Automatically creates admin@gmail.com if it doesn't
//  exist yet. Called on every admin login attempt.
// =====================================================
function seedAdminAccount($db): void {
    $adminEmail = 'admin@gmail.com';
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$adminEmail]);
    if ($stmt->fetch()) {
        return; // Already exists
    }
    $hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $db->prepare(
            'INSERT INTO users (name, email, password_hash, provider, email_verified) VALUES (?, ?, ?, ?, 1)'
        )->execute(['Administrator', $adminEmail, $hash, 'email']);
    } catch (\Throwable $e) {
        // Silently fail if seeding fails (e.g. different schema)
    }
}

// =====================================================
//  SIGNUP — creates account, then requires OTP verify
// =====================================================
function verifyRecaptcha(string $token): bool {
    // Allow bypass for local development
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'localhost') !== false || strpos($host, '.test') !== false || strpos($host, '127.0.0.1') !== false) {
        return true;
    }

    $secretKey = getenv('RECAPTCHA_SECRET_KEY') ?: '6LcpWt4sAAAAAGPrhF2EIUDLbAy3Ocp_pFvDUdCE';
    if ($token === '') {
        error_log('reCAPTCHA: Empty token provided');
        return false;
    }

    try {
        $postData = http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 5,
            ],
        ]);
        $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        if (!$response) {
            error_log('reCAPTCHA: Failed to connect to Google API');
            return false;
        }
        $result = json_decode($response, true);
        if (empty($result['success'])) {
            error_log('reCAPTCHA: Verification failed - ' . $response);
            return false;
        }
        return (float)($result['score'] ?? 0.0) > 0.3;
    } catch (\Throwable $e) {
        error_log('reCAPTCHA: Exception during verification - ' . $e->getMessage());
        return false;
    }
}

function handleSignup(array $data): void {
    $name     = trim($data['name']     ?? '');
    $email    = strtolower(trim($data['email']    ?? ''));
    $password = $data['password'] ?? '';
    $runId    = (string)($data['runId'] ?? (string)($GLOBALS['DEBUG_RUN_ID'] ?? 'unknown'));

    debugLog($runId, 'H7', 'Auth.php:handleSignup:start', 'Signup request received', [
        'hasName' => $name !== '',
        'email' => $email,
        'passwordLength' => strlen($password),
        'sessionId' => session_id(),
    ]);

    // Block admin email from self-registering
    if ($email === 'admin@gmail.com') {
        respond(false, 'This email address is reserved.');
    }

    if (!$name)                                          respond(false, 'Name is required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      respond(false, 'Invalid email address.');
    if (strlen($password) < 8)                           respond(false, 'Password must be at least 8 characters.');
    if (!verifyRecaptcha((string)($data['recaptchaToken'] ?? ''))) {
        respond(false, 'reCAPTCHA verification failed. Please try again.');
    }

    $db = getDB();

    // Allow re-registration if previous attempt was never verified
    $stmt = $db->prepare('SELECT id, email_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($existing['email_verified']) {
            respond(false, 'An account with this email already exists.');
        }
        // Unverified leftover — clean it up so they can retry
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$existing['id']]);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, provider, email_verified) VALUES (?, ?, ?, ?, 0)'
    );
    $stmt->execute([$name, $email, $hash, 'email']);
    $userId = (int) $db->lastInsertId();
    debugLog($runId, 'H7', 'Auth.php:handleSignup:userCreated', 'User created for signup pending verification', [
        'userId' => $userId,
        'email' => $email,
    ]);

    // Store pending data in session (never in the response)
    $_SESSION['pending_user_id']    = $userId;
    $_SESSION['pending_user_name']  = $name;
    $_SESSION['pending_user_email'] = $email;
    $_SESSION['pending_context']    = 'signup';

    $sent = generateAndSendOtp($db, $userId, $email, $name);
    debugLog($runId, 'H7', 'Auth.php:handleSignup:otp', 'OTP send attempt completed', [
        'userId' => $userId,
        'otpSent' => (bool)$sent,
    ]);
    if (!$sent) {
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'],
              $_SESSION['pending_user_email'], $_SESSION['pending_context']);
        respond(false, otpDeliveryErrorMessage());
    }

    respond(true, 'Verification code sent to your email.', [
        'requires_2fa' => true,
        'email_hint'   => maskEmail($email),
    ]);
}

// =====================================================
//  LOGIN — validates credentials, then requires OTP
//  Admin account (admin@gmail.com) bypasses OTP entirely.
// =====================================================
function handleLogin(array $data): void {
    $email    = strtolower(trim($data['email']    ?? ''));
    $password = $data['password'] ?? '';
    $remember = !empty($data['remember']);
    $runId    = (string)($data['runId'] ?? 'unknown');

    debugLog($runId, 'H1', 'Auth.php:handleLogin:start', 'Login request received', [
        'email' => $email,
        'hasPassword' => $password !== '',
        'sessionId' => session_id(),
    ]);

    if (!$email || !$password) respond(false, 'Email and password are required.');
    if ($email !== 'admin@gmail.com' && !verifyRecaptcha((string)($data['recaptchaToken'] ?? ''))) {
        respond(false, 'reCAPTCHA verification failed. Please try again.');
    }

    $db = getDB();

    // Auto-seed the admin account if it doesn't exist yet
    if ($email === 'admin@gmail.com') {
        seedAdminAccount($db);
    }

    $stmt = $db->prepare(
        'SELECT id, name, email, password_hash, provider, role, status FROM users WHERE email = ? AND COALESCE(is_archived,0) = 0'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    debugLog($runId, 'H1', 'Auth.php:handleLogin:userFetch', 'User fetched from DB', [
        'email' => $email,
        'userFound' => (bool)$user,
        'userProvider' => $user['provider'] ?? null,
    ]);

    // ── ADMIN/STAFF BYPASS: skip OTP entirely ──
    $userRole = $user ? ($user['role'] ?? 'customer') : 'customer';
    $isAdminOrStaff = ($email === 'admin@gmail.com') || in_array($userRole, ['admin', 'staff']);

    if ($isAdminOrStaff) {
        debugLog($runId, 'H1', 'Auth.php:handleLogin:staffAdminCheck', 'Staff/Admin CHECK PASSED', [
            'email' => $email,
            'role' => $userRole,
            'userFound' => (bool)$user,
        ]);
        if (!$user) {
            debugLog($runId, 'H1', 'Auth.php:handleLogin:noUser', 'No user found with this email', []);
            respond(false, 'No account found with this email.');
        }

        // Check if user is suspended
        $userStatus = $user['status'] ?? 'active';
        if ($userStatus === 'suspended') {
            debugLog($runId, 'H1', 'Auth.php:handleLogin:suspended', 'User account is suspended', [
                'userStatus' => $userStatus,
            ]);
            respond(false, 'Your account has been suspended. Please contact the administrator.');
        }

        $passwordMatch = password_verify($password, $user['password_hash']);
        debugLog($runId, 'H1', 'Auth.php:handleLogin:password', 'Password verification result', [
            'passwordMatch' => $passwordMatch,
        ]);

        if (!$passwordMatch) {
            debugLog($runId, 'H1', 'Auth.php:handleLogin:wrongPassword', 'Password did not match', []);
            respond(false, 'Incorrect password.');
        }
        
        debugLog($runId, 'H1', 'Auth.php:handleLogin:staffAdmin', 'Staff/Admin login — bypassing OTP', [
            'userId' => (int) $user['id'],
            'role' => $userRole
        ]);
        
        $side = $GLOBALS['side'] ?? 'customer';
        startUserSession((int)$user['id'], $user['name'], $user['email'], $userRole, $side);
        
        $redirectUrl = '';
        if ($userRole === 'admin' || $email === 'admin@gmail.com') {
            $redirectUrl = 'adminSide/admin-dashboard.php';
        } else {
            $redirectUrl = 'staffSide/staff-dashboard.php';
        }
        
        // Log login activity
        $logRole = ($email === 'admin@gmail.com') ? 'admin' : $userRole;
        logActivity($logRole . '_login', ucfirst($logRole) . ' user logged in successfully', $user['email'], $user['name']);
        
        respond(true, 'Login successful.', [
            'name'     => $user['name'],
            'email'    => $user['email'],
            'redirect' => $redirectUrl,
        ]);
    } else {
        debugLog($runId, 'H1', 'Auth.php:handleLogin:notStaffAdmin', 'Check FAILED - not staff or admin', [
            'email' => $email,
            'role' => $userRole
        ]);
    }

    // For regular users, check if provider is email
    if (!$user || $user['provider'] !== 'email') {
        respond(false, 'No account found with this email.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        respond(false, 'Incorrect password.');
    }

    // Check if user is suspended
    $userStatus = $user['status'] ?? 'active';
    if ($userStatus === 'suspended') {
        debugLog($runId, 'H1', 'Auth.php:handleLogin:suspended', 'User account is suspended', [
            'userStatus' => $userStatus,
        ]);
        respond(false, 'Your account has been suspended. Please contact the administrator.');
    }
    $_SESSION['pending_user_id']    = $user['id'];
    $_SESSION['pending_user_name']  = $user['name'];
    $_SESSION['pending_user_email'] = $user['email'];
    $_SESSION['pending_context']    = 'login';
    $_SESSION['pending_remember']   = $remember;

    $sent = generateAndSendOtp($db, $user['id'], $user['email'], $user['name']);
    debugLog($runId, 'H2', 'Auth.php:handleLogin:otp', 'OTP generation result during login', [
        'userId' => (int)$user['id'],
        'otpSent' => (bool)$sent,
        'pendingUserId' => $_SESSION['pending_user_id'] ?? null,
        'pendingContext' => $_SESSION['pending_context'] ?? null,
    ]);
    if (!$sent) {
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_name'],
              $_SESSION['pending_user_email'], $_SESSION['pending_context'],
              $_SESSION['pending_remember']);
        respond(false, otpDeliveryErrorMessage());
    }

    respond(true, 'Verification code sent to your email.', [
        'requires_2fa' => true,
        'email_hint'   => maskEmail($user['email']),
    ]);
}

// =====================================================
//  VERIFY OTP — completes login or signup
// =====================================================
function handleVerifyOtp(array $data): void {
    $code = trim($data['code'] ?? '');
    $runId = (string)($data['runId'] ?? 'unknown');

    debugLog($runId, 'H1', 'Auth.php:handleVerifyOtp:start', 'OTP verification request received', [
        'codeLength' => strlen($code),
        'hasPendingUser' => !empty($_SESSION['pending_user_id']),
        'sessionId' => session_id(),
    ]);

    if (!$code || strlen($code) !== 6 || !ctype_digit($code)) {
        respond(false, 'Please enter a valid 6-digit code.');
    }

    if (empty($_SESSION['pending_user_id'])) {
        respond(false, 'Session expired. Please start over.');
    }

    $userId  = (int) $_SESSION['pending_user_id'];
    $context = $_SESSION['pending_context'] ?? 'login';

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT otp_code, otp_expires_at, otp_attempts, COALESCE(is_archived,0) AS is_archived FROM users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    debugLog($runId, 'H2', 'Auth.php:handleVerifyOtp:user', 'Loaded OTP state from DB', [
        'userId' => $userId,
        'hasUser' => (bool)$user,
        'otpCodePreview' => $user ? substr((string)$user['otp_code'], 0, 2) . '****' : null,
        'otpExpiresAt' => $user['otp_expires_at'] ?? null,
        'otpAttempts' => isset($user['otp_attempts']) ? (int)$user['otp_attempts'] : null,
    ]);

    if (!$user) respond(false, 'User not found.');

    if (!empty($user['is_archived'])) {
        respond(false, 'This account is no longer active.');
    }

    // Max 5 wrong attempts
    if ((int) $user['otp_attempts'] >= 5) {
        respond(false, 'Too many incorrect attempts. Please request a new code.');
    }

    // Check expiry
    if (!$user['otp_expires_at'] || strtotime($user['otp_expires_at']) < time()) {
        respond(false, 'This code has expired. Please request a new one.');
    }

    // Wrong code — increment attempts
    if ($user['otp_code'] !== $code) {
        debugLog($runId, 'H3', 'Auth.php:handleVerifyOtp:mismatch', 'OTP mismatch', [
            'userId' => $userId,
            'submittedPrefix' => substr($code, 0, 2) . '****',
            'storedPrefix' => substr((string)$user['otp_code'], 0, 2) . '****',
            'attemptsBeforeIncrement' => (int)$user['otp_attempts'],
        ]);
        $db->prepare(
            'UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?'
        )->execute([$userId]);
        $remaining = 4 - (int) $user['otp_attempts'];
        respond(false, "Incorrect code. {$remaining} attempt(s) remaining.");
    }

    // ✅ OTP is valid — clear it
    $db->prepare(
        'UPDATE users SET otp_code = NULL, otp_expires_at = NULL, otp_attempts = 0 WHERE id = ?'
    )->execute([$userId]);

    $name  = $_SESSION['pending_user_name'];
    $email = $_SESSION['pending_user_email'];

    if ($context === 'signup') {
        $db->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([$userId]);
    }

    if ($context === 'login' && !empty($_SESSION['pending_remember'])) {
        $token = bin2hex(random_bytes(32));
        $db->prepare('UPDATE users SET remember_token = ? WHERE id = ?')
           ->execute([$token, $userId]);
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }

    // Clear all pending session keys
    foreach (['pending_user_id','pending_user_name','pending_user_email',
              'pending_context','pending_remember'] as $k) {
        unset($_SESSION[$k]);
    }

    $side = $GLOBALS['side'] ?? 'customer';
    startUserSession($userId, $name, $email, 'customer', $side);
    
    // Log user login activity
    $actionType = $context === 'signup' ? 'user_signup' : 'user_login';
    $details = $context === 'signup' ? 'New user completed signup and verification' : 'User logged in successfully';
    logActivity($actionType, $details, $email, $name);
    
    debugLog($runId, 'H4', 'Auth.php:handleVerifyOtp:success', 'OTP verification succeeded and user session started', [
        'userId' => $userId,
        'context' => $context,
        'sessionUserId' => $_SESSION['user_id'] ?? null,
    ]);
    respond(true, 'Verified successfully.', ['name' => $name, 'email' => $email]);
}

// =====================================================
//  RESEND OTP — rate-limited to once per 60 seconds
// =====================================================
function handleResendOtp(): void {
    if (empty($_SESSION['pending_user_id'])) {
        respond(false, 'Session expired. Please start over.');
    }

    $userId = (int) $_SESSION['pending_user_id'];
    $email  = $_SESSION['pending_user_email'];
    $name   = $_SESSION['pending_user_name'];

    $db   = getDB();
    $stmt = $db->prepare('SELECT otp_expires_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Rate limit: OTP expires_at = sent_at + 600s, so sent_at = expires_at - 600
    if ($user && $user['otp_expires_at']) {
        $sentAt = strtotime($user['otp_expires_at']) - 600;
        if (time() - $sentAt < 60) {
            $waitSecs = 60 - (time() - $sentAt);
            respond(false, "Please wait {$waitSecs} seconds before requesting a new code.");
        }
    }

    $sent = generateAndSendOtp($db, $userId, $email, $name);
    if (!$sent) respond(false, 'Could not resend email. Please try again.');

    respond(true, 'A new verification code has been sent to your email.', [
        'email_hint' => maskEmail($email),
    ]);
}

// =====================================================
//  CHANGE PASSWORD
// =====================================================
function handleChangePassword(array $data): void {
    if (empty($_SESSION['user_id'])) {
        respond(false, 'You must be logged in to change your password.');
    }

    $current = $data['current_password'] ?? '';
    $new     = $data['new_password']     ?? '';
    $confirm = $data['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) respond(false, 'All fields are required.');
    if (strlen($new) < 8)                respond(false, 'New password must be at least 8 characters.');
    if ($new !== $confirm)               respond(false, 'New passwords do not match.');

    $db   = getDB();
    $stmt = $db->prepare('SELECT password_hash, provider FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) respond(false, 'User not found.');

    if ($user['provider'] !== 'email') {
        respond(false, 'Password cannot be changed for social login accounts (Google / Facebook).');
    }
    if (!password_verify($current, $user['password_hash'])) {
        respond(false, 'Current password is incorrect.');
    }
    if ($current === $new) {
        respond(false, 'New password must be different from your current password.');
    }

    $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([$newHash, $_SESSION['user_id']]);

    respond(true, 'Password changed successfully.');
}

// =====================================================
//  GOOGLE OAUTH
// =====================================================
function handleGoogleAuth(array $data): void {
    $idToken = trim($data['id_token'] ?? '');
    $runId = (string)($GLOBALS['DEBUG_RUN_ID'] ?? 'unknown');
    if (!$idToken) respond(false, 'Missing Google ID token.');

    $url      = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $response = fbCurlGet($url); // Using the existing fbCurlGet helper
    if (!$response) respond(false, 'Could not verify Google token. Please check server connection.');

    $payload = json_decode($response, true);

    $expectedClientId = getenv('GOOGLE_CLIENT_ID') ?: '694050007372-2crn9q3ek8jav88iduut5ddf50ecgj0a.apps.googleusercontent.com';
    debugLog($runId, 'H13', 'Auth.php:handleGoogleAuth:config', 'Google auth config loaded', [
        'hasExpectedClientId' => $expectedClientId !== '',
    ]);
    if (($payload['aud'] ?? '') !== $expectedClientId) {
        respond(false, 'Google token audience mismatch.');
    }

    $googleId = $payload['sub']     ?? '';
    $email    = strtolower($payload['email']   ?? '');
    $name     = $payload['name']    ?? 'Google User';
    $avatar   = $payload['picture'] ?? null;

    if (!$googleId || !$email) respond(false, 'Invalid Google token payload.');

    $db = getDB();

    $stmt = $db->prepare(
        'SELECT id, name, email, status FROM users WHERE provider = "google" AND provider_id = ? AND COALESCE(is_archived,0) = 0 LIMIT 1'
    );
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            respond(false, 'This email is already registered. Please sign in with email/password.');
        }
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, provider, provider_id, avatar_url, email_verified) VALUES (?, ?, "google", ?, ?, 1)'
        );
        $stmt->execute([$name, $email, $googleId, $avatar]);
        $userId = (int) $db->lastInsertId();
    } else {
        // Check if user is suspended
        $userStatus = $user['status'] ?? 'active';
        if ($userStatus === 'suspended') {
            debugLog($runId, 'H13', 'Auth.php:handleGoogleAuth:suspended', 'Google auth user account is suspended', [
                'userStatus' => $userStatus,
            ]);
            respond(false, 'Your account has been suspended. Please contact the administrator.');
        }
        
        $userId = $user['id'];
        $name   = $user['name'];
        $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatar, $userId]);
    }

    $side = $GLOBALS['side'] ?? 'customer';
    startUserSession($userId, $name, $email, 'customer', $side);
    
    // Log Google login activity
    logActivity('google_login', 'User logged in with Google OAuth', $email, $name);
    
    respond(true, 'Signed in with Google.', ['name' => $name, 'email' => $email]);
}

// =====================================================
//  FACEBOOK OAUTH
// =====================================================
function fbCurlGet(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'LukesSeafood/1.0',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ?: null;
}

function handleFacebookAuth(array $data): void {
    $accessToken       = trim($data['access_token'] ?? '');
    $facebookAppId     = getenv('FACEBOOK_APP_ID') ?: '1282425887392045';
    $facebookAppSecret = getenv('FACEBOOK_APP_SECRET') ?: '3914094653c7aa77bc2879c77f5d9fa3';
    $runId             = (string)($GLOBALS['DEBUG_RUN_ID'] ?? 'unknown');

    debugLog($runId, 'H12', 'Auth.php:handleFacebookAuth:config', 'Facebook auth config loaded', [
        'hasAppId'     => $facebookAppId !== '',
        'hasAppSecret' => $facebookAppSecret !== '',
    ]);

    if (!$accessToken) respond(false, 'Missing Facebook access token.');
    if (!$facebookAppId || !$facebookAppSecret) {
        respond(false, 'Facebook login is not configured.');
    }

    // Verify token with Facebook
    $appToken  = $facebookAppId . '|' . $facebookAppSecret;
    $verifyUrl = 'https://graph.facebook.com/debug_token?input_token=' . urlencode($accessToken)
               . '&access_token=' . urlencode($appToken);
    $verifyResponse = fbCurlGet($verifyUrl);
    if (!$verifyResponse) respond(false, 'Could not verify Facebook token. Check server cURL/SSL settings.');

    $verifyData = json_decode($verifyResponse, true);
    if (empty($verifyData['data']['is_valid'])) respond(false, 'Invalid Facebook token.');

    // Fetch user info
    $userUrl  = 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . urlencode($accessToken);
    $userResp = fbCurlGet($userUrl);
    if (!$userResp) respond(false, 'Could not fetch Facebook user info.');

    $fbUser     = json_decode($userResp, true);
    $facebookId = $fbUser['id']   ?? '';
    $name       = $fbUser['name'] ?? 'Facebook User';
    $email      = strtolower($fbUser['email'] ?? '');
    $avatar     = $fbUser['picture']['data']['url'] ?? null;

    if (!$facebookId) respond(false, 'Invalid Facebook user data.');

    $db = getDB();

    $stmt = $db->prepare(
        'SELECT id, name, email, status FROM users WHERE provider = "facebook" AND provider_id = ? AND COALESCE(is_archived,0) = 0 LIMIT 1'
    );
    $stmt->execute([$facebookId]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($email) {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                respond(false, 'This email is already registered. Please sign in with email/password.');
            }
        }
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, provider, provider_id, avatar_url, email_verified) VALUES (?, ?, "facebook", ?, ?, 1)'
        );
        $stmt->execute([$name, $email ?: null, $facebookId, $avatar]);
        $userId = (int) $db->lastInsertId();
    } else {
        $userId = $user['id'];
        $name   = $user['name'];
        $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatar, $userId]);
    }

    $side = $GLOBALS['side'] ?? 'customer';
    startUserSession($userId, $name, $email ?: '', 'customer', $side);
    logActivity('facebook_login', 'User logged in with Facebook OAuth', $email ?: '', $name);
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
    $runId = (string)($GLOBALS['DEBUG_RUN_ID'] ?? 'unknown');
    debugLog($runId, 'H6', 'Auth.php:handleCheckSession:start', 'Checking current session', [
        'hasUserSession' => !empty($_SESSION['user_id']),
        'hasRememberToken' => !empty($_COOKIE['remember_token']),
    ]);

    if (!empty($_SESSION['user_id'])) {
        debugLog($runId, 'H6', 'Auth.php:handleCheckSession:active', 'Active session found', [
            'userId' => $_SESSION['user_id'],
        ]);
        
        // Check if user is suspended
        $db = getDB();
        $stmt = $db->prepare('SELECT role, status, phone, address, COALESCE(is_archived,0) AS is_archived FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['is_archived'])) {
            session_destroy();
            respond(false, 'This account is no longer active.');
        }
        
        if ($user && $user['status'] === 'suspended') {
            debugLog($runId, 'H6', 'Auth.php:handleCheckSession:suspended', 'Active user is suspended', [
                'userId' => $_SESSION['user_id'],
                'userStatus' => $user['status'],
            ]);
            // Log out the suspended user
            session_destroy();
            respond(false, 'Your account has been suspended.');
        }
        $role = $user['role'] ?? ($_SESSION['role'] ?? 'customer');
        $_SESSION['role'] = $role;
        unset($_SESSION['is_admin'], $_SESSION['is_staff']);
        if ($role === 'admin' || ($_SESSION['user_email'] ?? '') === 'admin@gmail.com') {
            $_SESSION['is_admin'] = true;
        } elseif ($role === 'staff') {
            $_SESSION['is_staff'] = true;
        }
        
        // Fetch last used address and mobile from orders
        $lastOrder = $db->prepare('SELECT address, payment_details FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $lastOrder->execute([$_SESSION['user_id']]);
        $orders = $lastOrder->fetchAll();
        
        $lastAddress = '';
        $mobiles = [];
        foreach ($orders as $o) {
            if (!$lastAddress && !empty($o['address'])) $lastAddress = $o['address'];
            $pd = json_decode($o['payment_details'] ?? '{}', true);
            $m = $pd['mobile'] ?? $pd['codMobile'] ?? '';
            if ($m && !in_array($m, $mobiles)) $mobiles[] = $m;
        }
        
        $redirect = 'account-dashboard.php';
        if (!empty($_SESSION['is_admin'])) {
            $redirect = 'adminSide/admin-dashboard.php';
        } elseif (!empty($_SESSION['is_staff']) || ($_SESSION['session_side'] ?? '') === 'staff') {
            $redirect = 'staffSide/staff-dashboard.php';
        }

        respond(true, 'Session active.', [
            'user_id' => (int)$_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $role,
            'session_side' => $_SESSION['session_side'] ?? '',
            'is_admin' => $_SESSION['is_admin'] ?? false,
            'is_staff' => $_SESSION['is_staff'] ?? false,
            'last_address' => $user['address'] ?: $lastAddress,
            'phone' => $user['phone'] ?: '',
            'address' => $user['address'] ?: '',
            'last_mobiles' => $mobiles,
            'redirect' => $redirect,
        ]);
    }

    $token = $_COOKIE['remember_token'] ?? '';
    if ($token) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, email, role, status, COALESCE(is_archived,0) AS is_archived FROM users WHERE remember_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            if (!empty($user['is_archived'])) {
                debugLog($runId, 'H6', 'Auth.php:handleCheckSession:archivedRemember', 'Remember token user is archived', [
                    'userId' => $user['id'],
                ]);
                respond(false, 'Not logged in.');
            }
            // Check if user is suspended
            if ($user['status'] === 'suspended') {
                debugLog($runId, 'H6', 'Auth.php:handleCheckSession:suspendedRemember', 'Remember token user is suspended', [
                    'userId' => $user['id'],
                    'userStatus' => $user['status'],
                ]);
                respond(false, 'Your account has been suspended.');
            }
            
            $side = $GLOBALS['side'] ?? 'customer';
    startUserSession($user['id'], $user['name'], $user['email'], $user['role'] ?? 'customer', $side);
            debugLog($runId, 'H6', 'Auth.php:handleCheckSession:restored', 'Session restored via remember token', [
                'userId' => $user['id'],
            ]);
            $redirect = 'account-dashboard.php';
            if (($user['role'] ?? '') === 'admin' || $user['email'] === 'admin@gmail.com') {
                $redirect = 'adminSide/admin-dashboard.php';
            } elseif (($user['role'] ?? '') === 'staff') {
                $redirect = 'staffSide/staff-dashboard.php';
            }

            respond(true, 'Session restored.', [
                'user_id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'customer',
                'is_admin' => ($user['role'] ?? '') === 'admin' || $user['email'] === 'admin@gmail.com',
                'is_staff' => ($user['role'] ?? '') === 'staff',
                'redirect' => $redirect,
            ]);
        }
    }

    debugLog($runId, 'H6', 'Auth.php:handleCheckSession:none', 'No active session', []);
    respond(false, 'Not logged in.');
}

// =====================================================
//  HELPERS
// =====================================================

/**
 * Generates a 6-digit OTP, stores it in the DB (10 min expiry), and emails it.
 */
function generateAndSendOtp($db, int $userId, string $email, string $name): bool {
    $code      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    $db->prepare(
        'UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_attempts = 0 WHERE id = ?'
    )->execute([$code, $expiresAt, $userId]);

    return sendOtpEmail($email, $name, $code);
}

/**
 * Sends OTP via PHPMailer SMTP.
 */
function sendOtpEmail(string $to, string $name, string $code): bool {
    $firstName = explode(' ', trim($name))[0];
    $subject   = "Your Luke's Seafood verification code";

    $html = "
<!DOCTYPE html>
<html>
<body style='margin:0;padding:0;background:#191919;font-family:Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0'>
    <tr><td align='center' style='padding:40px 20px;'>
      <table width='480' cellpadding='0' cellspacing='0'
             style='background:#222;border-radius:16px;overflow:hidden;border:1px solid rgba(194,38,38,0.3);'>
        <!-- Header -->
        <tr>
          <td style='background:linear-gradient(90deg,#9B0A1E 0%,#C22626 100%);padding:22px 32px;'>
            <p style='margin:0;font-family:Georgia,serif;font-size:1.2rem;color:#fff;
                      letter-spacing:0.05em;'>Luke&#39;s Seafood Trading</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style='padding:36px 32px;'>
            <p style='color:#f0ece6;font-size:1rem;margin:0 0 6px;'>Hi {$firstName},</p>
            <p style='color:rgba(255,255,255,0.55);font-size:0.88rem;margin:0 0 28px;line-height:1.65;'>
              Use the code below to verify your identity.
              This code expires in <strong style='color:#fff;'>10 minutes</strong>
              and can only be used once.
            </p>
            <!-- OTP Box -->
            <div style='background:#2a2a2a;border:1px solid rgba(194,38,38,0.4);
                        border-radius:12px;padding:28px 24px;text-align:center;margin-bottom:28px;'>
              <p style='margin:0 0 10px;font-size:0.72rem;color:rgba(255,255,255,0.38);
                        letter-spacing:0.2em;text-transform:uppercase;'>Verification Code</p>
              <p style='margin:0;font-size:2.8rem;font-weight:900;color:#fff;
                        letter-spacing:0.4em;font-family:monospace;'>{$code}</p>
            </div>
            <p style='color:rgba(255,255,255,0.35);font-size:0.78rem;margin:0;line-height:1.6;'>
              If you didn&#39;t request this, please ignore this email — your account is safe.
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style='padding:14px 32px;border-top:1px solid rgba(255,255,255,0.07);'>
            <p style='margin:0;font-size:0.72rem;color:rgba(255,255,255,0.25);'>
              &copy; 2025 Luke&#39;s Seafood Trading &nbsp;|&nbsp; Taguig, Philippines
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

    $runId = (string)($GLOBALS['DEBUG_RUN_ID'] ?? 'unknown');
    $smtpHost = getenv('OTP_SMTP_HOST') ?: (ini_get('SMTP') ?: 'localhost');
    $smtpPort = (int)(getenv('OTP_SMTP_PORT') ?: (ini_get('smtp_port') ?: 25));
    $smtpUser = getenv('OTP_SMTP_USER') ?: '';
    $smtpPass = getenv('OTP_SMTP_PASS') ?: '';
    $smtpSecure = strtolower((string)(getenv('OTP_SMTP_SECURE') ?: ''));
    $fromEmail = getenv('OTP_FROM_EMAIL') ?: 'noreply@lukesseafood.com';
    $fromName = getenv('OTP_FROM_NAME') ?: "Luke's Seafood Trading";

    debugLog($runId, 'H11', 'Auth.php:sendOtpEmail:pre', 'Preparing PHPMailer SMTP send', [
        'toDomain' => substr(strrchr($to, '@') ?: '', 1),
        'smtpHost' => $smtpHost,
        'smtpPort' => $smtpPort,
        'smtpSecure' => $smtpSecure ?: 'none',
        'hasSmtpUser' => $smtpUser !== '',
        'hasSmtpPass' => $smtpPass !== '',
        'fromEmail' => $fromEmail,
    ]);

    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        debugLog($runId, 'H11', 'Auth.php:sendOtpEmail:missing', 'PHPMailer class not available', []);
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = $smtpUser !== '' || $smtpPass !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
        }
        if ($smtpSecure === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = "Your verification code is {$code}. It expires in 10 minutes.";

        $ok = $mail->send();
        debugLog($runId, 'H11', 'Auth.php:sendOtpEmail:post', 'PHPMailer send result', [
            'mailOk' => (bool)$ok,
            'errorInfo' => $mail->ErrorInfo ?: null,
        ]);
        return (bool)$ok;
    } catch (\Throwable $e) {
        debugLog($runId, 'H11', 'Auth.php:sendOtpEmail:error', 'PHPMailer threw exception', [
            'errorMessage' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Masks an email for display: j***@gmail.com
 */
function maskEmail(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, 1);
    return $visible . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
}

function startUserSession(int $id, string $name, string $email, string $role = 'customer', string $side = 'customer'): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = (int)$id;
    $_SESSION['user_name']    = $name;
    $_SESSION['user_email']   = $email;
    $_SESSION['role']         = $role;
    
    // Auto-detect session side for admin/staff to prevent dashboard loops
    if ($role === 'admin' || $role === 'staff' || $email === 'admin@gmail.com') {
        $_SESSION['session_side'] = 'staff';
    } else {
        $_SESSION['session_side'] = $side;
    }

    unset($_SESSION['is_admin'], $_SESSION['is_staff']);
    if ($role === 'admin' || $email === 'admin@gmail.com') {
        $_SESSION['is_admin'] = true;
    } elseif ($role === 'staff') {
        $_SESSION['is_staff'] = true;
    }
}

function otpDeliveryErrorMessage(): string {
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        return 'Email verification service is unavailable (PHPMailer is not installed). Install PHPMailer and configure SMTP, then try again.';
    }
    $smtp = getenv('OTP_SMTP_HOST') ?: (ini_get('SMTP') ?: 'not-configured');
    $port = getenv('OTP_SMTP_PORT') ?: (ini_get('smtp_port') ?: 'not-configured');
    return "Email verification service is unavailable (SMTP {$smtp}:{$port} failed). Check SMTP credentials/settings and try again.";
}

function respond(bool $success, string $message, array $data = []): never {
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ));
    exit;
}

function debugLog(string $runId, string $hypothesisId, string $location, string $message, array $data = []): void {
    $entry = [
        'sessionId' => '717d92',
        'runId' => $runId,
        'hypothesisId' => $hypothesisId,
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'timestamp' => (int)round(microtime(true) * 1000),
    ];
    @file_put_contents(__DIR__ . '/debug-717d92.log', json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function loadLocalEnv(string $envPath): void {
    if (!file_exists($envPath)) {
        return;
    }
    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        $eqPos = strpos($trimmed, '=');
        if ($eqPos === false) {
            continue;
        }
        $key = trim(substr($trimmed, 0, $eqPos));
        $value = trim(substr($trimmed, $eqPos + 1));
        if ($key === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}


