<?php
/**
 * Forgot Password - Luke's Seafood Trading
 * Handles password reset requests via email
 */

// Include database connection and mail
require_once 'db.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load environment variables
function loadForgotPasswordEnv(string $envPath): void {
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
        }
    }
}
loadForgotPasswordEnv(__DIR__ . '/.env');

// Function to send password reset email (same as OTP email system)
function sendPasswordResetEmail($recipient_email, $recipient_name, $reset_token) {
    $firstName = explode(' ', trim($recipient_name))[0];
    $reset_url = 'http://' . $_SERVER['HTTP_HOST'] . '/FINAL/WEBTOOLSs/forgotpassword.php?token=' . $reset_token;
    $subject = "Reset Your Password - Luke's Seafood Trading";

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
              We received a request to reset your password. Click the button below to create a new password.
              This link expires in <strong style='color:#fff;'>1 hour</strong>.
            </p>
            <!-- Reset Button -->
            <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:28px;'>
              <tr><td align='center'>
                <a href='{$reset_url}' style='display:inline-block;background:linear-gradient(135deg,#C22626 0%,#9B0A1E 100%);
                   color:#fff;padding:14px 32px;text-decoration:none;border-radius:10px;
                   font-weight:bold;font-size:0.95rem;letter-spacing:0.03em;'>
                  Reset Password
                </a>
              </td></tr>
            </table>
            <p style='color:rgba(255,255,255,0.35);font-size:0.78rem;margin:0;line-height:1.6;'>
              If you didn&#39;t request this, please ignore this email — your account is safe.
              <br><br>
              Or copy this link:<br>
              <code style='color:rgba(255,255,255,0.5);word-break:break-all;'>{$reset_url}</code>
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

    // Use OTP SMTP settings (same as OTP system)
    $smtpHost = getenv('OTP_SMTP_HOST') ?: (ini_get('SMTP') ?: 'localhost');
    $smtpPort = (int)(getenv('OTP_SMTP_PORT') ?: (ini_get('smtp_port') ?: 25));
    $smtpUser = getenv('OTP_SMTP_USER') ?: '';
    $smtpPass = getenv('OTP_SMTP_PASS') ?: '';
    $smtpSecure = strtolower((string)(getenv('OTP_SMTP_SECURE') ?: ''));
    $fromEmail = getenv('OTP_FROM_EMAIL') ?: 'noreply@lukesseafood.com';
    $fromName = getenv('OTP_FROM_NAME') ?: "Luke's Seafood Trading";

    error_log('=== PASSWORD RESET EMAIL ===');
    error_log('To: ' . $recipient_email);
    error_log('SMTP Host: ' . $smtpHost);
    error_log('SMTP Port: ' . $smtpPort);
    error_log('Has credentials: ' . (!empty($smtpUser) ? 'Yes' : 'No'));
    
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        error_log('ERROR: PHPMailer not available');
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
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = "Reset your password here: {$reset_url}";

        $result = $mail->send();
        error_log('Send result: ' . ($result ? 'SUCCESS' : 'FAILED - ' . $mail->ErrorInfo));
        error_log('=== END ===');
        
        return (bool)$result;
    } catch (\Throwable $e) {
        error_log('Exception: ' . $e->getMessage());
        error_log('=== END ===');
        return false;
    }
}

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists in database
        try {
            $conn = getDB();
            
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $result = $stmt->fetchAll();
            
            if (count($result) > 0) {
                // User found - generate reset token
                $user = $result[0];
                $reset_token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Try to store reset token in database (won't fail if columns don't exist)
                try {
                    $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
                    $update_stmt->execute([$reset_token, $token_expiry, $email]);
                } catch (Exception $e) {
                    // Column doesn't exist yet - just log it
                    error_log('Warning: reset_token column not found. Please run the migration SQL.');
                }
                
                // Send password reset email (using same SMTP as OTP)
                $email_sent = sendPasswordResetEmail($email, $user['name'], $reset_token);
                
                $success = true;
                if ($email_sent) {
                    $message = 'Password reset link has been sent to your email. Please check your inbox and spam folder.';
                } else {
                    $message = 'We\'ve processed your request but email sending failed. Please try again or contact support.';
                }
            } else {
                // For security, don't reveal if email exists or not
                $success = true;
                $message = 'If an account with that email exists, we\'ve sent a password reset link to it.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            // Log the error for debugging
            error_log('Forgot Password Error: ' . $e->getMessage());
        }
    }
}

// Handle password reset via token
$reset_mode = !empty($_GET['token']);

if ($reset_mode) {
    // Redirect to changepassword.php with token
    $token = $_GET['token'] ?? '';
    header('Location: changepassword.php?token=' . urlencode($token));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Luke's Seafood Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="account.css">
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

    <div class="modal-overlay" id="modalOverlay" style="display: flex; padding-top: 90px;">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <div class="modal-fish-icon"><i class="fas fa-key"></i></div>
                <h2>RESET PASSWORD</h2>
                <p class="modal-sub">Enter your email to receive a reset link</p>
            </div>

            <?php if ($success): ?>
                <div style="background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.35); border-radius: 8px; padding: 12px 14px; font-size: 0.9rem; color: #86efac; margin-bottom: 16px;">
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <i class="fas fa-check-circle" style="flex-shrink: 0; margin-top: 2px;"></i>
                        <span>Success!</span>
                    </div>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="display: flex; align-items: center; gap: 8px; background: rgba(194,38,38,0.18); border: 1px solid rgba(194,38,38,0.45); border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; color: #ffaaaa; margin-bottom: 16px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- EMAIL REQUEST FORM -->
            <form class="modal-form" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Send Reset Link</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <p class="modal-switch" style="margin-top: 16px;">
                Remember your password? <a href="account.php">Back to login</a>
            </p>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('mobile-menu');
        const navMenu = document.getElementById('navMenu');
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
        }
    </script>
</body>
</html>
