<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Rider Login — Luke's Seafood</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="login.css">
</head>
<body>

<div class="bg-blob bg-blob-1"></div>
<div class="bg-blob bg-blob-2"></div>
<div class="bg-blob bg-blob-3"></div>

<div class="phone-shell">

  <!-- Top bar -->
  <div class="login-topbar">
    <div class="topbar-icon"><i class="fas fa-motorcycle"></i></div>
    <div>
      <div class="topbar-text">Luke's Seafood</div>
      <div class="topbar-sub">Rider Portal</div>
    </div>
  </div>

  <!-- Hero -->
  <div class="login-hero">
    <div class="hero-icon-wrap">
      <i class="fas fa-motorcycle"></i>
    </div>
    <div class="hero-title">Rider Sign In</div>
    <div class="hero-sub">Log in to access your deliveries,<br>map, and earnings dashboard.</div>
  </div>

  <!-- Form -->
  <div class="login-form">

    <!-- Error box -->
    <div class="error-box hidden" id="errorBox">
      <i class="fas fa-exclamation-circle"></i>
      <span id="errorText">Invalid credentials.</span>
    </div>

    <div class="field-group">
      <label class="field-label">Email Address</label>
      <div class="field-wrap">
        <input type="email" id="emailInput" class="field-input" placeholder="rider@example.com" autocomplete="username">
        <i class="fas fa-envelope field-icon"></i>
      </div>
    </div>

    <div class="field-group">
      <label class="field-label">Password</label>
      <div class="field-wrap">
        <input type="password" id="passwordInput" class="field-input" placeholder="••••••••••" autocomplete="current-password">
        <i class="fas fa-lock field-icon"></i>
        <button type="button" class="pw-toggle" id="pwToggle" onclick="togglePw()">
          <i class="fas fa-eye" id="pwEyeIcon"></i>
        </button>
      </div>
    </div>

    <button class="btn-login" id="loginBtn" onclick="doLogin()">
      <i class="fas fa-arrow-right-to-bracket"></i> Sign In
    </button>

    <div class="login-hint">
      <i class="fas fa-shield-halved"></i>
      Access restricted to authorized riders only.
    </div>

  </div>

  <!-- Status strip -->
  <div class="rider-strip">
    <div class="rider-strip-dot"></div>
    <div class="rider-strip-text">System <strong>Online</strong> · Luke's Seafood Delivery</div>
  </div>

</div>

<script src="login.js?v=<?= time() ?>"></script>
</body>
</html>
