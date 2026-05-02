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

    <style>
        /* =====================================================
           OTP OVERLAY — sits above the login modal
           ===================================================== */
        .password-overlay {
            display: flex;
            position: fixed; inset: 0; z-index: 300;
            align-items: center; justify-content: center;
            padding: 90px 20px 20px;
            background: rgba(8, 1, 3, 0.78);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            animation: otpFadeIn 0.25s ease;
        }
        @keyframes otpFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .password-card {
            background: linear-gradient(160deg, #6b0d18 0%, #8B0A1E 50%, #7a0c19 100%);
            border-radius: 20px;
            padding: 36px 32px 28px;
            width: 100%;
            max-width: 400px;
            box-shadow:
                0 32px 80px rgba(0,0,0,0.65),
                0 0 0 1px rgba(255,255,255,0.06),
                inset 0 1px 0 rgba(255,255,255,0.1);
            animation: otpSlideUp 0.3s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes otpSlideUp {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Header */
        .password-header { text-align: center; margin-bottom: 24px; }
        .password-icon {
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: rgba(255,255,255,0.85);
            margin: 0 auto 14px;
        }
        .password-header h2 {
            font-family: 'Aclonica', sans-serif;
            font-size: 1.25rem; font-weight: 400;
            letter-spacing: 0.06em; color: #fff; margin-bottom: 6px;
        }
        .password-header p {
            font-size: 0.82rem; color: rgba(255,255,255,0.5);
            line-height: 1.55;
        }

        /* Form fields */
        .password-form { display: flex; flex-direction: column; gap: 16px; margin: 4px 0 22px; }
        .password-field { display: flex; flex-direction: column; gap: 6px; }
        .password-field label {
            font-size: 0.82rem;
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            letter-spacing: 0.04em;
        }
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.4);
            pointer-events: none;
            z-index: 1;
        }
        .input-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.92);
            border: 1.5px solid transparent;
            border-radius: 10px;
            padding: 11px 42px 11px 38px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 0.9rem;
            color: #1a0408;
            outline: none;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        .input-wrap input::placeholder { color: rgba(100,30,30,0.4); }
        .input-wrap input:focus {
            border-color: rgba(194,38,38,0.5);
            box-shadow: 0 0 0 3px rgba(194,38,38,0.15);
            background: #fff;
        }
        .toggle-pw {
            position: absolute; right: 12px;
            background: none; border: none; cursor: pointer;
            color: rgba(100,30,30,0.5); font-size: 0.85rem;
            transition: color 0.2s; padding: 4px;
        }
        .toggle-pw:hover { color: #C22626; }

        /* Error message */
        .password-error-msg {
            display: none;
            align-items: center; gap: 8px;
            background: rgba(194,38,38,0.2);
            border: 1px solid rgba(194,38,38,0.45);
            border-radius: 8px; padding: 10px 14px;
            font-size: 0.83rem; color: #ffaaaa;
            margin-bottom: 14px;
            animation: errorShake 0.35s cubic-bezier(.36,.07,.19,.97) both;
        }
        .password-error-msg.visible { display: flex; }
        .password-error-msg i { font-size: 0.88rem; flex-shrink: 0; }

        /* Success message */
        .password-success-msg {
            display: none;
            align-items: center; gap: 8px;
            background: rgba(34,197,94,0.15);
            border: 1px solid rgba(34,197,94,0.35);
            border-radius: 8px; padding: 10px 14px;
            font-size: 0.83rem; color: #86efac;
            margin-bottom: 14px;
        }
        .password-success-msg.visible { display: flex; }

        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        /* Submit button */
        .password-submit-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%;
            background: linear-gradient(135deg, #C22626 0%, #9B0A1E 100%);
            color: white; border: none; border-radius: 10px;
            padding: 13px 20px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 0.95rem; font-weight: 700;
            letter-spacing: 0.04em; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, filter 0.2s, opacity 0.2s;
            box-shadow: 0 4px 20px rgba(194,38,38,0.4);
        }
        .password-submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(194,38,38,0.55);
            filter: brightness(1.08);
        }
        .password-submit-btn:disabled { opacity: 0.65; cursor: not-allowed; }

        /* Back link */
        .password-back {
            text-align: center; margin-top: 16px;
            font-size: 0.82rem;
        }
        .password-back a {
            color: #ff8080; font-weight: 600; cursor: pointer;
            transition: color 0.2s; text-decoration: none;
        }
        .password-back a:hover { color: #ffaaaa; }

        @media (max-width: 480px) {
            .password-card { padding: 28px 18px 22px; border-radius: 16px; }
        }
    </style>
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

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('mobile-menu');
        const navMenu = document.getElementById('navMenu');
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
        }

        // Password visibility toggle
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Validate passwords match
        function validatePasswords() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('passwordErrorMsg');
            const errorText = document.getElementById('passwordErrorText');

            if (newPassword !== confirmPassword) {
                errorMsg.classList.add('visible');
                errorText.textContent = 'Passwords do not match.';
                return false;
            }

            if (newPassword.length < 8) {
                errorMsg.classList.add('visible');
                errorText.textContent = 'Password must be at least 8 characters long.';
                return false;
            }

            errorMsg.classList.remove('visible');
            return true;
        }
    </script>
</body>
</html>
