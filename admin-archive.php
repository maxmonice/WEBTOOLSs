<?php
require_once __DIR__ . '/adminSide/admin-config.php';

requireAdmin();

// ── Ensure archive columns exist (Redirected to menu_items) ───────────────────
try { $pdo->exec("ALTER TABLE menu_items ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $_) {}
try { $pdo->exec("ALTER TABLE menu_items ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL"); } catch (PDOException $_) {}

// ── POST / AJAX handlers ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    // ── get_archived ──────────────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'get_archived') {
        $type = $data['type'] ?? 'all';
        try {
            if ($type === 'users') {
                $stmt = $pdo->prepare(
                    "SELECT id, name, email, provider, COALESCE(status,'active') AS status, role, archived_at
                     FROM users
                     WHERE COALESCE(is_archived,0)=1 AND email != 'admin@gmail.com'
                     ORDER BY archived_at DESC"
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                exit;
            }
            if ($type === 'menu') {
                $stmt = $pdo->prepare("SELECT m.*, c.name as category FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE m.is_archived=1 AND c.slug != 'gallery' ORDER BY m.archived_at DESC");
            } elseif ($type === 'gallery') {
                $stmt = $pdo->prepare("SELECT m.*, c.name as category FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE m.is_archived=1 AND c.slug='gallery' ORDER BY m.archived_at DESC");
            } else {
                $stmt = $pdo->prepare("SELECT m.*, c.name as category FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE m.is_archived=1 ORDER BY m.archived_at DESC");
            }
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) { $item['image'] = $item['image_path']; }
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── restore_content ────────────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'restore_content') {
        $id = (int)($data['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE menu_items SET is_archived=0, archived_at=NULL WHERE id=?")->execute([$id]);
            logAdminActivity($pdo, 'content_restored', "Restored menu item (ID:{$id})");
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
            $infoStmt = $pdo->prepare("SELECT name FROM menu_items WHERE id=?");
            $infoStmt->execute([$id]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("DELETE FROM menu_items WHERE id=? AND is_archived=1")->execute([$id]);
            $label = $info['name'] ?? ('#' . $id);
            logAdminActivity($pdo, 'content_deleted', "Permanently deleted '{$label}' (ID:{$id})");
            echo json_encode(['success' => true, 'message' => 'Item permanently deleted']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── restore_user ───────────────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'restore_user') {
        $id = (int)($data['id'] ?? 0);
        try {
            $infoStmt = $pdo->prepare("SELECT name, email FROM users WHERE id=? AND COALESCE(is_archived,0)=1 AND email != 'admin@gmail.com'");
            $infoStmt->execute([$id]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$info) {
                echo json_encode(['success' => false, 'message' => 'Archived user not found.']);
                exit;
            }
            $pdo->prepare("UPDATE users SET is_archived=0, archived_at=NULL WHERE id=?")->execute([$id]);
            logAdminActivity($pdo, 'user_restored', 'Restored user: ' . ($info['name'] ?? '') . ' (' . ($info['email'] ?? '') . ") (ID:{$id})");
            echo json_encode(['success' => true, 'message' => 'User restored successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── permanent_delete_user ──────────────────────────────────────────────────
    if (($data['action'] ?? '') === 'permanent_delete_user') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user.']);
            exit;
        }
        try {
            $chk = $pdo->prepare("SELECT email FROM users WHERE id=? AND COALESCE(is_archived,0)=1");
            $chk->execute([$id]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row || ($row['email'] ?? '') === 'admin@gmail.com') {
                echo json_encode(['success' => false, 'message' => 'Archived user not found.']);
                exit;
            }
            $ordStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
            $ordStmt->execute([$id]);
            if ((int) $ordStmt->fetchColumn() > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This account has orders on file. For data integrity it cannot be erased permanently; keep it archived or contact support.',
                ]);
                exit;
            }
            $pdo->prepare("DELETE FROM users WHERE id=? AND COALESCE(is_archived,0)=1 AND email != 'admin@gmail.com'")->execute([$id]);
            logAdminActivity($pdo, 'user_deleted_permanent', "Permanently deleted user ID {$id} ({$row['email']})");
            echo json_encode(['success' => true, 'message' => 'User permanently removed']);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, '1451') !== false || stripos($msg, 'foreign key') !== false) {
                $msg = 'This account is still referenced by other records (orders, ratings, etc.) and cannot be removed permanently.';
            }
            echo json_encode(['success' => false, 'message' => $msg]);
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
$archivedUsers   = 0;
try {
    $archivedTotal   = (int)$pdo->query("SELECT COUNT(*) FROM menu_items WHERE is_archived=1")->fetchColumn();
    $archivedMenu    = (int)$pdo->query("SELECT COUNT(m.id) FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE m.is_archived=1 AND c.slug != 'gallery'")->fetchColumn();
    $archivedGallery = (int)$pdo->query("SELECT COUNT(m.id) FROM menu_items m JOIN categories c ON m.category_id = c.id WHERE m.is_archived=1 AND c.slug = 'gallery'")->fetchColumn();
} catch (Throwable $_) {}
try {
    $archivedUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE COALESCE(is_archived,0)=1 AND email != 'admin@gmail.com'")->fetchColumn();
} catch (Throwable $_) {}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Archive — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<script defer src="adminSide/admin-notifications.js?v=<?= time() ?>"></script>
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
        <?php $adminTopbarFromRoot = true; require __DIR__ . '/adminSide/admin-topbar-right.php'; ?>
      </div>
    </header>

    <div class="page-content">

      <!-- ── Page header ──────────────────────────────────────── -->
      <div class="page-header flex-between">
        <div>
          <h1>Archive</h1>
          <p>View, restore, or permanently remove archived content and user accounts.</p>
        </div>
        <a href="admin-content.php" class="btn btn-outline">
          <i class="fa-solid fa-arrow-left"></i> Back to Content
        </a>
      </div>

      <!-- ── Stats ────────────────────────────────────────────── -->
      <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:28px;">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-box-archive"></i></div>
          <div class="stat-card-value"><?= $archivedTotal ?></div>
          <div class="stat-card-label">Archived content</div>
          <div class="stat-card-change"><i class="fa-solid fa-layer-group"></i> Menu & gallery</div>
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
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= $archivedUsers ?></div>
          <div class="stat-card-label">Archived users</div>
          <div class="stat-card-change"><i class="fa-solid fa-user-slash"></i> User Management</div>
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

          <div id="archViewUsers" style="display:none;">
            <div id="archUsersBody">
              <div class="arch-empty"><i class="fa-solid fa-spinner fa-spin"></i><strong>Loading…</strong></div>
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
    <h3 id="deleteModalTitle">Permanently Delete?</h3>
    <p id="deleteModalText">This action <strong>cannot be undone</strong>. The item will be removed from the database forever.</p>
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

// ─── Tab switching ────────────────────────────────────────────────
let currentTab = 'content';
let currentSub = 'menu';

function archSwitchTab(tab) {
  currentTab = tab;
  document.getElementById('archTabContent').classList.toggle('active', tab === 'content');
  document.getElementById('archTabUsers').classList.toggle('active',   tab === 'users');
  document.getElementById('archViewContent').style.display = tab === 'content' ? '' : 'none';
  document.getElementById('archViewUsers').style.display   = tab === 'users'   ? '' : 'none';
  if (tab === 'users') archLoadUsers();
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
                  <button class="btn btn-danger btn-sm" onclick="openDeleteModal(${item.id}, 'content')">
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

function archLoadUsers() {
  const body = document.getElementById('archUsersBody');
  if (!body) return;
  body.innerHTML = '<div class="arch-empty"><i class="fa-solid fa-spinner fa-spin"></i><strong>Loading…</strong></div>';

  fetch('admin-archive.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_archived', type: 'users' })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) {
      body.innerHTML = '<div class="arch-empty"><i class="fa-solid fa-circle-exclamation"></i><strong>Error loading users</strong></div>';
      return;
    }
    const items = d.items || [];
    if (!items.length) {
      body.innerHTML = `<div class="arch-empty">
        <i class="fa-solid fa-users"></i>
        <strong>No archived users</strong>
        <p>Users archived from User Management will appear here.</p>
      </div>`;
      return;
    }

    body.innerHTML = `<div style="overflow-x:auto;">
      <table class="arch-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Provider</th>
            <th>Archived on</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${items.map(u => {
            const dateStr = u.archived_at
              ? new Date(u.archived_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' })
              : '—';
            return `<tr>
              <td style="font-weight:600;color:#fff;">${escHtml(u.name)}</td>
              <td style="color:rgba(255,255,255,0.75);">${escHtml(u.email)}</td>
              <td><span class="arch-badge">${escHtml(u.role || 'customer')}</span></td>
              <td style="color:rgba(255,255,255,0.55);font-size:0.82rem;">${escHtml(u.status || 'active')}</td>
              <td style="color:rgba(255,255,255,0.45);font-size:0.82rem;">${escHtml(u.provider || '—')}</td>
              <td style="color:rgba(255,255,255,0.45);font-size:0.82rem;">${dateStr}</td>
              <td>
                <div class="arch-actions">
                  <button class="btn btn-outline btn-sm" onclick="restoreUser(${u.id})">
                    <i class="fa-solid fa-rotate-left"></i> Restore
                  </button>
                  <button class="btn btn-danger btn-sm" onclick="openDeleteModal(${u.id}, 'user')">
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

function restoreUser(id) {
  if (!confirm('Restore this user? They can sign in again and will appear in User Management.')) return;
  fetch('admin-archive.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'restore_user', id })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast('User restored.', 'success');
      archLoadUsers();
    } else {
      showToast(d.message || 'Failed to restore', 'error');
    }
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
let pendingDeleteKind = 'content';

function openDeleteModal(id, kind = 'content') {
  pendingDeleteId = id;
  pendingDeleteKind = kind;
  const titleEl = document.getElementById('deleteModalTitle');
  const textEl = document.getElementById('deleteModalText');
  if (kind === 'user') {
    if (titleEl) titleEl.textContent = 'Permanently delete user?';
    if (textEl) textEl.innerHTML = 'This removes the account from the database <strong>forever</strong>. Accounts with orders cannot be erased; you will see an error instead.';
  } else {
    if (titleEl) titleEl.textContent = 'Permanently Delete?';
    if (textEl) textEl.innerHTML = 'This action <strong>cannot be undone</strong>. The item will be removed from the database forever.';
  }
  document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() {
  pendingDeleteId = null;
  pendingDeleteKind = 'content';
  const titleEl = document.getElementById('deleteModalTitle');
  const textEl = document.getElementById('deleteModalText');
  if (titleEl) titleEl.textContent = 'Permanently Delete?';
  if (textEl) textEl.innerHTML = 'This action <strong>cannot be undone</strong>. The item will be removed from the database forever.';
  document.getElementById('deleteModal').classList.remove('open');
}
function confirmDelete() {
  if (!pendingDeleteId) return;
  const btn = document.getElementById('confirmDeleteBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting…';

  const payload = pendingDeleteKind === 'user'
    ? { action: 'permanent_delete_user', id: pendingDeleteId }
    : { action: 'permanent_delete', id: pendingDeleteId };

  fetch('admin-archive.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(d => {
    const wasUser = pendingDeleteKind === 'user';
    closeDeleteModal();
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Forever';
    if (d.success) {
      showToast(wasUser ? 'User permanently removed.' : 'Item permanently deleted.', 'success');
      if (wasUser) archLoadUsers();
      else archLoad(currentSub);
    } else {
      showToast(d.message || 'Failed to delete', 'error');
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Forever';
    showToast('Network error. Please try again.', 'error');
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

