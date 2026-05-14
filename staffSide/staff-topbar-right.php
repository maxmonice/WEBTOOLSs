<?php
declare(strict_types=1);

require_once __DIR__ . '/../Notifications.php';

$__staffTopbarName = isset($staffName)
    ? (string)$staffName
    : htmlspecialchars($_SESSION['user_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8');
$__staffTopbarFromRoot = !empty($staffTopbarFromRoot);
$__staffNotifUrl = $__staffTopbarFromRoot ? 'staff-handle-notifications.php' : '../staff-handle-notifications.php';
$__staffUserViewHref = $__staffTopbarFromRoot ? 'account-dashboard.php?user_view=true' : '../account-dashboard.php?user_view=true';
$__staffTopbarInitial = strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1));
$__staffUserId = (int)($_SESSION['user_id'] ?? 0);
$__staffNotifications = [];
$__staffUnreadCount = 0;

if (isset($pdo) && $pdo instanceof PDO && $__staffUserId > 0) {
    $notifications = $notifications ?? new Notifications($pdo);
    $__staffNotifications = $userNotifications ?? $notifications->getForUser('staff', $__staffUserId, 8);
    $__staffUnreadCount = isset($unreadCount)
        ? (int)$unreadCount
        : $notifications->getUnreadCount('staff', $__staffUserId);
}
?>
<style>
.live-notif-wrap { position: relative; }
.live-notif-menu {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: min(340px, 86vw);
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 12px;
  box-shadow: 0 18px 50px rgba(0,0,0,0.35);
  z-index: 1000;
  display: none;
  overflow: hidden;
}
.live-notif-menu.show { display: block; }
.live-notif-header {
  padding: 12px 14px;
  border-bottom: 1px solid var(--line-w);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.live-notif-header h4 { margin: 0; font-size: 0.92rem; color: #fff; }
.live-notif-mark-all {
  background: transparent;
  border: none;
  color: var(--red);
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 700;
}
.live-notif-list { max-height: 320px; overflow-y: auto; }
.live-notif-empty { padding: 22px; text-align: center; color: var(--muted); font-size: 0.85rem; }
.live-notif-item { padding: 10px 14px; border-bottom: 1px solid var(--line-w); cursor: pointer; }
.live-notif-item:last-child { border-bottom: none; }
.live-notif-item:hover { background: rgba(255,255,255,0.04); }
.live-notif-item.unread { background: rgba(52,152,219,0.08); border-left: 3px solid #3498db; }
.live-notif-row { display: flex; gap: 10px; align-items: flex-start; }
.live-notif-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.live-notif-title { font-size: 0.84rem; font-weight: 600; color: #fff; margin-bottom: 3px; }
.live-notif-msg { font-size: 0.76rem; color: var(--muted); line-height: 1.35; }
.live-notif-time { font-size: 0.7rem; color: var(--muted); margin-top: 4px; }
.live-notif-count {
  position: absolute;
  top: -8px;
  right: -8px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--red);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.68rem;
  font-weight: 800;
}
.topbar-badge.live-notif-trigger { position: relative; }
.badge-dot.live-notif-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  background: var(--red);
}
.btn-user-view-dash.staff-user-view {
  background: rgba(243,156,18,0.12);
  color: #f39c12;
  border-color: rgba(243,156,18,0.28);
  white-space: nowrap;
}
.btn-user-view-dash.staff-user-view:hover {
  background: rgba(243,156,18,0.18);
  border-color: rgba(243,156,18,0.48);
}
</style>
<div class="live-notif-wrap">
  <div class="topbar-badge live-notif-trigger" onclick="toggleLiveNotifications(event)">
    <i class="fa-regular fa-bell"></i>
    <?php if ($__staffUnreadCount > 0): ?>
    <span class="badge-dot live-notif-dot"></span>
    <span class="live-notif-count"><?= $__staffUnreadCount ?></span>
    <?php endif; ?>
  </div>
  <div class="live-notif-menu" id="liveNotifMenu" role="menu">
    <div class="live-notif-header">
      <h4>Notifications</h4>
      <button type="button" class="live-notif-mark-all" onclick="markAllLiveNotificationsRead()">Mark all read</button>
    </div>
    <div class="live-notif-list" id="liveNotifList">
      <?php if (empty($__staffNotifications)): ?>
        <div class="live-notif-empty">No notifications</div>
      <?php else: ?>
        <?php foreach ($__staffNotifications as $notif): ?>
        <div class="live-notif-item<?= !$notif['is_read'] ? ' unread' : '' ?>" data-id="<?= (int)$notif['id'] ?>" onclick="markLiveNotificationRead(<?= (int)$notif['id'] ?>, this)">
          <div class="live-notif-row">
            <div class="live-notif-icon" style="background: <?= htmlspecialchars(getNotificationColor($notif['type'])) ?>20; color: <?= htmlspecialchars(getNotificationColor($notif['type'])) ?>;">
              <i class="fa-solid <?= htmlspecialchars(getNotificationIcon($notif['type'])) ?>"></i>
            </div>
            <div class="live-notif-body">
              <div class="live-notif-title"><?= htmlspecialchars($notif['title']) ?></div>
              <div class="live-notif-msg"><?= htmlspecialchars($notif['message']) ?></div>
              <div class="live-notif-time"><?= htmlspecialchars(timeAgo($notif['created_at'])) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);" title="<?= $__staffTopbarName ?>">
  <?= htmlspecialchars($__staffTopbarInitial) ?>
</div>
<a href="<?= htmlspecialchars($__staffUserViewHref) ?>" class="btn-user-view-dash staff-user-view" title="Open customer account page">
  <i class="fa-solid fa-user"></i> User view
</a>
<script>
if (typeof window.toggleLiveNotifications !== 'function') {
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
}

if (typeof window.markLiveNotificationRead !== 'function') {
  window.markLiveNotificationRead = function (id, row) {
    fetch(<?= json_encode($__staffNotifUrl) ?>, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_read', notification_id: id }),
      credentials: 'same-origin'
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.success) return;
        var item = row || document.querySelector('.live-notif-item[data-id="' + id + '"]');
        if (item) item.classList.remove('unread');
        var unread = document.querySelectorAll('.live-notif-item.unread').length;
        var countEl = document.querySelector('.live-notif-count');
        var dot = document.querySelector('.live-notif-dot');
        if (countEl) {
          countEl.textContent = String(unread);
          countEl.style.display = unread > 0 ? 'inline-flex' : 'none';
        }
        if (dot) dot.style.display = unread > 0 ? 'block' : 'none';
      })
      .catch(function () {});
  };
}

if (typeof window.markAllLiveNotificationsRead !== 'function') {
  window.markAllLiveNotificationsRead = function () {
    fetch(<?= json_encode($__staffNotifUrl) ?>, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_all_read' }),
      credentials: 'same-origin'
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.success) return;
        document.querySelectorAll('.live-notif-item.unread').forEach(function (item) {
          item.classList.remove('unread');
        });
        var countEl = document.querySelector('.live-notif-count');
        var dot = document.querySelector('.live-notif-dot');
        if (countEl) {
          countEl.textContent = '0';
          countEl.style.display = 'none';
        }
        if (dot) dot.style.display = 'none';
      })
      .catch(function () {});
  };
}
</script>
