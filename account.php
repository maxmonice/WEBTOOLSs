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
$facebookAppIdForJs = getenv('FACEBOOK_APP_ID') ?: '1282425887392045';
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
                    <a href="forgotpassword.php" class="forgot-link">Forgot Password?</a>
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
                <div class="form-group">
                    <label for="signupConfirmPassword">Confirm Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="signupConfirmPassword" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('signupConfirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchError" style="color: #ff6b6b; font-size: 0.85rem; margin-top: 5px; display: none;">
                        <i class="fas fa-exclamation-circle"></i> Passwords do not match
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
        window.APP_CONFIG.facebookAppId = <?php echo json_encode($facebookAppIdForJs, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="account.js?v=<?= time() ?>"></script>
</body>
</html>