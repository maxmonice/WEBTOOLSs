<?php
require_once 'admin-config.php';
require_once '../activity-logger.php';
requireAdmin();

// Handle log operations (JSON or form body)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = $raw !== '' && $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $data = $_POST;
    }

    if (($data['action'] ?? '') === 'create_log') {
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
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Log entry created successfully']);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get logs for display using activity logger
$logs = getRecentActivities(100);

// Get activity statistics
$stats = getActivityStats();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Security & Logs — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<link rel="stylesheet" href="admin-logs.css?v=<?= time() ?>">
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <aside class="sidebar" id="sidebar">
<?php $adminNavActive = 'logs'; require __DIR__ . '/admin-sidebar-nav.php'; ?>
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
        <?php require __DIR__ . '/admin-topbar-right.php'; ?>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Security & Audit Logs</h1>
          <p>Monitor system activity, user actions, and security events.</p>
        </div>
        <a href="../report-download.php?type=audit" class="btn btn-outline"><i class="fa-solid fa-file-pdf"></i> Audit Logs Report</a>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= $stats['users_today'] ?></div>
          <div class="stat-card-label">Active Users Today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="stat-card-value"><?= $stats['security'] ?></div>
          <div class="stat-card-label">Security Events</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= $stats['today'] ?></div>
          <div class="stat-card-label">Activities Today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-server"></i></div>
          <div class="stat-card-value">Online</div>
          <div class="stat-card-label">System Status</div>
        </div>
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

<script defer src="admin-notifications.js?v=<?= time() ?>"></script>
<script src="admin-logs.js?v=<?= time() ?>"></script>
</body>
</html>
