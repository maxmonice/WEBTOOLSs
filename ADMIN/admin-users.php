<?php
session_start();

// Database configuration for XAMPP
$host = 'localhost';
$dbname = 'lukes_seafood';
$username = 'root';
$password = '';  // XAMPP default - no password

// Check connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Show detailed error for debugging
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px; font-family: Arial;'>";
    echo "<strong>❌ Database Connection Error:</strong><br><br>";
    echo "<strong>Details:</strong><br>";
    echo "Host: " . htmlspecialchars($host) . "<br>";
    echo "Database: " . htmlspecialchars($dbname) . "<br>";
    echo "Username: " . htmlspecialchars($username) . "<br>";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "<strong>Solutions:</strong><br>";
    echo "1. Make sure XAMPP is started (Start Apache and MySQL)<br>";
    echo "2. Make sure database 'lukes_seafood' exists<br>";
    echo "3. Check phpMyAdmin to verify database<br>";
    echo "</div>";
    exit;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new user
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $firstName = htmlspecialchars($_POST['first_name']);
        $lastName = htmlspecialchars($_POST['last_name']);
        $email = htmlspecialchars($_POST['email']);
        $role = htmlspecialchars($_POST['role']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, role, password, status, created_at) 
                                 VALUES (:firstName, :lastName, :email, :role, :password, 'active', NOW())");
            $stmt->execute([
                ':firstName' => $firstName,
                ':lastName' => $lastName,
                ':email' => $email,
                ':role' => $role,
                ':password' => $password
            ]);
            $success = "✅ User added successfully!";
        } catch (PDOException $e) {
            $error = "❌ Error adding user: " . $e->getMessage();
        }
    }
    
    // Suspend/Reactivate user
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_suspend') {
        $userId = (int)$_POST['user_id'];
        
        try {
            // Get current status
            $stmt = $pdo->prepare("SELECT status FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $newStatus = ($user['status'] === 'active') ? 'suspended' : 'active';
                
                $updateStmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
                $updateStmt->execute([
                    ':status' => $newStatus,
                    ':id' => $userId
                ]);
                
                $success = "✅ User " . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . " successfully!";
            }
        } catch (PDOException $e) {
            $error = "❌ Error updating user: " . $e->getMessage();
        }
    }
}

// Get search parameters
$searchTerm = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : '';

// Fetch users with filtering
$query = "SELECT id, first_name, last_name, email, role, status, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $query .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$searchTerm%";
}

if (!empty($statusFilter)) {
    $query .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];

$activeStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$activeUsers = $activeStmt->fetch(PDO::FETCH_ASSOC)['count'];

$suspendedStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'suspended'");
$suspendedUsers = $suspendedStmt->fetch(PDO::FETCH_ASSOC)['count'];

$weekStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$weekUsers = $weekStmt->fetch(PDO::FETCH_ASSOC)['count'];
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
.role-tag {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:100px; font-size:0.7rem; font-weight:700;
    letter-spacing:0.06em; text-transform:uppercase;
}
.role-admin  { background:rgba(194,38,38,0.18); color:#ff6b6b; border:1px solid rgba(194,38,38,0.3); }
.role-customer { background:rgba(52,152,219,0.12); color:#3498db; border:1px solid rgba(52,152,219,0.25); }
.action-btn {
    width:30px; height:30px; border-radius:6px; border:1px solid var(--line-w);
    background:transparent; color:var(--muted); font-size:0.8rem;
    display:inline-grid; place-items:center; cursor:pointer; transition:all 0.2s;
}
.action-btn:hover { border-color:var(--red); color:#ff6b6b; background:rgba(194,38,38,0.1); }
.action-btn.edit:hover { border-color:#3498db; color:#3498db; background:rgba(52,152,219,0.1); }

.stat-mini { display:flex; align-items:center; gap:14px; }
.stat-mini-val { font-family:'Aclonica',sans-serif; font-size:1.4rem; color:#fff; }
.stat-mini-label { font-size:0.72rem; color:var(--muted); }

/* Modal form */
.modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
.alert-success { background: rgba(46,204,113,0.12); color: #2ecc71; border: 1px solid rgba(46,204,113,0.25); }
.alert-error { background: rgba(194,38,38,0.12); color: #ff6b6b; border: 1px solid rgba(194,38,38,0.25); }
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
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
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
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i><span class="badge-dot"></span></div>
        <div class="admin-avatar">A</div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>User Management</h1>
          <p>View, manage and monitor all registered users.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addUserModal')"><i class="fa-solid fa-user-plus"></i> Add User</button>
      </div>

      <!-- ALERTS -->
      <?php if (isset($success)): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-check-circle"></i> <?php echo $success; ?>
        </div>
      <?php endif; ?>
      <?php if (isset($error)): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
      <?php endif; ?>

      <!-- MINI STATS -->
      <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?php echo $totalUsers; ?></div>
          <div class="stat-card-label">Total Users</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +<?php echo $weekUsers; ?> this week</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-check"></i></div>
          <div class="stat-card-value"><?php echo $activeUsers; ?></div>
          <div class="stat-card-label">Active Users</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> <?php echo ($totalUsers > 0) ? round(($activeUsers/$totalUsers)*100, 1) : 0; ?>% active</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-slash"></i></div>
          <div class="stat-card-value"><?php echo $suspendedUsers; ?></div>
          <div class="stat-card-label">Suspended Accounts</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs review</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-user-plus"></i></div>
          <div class="stat-card-value"><?php echo $weekUsers; ?></div>
          <div class="stat-card-label">New This Week</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> recent signups</div>
        </div>
      </div>

      <!-- TABLE PANEL -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Users</span>
          <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="search-input" name="search" placeholder="Search users..." value="<?php echo $searchTerm; ?>"/>
              </div>
              <select class="form-control" name="status" style="width:auto;padding:8px 12px;font-size:0.82rem;">
                <option value="">All Status</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </form>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                <tr data-user-id="<?php echo $user['id']; ?>">
                  <td>
                    <div class="flex-gap">
                      <div class="user-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                      <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td><span class="role-tag role-<?php echo strtolower($user['role']); ?>"><?php echo htmlspecialchars($user['role']); ?></span></td>
                  <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                  <td>
                    <span class="badge badge-<?php echo ($user['status'] === 'active') ? 'green' : 'red'; ?>">
                      <?php echo ucfirst($user['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div class="flex-gap">
                      <button class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_suspend">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="action-btn" title="<?php echo ($user['status'] === 'active') ? 'Suspend' : 'Reactivate'; ?>" onclick="return confirm('Are you sure?')">
                          <i class="fa-solid fa-<?php echo ($user['status'] === 'active') ? 'ban' : 'rotate-left'; ?>"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 20px; color: var(--muted);">
                    No users found. <?php if (!empty($searchTerm) || !empty($statusFilter)): ?>Try adjusting your filters.<?php else: ?>Add your first user!<?php endif; ?>
                  </td>
                </tr>
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
      <div class="modal-grid">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" placeholder="e.g. Maria" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <input type="text" name="last_name" class="form-control" placeholder="e.g. Santos" required/>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="user@email.com" required/>
      </div>
      <div class="form-group">
        <label class="form-label">Role</label>
        <select name="role" class="form-control" required>
          <option value="">-- Select Role --</option>
          <option value="Customer">Customer</option>
          <option value="Admin">Admin</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="Enter password" required/>
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
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});
</script>
</body>
</html>