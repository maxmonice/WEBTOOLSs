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