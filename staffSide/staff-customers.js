(function () {
  'use strict';
  var overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    var icon = document.querySelector('.sidebar-toggle i');
    if (icon) icon.className = 'fa-solid fa-xmark';
  }
  function closeSidebarMenu() {
    document.getElementById('sidebar').classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    var icon = document.querySelector('.sidebar-toggle i');
    if (icon) icon.className = 'fa-solid fa-bars';
  }
  window.toggleSidebar = function () {
    document.getElementById('sidebar').classList.contains('open') ? closeSidebarMenu() : openSidebar();
  };
  overlay.addEventListener('click', closeSidebarMenu);
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.nav-item:not(.locked)').forEach(function (item) {
      item.addEventListener('click', function () { if (window.innerWidth <= 900) closeSidebarMenu(); });
    });
  });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) closeSidebarMenu(); });
})();