<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Admin Login — Luke's Seafood</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../account.css">
<link rel="stylesheet" href="login.css">
</head>
<body class="admin-login-page">

    <div class="grain-overlay"></div>

    <!-- PAGE BG -->
    <div class="page-bg">
        <div class="page-bg-blob page-bg-blob--1"></div>
        <div class="page-bg-blob page-bg-blob--2"></div>
    </div>

    <!-- ══ LOGIN CONTAINER ══ -->
    <div class="auth-wrapper">
        <div class="auth-container" id="loginModal">
            <div class="modal-header">
                <div class="modal-fish-icon"><i class="fas fa-shield-halved"></i></div>
                <h2 style="font-family: 'Aclonica', sans-serif;">ADMIN PORTAL</h2>
                <p class="modal-sub">Restricted Administrative Access</p>
            </div>

            <form class="modal-form" id="loginForm" onsubmit="return false;">
                <!-- Error box -->
                <div class="auth-error" id="errorBox" style="display:none; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText">Invalid credentials.</span>
                </div>

                <div class="form-group">
                    <label for="emailInput">Admin Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-user-shield input-icon"></i>
                        <input type="email" id="emailInput" placeholder="admin@example.com" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="passwordInput">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="passwordInput" placeholder="••••••••" required>
                        <button type="button" class="toggle-pw" onclick="togglePw()">
                            <i class="fas fa-eye" id="pwEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <label class="remember-label" style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: rgba(255,255,255,0.7); cursor: pointer;">
                        <input type="checkbox" id="rememberMe" style="accent-color: var(--red);">
                        Remember me
                    </label>
                    <a href="../forgotpassword.php" class="forgot-link" style="color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.85rem;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary" id="loginBtn" onclick="doLogin()">
                    <span>Authorize Login</span>
                    <i class="fas fa-shield-check"></i>
                </button>

                <div class="security-strip" style="margin-top: 30px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 30px; padding: 8px 16px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-lock" style="font-size: 0.7rem; color: #22c55e;"></i>
                    <div class="rider-strip-text" style="font-size: 0.72rem; color: rgba(255,255,255,0.5);">Secure Admin Channel</div>
                </div>
            </form>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>


