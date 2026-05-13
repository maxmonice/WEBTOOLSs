<?php
require_once __DIR__ . '/adminSide/admin-config.php';
require_once __DIR__ . '/Notifications.php';

requireAdmin();

$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// ── Ensure archive columns exist ──────────────────────────────────────────────
try { $pdo->exec("ALTER TABLE content_items ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $_) {}
try { $pdo->exec("ALTER TABLE content_items ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL"); } catch (PDOException $_) {}

// ── POST / AJAX handlers ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    // ── get_archived ──────────────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'get_archived') {
        $type = $data['type'] ?? 'all';
        try {
            if ($type === 'menu') {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE is_archived=1 AND category IN ('Salad','Fusion','A La Carte','Platters','Bento') ORDER BY archived_at DESC");
            } elseif ($type === 'gallery') {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE is_archived=1 AND category='gallery' ORDER BY archived_at DESC");
            } else {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE is_archived=1 ORDER BY archived_at DESC");
            }
            $stmt->execute();
            echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── restore_content ────────────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'restore_content') {
        $id = (int)($data['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE content_items SET is_archived=0, archived_at=NULL WHERE id=?")->execute([$id]);
            logAdminActivity($pdo, 'content_restored', "Restored content item (ID:{$id})");
            echo json_encode(['success' => true, 'message' => 'Item restored successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── permanent_delete ───────────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'permanent_delete') {
        $id = (int)($data['id'] ?? 0);
        try {
            $infoStmt = $pdo->prepare("SELECT name FROM content_items WHERE id=?");
            $infoStmt->execute([$id]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("DELETE FROM content_items WHERE id=? AND is_archived=1")->execute([$id]);
            logAdminActivity($pdo, 'content_deleted', "Permanently deleted '{$info['name']}' (ID:{$id})");
            echo json_encode(['success' => true, 'message' => 'Item permanently deleted']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── Stats ──────────────────────────────────────────────────────────────────────
$archivedTotal   = 0;
$archivedMenu    = 0;
$archivedGallery = 0;
try {
    $archivedTotal   = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE is_archived=1")->fetchColumn();
    $archivedMenu    = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE is_archived=1 AND category IN ('Salad','Fusion','A La Carte','Platters','Bento')")->fetchColumn();
    $archivedGallery = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE is_archived=1 AND category='gallery'")->fetchColumn();
} catch (Throwable $_) {}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Archive — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ── Archive page styles ─────────────────────────────────────────────────── */
.arch-tabs {
  display: flex; gap: 0;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 24px;
}
.arch-tab {
  padding: 12px 26px; cursor: pointer; font-size: 0.9rem; font-weight: 600;
  color: rgba(255,255,255,0.45); border-bottom: 2px solid transparent;
  transition: all 0.2s; margin-bottom: -1px;
}
.arch-tab.active { color: #fff; border-bottom-color: #C22626; }

.arch-sub-tabs { display: flex; gap: 8px; margin-bottom: 18px; }
.arch-sub-tab {
  padding: 7px 16px; border-radius: 6px; cursor: pointer; font-size: 0.82rem;
  border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.45); transition: all 0.2s;
}
.arch-sub-tab.active {
  background: rgba(194,38,38,0.2); border-color: rgba(194,38,38,0.4); color: #fff;
}

.arch-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.arch-table th {
  text-align: left; padding: 10px 14px;
  color: rgba(255,255,255,0.4); font-size: 0.76rem;
  text-transform: uppercase; letter-spacing: 0.04em;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.arch-table td {
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  vertical-align: middle;
}
.arch-table tr:hover td { background: rgba(255,255,255,0.02); }

.arch-thumb {
  width: 44px; height: 44px; border-radius: 8px;
  object-fit: cover; display: block;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.08);
}
.arch-thumb-placeholder {
  width: 44px; height: 44px; border-radius: 8px;
  background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,0.2); font-size: 1rem;
}

.arch-badge {
  display: inline-block; padding: 3px 9px; border-radius: 20px;
  font-size: 0.72rem; font-weight: 600;
  background: rgba(194,38,38,0.18); color: #C22626;
}
.arch-badge.gallery {
  background: rgba(52,152,219,0.15); color: #3498db;
}

.arch-empty {
  text-align: center; padding: 60px 20px;
  color: rgba(255,255,255,0.3); font-size: 0.88rem;
}
.arch-empty i { font-size: 2.8rem; display: block; margin-bottom: 16px; opacity: 0.4; }
.arch-empty strong { display: block; font-size: 1rem; color: rgba(255,255,255,0.5); margin-bottom: 6px; }

.arch-actions { display: flex; gap: 8px; }

/* Notification dropdown */
.notification-dropdown {
  position:absolute; top:100%; right:0; width:320px;
  background:var(--card2); border:1px solid var(--line-w);
  border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.3);
  z-index:1000; display:none; max-height:400px; overflow-y:auto;
}
.notification-dropdown.show { display:block; }
.notification-header { padding:12px 16px; border-bottom:1px solid var(--line-w); display:flex; justify-content:space-between; align-items:center; }
.notification-header h3 { margin:0; font-size:0.9rem; color:#fff; }
.notification-header .mark-all { font-size:0.75rem; color:var(--red); background:transparent; border:none; cursor:pointer; }
.notification-item { padding:12px 16px; border-bottom:1px solid var(--line-w); cursor:pointer; transition:background 0.2s; }
.notification-item:hover { background:rgba(194,38,38,0.05); }
.notification-item.unread { background:rgba(52,152,219,0.08); border-left:3px solid #3498db; }
.notification-content { display:flex; gap:12px; align-items:flex-start; }
.notification-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.9rem; }
.notification-text { flex:1; }
.notification-title { font-size:0.85rem; font-weight:600; color:#fff; margin-bottom:4px; }
.notification-message { font-size:0.78rem; color:var(--muted); line-height:1.4; }
.notification-time { font-size:0.72rem; color:var(--muted); margin-top:4px; }
.notification-empty { padding:24px; text-align:center; color:var(--muted); font-size:0.85rem; }

/* Confirm delete modal */
.del-modal-overlay {
  position:fixed; inset:0; z-index:7000;
  background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);
  display:none; align-items:center; justify-content:center;
}
.del-modal-overlay.open { display:flex; }
.del-modal {
  background:var(--card2,#222); border:1px solid rgba(231,76,60,0.3);
  border-radius:16px; padding:32px; max-width:420px; width:90%;
  text-align:center;
}
.del-modal i { font-size:2.5rem; color:#e74c3c; display:block; margin-bottom:16px; }
.del-modal h3 { color:#fff; margin-bottom:10px; }
.del-modal p { color:rgba(255,255,255,0.55); font-size:0.88rem; margin-bottom:24px; }
.del-modal-actions { display:flex; gap:12px; justify-content:center; }
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
  <aside class="sidebar" id="sidebar">
<?php
$adminNavActive = 'archive';
$adminNavFromRoot = true;
require __DIR__ . '/adminSide/admin-sidebar-nav.php';
?>
  </aside>

  <!-- ═══════════════════════ MAIN ═══════════════════════════ -->
  <div class="main-content">

    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Archive</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Archive</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge" style="position:relative;" onclick="toggleNotifications()">
          <i class="fa-regular fa-bell"></i>
          <?php if ($unreadCount > 0): ?>
          <span class="badge-dot" style="background:var(--red);"></span>
          <span style="position:absolute;top:-8px;right:-8px;background:var(--red);color:#fff;border-radius:10px;padding:2px 6px;font-size:0.7rem;font-weight:bold;min-width:18px;text-align:center;"><?= $unreadCount ?></span>
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
                <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" onclick="markNotificationRead(<?= $notif['id'] ?>)">
                  <div class="notification-content">
                    <div class="notification-icon" style="background:<?= getNotificationColor($notif['type']) ?>20;color:<?= getNotificationColor($notif['type']) ?>;"><i class="fa-solid <?= getNotificationIcon($notif['type']) ?>"></i></div>
                    <div class="notification-text">
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
        <a href="admin-settings.php" class="admin-avatar" title="Account Settings" style="text-decoration:none;cursor:pointer;"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></a>
        <a href="account-dashboard.php?user_view=true" class="btn btn-success" style="margin-left:12px;padding:10px 18px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600;border:2px solid var(--red);border-radius:6px;background:linear-gradient(135deg,#C22626,#8B0A1E);box-shadow:0 4px 12px rgba(194,38,38,0.4);color:#ff6b6b;">
          <i class="fa-solid fa-user"></i> User View
        </a>
      </div>
    </header>

    <div class="page-content">

      <!-- ── Page header ──────────────────────────────────────── -->
      <div class="page-header flex-between">
        <div>
          <h1>Archive</h1>
          <p>View, restore, or permanently remove archived content.</p>
        </div>
        <a href="admin-content.php" class="btn btn-outline">
          <i class="fa-solid fa-arrow-left"></i> Back to Content
        </a>
      </div>

      <!-- ── Stats ────────────────────────────────────────────── -->
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-box-archive"></i></div>
          <div class="stat-card-value"><?= $archivedTotal ?></div>
          <div class="stat-card-label">Total Archived</div>
          <div class="stat-card-change"><i class="fa-solid fa-layer-group"></i> All types</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-utensils"></i></div>
          <div class="stat-card-value"><?= $archivedMenu ?></div>
          <div class="stat-card-label">Menu Items</div>
          <div class="stat-card-change"><i class="fa-solid fa-fish"></i> Food & drinks</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-images"></i></div>
          <div class="stat-card-value"><?= $archivedGallery ?></div>
          <div class="stat-card-label">Gallery Photos</div>
          <div class="stat-card-change"><i class="fa-solid fa-camera"></i> Photo items</div>
        </div>
      </div>

      <!-- ── Archive Panel ──────────────────────────────────────── -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title"><i class="fa-solid fa-box-archive" style="color:#C22626;margin-right:8px;"></i>Archived Items</span>
          <span style="color:rgba(255,255,255,0.4);font-size:0.82rem;">Items here are hidden from the website. Restore to make them live again.</span>
        </div>
        <div style="padding:24px;">

          <!-- Tab bar -->
          <div class="arch-tabs">
            <div class="arch-tab active" id="archTabContent" onclick="archSwitchTab('content')">Content</div>
            <div class="arch-tab"        id="archTabUsers"   onclick="archSwitchTab('users')">Users</div>
          </div>

          <!-- Content sub-tabs -->
          <div id="archViewContent">
            <div class="arch-sub-tabs">
              <div class="arch-sub-tab active" id="archSubMenu"    onclick="archSwitchSub('menu')">
                <i class="fa-solid fa-utensils"></i> Item List
              </div>
              <div class="arch-sub-tab"        id="archSubGallery" onclick="archSwitchSub('gallery')">
                <i class="fa-solid fa-images"></i> Gallery Collection
              </div>
              <div class="arch-sub-tab"        id="archSubAll"     onclick="archSwitchSub('all')">
                <i class="fa-solid fa-layer-group"></i> All
              </div>
            </div>
            <div id="archContentBody">
              <div class="arch-empty"><i class="fa-solid fa-spinner fa-spin"></i><strong>Loading…</strong></div>
            </div>
          </div>

          <!-- Users (placeholder) -->
          <div id="archViewUsers" style="display:none;">
            <div class="arch-empty">
              <i class="fa-solid fa-users"></i>
              <strong>No archived users</strong>
              <p>Users archived from User Management will appear here.</p>
            </div>
          </div>

        </div>
      </div>
    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /admin-layout -->

<!-- Permanent delete confirm modal -->
<div class="del-modal-overlay" id="deleteModal">
  <div class="del-modal">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <h3>Permanently Delete?</h3>
    <p>This action <strong>cannot be undone</strong>. The item will be removed from the database forever.</p>
    <div class="del-modal-actions">
      <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-danger"  id="confirmDeleteBtn" onclick="confirmDelete()"><i class="fa-solid fa-trash"></i> Delete Forever</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ─── Utilities ────────────────────────────────────────────────────
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

function showToast(msg, type='') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid fa-${type==='error'?'circle-xmark':'check-circle'}"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toggleNotifications() {
  const d = document.getElementById('notificationDropdown');
  d.classList.toggle('show');
  if (!d.dataset.listenerAdded) {
    d.dataset.listenerAdded = 'true';
    document.addEventListener('click', e => {
      if (!d.contains(e.target) && !e.target.closest('.topbar-badge')) d.classList.remove('show');
    });
  }
}
function markNotificationRead(id) {
  fetch('admin-handle-notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read',notification_id:id})})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); });
}
function markAllNotificationsRead() {
  fetch('admin-handle-notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_all_read'})})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); });
}

