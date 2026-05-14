<?php
/**
 * Shared admin topbar notifications (reads `notifications` table).
 * Include after $pdo is available and the visitor is authenticated as admin.
 */
if (!isset($pdo) || empty($_SESSION['is_admin'])) {
    return;
}

require_once __DIR__ . '/../Notifications.php';

if (!function_exists('admin_notif_time_label')) {
    function admin_notif_time_label(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }
        if (function_exists('timeAgo')) {
            return timeAgo($datetime);
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 45) {
            return 'Just now';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . ' min ago';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' hr ago';
        }
        if ($diff < 604800) {
            return (int) floor($diff / 86400) . ' days ago';
        }
        return date('M j, Y', $ts);
    }
}

$__adminNotifSvc = new Notifications($pdo);
$__adminUid = $_SESSION['user_id'] ?? null;
$__adminUserNotifications = $__adminNotifSvc->getForUser('admin', $__adminUid, 12);
$__adminUnreadCount = $__adminNotifSvc->getUnreadCount('admin', $__adminUid);
?>
        <div class="live-notif-wrap">
          <div class="topbar-badge live-notif-trigger" role="button" tabindex="0" onclick="toggleLiveNotifications(event)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleLiveNotifications(event);}" aria-label="Notifications" aria-expanded="false" aria-controls="liveNotifMenu">
            <i class="fa-regular fa-bell" aria-hidden="true"></i>
            <?php if ($__adminUnreadCount > 0): ?>
            <span class="live-notif-dot" aria-hidden="true"></span>
            <span class="live-notif-count"><?= (int) $__adminUnreadCount ?></span>
            <?php endif; ?>
          </div>
          <div class="live-notif-menu" id="liveNotifMenu" role="menu" aria-labelledby="adminNotifBell">
            <div class="live-notif-header">
              <h4>Notifications</h4>
              <button type="button" class="live-notif-mark-all" onclick="markAllLiveNotificationsRead(event)">Mark all read</button>
            </div>
            <div class="live-notif-list" id="liveNotifList">
              <?php if (empty($__adminUserNotifications)): ?>
                <div class="live-notif-empty">You're all caught up. New orders, bookings, and sign-ups will show here.</div>
              <?php else: ?>
                <?php foreach ($__adminUserNotifications as $notif): ?>
                <?php
                  $isUnread = empty($notif['is_read']) || (int) $notif['is_read'] === 0;
                  $type = $notif['type'] ?? 'info';
                  $icon = function_exists('getNotificationIcon') ? getNotificationIcon($type) : 'fa-info-circle';
                  $color = function_exists('getNotificationColor') ? getNotificationColor($type) : '#3498db';
                ?>
                <div class="live-notif-item<?= $isUnread ? ' unread' : '' ?>" data-id="<?= (int) $notif['id'] ?>" role="menuitem" onclick="markLiveNotificationRead(<?= (int) $notif['id'] ?>, this)">
                  <div class="live-notif-row">
                    <div class="live-notif-icon" style="background: <?= htmlspecialchars($color) ?>20; color: <?= htmlspecialchars($color) ?>;">
                      <i class="fa-solid <?= htmlspecialchars($icon) ?>"></i>
                    </div>
                    <div class="live-notif-body">
                      <div class="live-notif-title"><?= htmlspecialchars($notif['title'] ?? '') ?></div>
                      <div class="live-notif-msg"><?= htmlspecialchars($notif['message'] ?? '') ?></div>
                      <div class="live-notif-time"><?= htmlspecialchars(admin_notif_time_label($notif['created_at'] ?? '')) ?></div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="live-notif-footer">
              <a href="<?= htmlspecialchars($adminNotifLogsHref ?? 'admin-logs.php', ENT_QUOTES, 'UTF-8') ?>" class="live-notif-footer-link"><i class="fa-solid fa-clipboard-list"></i> Activity log</a>
            </div>
          </div>
        </div>
