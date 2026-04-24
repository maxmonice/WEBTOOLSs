<?php
function loadAccountEnv(string $envPath): void {
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
loadAccountEnv(__DIR__ . '/.env');
$googleClientIdForJs = getenv('GOOGLE_CLIENT_ID') ?: '694050007372-2crn9q3ek8jav88iduut5ddf50ecgj0a.apps.googleusercontent.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Luke's Seafood Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="account.css">

    <style>
        /* =====================================================
           OTP OVERLAY — sits above the login modal
           ===================================================== */
        .otp-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 300;
            align-items: center; justify-content: center;
            padding: 90px 20px 20px;
            background: rgba(8, 1, 3, 0.78);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .otp-overlay.active {
            display: flex;
            animation: otpFadeIn 0.25s ease;
        }
        @keyframes otpFadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Inherits .modal card styles from account.css */
        .otp-card {
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
        .otp-header { text-align: center; margin-bottom: 24px; }
        .otp-icon {
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: rgba(255,255,255,0.85);
            margin: 0 auto 14px;
        }
        .otp-header h2 {
            font-family: 'Aclonica', sans-serif;
            font-size: 1.25rem; font-weight: 400;
            letter-spacing: 0.06em; color: #fff; margin-bottom: 6px;
        }
        .otp-header p {
            font-size: 0.82rem; color: rgba(255,255,255,0.5);
            line-height: 1.55;
        }
        .otp-header p strong { color: rgba(255,255,255,0.8); font-weight: 700; }

        /* 6-digit boxes */
        .otp-digits {
            display: flex; gap: 10px; justify-content: center;
            margin: 4px 0 22px;
        }
        .otp-digit {
            width: 46px; height: 54px;
            background: rgba(255,255,255,0.92);
            border: 1.5px solid transparent;
            border-radius: 10px;
            font-size: 1.55rem; font-weight: 800;
            color: #1a0408; text-align: center;
            outline: none; caret-color: transparent;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            font-family: 'Be Vietnam Pro', sans-serif;
        }
        .otp-digit:focus {
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.12);
            background: #fff;
        }
        .otp-digit.filled { border-color: rgba(255,255,255,0.35); }
        .otp-digit.error-shake {
            border-color: rgba(255,100,100,0.7) !important;
            box-shadow: 0 0 0 3px rgba(255,80,80,0.18) !important;
            animation: digitShake 0.35s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes digitShake {
            0%,100% { transform: translateX(0); }
            20%     { transform: translateX(-5px); }
            40%     { transform: translateX(5px); }
            60%     { transform: translateX(-3px); }
            80%     { transform: translateX(3px); }
        }

        /* OTP error message */
        .otp-error-msg {
            display: none;
            align-items: center; gap: 8px;
            background: rgba(194,38,38,0.2);
            border: 1px solid rgba(194,38,38,0.45);
            border-radius: 8px; padding: 10px 14px;
            font-size: 0.83rem; color: #ffaaaa;
            margin-bottom: 14px;
            animation: errorShake 0.35s cubic-bezier(.36,.07,.19,.97) both;
        }
        .otp-error-msg.visible { display: flex; }
        .otp-error-msg i { font-size: 0.88rem; flex-shrink: 0; }

        /* OTP success message */
        .otp-success-msg {
            display: none;
            align-items: center; gap: 8px;
            background: rgba(34,197,94,0.15);
            border: 1px solid rgba(34,197,94,0.35);
            border-radius: 8px; padding: 10px 14px;
            font-size: 0.83rem; color: #86efac;
            margin-bottom: 14px;
        }
        .otp-success-msg.visible { display: flex; }

        /* Verify button — reuses .btn-primary styles from account.css */
        .otp-verify-btn {
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
        .otp-verify-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(194,38,38,0.55);
            filter: brightness(1.08);
        }
        .otp-verify-btn:disabled { opacity: 0.65; cursor: not-allowed; }

        /* Resend row */
        .otp-resend {
            text-align: center; margin-top: 16px;
            font-size: 0.82rem; color: rgba(255,255,255,0.45);
        }
        .otp-resend a {
            color: #ff8080; font-weight: 600; cursor: pointer;
            transition: color 0.2s; text-decoration: none;
        }
        .otp-resend a:hover { color: #ffaaaa; }
        .otp-resend a.disabled { color: rgba(255,255,255,0.3); pointer-events: none; }
        #otpCountdown { color: rgba(255,255,255,0.4); }

        /* Back button */
        .otp-back-btn {
            display: block; width: 100%; margin-top: 12px;
            background: none; border: none; padding: 8px;
            color: rgba(255,255,255,0.38); font-size: 0.8rem;
            font-family: 'Be Vietnam Pro', sans-serif;
            cursor: pointer; transition: color 0.2s; text-align: center;
        }
        .otp-back-btn:hover { color: rgba(255,255,255,0.65); }

        @media (max-width: 480px) {
            .otp-card { padding: 28px 18px 22px; border-radius: 16px; }
            .otp-digit { width: 40px; height: 48px; font-size: 1.3rem; }
            .otp-digits { gap: 7px; }
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
                <a href="account.php" class="nav-account-icon active" title="Account">
                    <i class="fas fa-user-circle"></i>
                </a>
            </nav>
        </div>
    </header>

    <!-- PAGE BG -->
    <div class="page-bg">
        <div class="page-bg-blob page-bg-blob--1"></div>
        <div class="page-bg-blob page-bg-blob--2"></div>
    </div>

    <!-- ══ LOGIN / SIGNUP MODALS ══ -->
    <div class="modal-overlay" id="modalOverlay">

        <!-- LOGIN MODAL -->
        <div class="modal" id="loginModal">
            <div class="modal-header">
                <div class="modal-fish-icon"><i class="fas fa-fish"></i></div>
                <h2>WELCOME BACK!</h2>
                <p class="modal-sub">Sign in to your account</p>
            </div>

            <form class="modal-form" id="loginForm" onsubmit="return false;">
                <div class="form-group">
                    <label for="loginEmail">Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="loginEmail" placeholder="you@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="loginPassword" placeholder="••••••••" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('loginPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="remember-label">
                        <input type="checkbox" id="rememberMe">
                        <span class="custom-check"></span>
                        Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary" id="loginSubmitBtn">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right"></i>
                </button>

                <div class="divider"><span>or</span></div>

                <button type="button" class="btn-google">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" width="20">
                    Sign in with Google
                </button>

                <button type="button" class="btn-facebook">
                    <i class="fab fa-facebook"></i>
                    Sign in with Facebook
                </button>
            </form>

            <p class="modal-switch">
                Don't have an account?
                <a href="#" onclick="switchModal('signup')">Sign up for free!</a>
            </p>
        </div>

        <!-- SIGNUP MODAL -->
        <div class="modal hidden" id="signupModal">
            <div class="modal-header">
                <div class="modal-fish-icon"><i class="fas fa-fish"></i></div>
                <h2>CREATE YOUR ACCOUNT</h2>
                <p class="modal-sub">Join Luke's Seafood today</p>
            </div>

            <form class="modal-form" id="signupForm" onsubmit="return false;">
                <div class="form-group">
                    <label for="signupName">Name</label>
                    <div class="input-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="signupName" placeholder="Your full name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="signupEmail">Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="signupEmail" placeholder="you@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="signupPassword">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="signupPassword" placeholder="Create a password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('signupPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="signupSubmitBtn">
                    <span>Create Account</span>
                    <i class="fas fa-arrow-right"></i>
                </button>

                <div class="divider"><span>or</span></div>

                <button type="button" class="btn-google">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" width="20">
                    Sign up with Google
                </button>

                <button type="button" class="btn-facebook">
                    <i class="fab fa-facebook"></i>
                    Sign up with Facebook
                </button>
            </form>

            <p class="modal-switch">
                Already have an account?
                <a href="#" onclick="switchModal('login')">Sign in here!</a>
            </p>
        </div>

    </div><!-- /modal-overlay -->

    <!-- ══ OTP VERIFICATION OVERLAY ══ -->
    <div class="otp-overlay" id="otpOverlay">
        <div class="otp-card">

            <div class="otp-header">
                <div class="otp-icon"><i class="fas fa-envelope-open-text"></i></div>
                <h2>CHECK YOUR EMAIL</h2>
                <p>We sent a 6-digit code to<br><strong id="otpEmailHint"></strong></p>
            </div>

            <!-- 6 digit boxes -->
            <div class="otp-digits" id="otpDigits">
                <input class="otp-digit" type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
                <input class="otp-digit" type="text" maxlength="1" inputmode="numeric">
                <input class="otp-digit" type="text" maxlength="1" inputmode="numeric">
                <input class="otp-digit" type="text" maxlength="1" inputmode="numeric">
                <input class="otp-digit" type="text" maxlength="1" inputmode="numeric">
                <input class="otp-digit" type="text" maxlength="1" inputmode="numeric">
            </div>

            <!-- Error / success messages -->
            <div class="otp-error-msg" id="otpErrorMsg">
                <i class="fas fa-exclamation-circle"></i>
                <span id="otpErrorText"></span>
            </div>
            <div class="otp-success-msg" id="otpSuccessMsg">
                <i class="fas fa-check-circle"></i>
                <span id="otpSuccessText"></span>
            </div>

            <button class="otp-verify-btn" id="otpVerifyBtn" onclick="submitOtp()">
                <span>Verify Code</span>
                <i class="fas fa-arrow-right"></i>
            </button>

            <div class="otp-resend">
                Didn't receive it? &nbsp;
                <a href="#" id="otpResendLink" onclick="resendOtp(); return false;">Resend code</a>
                <span id="otpCountdown"></span>
            </div>

            <button class="otp-back-btn" id="otpBackBtn" onclick="hideOtpOverlay()">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <script>
        window.APP_CONFIG = window.APP_CONFIG || {};
        window.APP_CONFIG.googleClientId = <?php echo json_encode($googleClientIdForJs, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="account.js"></script>
</body>
</html>