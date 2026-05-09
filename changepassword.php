<?php
/**
 * Change Password - Luke's Seafood Trading
 * Handles password reset via valid token
 */

require_once 'db.php';

$error = '';
$success = false;
$token_valid = false;
$reset_email = '';
$token = $_GET['token'] ?? '';

// Validate token
if (empty($token)) {
    $error = 'Invalid or missing reset token. Please request a new password reset.';
} else {
    try {
        $conn = getDB();
        
        // Debug: Log what we're looking for
        error_log('=== TOKEN VALIDATION ===');
        error_log('Token: ' . substr($token, 0, 8) . '...');
        error_log('Token length: ' . strlen($token));
        
        // Check if reset_token column exists
        try {
            $stmt = $conn->prepare("SELECT email, name, reset_token, token_expiry FROM users WHERE reset_token = ?");
            $stmt->execute([$token]);
            $token_result = $stmt->fetchAll();
            
            error_log('Found matching tokens: ' . count($token_result));
            
            if (count($token_result) > 0) {
                $user = $token_result[0];
                error_log('Token user email: ' . $user['email']);
                error_log('Token expiry: ' . $user['token_expiry']);
                error_log('Current time: ' . date('Y-m-d H:i:s'));
                
                // Check expiry manually
                $expiry = strtotime($user['token_expiry']);
                $now = time();
                error_log('Expiry time (unix): ' . $expiry);
                error_log('Current time (unix): ' . $now);
                error_log('Expired: ' . ($now > $expiry ? 'YES' : 'NO'));
                
                if ($now <= $expiry) {
                    $token_valid = true;
                    $reset_email = $user['email'];
                    error_log('Token VALID');
                } else {
                    $error = 'This password reset link has expired. Please request a new one.';
                    error_log('Token EXPIRED');
                }
            } else {
                $error = 'This password reset link is invalid. Please request a new one.';
                error_log('Token NOT FOUND in database');
                
                // Debug: Check if ANY tokens exist
                $debug_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE reset_token IS NOT NULL");
                $debug_stmt->execute();
                $debug = $debug_stmt->fetchAll();
                error_log('Total tokens in DB: ' . ($debug[0]['cnt'] ?? 0));
            }
            error_log('=== END ===');
        } catch (Exception $col_error) {
            // Column might not exist
            if (strpos($col_error->getMessage(), 'reset_token') !== false) {
                $error = 'Database not yet configured. Run this SQL in phpMyAdmin:<br><br>
                    <code style="background: rgba(0,0,0,0.3); padding: 5px; border-radius: 3px; display: block; margin: 10px 0;">
                    ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL;<br>
                    ALTER TABLE users ADD COLUMN token_expiry DATETIME DEFAULT NULL;
                    </code>';
                error_log('Missing reset_token column');
            } else {
                throw $col_error;
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log('Token validation error: ' . $e->getMessage());
    }
}

// Handle password submission
if ($token_valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $conn = getDB();
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $update = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
            $update->execute([$password_hash, $reset_email]);
            
            $success = true;
            // Redirect to account page after 2 seconds
            header('Refresh: 2; url=account.php');
        } catch (Exception $e) {
            $error = 'Error resetting password. Please try again.';
            error_log('Password reset error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Luke's Seafood Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="account.css">

    <link rel="stylesheet" href="changepassword.css">
</head>
<body>

    <div class="grain-overlay"></div>

    <header>
        <div class="container header-container">
            <div class="logo">Luke's Seafood Trading</div>
            <div class="menu-toggle" id="mobile-menu"><i class="fa-solid fa-bars"></i></div>
            <nav class="nav-menu" id="navMenu">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="bookbar.php">Book Bar</a>
                <a href="gallery.php">Gallery</a>
                <a href="aboutUs.php">About Us</a>
                <a href="account.php" class="nav-account-icon" title="Account">
                    <i class="fas fa-user-circle"></i>
                </a>
            </nav>
        </div>
    </header>

    <div class="page-bg">
        <div class="page-bg-blob page-bg-blob--1"></div>
        <div class="page-bg-blob page-bg-blob--2"></div>
    </div>

    <!-- ══ PASSWORD CHANGE OVERLAY ══ -->
    <div class="password-overlay">
        <div class="password-card">

            <div class="password-header">
                <div class="password-icon"><i class="fas fa-lock"></i></div>
                <h2>SET NEW PASSWORD</h2>
                <p>Create a secure password for your account</p>
            </div>

            <!-- Error message -->
            <div class="password-error-msg" id="passwordErrorMsg">
                <i class="fas fa-exclamation-circle"></i>
                <span id="passwordErrorText"></span>
            </div>

            <!-- Success message -->
            <div class="password-success-msg" id="passwordSuccessMsg">
                <i class="fas fa-check-circle"></i>
                <span id="passwordSuccessText"></span>
            </div>

            <?php if (!$token_valid && !$success): ?>
                <div style="display: flex; align-items: center; gap: 8px; background: rgba(194,38,38,0.2); border: 1px solid rgba(194,38,38,0.45); border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #ffaaaa; margin-bottom: 14px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <div style="text-align: center;">
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-bottom: 16px;">
                        Please request a new password reset.
                    </p>
                    <a href="forgotpassword.php" style="display: inline-block; background: #C22626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 600;">
                        Request Reset Link
                    </a>
                </div>
            <?php elseif ($success): ?>
                <div style="display: flex; align-items: center; gap: 8px; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.35); border-radius: 8px; padding: 10px 14px; font-size: 0.83rem; color: #86efac; margin-bottom: 14px;">
                    <i class="fas fa-check-circle"></i>
                    <span>Password changed successfully! Redirecting to account...</span>
                </div>
            <?php else: ?>
                <!-- PASSWORD FORM -->
                <form class="password-form" method="POST" action="" id="passwordForm" onsubmit="return validatePasswords()">
                    <div class="password-field">
                        <label for="new_password">New Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="new_password" name="new_password" placeholder="Create a strong password" required minlength="8">
                            <button type="button" class="toggle-pw" onclick="togglePasswordVisibility('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-field">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required minlength="8">
                            <button type="button" class="toggle-pw" onclick="togglePasswordVisibility('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="password-submit-btn">
                        <span>Change Password</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="password-back">
                    <a href="account.php">← Back to Account</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="changepassword.js?v=<?= time() ?>"></script>
</body>
</html>
