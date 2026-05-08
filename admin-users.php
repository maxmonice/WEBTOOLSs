<?php
require_once 'admin-config.php';
require_once 'Notifications.php';
requireAdmin();  // 🔒 must be admin

// Initialize notifications
$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// Handle user actions via POST
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add new user
    if (($_POST['action'] ?? '') === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $role     = trim($_POST['role'] ?? 'customer');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
            $errorMsg = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $errorMsg = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $errorMsg = 'Passwords do not match.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, provider, role, email_verified, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, NOW())'
                )->execute([$name, $email, $hash, 'email', $role]);
                $successMsg = "User <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
                
                // Audit log
                logAdminActivity($pdo, 'user_created', "Created user {$name} ({$email}) with role {$role}");
                
                // Get the new user ID
                $newUserId = $pdo->lastInsertId();
                
                // Create comprehensive notifications
                $notifications = new Notifications($pdo);
                $notifications->autoNotify('admin_created_user', [
                    'admin_name' => $_SESSION['user_name'] ?? 'Admin',
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'user_id' => $newUserId
                ]);
            } catch (\Throwable $e) {
                $errorMsg = 'Error: ' . $e->getMessage();
            }
        }
    }

    // Toggle suspend / reactivate
    if (($_POST['action'] ?? '') === 'toggle_suspend') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            try {
                // Check current status (using a 'status' column if it exists, else use is_active flag)
                $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
                $stmt->execute([$uid]);
                $u = $stmt->fetch();
                if ($u) {
                    $newStatus = 'active';
                    // Try toggling a `status` column; fall back to `is_active`
                    try {
                        $cur = $pdo->prepare('SELECT status FROM users WHERE id = ?');
                        $cur->execute([$uid]);
                        $row = $cur->fetch();
                        $newStatus = ($row['status'] ?? 'active') === 'active' ? 'suspended' : 'active';
                        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')
                            ->execute([$newStatus, $uid]);
                    } catch (\Throwable $_) {
                        // If no status column, skip
                    }
                    $successMsg = "User <strong>" . htmlspecialchars($u['name']) . "</strong> updated.";
                    
                    // Audit log
                    logAdminActivity($pdo, 'user_updated', "Toggled status for user {$u['name']} (ID: {$uid}) to {$newStatus}");
                }
            } catch (\Throwable $e) {
                $errorMsg = 'Error: ' . $e->getMessage();
            }
        }
    }

    // Delete user
    if (($_POST['action'] ?? '') === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            try {
                $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
                $stmt->execute([$uid]);
                $u = $stmt->fetch();
                $pdo->prepare('DELETE FROM users WHERE id = ? AND email != ?')
                    ->execute([$uid, 'admin@gmail.com']);
                $successMsg = "User <strong>" . htmlspecialchars($u['name'] ?? '') . "</strong> deleted.";
                
                // Audit log
                logAdminActivity($pdo, 'user_deleted', "Deleted user {$u['name']} (ID: {$uid})");
            } catch (\Throwable $e) {
                $errorMsg = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// ── Filters ───────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$provider = trim($_GET['provider'] ?? '');

// ── Fetch users ───────────────────────────────────
$whereClause = "WHERE email != 'admin@gmail.com'";
$params      = [];

if ($search !== '') {
    $whereClause .= " AND (name LIKE :s OR email LIKE :s)";
    $params[':s'] = "%$search%";
}
if ($provider !== '') {
    $whereClause .= " AND provider = :p";
    $params[':p'] = $provider;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, name, email, provider, role, email_verified, status, created_at
         FROM users $whereClause
         ORDER BY created_at DESC"
    );
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (\Throwable $_) {
    $users = [];
}

// ── Stats ─────────────────────────────────────────
$stats = getAdminStats($pdo);

// Active vs suspended (if status column exists)
$activeCount    = 0;
$suspendedCount = 0;
try {
    $activeCount    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND email != 'admin@gmail.com' AND status = 'active'")->fetchColumn();
    $suspendedCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND email != 'admin@gmail.com' AND status = 'suspended'")->fetchColumn();
} catch (\Throwable $_) {
    $activeCount    = $stats['total_users'];
    $suspendedCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>User Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.provider-tag {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:100px; font-size:0.7rem; font-weight:700;
    letter-spacing:0.06em; text-transform:uppercase;
}
.provider-email    { background:rgba(194,38,38,0.18); color:#ff8080; border:1px solid rgba(194,38,38,0.3); }
.provider-google   { background:rgba(66,133,244,0.15); color:#6aa0f7; border:1px solid rgba(66,133,244,0.25); }
.provider-facebook { background:rgba(24,119,242,0.15); color:#60a0f7; border:1px solid rgba(24,119,242,0.25); }
.role-tag {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:100px; font-size:0.7rem; font-weight:700;
    letter-spacing:0.06em; text-transform:uppercase;
}
.role-customer { background:rgba(107,114,128,0.15); color:#6b7280; border:1px solid rgba(107,114,128,0.25); }
.role-staff    { background:rgba(59,130,246,0.15); color:#3b82f6; border:1px solid rgba(59,130,246,0.25); }
.role-admin    { background:rgba(220,38,38,0.15); color:#dc2626; border:1px solid rgba(220,38,38,0.25); }
.verified-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:5px; }
.dot-yes { background:#22c55e; }
.dot-no  { background:#ef4444; }
.action-btn {
    width:30px; height:30px; border-radius:6px; border:1px solid var(--line-w);
    background:transparent; color:var(--muted); font-size:0.8rem;
    display:inline-grid; place-items:center; cursor:pointer; transition:all 0.2s;
}
.action-btn:hover { border-color:var(--red); color:#ff6b6b; background:rgba(194,38,38,0.1); }
.action-btn.edit:hover { border-color:#3498db; color:#3498db; background:rgba(52,152,219,0.1); }
.password-toggle {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--muted);
    cursor: pointer; padding: 8px; font-size: 0.9rem;
    transition: color 0.2s;
}
.password-toggle:hover { color: var(--red); }
.alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.86rem; }
.alert-success { background:rgba(46,204,113,0.12); color:#2ecc71; border:1px solid rgba(46,204,113,0.25); }
.alert-error   { background:rgba(194,38,38,0.12);  color:#ff6b6b; border:1px solid rgba(194,38,38,0.25); }

.notification-dropdown {
    position: absolute; top: 100%; right: 0; width: 320px;
    background: var(--card2); border: 1px solid var(--line-w);
    border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    z-index: 1000; display: none; max-height: 400px; overflow-y: auto;
}
.notification-dropdown.show { display: block; }
.notification-header {
    padding: 12px 16px; border-bottom: 1px solid var(--line-w);
    display: flex; justify-content: space-between; align-items: center;
}
.notification-header h3 { margin: 0; font-size: 0.9rem; color: #fff; }
.notification-header .mark-all {
    font-size: 0.75rem; color: var(--red); text-decoration: none;
    background: transparent; border: none; cursor: pointer;
}
.notification-header .mark-all:hover { text-decoration: underline; }
.notification-item {
    padding: 12px 16px; border-bottom: 1px solid var(--line-w);
    cursor: pointer; transition: background 0.2s;
}
.notification-item:hover { background: rgba(194,38,38,0.05); }
.notification-item:last-child { border-bottom: none; }
.notification-item.unread {
    background: rgba(52,152,219,0.08); border-left: 3px solid #3498db;
}
.notification-content {
    display: flex; gap: 12px; align-items: flex-start;
}
.notification-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.9rem;
}
.notification-text { flex: 1; }
.notification-title {
    font-size: 0.85rem; font-weight: 600; color: #fff; margin-bottom: 4px;
}
.notification-message {
    font-size: 0.78rem; color: var(--muted); line-height: 1.4;
}
.notification-time {
    font-size: 0.72rem; color: var(--muted); margin-top: 4px;
}
.notification-empty {
    padding: 24px; text-align: center; color: var(--muted);
    font-size: 0.85rem;
}
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Admin Panel</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="admin-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="admin-users.php" class="nav-item active"><i class="fa-solid fa-users"></i> User Management</a>
      <a href="admin-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
      <a href="admin-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
      <a href="admin-content.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Content Management</a>
      <a href="admin-messages.php" class="nav-item"><i class="fa-solid fa-message"></i> Messages</a>
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
          <div class="topbar-title">User Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Users</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge" style="position: relative;" onclick="toggleNotifications()">
          <i class="fa-regular fa-bell"></i>
          <?php if ($unreadCount > 0): ?>
          <span class="badge-dot" style="background: var(--red);"></span>
          <span class="notification-count" style="position: absolute; top: -8px; right: -8px; background: var(--red); color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; min-width: 18px; text-align: center;"><?= $unreadCount ?></span>
          <?php endif; ?>
        </div>
        
        <!-- Notification Dropdown -->
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
                    <div class="notification-icon" style="background: <?= getNotificationColor($notif['type']) ?>20; color: <?= getNotificationColor($notif['type']) ?>;">
                      <i class="fa-solid <?= getNotificationIcon($notif['type']) ?>"></i>
                    </div>
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
        
        <a href="admin-settings.php" class="admin-avatar" title="Account Settings" style="text-decoration: none; cursor: pointer;">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
        </a>
        <a href="account-dashboard.php?user_view=true" class="btn btn-success" title="Go to User Webpage" style="margin-left: 12px; padding: 10px 18px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; letter-spacing: 0.5px; border: 2px solid var(--red); border-radius: 6px; background: linear-gradient(135deg, #C22626, #8B0A1E); box-shadow: 0 4px 12px rgba(194, 38, 38, 0.4); transition: all 0.3s; color: #ff6b6b;">
          <i class="fa-solid fa-user"></i> User View
        </a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>User Management</h1>
          <p>View, manage and monitor all registered customers.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addUserModal')">
          <i class="fa-solid fa-user-plus"></i> Add User
        </button>
      </div>

      <!-- ALERTS -->
      <?php if ($successMsg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
        <div class="alert alert-error"><i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= number_format($stats['total_users']) ?></div>
          <div class="stat-card-label">Total Customers</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +<?= $stats['new_users_week'] ?> this week</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-check"></i></div>
          <div class="stat-card-value"><?= number_format($activeCount) ?></div>
          <div class="stat-card-label">Active Accounts</div>
          <div class="stat-card-change up">
            <i class="fa-solid fa-arrow-up"></i>
            <?= $stats['total_users'] > 0 ? round($activeCount / $stats['total_users'] * 100, 1) : 0 ?>% rate
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-slash"></i></div>
          <div class="stat-card-value"><?= number_format($suspendedCount) ?></div>
          <div class="stat-card-label">Suspended</div>
          <div class="stat-card-change <?= $suspendedCount > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-<?= $suspendedCount > 0 ? 'down' : 'up' ?>"></i>
            <?= $suspendedCount > 0 ? 'needs review' : 'none suspended' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-plus"></i></div>
          <div class="stat-card-value"><?= number_format($stats['new_users_week']) ?></div>
          <div class="stat-card-label">New This Week</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> recent signups</div>
        </div>
      </div>

      <!-- TABLE -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Users
            <span style="color:var(--muted);font-weight:400;font-size:0.82rem;margin-left:8px;">
              (<?= count($users) ?> shown)
            </span>
          </span>
          <div class="filter-bar">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="search-input" name="search"
                       placeholder="Search name or email…"
                       value="<?= htmlspecialchars($search) ?>"/>
              </div>
              <select class="form-control" name="provider"
                      style="width:auto;padding:8px 12px;font-size:0.82rem;">
                <option value="">All Providers</option>
                <option value="email"    <?= $provider==='email'    ?'selected':'' ?>>Email</option>
                <option value="google"   <?= $provider==='google'   ?'selected':'' ?>>Google</option>
                <option value="facebook" <?= $provider==='facebook' ?'selected':'' ?>>Facebook</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Filter</button>
              <?php if ($search || $provider): ?>
              <a href="admin-users.php" class="btn btn-outline btn-sm">Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th>Provider</th>
                <th>Role</th>
                <th>Verified</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
              <tr>
                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted);">
                  <?= $search || $provider ? 'No users match your filters.' : 'No users registered yet.' ?>
                </td>
              </tr>
              <?php else: ?>
              <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar">
                      <?= strtoupper(substr($u['name'] ?? '?', 0, 2)) ?>
                    </div>
                    <strong><?= htmlspecialchars($u['name'] ?? '—') ?></strong>
                  </div>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <span class="provider-tag provider-<?= htmlspecialchars($u['provider'] ?? 'email') ?>">
                    <i class="fa-<?= $u['provider'] === 'google' ? 'brands fa-google'
                                  : ($u['provider'] === 'facebook' ? 'brands fa-facebook' : 'solid fa-envelope') ?>"></i>
                    <?= htmlspecialchars(ucfirst($u['provider'] ?? 'email')) ?>
                  </span>
                </td>
                <td>
                  <span class="role-tag role-<?= htmlspecialchars($u['role'] ?? 'customer') ?>">
                    <i class="fa-solid fa-<?= $u['role'] === 'admin' ? 'user-shield' 
                                  : ($u['role'] === 'staff' ? 'user-tie' : 'user') ?>"></i>
                    <?= htmlspecialchars(ucfirst($u['role'] ?? 'customer')) ?>
                  </span>
                </td>
                <td>
                  <span class="verified-dot <?= $u['email_verified'] ? 'dot-yes' : 'dot-no' ?>"></span>
                  <?= $u['email_verified'] ? 'Yes' : 'No' ?>
                </td>
                <td>
                  <?= $u['created_at'] ? date('M d, Y', strtotime($u['created_at'])) : '—' ?>
                </td>
                <td><?= statusBadge($u['status'] ?? 'active') ?></td>
                <td>
                  <div class="flex-gap">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="toggle_suspend">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="action-btn"
                              title="<?= ($u['status'] ?? 'active') === 'suspended' ? 'Reactivate Account' : 'Suspend Account' ?>"
                              onclick="return confirm('Toggle this account?')">
                        <i class="fa-solid fa-<?= ($u['status'] ?? 'active') === 'suspended' ? 'rotate-left' : 'ban' ?>"></i>
                      </button>
                    </form>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="action-btn"
                              title="Delete User"
                              onclick="return confirm('Permanently delete this user?')">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--red);margin-right:8px;"></i>Add New User</div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Maria Santos" required/>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="user@email.com" required/>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div style="position: relative;">
          <input type="password" name="password" id="password" class="form-control" placeholder="At least 8 characters" required/>
          <button type="button" class="password-toggle" onclick="togglePassword('password')">
            <i class="fa-solid fa-eye" id="password-icon"></i>
          </button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <div style="position: relative;">
          <input type="password" name="confirmPassword" id="confirm_password" class="form-control" placeholder="Confirm password" required/>
          <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
            <i class="fa-solid fa-eye" id="confirm_password-icon"></i>
          </button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">User Role</label>
        <select name="role" class="form-control" required>
          <option value="customer">Customer</option>
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Add User</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function openModal(id)   { document.getElementById(id).classList.add('open'); }

// Notification functions
function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  dropdown.classList.toggle('show');
  
  // Close dropdown when clicking outside
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
  .then(data => {
    if (data.success) {
      location.reload();
    }
  });
}

function markAllNotificationsRead() {
  fetch('admin-handle-notifications.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_all_read' })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    }
  });
}
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const icon = document.getElementById(fieldId + '-icon');
  
  if (field.type === 'password') {
    field.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    field.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

function closeModal(id)  { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});
</script>
</body>
</html>