// ─── Tab switching ────────────────────────────────────────────────
let currentTab = 'content';
let currentSub = 'menu';

function archSwitchTab(tab) {
  currentTab = tab;
  document.getElementById('archTabContent').classList.toggle('active', tab === 'content');
  document.getElementById('archTabUsers').classList.toggle('active',   tab === 'users');
  document.getElementById('archViewContent').style.display = tab === 'content' ? '' : 'none';
  document.getElementById('archViewUsers').style.display   = tab === 'users'   ? '' : 'none';
}

function archSwitchSub(sub) {
  currentSub = sub;
  document.getElementById('archSubMenu').classList.toggle('active',    sub === 'menu');
  document.getElementById('archSubGallery').classList.toggle('active', sub === 'gallery');
  document.getElementById('archSubAll').classList.toggle('active',     sub === 'all');
  archLoad(sub);
}

// ─── Load archived items ──────────────────────────────────────────
function archLoad(type) {
  const body = document.getElementById('archContentBody');
  body.innerHTML = '<div class="arch-empty"><i class="fa-solid fa-spinner fa-spin"></i><strong>Loading…</strong></div>';

  fetch('admin-archive.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_archived', type })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) {
      body.innerHTML = '<div class="arch-empty"><i class="fa-solid fa-circle-exclamation"></i><strong>Error loading archive</strong></div>';
      return;
    }
    const items = d.items || [];
    if (!items.length) {
      const labels = { menu: 'menu items', gallery: 'gallery photos', all: 'items' };
      body.innerHTML = `<div class="arch-empty">
        <i class="fa-solid fa-box-archive"></i>
        <strong>No archived ${labels[type] || 'items'}</strong>
        <p>Items you archive from Content Management will appear here.</p>
      </div>`;
      return;
    }

    body.innerHTML = `<div style="overflow-x:auto;">
      <table class="arch-table">
        <thead>
          <tr>
            <th style="width:56px;">Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Archived On</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${items.map(item => {
            const thumb = item.image
              ? `<img src="${escHtml(item.image)}" class="arch-thumb" alt="" onerror="this.style.display='none'">`
              : `<div class="arch-thumb-placeholder"><i class="fa-solid fa-image"></i></div>`;
            const dateStr = item.archived_at
              ? new Date(item.archived_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' })
              : '—';
            const isGallery = item.category === 'gallery';
            const badgeCls = isGallery ? 'arch-badge gallery' : 'arch-badge';
            const price = parseFloat(item.price || 0);
            const priceStr = !isGallery ? `₱${price.toLocaleString('en-PH',{minimumFractionDigits:2})}` : '—';
            return `<tr>
              <td>${thumb}</td>
              <td style="font-weight:600;color:#fff;">${escHtml(item.name)}</td>
              <td><span class="${badgeCls}">${escHtml(item.category)}</span></td>
              <td style="color:#C22626;font-weight:600;">${priceStr}</td>
              <td style="color:rgba(255,255,255,0.45);font-size:0.82rem;">${dateStr}</td>
              <td>
                <div class="arch-actions">
                  <button class="btn btn-outline btn-sm" onclick="restoreItem(${item.id})">
                    <i class="fa-solid fa-rotate-left"></i> Restore
                  </button>
                  <button class="btn btn-danger btn-sm" onclick="openDeleteModal(${item.id})">
                    <i class="fa-solid fa-trash"></i> Delete
                  </button>
                </div>
              </td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    </div>`;
  })
  .catch(() => {
    body.innerHTML = '<div class="arch-empty"><i class="fa-solid fa-wifi"></i><strong>Network error</strong><p>Please try refreshing the page.</p></div>';
  });
}

// ─── Restore ──────────────────────────────────────────────────────
function restoreItem(id) {
  if (!confirm('Restore this item? It will reappear live on the website.')) return;
  fetch('admin-archive.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'restore_content', id })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast('Item restored and is now live!', 'success');
      archLoad(currentSub);
    } else {
      showToast(d.message || 'Failed to restore', 'error');
    }
  });
}

// ─── Permanent delete ─────────────────────────────────────────────
let pendingDeleteId = null;

function openDeleteModal(id) {
  pendingDeleteId = id;
  document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() {
  pendingDeleteId = null;
  document.getElementById('deleteModal').classList.remove('open');
}
function confirmDelete() {
  if (!pendingDeleteId) return;
  const btn = document.getElementById('confirmDeleteBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting…';

  fetch('admin-archive.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'permanent_delete', id: pendingDeleteId })
  })
  .then(r => r.json())
  .then(d => {
    closeDeleteModal();
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Forever';
    if (d.success) {
      showToast('Item permanently deleted.', 'success');
      archLoad(currentSub);
    } else {
      showToast(d.message || 'Failed to delete', 'error');
    }
  });
}

document.getElementById('deleteModal').addEventListener('click', e => {
  if (e.target === document.getElementById('deleteModal')) closeDeleteModal();
});

// ─── Boot ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  archLoad('menu');
});
</script>
</body>
</html>