// theme-manager.js - Apply saved theme immediately on page load
(function() {
  const savedTheme = localStorage.getItem('rider-theme');
  if (savedTheme === 'light') {
    document.body.classList.add('light-theme');
  } else {
    document.body.classList.remove('light-theme');
  }
})();
