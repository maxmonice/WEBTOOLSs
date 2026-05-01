// =====================================================
// ACCOUNT PAGE JAVASCRIPT
// =====================================================

function toggleSwitch(el) {
  el.classList.toggle('on');
}

function confirmLogout() {
  if (confirm('Are you sure you want to log out?')) {
    window.location.href = 'login.php';
  }
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

function updateClock() {
  const now = new Date();
  document.querySelector('.time').textContent =
    now.getHours().toString().padStart(2, '0') + ':' +
    now.getMinutes().toString().padStart(2, '0');
}
updateClock();
setInterval(updateClock, 60000);