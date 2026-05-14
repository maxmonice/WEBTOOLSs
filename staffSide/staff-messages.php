<?php
require_once 'staff-config.php';
require_once __DIR__ . '/../Notifications.php';
requireStaff();

$staffId = (int)($_SESSION['user_id'] ?? 0);
$staffNameRaw = $_SESSION['user_name'] ?? 'Staff';
$staffEmail = $_SESSION['user_email'] ?? null;
$staffName = htmlspecialchars($staffNameRaw);
$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('staff', $staffId, 8);
$unreadCount = $notifications->getUnreadCount('staff', $staffId);

function ensureInternalMessagesTable(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) {
        return;
    }

    $pdo->exec("CREATE TABLE messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NULL,
        sender_role VARCHAR(32) NOT NULL,
        sender_id INT NULL,
        sender_name VARCHAR(160) NOT NULL,
        sender_email VARCHAR(190) NULL,
        recipient_role VARCHAR(32) NOT NULL,
        recipient_id INT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_messages_parent (parent_id),
        INDEX idx_messages_roles (sender_role, recipient_role),
        INDEX idx_messages_recipient (recipient_role, recipient_id)
    )");
}

ensureInternalMessagesTable($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? '';

    if ($action === 'send_admin_message') {
        $recipientId = (int)($data['recipient_id'] ?? 0);
        $subject = trim($data['subject'] ?? '');
        $message = trim($data['message'] ?? '');

        if ($recipientId <= 0 || $subject === '' || $message === '') {
            echo json_encode(['success' => false, 'message' => 'Admin, subject, and message are required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$recipientId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'Admin contact not found']);
                exit;
            }

            $insert = $pdo->prepare("
                INSERT INTO messages (
                    sender_role, sender_id, sender_name, sender_email,
                    recipient_role, recipient_id, subject, message, status, created_at
                ) VALUES ('staff', ?, ?, ?, 'admin', ?, ?, ?, 'open', NOW())
            ");
            $insert->execute([$staffId, $staffNameRaw, $staffEmail, $recipientId, $subject, $message]);

            $notifications->create('Message From Staff', $subject, 'info', 'admin', $recipientId);
            echo json_encode(['success' => true, 'message' => 'Message sent to admin']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'reply_message') {
        $parentId = (int)($data['parent_id'] ?? 0);
        $reply = trim($data['message'] ?? '');

        if ($parentId <= 0 || $reply === '') {
            echo json_encode(['success' => false, 'message' => 'Reply message is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM messages
                WHERE id = ?
                  AND parent_id IS NULL
                  AND (
                    (sender_role = 'staff' AND sender_id = ? AND recipient_role = 'admin')
                    OR (sender_role = 'admin' AND recipient_role = 'staff' AND (recipient_id = ? OR recipient_id IS NULL))
                  )
            ");
            $stmt->execute([$parentId, $staffId, $staffId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                exit;
            }

            $recipientId = $parent['sender_role'] === 'admin'
                ? (int)$parent['sender_id']
                : (int)$parent['recipient_id'];
            if ($recipientId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Admin recipient is missing']);
                exit;
            }

            $insert = $pdo->prepare("
                INSERT INTO messages (
                    parent_id, sender_role, sender_id, sender_name, sender_email,
                    recipient_role, recipient_id, subject, message, status, created_at
                ) VALUES (?, 'staff', ?, ?, ?, 'admin', ?, ?, ?, 'replied', NOW())
            ");
            $insert->execute([$parentId, $staffId, $staffNameRaw, $staffEmail, $recipientId, $parent['subject'], $reply]);

            $pdo->prepare("UPDATE messages SET status = 'replied', updated_at = NOW() WHERE id = ?")->execute([$parentId]);
            $notifications->create('Staff Reply', "{$staffNameRaw} replied to: {$parent['subject']}", 'info', 'admin', $recipientId);

            echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$adminUsers = [];
$staffMessages = [];
$repliesByParent = [];

try {
    $adminUsers = $pdo->query("
        SELECT id, name, email
        FROM users
        WHERE role = 'admin'
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $_) {}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM messages
        WHERE parent_id IS NULL
          AND (
            (sender_role = 'staff' AND sender_id = ? AND recipient_role = 'admin')
            OR (sender_role = 'admin' AND recipient_role = 'staff' AND (recipient_id = ? OR recipient_id IS NULL))
          )
        ORDER BY FIELD(status, 'open', 'replied', 'closed'), created_at DESC
    ");
    $stmt->execute([$staffId, $staffId]);
    $staffMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $_) {}

$parentIds = array_column($staffMessages, 'id');
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

$openCount = count(array_filter($staffMessages, fn($m) => $m['status'] === 'open'));
$sentCount = count(array_filter($staffMessages, fn($m) => $m['sender_role'] === 'staff'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Messages - Luke's Staff</title>
<link rel="stylesheet" href="../adminSide/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="staff-dashboard.css?v=<?= time() ?>">
<style>
.message-card { background: var(--card2); border: 1px solid var(--line-w); border-radius: 10px; padding: 16px; margin-bottom: 14px; }
.message-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
.message-title { font-weight: 700; color: #fff; }
.message-meta { color: var(--muted); font-size: 0.75rem; margin-top: 3px; }
.message-body { color: rgba(255,255,255,0.82); font-size: 0.86rem; line-height: 1.55; margin: 10px 0; overflow-wrap: anywhere; }
.reply-list { border-left: 2px solid rgba(194,38,38,0.28); padding-left: 12px; margin: 12px 0; }
.reply-item { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
.reply-item:last-child { border-bottom: none; }
.message-actions { display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; margin-top:12px; }
.message-actions textarea { min-width:260px; flex:1; }
.message-source { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 0.76rem; margin-top: 4px; }
@media (max-width: 640px) {
  .message-head, .message-actions { flex-direction: column; }
  .message-actions textarea { min-width: 0; width: 100%; }
}
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">
  <aside class="sidebar" id="sidebar">
<?php $staffNavActive = 'messages'; require __DIR__ . '/staff-sidebar-nav.php'; ?>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Messages</div>
          <div class="topbar-breadcrumb">Staff <span>/</span> Messages</div>
        </div>
      </div>
      <div class="topbar-right">
        <?php require __DIR__ . '/staff-topbar-right.php'; ?>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Messages</h1>
        <p>Send messages to admin and reply to internal staff conversations.</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-inbox"></i></div>
          <div class="stat-card-value"><?= number_format(count($staffMessages)) ?></div>
          <div class="stat-card-label">Conversations</div>
          <div class="stat-card-change up"><i class="fa-solid fa-message"></i> admin and staff</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-envelope-open"></i></div>
          <div class="stat-card-value"><?= number_format($openCount) ?></div>
          <div class="stat-card-label">Open Threads</div>
          <div class="stat-card-change up"><i class="fa-solid fa-clock"></i> needs attention</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-paper-plane"></i></div>
          <div class="stat-card-value"><?= number_format($sentCount) ?></div>
          <div class="stat-card-label">Started by You</div>
          <div class="stat-card-change up"><i class="fa-solid fa-user"></i> staff messages</div>
        </div>
      </div>

      <div class="grid-2" style="grid-template-columns:1fr;">
        <div>
          <div class="panel">
            <div class="panel-header"><span class="panel-title">Message Admin</span></div>
            <div class="panel-body">
              <form id="adminMessageForm">
                <div class="form-group">
                  <label class="form-label">Admin Contact</label>
                  <select class="form-control" id="adminRecipient" required>
                    <option value="">Select admin</option>
                    <?php foreach ($adminUsers as $admin): ?>
                      <option value="<?= (int)$admin['id'] ?>"><?= htmlspecialchars($admin['name']) ?> · <?= htmlspecialchars($admin['email']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Subject</label>
                  <input type="text" class="form-control" id="adminSubject" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Message</label>
                  <textarea class="form-control" id="adminMessage" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
              </form>
            </div>
          </div>

          <div class="panel">
            <div class="panel-header">
              <span class="panel-title">My Conversations</span>
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
                        <div class="message-source">
                          <i class="fa-solid fa-<?= $message['sender_role'] === 'admin' ? 'user-shield' : 'id-badge' ?>"></i>
                          <?= $message['sender_role'] === 'admin' ? 'From Admin' : 'Sent to Admin' ?>
                          <?= $message['recipient_id'] === null && $message['recipient_role'] === 'staff' ? ' · All Staff' : '' ?>
                        </div>
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
                    <?php if ($message['status'] !== 'closed'): ?>
                      <div class="message-actions">
                        <textarea class="form-control" rows="3" id="reply<?= (int)$message['id'] ?>" placeholder="Write a reply..."></textarea>
                        <button class="btn btn-primary" onclick="replyMessage(<?= (int)$message['id'] ?>)"><i class="fa-solid fa-reply"></i> Reply</button>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="text-align:center;color:var(--muted);padding:24px;">No messages yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function postMessage(payload) {
  return fetch('staff-messages.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  }).then(res => res.json());
}

function replyMessage(parentId) {
  const input = document.getElementById(`reply${parentId}`);
  const message = input.value.trim();
  if (!message) {
    alert('Please write a reply first.');
    return;
  }

  postMessage({ action: 'reply_message', parent_id: parentId, message })
    .then(data => {
      if (data.success) location.reload();
      else alert(data.message || 'Failed to send reply');
    })
    .catch(() => alert('Failed to send reply'));
}

document.getElementById('adminMessageForm').addEventListener('submit', function(e) {
  e.preventDefault();
  postMessage({
    action: 'send_admin_message',
    recipient_id: Number(document.getElementById('adminRecipient').value),
    subject: document.getElementById('adminSubject').value.trim(),
    message: document.getElementById('adminMessage').value.trim()
  })
    .then(data => {
      if (data.success) location.reload();
      else alert(data.message || 'Failed to send message');
    })
    .catch(() => alert('Failed to send message'));
});
</script>
<script src="staff-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>
