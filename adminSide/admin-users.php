<?php
require_once 'admin-config.php';
require_once '../activity-logger.php';
requireAdmin();  // 🔒 must be admin

// Handle user actions via POST
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add new user
    if (($_POST['action'] ?? '') === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $role     = trim($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            $errorMsg = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $errorMsg = 'Password must be at least 8 characters.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, provider, email_verified, created_at)
                     VALUES (?, ?, ?, ?, 1, NOW())'
                )->execute([$name, $email, $hash, 'email']);
                logActivity('user_added', "Admin added new user: " . htmlspecialchars($name), $_SESSION['admin_user_email'] ?? '', $_SESSION['admin_user_name'] ?? 'Admin');
                $successMsg = "User <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
            } catch (\Throwable $e) {
                $errorMsg = 'Error: ' . $e->getMessage();
            }
        }
    }

    // Toggle suspend / reactivate (AJAX endpoint)
    if (($_POST['action'] ?? '') === 'toggle_suspend') {
        $uid = (int)($_POST['user_id'] ?? 0);
        
        if ($uid > 0) {
            try {
                // First, add status column if it doesn't exist
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active'");
                } catch (\Throwable $_) {
                    // Column already exists, continue
                }
                
                // Check current status
                $stmt = $pdo->prepare('SELECT name, status FROM users WHERE id = ?');
                $stmt->execute([$uid]);
                $u = $stmt->fetch();
                
                if ($u) {
                    $currentStatus = $u['status'] ?? 'active';
                    $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
                    
                    $updateStmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
                    $result = $updateStmt->execute([$newStatus, $uid]);
                    
                    if ($result) {
                        $action = $newStatus === 'suspended' ? 'user_suspended' : 'user_reactivated';
                        $details = "Admin " . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . " user: " . $u['name'];
                        logActivity($action, $details, $_SESSION['admin_user_email'] ?? '', $_SESSION['admin_user_name'] ?? 'Admin');
                        
                        $successMsg = "User <strong>" . htmlspecialchars($u['name']) . "</strong> " . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . ".";
                    } else {
                        $errorMsg = "Failed to update user status";
                    }
                    echo json_encode(['success' => true, 'message' => "User " . ($newStatus === 'suspended' ? 'suspended' : 'reactivated'), 'new_status' => $newStatus]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    exit;
                }
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
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
                
                logActivity('user_deleted', "Admin deleted user: " . htmlspecialchars($u['name'] ?? ''), $_SESSION['admin_user_email'] ?? '', $_SESSION['admin_user_name'] ?? 'Admin');
                $successMsg = "User <strong>" . htmlspecialchars($u['name'] ?? '') . "</strong> deleted.";
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
        "SELECT id, name, email, provider, email_verified, created_at
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
    $activeCount    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email != 'admin@gmail.com' AND status = 'active'")->fetchColumn();
    $suspendedCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email != 'admin@gmail.com' AND status = 'suspended'")->fetchColumn();
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

<link rel="stylesheet" href="admin-users.css?v=<?= time() ?>">
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
      <a href="admin-promos.php" class="nav-item"><i class="fa-solid fa-ticket"></i> Promo Management</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-account.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="../index.php" class="logout-btn" style="background: #22c55e; color: #fff;"><i class="fa-solid fa-home"></i> Home</a>
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
        <div class="notification-dropdown">
          <div class="topbar-badge" onclick="toggleNotifications()">
            <i class="fa-regular fa-bell"></i>
            <span class="badge-dot"></span>
          </div>
          <div class="notification-menu" id="notificationMenu">
            <div class="notification-header">
              <h4>Notifications</h4>
              <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
            </div>
            <div class="notification-list">
              <div class="notification-item unread">
                <div class="notification-icon">
                  <i class="fa-solid fa-shopping-cart"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">New Order Received</div>
                  <div class="notification-message">Order #ORD-0001 has been placed</div>
                  <div class="notification-time">2 minutes ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
              <div class="notification-item unread">
                <div class="notification-icon">
                  <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">New Booking Confirmed</div>
                  <div class="notification-message">Event booking for May 15, 2025</div>
                  <div class="notification-time">15 minutes ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
              <div class="notification-item">
                <div class="notification-icon">
                  <i class="fa-solid fa-user-plus"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">New User Registered</div>
                  <div class="notification-message">John Doe joined the platform</div>
                  <div class="notification-time">1 hour ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
              <div class="notification-item">
                <div class="notification-icon">
                  <i class="fa-solid fa-truck"></i>
                </div>
                <div class="notification-content">
                  <div class="notification-title">Order Shipped</div>
                  <div class="notification-message">Order #ORD-0002 has been shipped</div>
                  <div class="notification-time">2 hours ago</div>
                </div>
                <div class="notification-close" onclick="removeNotification(this)">
                  <i class="fa-solid fa-times"></i>
                </div>
              </div>
            </div>
            <div class="notification-footer">
              <a href="admin-logs.php" class="view-all-link">View all notifications</a>
            </div>
          </div>
        </div>
        <a href="admin-account.php" class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_user_name'] ?? 'A', 0, 1)) ?></a>
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
                <th>Verified</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:24px;color:var(--muted);">
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
                  <span class="verified-dot <?= $u['email_verified'] ? 'dot-yes' : 'dot-no' ?>"></span>
                  <?= $u['email_verified'] ? 'Yes' : 'No' ?>
                </td>
                <td>
                  <?= $u['created_at'] ? date('M d, Y', strtotime($u['created_at'])) : '—' ?>
                </td>
                <td>
                  <?php 
                  $userStatus = $u['status'] ?? 'active';
                  $statusClass = $userStatus === 'active' ? 'dot-yes' : 'dot-no';
                  $statusText = ucfirst($userStatus);
                  ?>
                  <span class="verified-dot <?= $statusClass ?>"></span>
                  <?= $statusText ?>
                </td>
                <td>
                  <div class="flex-gap">
                    <?php 
                    $userStatus = $u['status'] ?? 'active';
                    $buttonTitle = $userStatus === 'active' ? 'Suspend User' : 'Reactivate User';
                    $buttonIcon = $userStatus === 'active' ? 'fa-ban' : 'fa-check';
                    $confirmText = $userStatus === 'active' ? 'Suspend this user account?' : 'Reactivate this user account?';
                    ?>
                    <button type="button" class="action-btn"
                            title="<?= $buttonTitle ?>"
                            onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= $confirmText ?>')">
                      <i class="fa-solid <?= $buttonIcon ?>"></i>
                    </button>
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
        <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required/>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Add User</button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>





<script src="admin-users.js?v=<?= time() ?>"></script>
</body>
</html>