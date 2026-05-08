<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Rider Login — Luke's Seafood</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Be Vietnam Pro', sans-serif;
    background: #0d0d0d;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow: hidden;
  }

  /* Animated background blobs */
  .bg-blob {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
    pointer-events: none;
    animation: blob-drift 12s ease-in-out infinite alternate;
  }
  .bg-blob-1 { width: 380px; height: 380px; background: #C22626; top: -100px; left: -100px; animation-delay: 0s; }
  .bg-blob-2 { width: 260px; height: 260px; background: #8B0A1E; bottom: -80px; right: -60px; animation-delay: 4s; }
  .bg-blob-3 { width: 200px; height: 200px; background: #C22626; top: 50%; left: 60%; animation-delay: 8s; }
  @keyframes blob-drift {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(30px, 20px) scale(1.08); }
  }

  /* Phone shell wrapper */
  .phone-shell {
    width: 100%;
    max-width: 390px;
    min-height: 680px;
    background: #141414;
    border-radius: 40px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.06), inset 0 1px 0 rgba(255,255,255,0.08);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
  }

  /* Top bar */
  .login-topbar {
    padding: 24px 28px 0;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .topbar-icon {
    width: 38px; height: 38px;
    border-radius: 12px;
    background: linear-gradient(135deg, #9B0A1E, #C22626);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff;
    box-shadow: 0 4px 14px rgba(194,38,38,0.4);
  }
  .topbar-text { font-family: 'Aclonica', sans-serif; font-size: 1rem; color: #fff; }
  .topbar-sub  { font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 1px; }

  /* Hero section */
  .login-hero {
    padding: 36px 28px 28px;
    text-align: center;
  }
  .hero-icon-wrap {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1a1a1a, #222);
    border: 2px solid rgba(194,38,38,0.4);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px;
    box-shadow: 0 0 0 8px rgba(194,38,38,0.06), 0 8px 28px rgba(0,0,0,0.4);
    animation: icon-pulse 2.5s ease-in-out infinite;
  }
  @keyframes icon-pulse {
    0%,100% { box-shadow: 0 0 0 8px rgba(194,38,38,0.06), 0 8px 28px rgba(0,0,0,0.4); }
    50%      { box-shadow: 0 0 0 14px rgba(194,38,38,0.03), 0 8px 28px rgba(0,0,0,0.4); }
  }
  .hero-icon-wrap i { font-size: 2rem; color: #C22626; }
  .hero-title { font-family: 'Aclonica', sans-serif; font-size: 1.5rem; color: #fff; margin-bottom: 6px; }
  .hero-sub { font-size: 0.8rem; color: rgba(255,255,255,0.4); line-height: 1.5; }

  /* Form */
  .login-form { padding: 0 28px 32px; flex: 1; }

  .error-box {
    display: none;
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 16px;
    font-size: 0.78rem;
    color: #fca5a5;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .error-box.hidden { display: none !important; }

  .field-group { margin-bottom: 14px; }
  .field-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
    margin-bottom: 7px;
  }
  .field-wrap { position: relative; }
  .field-icon {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.25);
    font-size: 0.85rem;
    pointer-events: none;
    transition: color 0.2s;
  }
  .field-input {
    width: 100%;
    padding: 13px 42px 13px 40px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    color: #f0ece6;
    font-family: 'Be Vietnam Pro', sans-serif;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  }
  .field-input:focus {
    border-color: #C22626;
    background: rgba(194,38,38,0.06);
    box-shadow: 0 0 0 3px rgba(194,38,38,0.15);
  }
  .field-input:focus + .field-icon,
  .field-wrap:focus-within .field-icon { color: #C22626; }
  .pw-toggle {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; color: rgba(255,255,255,0.25);
    font-size: 0.82rem; padding: 4px;
    transition: color 0.2s;
  }
  .pw-toggle:hover { color: rgba(255,255,255,0.6); }

  .btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #C22626, #8B0A1E);
    color: #fff;
    border: none;
    border-radius: 14px;
    font-family: 'Be Vietnam Pro', sans-serif;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    margin-top: 20px;
    box-shadow: 0 6px 24px rgba(194,38,38,0.35);
    transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
  }
  .btn-login:hover:not(:disabled) {
    opacity: 0.92;
    transform: translateY(-1px);
    box-shadow: 0 10px 30px rgba(194,38,38,0.45);
  }
  .btn-login:active:not(:disabled) { transform: translateY(0); }
  .btn-login:disabled { opacity: 0.6; cursor: not-allowed; }

  .login-hint {
    text-align: center;
    margin-top: 20px;
    font-size: 0.72rem;
    color: rgba(255,255,255,0.2);
    line-height: 1.6;
  }
  .login-hint i { color: rgba(194,38,38,0.5); }

  /* Rider badge strip */
  .rider-strip {
    margin: 0 28px 24px;
    padding: 12px 16px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    display: flex; align-items: center; gap: 10px;
  }
  .rider-strip-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; animation: dot-blink 1.5s infinite; }
  @keyframes dot-blink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }
  .rider-strip-text { font-size: 0.72rem; color: rgba(255,255,255,0.35); }
  .rider-strip-text strong { color: rgba(255,255,255,0.6); }

  /* Spinner */
  @keyframes spin { to { transform: rotate(360deg); } }
  .fa-spin-fast { animation: spin 0.6s linear infinite; }
</style>
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

<script>
  // Redirect if already logged in
  (async () => {
    try {
      const res  = await fetch('rider-auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'check_session' })
      });
      const data = await res.json();
      if (data.success) window.location.href = 'orders.php';
    } catch (e) {}
  })();

  function togglePw() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fas fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fas fa-eye';
    }
  }

  function showError(msg) {
    const box  = document.getElementById('errorBox');
    document.getElementById('errorText').textContent = msg;
    box.classList.remove('hidden');
  }

  function clearError() {
    document.getElementById('errorBox').classList.add('hidden');
  }

  async function doLogin() {
    const email    = document.getElementById('emailInput').value.trim();
    const password = document.getElementById('passwordInput').value;
    const btn      = document.getElementById('loginBtn');

    clearError();

    if (!email || !password) {
      showError('Please enter your email and password.');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin-fast"></i> Signing in…';

    try {
      const res  = await fetch('rider-auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'login', email, password })
      });
      const data = await res.json();

      if (data.success) {
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Welcome, ' + data.name + '!';
        setTimeout(() => { window.location.href = 'orders.php'; }, 700);
      } else {
        showError(data.message || 'Login failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In';
      }
    } catch (e) {
      showError('Network error. Please check your connection.');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In';
    }
  }

  // Allow Enter key to submit
  document.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });
</script>
</body>
</html>
