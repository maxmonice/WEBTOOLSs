(function () {
  'use strict';

  var HANDLE_URL = (function () {
    if (typeof window !== 'undefined' && window.ADMIN_NOTIF_HANDLE_URL) {
      return window.ADMIN_NOTIF_HANDLE_URL;
    }
    var path =
      typeof window !== 'undefined' && window.location && window.location.pathname
        ? window.location.pathname
        : '';
    var idx = path.indexOf('/adminSide/');
    if (idx !== -1) {
      return path.substring(0, idx) + '/admin-handle-notifications.php';
    }
    if (path.indexOf('adminSide/') !== -1) {
      var j = path.indexOf('adminSide/');
      return path.substring(0, j) + 'admin-handle-notifications.php';
    }
    var last = path.lastIndexOf('/');
    if (last !== -1) {
      return path.substring(0, last + 1) + 'admin-handle-notifications.php';
    }
    return 'admin-handle-notifications.php';
  })();

  var docCloseHandler = null;

  function getTrigger() {
    return document.querySelector('.live-notif-trigger');
  }

  function getMenu() {
    return document.getElementById('liveNotifMenu');
  }

  function detachDocClose() {
    if (docCloseHandler) {
      document.removeEventListener('click', docCloseHandler, true);
      docCloseHandler = null;
    }
  }

  window.toggleLiveNotifications = function (ev) {
    if (ev) {
      ev.preventDefault();
      ev.stopPropagation();
    }
    var menu = getMenu();
    var trigger = getTrigger();
    if (!menu) return;

    detachDocClose();

    var open = !menu.classList.contains('show');
    menu.classList.toggle('show', open);
    if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (!open) return;

    docCloseHandler = function (e) {
      if (!menu.contains(e.target) && !(e.target && e.target.closest && e.target.closest('.live-notif-wrap'))) {
        menu.classList.remove('show');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        detachDocClose();
      }
    };
    setTimeout(function () {
      document.addEventListener('click', docCloseHandler, true);
    }, 50);
  };

  function updateLiveNotifBadge() {
    var unread = document.querySelectorAll('.live-notif-item.unread').length;
    var countEl = document.querySelector('.live-notif-count');
    var dot = document.querySelector('.live-notif-dot');
    var trigger = getTrigger();
    if (countEl) {
      countEl.textContent = String(unread);
      countEl.style.display = unread > 0 ? 'inline-flex' : 'none';
    }
    if (dot) dot.style.display = unread > 0 ? 'block' : 'none';
    if (trigger && !countEl && unread === 0) {
      if (dot) dot.style.display = 'none';
    }
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
        if (!data || !data.success) return;
        var el = row || document.querySelector('.live-notif-item[data-id="' + id + '"]');
        if (el) el.classList.remove('unread');
        updateLiveNotifBadge();
      })
      .catch(function () {});
  };

  window.markAllLiveNotificationsRead = function (ev) {
    if (ev) ev.stopPropagation();
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
        if (!data || !data.success) return;
        document.querySelectorAll('.live-notif-item.unread').forEach(function (el) {
          el.classList.remove('unread');
        });
        updateLiveNotifBadge();
      })
      .catch(function () {});
  };

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var menu = getMenu();
    var trigger = getTrigger();
    if (menu && menu.classList.contains('show')) {
      menu.classList.remove('show');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      detachDocClose();
    }
  });
})();
