(function () {
  'use strict';

  // Create overlay backdrop element once
  var overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  function openSidebar() {
    var sidebar = document.getElementById('sidebar');
    sidebar.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    updateIcon(true);
  }

  function closeSidebar() {
    var sidebar = document.getElementById('sidebar');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    updateIcon(false);
  }

  function updateIcon(isOpen) {
    var btn = document.querySelector('.sidebar-toggle i');
    if (!btn) return;
    btn.className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
  }

  window.toggleSidebar = function () {
    var sidebar = document.getElementById('sidebar');
    if (sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  };

  // Close on overlay click
  overlay.addEventListener('click', closeSidebar);

  // Close on nav-item click (mobile navigation)
  document.addEventListener('DOMContentLoaded', function () {
    var navItems = document.querySelectorAll('.nav-item:not(.locked)');
    navItems.forEach(function (item) {
      item.addEventListener('click', function () {
        if (window.innerWidth <= 900) closeSidebar();
      });
    });
  });

  // Close sidebar on resize back to desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) {
      closeSidebar();
    }
  });
})();