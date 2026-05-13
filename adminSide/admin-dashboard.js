(function () {
  'use strict';

  var HANDLE_URL = '../admin-handle-notifications.php';

  window.toggleSidebar = function () {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('open');
  };

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
    fetch(HANDLE_URL, {
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
    fetch(HANDLE_URL, {
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
