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

  var STAFF_NOTIF_URL = '../staff-handle-notifications.php';

  window.toggleLiveNotifications = function (ev) {
    if (ev) ev.stopPropagation();
    var menu = document.getElementById('liveNotifMenu');
    if (!menu) return;
    var open = menu.classList.toggle('show');
    if (!open) return;
    function onDocClick(e) {
      if (!menu.contains(e.target) && !e.target.closest('.live-notif-trigger')) {
        menu.classList.remove('show');
        document.removeEventListener('click', onDocClick);
      }
    }
    setTimeout(function () {
      document.addEventListener('click', onDocClick);
    }, 0);
  };

  function updateLiveNotifBadge() {
    var unread = document.querySelectorAll('.live-notif-item.unread').length;
    var countEl = document.querySelector('.live-notif-count');
    var dot = document.querySelector('.live-notif-dot');
    if (countEl) {
      countEl.textContent = String(unread);
      countEl.style.display = unread > 0 ? 'inline-flex' : 'none';
    }
    if (dot) dot.style.display = unread > 0 ? 'block' : 'none';
  }

  window.markLiveNotificationRead = function (id, row) {
    fetch(STAFF_NOTIF_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_read', notification_id: id }),
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.success) return;
        var el = row || document.querySelector('.live-notif-item[data-id="' + id + '"]');
        if (el) el.classList.remove('unread');
        updateLiveNotifBadge();
      })
      .catch(function () {});
  };

  window.markAllLiveNotificationsRead = function () {
    fetch(STAFF_NOTIF_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_all_read' }),
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.success) return;
        document.querySelectorAll('.live-notif-item.unread').forEach(function (el) {
          el.classList.remove('unread');
        });
        updateLiveNotifBadge();
      })
      .catch(function () {});
  };
})();
