// =====================================================
// ACCOUNT PAGE JAVASCRIPT
// =====================================================

function toggleSwitch(el) {
  const isOn = el.classList.toggle('on');
  
  if (el.id === 'online-toggle') {
    const badgeText = document.getElementById('header-status-text');
    const badgeDot = document.getElementById('header-status-dot');
    const subLabel = document.getElementById('online-status-sub');
    
    if (isOn) {
      if (badgeText) badgeText.textContent = 'Online';
      if (badgeDot) badgeDot.style.background = '#22c55e';
      if (subLabel) subLabel.textContent = 'You are currently online';
    } else {
      if (badgeText) badgeText.textContent = 'Offline';
      if (badgeDot) badgeDot.style.background = '#666';
      if (subLabel) subLabel.textContent = 'You are currently offline';
    }
  }
}

function confirmLogout() { doLogout(); }

async function doLogout() {
  try {
    await fetch('rider-auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ action: 'logout' })
    });
  } catch (e) {}
  window.location.href = 'login.php';
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

// Theme Toggle Logic
function toggleTheme() {
  const isLight = document.body.classList.toggle('light-theme');
  const toggleBtn = document.getElementById('theme-toggle');
  
  if (isLight) {
    toggleBtn.classList.remove('on');
    localStorage.setItem('rider-theme', 'light');
  } else {
    toggleBtn.classList.add('on');
    localStorage.setItem('rider-theme', 'dark');
  }
}

// Apply theme on load for Account page specifically
(function() {
  const savedTheme = localStorage.getItem('rider-theme');
  const toggleBtn = document.getElementById('theme-toggle');
  if (savedTheme === 'light') {
    document.body.classList.add('light-theme');
    if (toggleBtn) toggleBtn.classList.remove('on');
  } else {
    document.body.classList.remove('light-theme');
    if (toggleBtn) toggleBtn.classList.add('on');
  }
})();

function updateClock() {
  const now = new Date();
  document.querySelector('.time').textContent =
    now.getHours().toString().padStart(2, '0') + ':' +
    now.getMinutes().toString().padStart(2, '0');
}
updateClock();
setInterval(updateClock, 60000);