<?php
require_once __DIR__ . '/adminSide/admin-config.php';
require_once __DIR__ . '/Notifications.php';
requireAdmin();

$notifications = new Notifications($pdo);

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
            $stmt = $pdo->prepare("SELECT * FROM communications WHERE id = ? AND parent_id IS NULL");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                echo json_encode(['success' => false, 'message' => 'Conversation not found']);
                exit;
            }

            $recipientRole = $parent['sender_type'] === 'admin' ? $parent['receiver_type'] : $parent['sender_type'];
            $recipientId = $parent['sender_type'] === 'admin' ? $parent['receiver_id'] : $parent['sender_id'];

            $insert = $pdo->prepare("
                INSERT INTO communications (
                    parent_id, sender_type, sender_id, 
                    receiver_type, receiver_id, subject, message, status, created_at
                ) VALUES (?, 'admin', ?, ?, ?, ?, ?, 'replied', NOW())
            ");
            $insert->execute([
                $parentId,
                $_SESSION['user_id'] ?? null,
                $recipientRole,
                $recipientId ?: null,
                $parent['subject'],
                $reply
            ]);

            $pdo->prepare("UPDATE communications SET status = 'replied', updated_at = NOW() WHERE id = ?")->execute([$parentId]);

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
                INSERT INTO communications (
                    sender_type, sender_id, 
                    receiver_type, receiver_id, subject, message, status, created_at
                ) VALUES ('admin', ?, 'staff', ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
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
            $pdo->prepare("UPDATE communications SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$messageId]);
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

$staffMessages = [];
$staffUsers = [];
$repliesByParent = [];

try {
    $staffMessages = $pdo->query("
        SELECT c.*, u.name as sender_name
        FROM communications c
        LEFT JOIN users u ON c.sender_id = u.id
        WHERE c.parent_id IS NULL
          AND ((c.sender_type = 'staff' AND c.receiver_type = 'admin')
            OR (c.sender_type = 'admin' AND c.receiver_type = 'staff'))
        ORDER BY FIELD(c.status, 'open', 'replied', 'closed'), c.created_at DESC
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
    array_column($staffMessages, 'id')
);
if (!empty($parentIds)) {
    try {
        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as sender_name 
            FROM communications c 
            LEFT JOIN users u ON c.sender_id = u.id 
            WHERE c.parent_id IN ($placeholders) 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute($parentIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $reply) {
            $repliesByParent[(int)$reply['parent_id']][] = $reply;
        }
    } catch (\Throwable $_) {}
}

$openStaffCount = count(array_filter($staffMessages, fn($m) => $m['status'] === 'open'));
$repliedToday = 0;
try {
    $repliedToday = (int)$pdo->query("SELECT COUNT(*) FROM communications WHERE sender_type = 'admin' AND parent_id IS NOT NULL AND DATE(created_at) = CURDATE()")->fetchColumn();
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
<script defer src="adminSide/admin-notifications.js?v=<?= time() ?>"></script>
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
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">
  <aside class="sidebar" id="sidebar">
<?php
$adminNavActive = 'messages';
$adminNavFromRoot = true;
require __DIR__ . '/adminSide/admin-sidebar-nav.php';
?>
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
        <?php $adminTopbarFromRoot = true; require __DIR__ . '/adminSide/admin-topbar-right.php'; ?>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Messages</h1>
        <p>Coordinate internal messages between admin and staff.</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-inbox"></i></div>
          <div class="stat-card-value"><?= number_format(count($staffMessages)) ?></div>
          <div class="stat-card-label">Staff Conversations</div>
          <div class="stat-card-change up"><i class="fa-solid fa-message"></i> internal only</div>
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

      <div class="grid-2" style="grid-template-columns:1fr;">
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

</script>
</body>
</html>


