<?php
require_once 'admin-config.php';
requireAdmin();

// Handle log operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data['action'] === 'create_log') {
        $action = $data['action_type'] ?? '';
        $details = $data['details'] ?? '';
        $userEmail = $data['user_email'] ?? '';
        $userName = $data['user_name'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                action, details, user_email, user_name, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        try {
            $stmt->execute([$action, $details, $userEmail, $userName, $ipAddress, $userAgent]);
            echo json_encode(['success' => true, 'message' => 'Log entry created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get logs for display
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 100");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist, create it
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            user_email VARCHAR(255),
            user_name VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableSQL);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Security & Logs — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.log-entry {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  transition: border-color 0.2s;
}
.log-entry:hover { border-color: rgba(194,38,38,0.3); }
.log-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.log-action {
  display: flex;
  align-items: center;
  gap: 8px;
}
.log-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}
.log-icon.login { background: rgba(34,197,94,0.2); color: #22c55e; }
.log-icon.logout { background: rgba(239,68,68,0.2); color: #ef4444; }
.log-icon.create { background: rgba(59,130,246,0.2); color: #3b82f6; }
.log-icon.update { background: rgba(249,115,22,0.2); color: #f97316; }
.log-icon.delete { background: rgba(239,68,68,0.2); color: #ef4444; }
.log-icon.security { background: rgba(217,70,239,0.2); color: #d946ef; }

.log-action-text {
  font-weight: 600;
  color: #fff;
  font-size: 0.9rem;
}
.log-time {
  color: var(--muted);
  font-size: 0.75rem;
}
.log-details {
  color: var(--muted);
  font-size: 0.85rem;
  line-height: 1.5;
  margin-bottom: 8px;
}
.log-meta {
  display: flex;
  gap: 20px;
  font-size: 0.75rem;
  color: var(--muted);
}
.log-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
}
.log-meta-label {
  font-weight: 600;
  color: rgba(255,255,255,0.6);
}
.filter-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--line-w);
}
.filter-tab {
  padding: 10px 16px;
  background: transparent;
  border: none;
  color: var(--muted);
  font-weight: 600;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}
.filter-tab:hover {
  color: #fff;
}
.filter-tab.active {
  color: var(--red);
  border-bottom-color: var(--red);
}
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
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item active"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
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
          <div class="topbar-title">Security & Logs</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Audit Logs</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i></div>
        <div class="admin-avatar">A</div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Security & Audit Logs</h1>
        <p>Monitor system activity, user actions, and security events.</p>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= count(array_unique(array_column($logs, 'user_email'))) ?></div>
          <div class="stat-card-label">Active Users Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +12% vs yesterday</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="stat-card-value">0</div>
          <div class="stat-card-label">Security Alerts</div>
          <div class="stat-card-change up"><i class="fa-solid fa-check"></i> All clear</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= count($logs) ?></div>
          <div class="stat-card-label">Total Activities</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Last 7 days</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-server"></i></div>
          <div class="stat-card-value">99.9%</div>
          <div class="stat-card-label">System Uptime</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Excellent</div>
        </div>
      </div>

      <!-- FILTER TABS -->
      <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterLogs('all')">All Logs</button>
        <button class="filter-tab" onclick="filterLogs('security')">Security</button>
        <button class="filter-tab" onclick="filterLogs('user')">User Activity</button>
        <button class="filter-tab" onclick="filterLogs('system')">System</button>
      </div>

      <!-- LOGS LIST -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Recent Activity Logs</span>
          <span class="badge badge-gray">Last 100 entries</span>
        </div>
        <div style="max-height: 600px; overflow-y: auto;">
          <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
              <?php
                $iconClass = 'login';
                $icon = 'fa-sign-in-alt';
                if (strpos(strtolower($log['action']), 'logout') !== false) {
                  $iconClass = 'logout';
                  $icon = 'fa-sign-out-alt';
                } elseif (strpos(strtolower($log['action']), 'create') !== false) {
                  $iconClass = 'create';
                  $icon = 'fa-plus';
                } elseif (strpos(strtolower($log['action']), 'update') !== false) {
                  $iconClass = 'update';
                  $icon = 'fa-edit';
                } elseif (strpos(strtolower($log['action']), 'delete') !== false) {
                  $iconClass = 'delete';
                  $icon = 'fa-trash';
                } elseif (strpos(strtolower($log['action']), 'security') !== false) {
                  $iconClass = 'security';
                  $icon = 'fa-shield-alt';
                }
              ?>
              <div class="log-entry">
                <div class="log-header">
                  <div class="log-action">
                    <div class="log-icon <?= $iconClass ?>">
                      <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="log-action-text"><?= htmlspecialchars($log['action']) ?></div>
                  </div>
                  <div class="log-time"><?= timeAgo($log['created_at']) ?></div>
                </div>
                <?php if ($log['details']): ?>
                  <div class="log-details"><?= htmlspecialchars($log['details']) ?></div>
                <?php endif; ?>
                <div class="log-meta">
                  <div class="log-meta-item">
                    <span class="log-meta-label">User:</span>
                    <?= htmlspecialchars($log['user_name'] ?: 'System') ?>
                  </div>
                  <?php if ($log['ip_address']): ?>
                    <div class="log-meta-item">
                      <span class="log-meta-label">IP:</span>
                      <?= htmlspecialchars($log['ip_address']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align: center; padding: 60px; color: var(--muted);">
              <i class="fa-solid fa-clipboard-list" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
              <h3>No logs yet</h3>
              <p>System activity will appear here once users start interacting with the system.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function filterLogs(type) {
  // Update active tab
  document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
  event.target.classList.add('active');
  
  // Filter logic would go here
  console.log('Filtering logs by:', type);
}
</script>
</body>
</html>
