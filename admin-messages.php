<?php
require_once 'admin-config.php';
require_once 'Notifications.php';
requireAdmin();

$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? '';

    if ($action === 'reply_message') {
        $parentId = (int)($data['parent_id'] ?? 0);
        $reply = trim($data['message'] ?? '');

        if ($parentId <= 0 || $reply === '') {
            echo json_encode(['success' => false, 'message' => 'Reply message is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND parent_id IS NULL");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                exit;
            }

            $recipientRole = $parent['sender_role'] === 'admin' ? $parent['recipient_role'] : $parent['sender_role'];
            $recipientId = $parent['sender_role'] === 'admin' ? $parent['recipient_id'] : $parent['sender_id'];

            $insert = $pdo->prepare("
                INSERT INTO messages (
                    parent_id, sender_role, sender_id, sender_name, sender_email,
                    recipient_role, recipient_id, subject, message, status, created_at
                ) VALUES (?, 'admin', ?, ?, ?, ?, ?, ?, ?, 'replied', NOW())
            ");
            $insert->execute([
                $parentId,
                $_SESSION['user_id'] ?? null,
                $_SESSION['user_name'] ?? 'Admin',
                $_SESSION['user_email'] ?? null,
                $recipientRole,
                $recipientId ?: null,
                $parent['subject'],
                $reply
            ]);

            $pdo->prepare("UPDATE messages SET status = 'replied', updated_at = NOW() WHERE id = ?")->execute([$parentId]);

            if ($recipientId) {
                $notifications->create(
                    'Message Reply',
                    "Admin replied to: {$parent['subject']}",
                    'info',
                    $recipientRole,
                    (int)$recipientId
                );
            } elseif ($recipientRole === 'staff') {
                $notifications->create(
                    'Staff Message',
                    "Admin replied to: {$parent['subject']}",
                    'info',
                    'staff'
                );
            }

            logAdminActivity($pdo, 'message_replied', "Replied to message #{$parentId}: {$parent['subject']}");
            echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'send_staff_message') {
        $recipientId = (int)($data['recipient_id'] ?? 0);
        $subject = trim($data['subject'] ?? '');
        $message = trim($data['message'] ?? '');

        if ($subject === '' || $message === '') {
            echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
            exit;
        }

        try {
            $recipientName = 'All Staff';
            if ($recipientId > 0) {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'staff'");
                $stmt->execute([$recipientId]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$staff) {
                    echo json_encode(['success' => false, 'message' => 'Staff member not found']);
                    exit;
                }
                $recipientName = $staff['name'];
            }

            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    sender_role, sender_id, sender_name, sender_email,
                    recipient_role, recipient_id, subject, message, status, created_at
                ) VALUES ('admin', ?, ?, ?, 'staff', ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $_SESSION['user_name'] ?? 'Admin',
                $_SESSION['user_email'] ?? null,
                $recipientId > 0 ? $recipientId : null,
                $subject,
                $message
            ]);

            $notifications->create(
                'Message From Admin',
                $subject,
                'info',
                'staff',
                $recipientId > 0 ? $recipientId : null
            );

            logAdminActivity($pdo, 'staff_message_sent', "Sent staff message to {$recipientName}: {$subject}");
            echo json_encode(['success' => true, 'message' => 'Staff message sent successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'close_message') {
        $messageId = (int)($data['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid message']);
            exit;
        }

        try {
            $pdo->prepare("UPDATE messages SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$messageId]);
            logAdminActivity($pdo, 'message_closed', "Closed message #{$messageId}");
            echo json_encode(['success' => true, 'message' => 'Message closed']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$customerMessages = [];
$staffMessages = [];
$staffUsers = [];
$repliesByParent = [];

try {
    $customerMessages = $pdo->query("
        SELECT *
        FROM messages
        WHERE parent_id IS NULL
          AND sender_role = 'customer'
          AND recipient_role = 'admin'
        ORDER BY FIELD(status, 'open', 'replied', 'closed'), created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $_) {}

try {
    $staffMessages = $pdo->query("
        SELECT *
        FROM messages
        WHERE parent_id IS NULL
          AND ((sender_role = 'staff' AND recipient_role = 'admin')
            OR (sender_role = 'admin' AND recipient_role = 'staff'))
        ORDER BY FIELD(status, 'open', 'replied', 'closed'), created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $_) {}

try {
    $staffUsers = $pdo->query("
        SELECT id, name, email
        FROM users
        WHERE role = 'staff' AND status = 'active'
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $_) {}

$parentIds = array_merge(
    array_column($customerMessages, 'id'),
    array_column($staffMessages, 'id')
);
if (!empty($parentIds)) {
    try {
        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE parent_id IN ($placeholders) ORDER BY created_at ASC");
        $stmt->execute($parentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $reply) {
            $repliesByParent[(int)$reply['parent_id']][] = $reply;
        }
    } catch (\Throwable $_) {}
}

$openCustomerCount = count(array_filter($customerMessages, fn($m) => $m['status'] === 'open'));
$openStaffCount = count(array_filter($staffMessages, fn($m) => $m['status'] === 'open'));
$repliedToday = 0;
try {
    $repliedToday = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE sender_role = 'admin' AND parent_id IS NOT NULL AND DATE(created_at) = CURDATE()")->fetchColumn();
} catch (\Throwable $_) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Messages - Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.message-card {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 14px;
}
.message-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}
.message-title { font-weight: 700; color: #fff; }
.message-meta { color: var(--muted); font-size: 0.75rem; margin-top: 3px; }
.message-body { color: rgba(255,255,255,0.82); font-size: 0.86rem; line-height: 1.55; margin: 10px 0; }
.reply-list {
  border-left: 2px solid rgba(194,38,38,0.28);
  padding-left: 12px;
  margin: 12px 0;
}
.reply-item {
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.reply-item:last-child { border-bottom: none; }
.message-actions { display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; margin-top:12px; }
.message-actions textarea { min-width:260px; flex:1; }
.notification-dropdown {
  position: absolute; top: 100%; right: 0; width: 320px;
  background: var(--card2); border: 1px solid var(--line-w);
  border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  z-index: 1000; display: none; max-height: 400px; overflow-y: auto;
}
.notification-dropdown.show { display: block; }
.notification-header { padding: 12px 16px; border-bottom: 1px solid var(--line-w); display:flex; justify-content:space-between; align-items:center; }
.notification-header h3 { margin:0; font-size:0.9rem; color:#fff; }
.notification-header .mark-all { font-size:0.75rem; color:var(--red); background:transparent; border:none; cursor:pointer; }
.notification-item { padding:12px 16px; border-bottom:1px solid var(--line-w); cursor:pointer; }
.notification-item.unread { background:rgba(52,152,219,0.08); border-left:3px solid #3498db; }
.notification-content { display:flex; gap:12px; align-items:flex-start; }
.notification-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.notification-title { font-size:0.85rem; font-weight:600; color:#fff; margin-bottom:4px; }
.notification-message, .notification-time, .notification-empty { font-size:0.78rem; color:var(--muted); }
.notification-empty { padding:24px; text-align:center; }
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Admin Panel</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="admin-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="admin-users.php" class="nav-item"><i class="fa-solid fa-users"></i> User Management</a>
      <a href="admin-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
      <a href="admin-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
      <a href="admin-content.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Content Management</a>
      <a href="admin-messages.php" class="nav-item active"><i class="fa-solid fa-message"></i> Messages</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-settings.php" class="nav-item"><i class="fa-solid fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="admin-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Messages</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Messages</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge" style="position: relative;" onclick="toggleNotifications()">
          <i class="fa-regular fa-bell"></i>
          <?php if ($unreadCount > 0): ?>
            <span class="badge-dot" style="background: var(--red);"></span>
            <span class="notification-count" style="position:absolute;top:-8px;right:-8px;background:var(--red);color:white;border-radius:10px;padding:2px 6px;font-size:0.7rem;font-weight:bold;min-width:18px;text-align:center;"><?= $unreadCount ?></span>
          <?php endif; ?>
        </div>
        <div class="notification-dropdown" id="notificationDropdown">
          <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all" onclick="markAllNotificationsRead()">Mark all read</button>
          </div>
          <div id="notificationList">
            <?php if (empty($userNotifications)): ?>
              <div class="notification-empty">No notifications</div>
            <?php else: ?>
              <?php foreach ($userNotifications as $notif): ?>
                <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" onclick="markNotificationRead(<?= (int)$notif['id'] ?>)">
                  <div class="notification-content">
                    <div class="notification-icon" style="background:<?= getNotificationColor($notif['type']) ?>20;color:<?= getNotificationColor($notif['type']) ?>;">
                      <i class="fa-solid <?= getNotificationIcon($notif['type']) ?>"></i>
                    </div>
                    <div>
                      <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                      <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                      <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <a href="admin-settings.php" class="admin-avatar" title="<?= $adminName ?>" style="text-decoration:none;"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Messages</h1>
        <p>Reply to customer concerns and coordinate with staff.</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-inbox"></i></div>
          <div class="stat-card-value"><?= number_format($openCustomerCount) ?></div>
          <div class="stat-card-label">Open Customer Concerns</div>
          <div class="stat-card-change <?= $openCustomerCount > 0 ? 'down' : 'up' ?>"><i class="fa-solid fa-<?= $openCustomerCount > 0 ? 'arrow-down' : 'check' ?>"></i> <?= $openCustomerCount > 0 ? 'needs reply' : 'all clear' ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-tie"></i></div>
          <div class="stat-card-value"><?= number_format($openStaffCount) ?></div>
          <div class="stat-card-label">Open Staff Threads</div>
          <div class="stat-card-change up"><i class="fa-solid fa-message"></i> internal</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-reply"></i></div>
          <div class="stat-card-value"><?= number_format($repliedToday) ?></div>
          <div class="stat-card-label">Replies Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-calendar-day"></i> admin responses</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Customer Concerns</span>
            <span class="badge badge-yellow"><?= number_format(count($customerMessages)) ?> total</span>
          </div>
          <div class="panel-body">
            <?php if (!empty($customerMessages)): ?>
              <?php foreach ($customerMessages as $message): ?>
                <div class="message-card">
                  <div class="message-head">
                    <div>
                      <div class="message-title"><?= htmlspecialchars($message['subject']) ?></div>
                      <div class="message-meta"><?= htmlspecialchars($message['sender_name']) ?> · <?= htmlspecialchars($message['sender_email'] ?: 'No email') ?> · <?= timeAgo($message['created_at']) ?></div>
                    </div>
                    <?= statusBadge($message['status']) ?>
                  </div>
                  <div class="message-body"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                  <?php if (!empty($repliesByParent[(int)$message['id']])): ?>
                    <div class="reply-list">
                      <?php foreach ($repliesByParent[(int)$message['id']] as $reply): ?>
                        <div class="reply-item">
                          <strong><?= htmlspecialchars($reply['sender_name']) ?></strong>
                          <span class="message-meta"> · <?= timeAgo($reply['created_at']) ?></span>
                          <div class="message-body"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="message-actions">
                    <textarea class="form-control" rows="3" id="reply<?= (int)$message['id'] ?>" placeholder="Write a reply..."></textarea>
                    <button class="btn btn-primary" onclick="replyMessage(<?= (int)$message['id'] ?>)"><i class="fa-solid fa-reply"></i> Reply</button>
                    <button class="btn btn-outline" onclick="closeMessage(<?= (int)$message['id'] ?>)"><i class="fa-solid fa-check"></i> Close</button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="text-align:center;color:var(--muted);padding:36px;">No customer concerns yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <div>
          <div class="panel">
            <div class="panel-header"><span class="panel-title">Message Staff</span></div>
            <div class="panel-body">
              <form id="staffMessageForm">
                <div class="form-group">
                  <label class="form-label">Recipient</label>
                  <select class="form-control" id="staffRecipient">
                    <option value="0">All Staff</option>
                    <?php foreach ($staffUsers as $staff): ?>
                      <option value="<?= (int)$staff['id'] ?>"><?= htmlspecialchars($staff['name']) ?> · <?= htmlspecialchars($staff['email']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Subject</label>
                  <input type="text" class="form-control" id="staffSubject" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Message</label>
                  <textarea class="form-control" id="staffMessage" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Staff Message</button>
              </form>
            </div>
          </div>

          <div class="panel">
            <div class="panel-header">
              <span class="panel-title">Staff Conversations</span>
              <span class="badge badge-blue"><?= number_format(count($staffMessages)) ?> total</span>
            </div>
            <div class="panel-body">
              <?php if (!empty($staffMessages)): ?>
                <?php foreach ($staffMessages as $message): ?>
                  <div class="message-card">
                    <div class="message-head">
                      <div>
                        <div class="message-title"><?= htmlspecialchars($message['subject']) ?></div>
                        <div class="message-meta"><?= htmlspecialchars($message['sender_name']) ?> · <?= timeAgo($message['created_at']) ?></div>
                      </div>
                      <?= statusBadge($message['status']) ?>
                    </div>
                    <div class="message-body"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                    <?php if (!empty($repliesByParent[(int)$message['id']])): ?>
                      <div class="reply-list">
                        <?php foreach ($repliesByParent[(int)$message['id']] as $reply): ?>
                          <div class="reply-item">
                            <strong><?= htmlspecialchars($reply['sender_name']) ?></strong>
                            <span class="message-meta"> · <?= timeAgo($reply['created_at']) ?></span>
                            <div class="message-body"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <div class="message-actions">
                      <textarea class="form-control" rows="3" id="reply<?= (int)$message['id'] ?>" placeholder="Write a staff reply..."></textarea>
                      <button class="btn btn-primary" onclick="replyMessage(<?= (int)$message['id'] ?>)"><i class="fa-solid fa-reply"></i> Reply</button>
                      <button class="btn btn-outline" onclick="closeMessage(<?= (int)$message['id'] ?>)"><i class="fa-solid fa-check"></i> Close</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="text-align:center;color:var(--muted);padding:24px;">No staff conversations yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

function replyMessage(parentId) {
  const input = document.getElementById(`reply${parentId}`);
  const message = input.value.trim();
  if (!message) {
    alert('Please write a reply first.');
    return;
  }

  fetch('admin-messages.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'reply_message', parent_id: parentId, message })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) location.reload();
    else alert(data.message || 'Failed to send reply');
  })
  .catch(() => alert('Failed to send reply'));
}

function closeMessage(messageId) {
  fetch('admin-messages.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'close_message', message_id: messageId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) location.reload();
    else alert(data.message || 'Failed to close message');
  })
  .catch(() => alert('Failed to close message'));
}

document.getElementById('staffMessageForm').addEventListener('submit', function(e) {
  e.preventDefault();
  fetch('admin-messages.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'send_staff_message',
      recipient_id: Number(document.getElementById('staffRecipient').value),
      subject: document.getElementById('staffSubject').value.trim(),
      message: document.getElementById('staffMessage').value.trim()
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) location.reload();
    else alert(data.message || 'Failed to send staff message');
  })
  .catch(() => alert('Failed to send staff message'));
});

function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  dropdown.classList.toggle('show');
  if (!dropdown.dataset.listenerAdded) {
    dropdown.dataset.listenerAdded = 'true';
    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target) && !e.target.closest('.topbar-badge')) {
        dropdown.classList.remove('show');
      }
    });
  }
}

function markNotificationRead(notificationId) {
  fetch('admin-handle-notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
  })
  .then(res => res.json())
  .then(data => { if (data.success) location.reload(); });
}

function markAllNotificationsRead() {
  fetch('admin-handle-notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_all_read' })
  })
  .then(res => res.json())
  .then(data => { if (data.success) location.reload(); });
}
</script>
</body>
</html>
