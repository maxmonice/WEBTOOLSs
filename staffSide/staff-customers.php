<?php
require_once 'staff-config.php';
requireStaff();

// Staff can ONLY READ customers. No add, suspend, or delete.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block all POST actions — staff is read-only here
    http_response_code(403);
    die('Forbidden: Staff accounts cannot modify customer data. Contact an admin.');
}

$search = trim($_GET['search'] ?? '');

$whereClause = "WHERE email != 'admin@gmail.com'";
$params      = [];
if ($search !== '') {
    $whereClause .= ' AND (name LIKE :s OR email LIKE :s)';
    $params[':s'] = "%$search%";
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

// Basic counts (no sensitive data)
$totalCustomers = 0;
try {
    $totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email != 'admin@gmail.com'")->fetchColumn();
} catch (\Throwable $_) {}

$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Customers — Luke's Staff</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.role-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 100px; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    background: rgba(243,156,18,0.15); color: #f39c12;
    border: 1px solid rgba(243,156,18,0.3); margin-top: 6px;
}
.nav-item.locked {
    opacity: 0.38; pointer-events: none; cursor: not-allowed; position: relative;
}
.nav-item.locked::after {
    content: '\f023'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
    font-size: 0.65rem; margin-left: auto; color: rgba(255,255,255,0.3);
}
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.provider-tag {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:100px; font-size:0.7rem; font-weight:700;
    letter-spacing:0.06em; text-transform:uppercase;
}
.provider-email    { background:rgba(194,38,38,0.18);   color:#ff8080; border:1px solid rgba(194,38,38,0.3); }
.provider-google   { background:rgba(66,133,244,0.15);  color:#6aa0f7; border:1px solid rgba(66,133,244,0.25); }
.provider-facebook { background:rgba(24,119,242,0.15);  color:#60a0f7; border:1px solid rgba(24,119,242,0.25); }
.verified-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:5px; }
.dot-yes { background:#22c55e; }
.dot-no  { background:#ef4444; }
.readonly-banner {
    display: flex; align-items: center; gap: 12px;
    background: rgba(52,152,219,0.08); border: 1px solid rgba(52,152,219,0.2);
    border-radius: 10px; padding: 12px 18px; margin-bottom: 22px;
    font-size: 0.82rem; color: rgba(255,255,255,0.65);
}
.readonly-banner i { color: #3498db; font-size: 1rem; flex-shrink: 0; }
.readonly-banner strong { color: #3498db; }
.permission-note {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; color: rgba(52,152,219,0.8);
    background: rgba(52,152,219,0.07); border: 1px solid rgba(52,152,219,0.15);
    border-radius: 6px; padding: 3px 8px;
}
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-name">Luke's Seafood Trading<span>Staff Panel</span></div>
      <div class="role-pill"><i class="fa-solid fa-id-badge"></i> Staff Access</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="staff-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <div class="nav-section-label">My Work</div>
      <a href="staff-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Bookings</a>
      <a href="staff-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Orders</a>
      <div class="nav-section-label">View Only</div>
      <a href="staff-customers.php" class="nav-item active"><i class="fa-solid fa-users"></i> Customers</a>
      <div class="nav-section-label">Restricted</div>
      <span class="nav-item locked"><i class="fa-solid fa-layer-group"></i> Content Management</span>
      <span class="nav-item locked"><i class="fa-solid fa-shield-halved"></i> Security & Logs</span>
      <span class="nav-item locked"><i class="fa-solid fa-sliders"></i> System Config</span>
    </nav>
    <div class="sidebar-footer">
      <a href="staff-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Customers</div>
          <div class="topbar-breadcrumb">Staff <span>/</span> Customers</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i></div>
        <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
        </div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Customer List</h1>
          <p>View registered customers and their contact details.</p>
        </div>
        <span class="permission-note"><i class="fa-solid fa-eye"></i> Read-only — no edits or deletions</span>
      </div>

      <!-- READ-ONLY NOTICE -->
      <div class="readonly-banner">
        <i class="fa-solid fa-eye"></i>
        <span>This is a <strong>view-only</strong> page. Staff can look up customer names and emails to assist with bookings and orders, but cannot add, suspend, or delete accounts. Those actions are <strong>admin-only</strong>.</span>
      </div>

      <!-- SIMPLE STAT -->
      <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value"><?= number_format($totalCustomers) ?></div>
          <div class="stat-card-label">Registered Customers</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> total accounts</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
          <div class="stat-card-value"><?= count($users) ?></div>
          <div class="stat-card-label">Results Shown</div>
          <div class="stat-card-change up"><i class="fa-solid fa-filter"></i>
            <?= $search ? 'filtered results' : 'all customers' ?>
          </div>
        </div>
      </div>

      <!-- TABLE -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Customers
            <span style="color:var(--muted);font-weight:400;font-size:0.82rem;margin-left:8px;">(<?= count($users) ?> shown)</span>
          </span>
          <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div class="search-wrap">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="search-input" name="search" placeholder="Search name or email…" value="<?= htmlspecialchars($search) ?>"/>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Search</button>
            <?php if ($search): ?>
            <a href="staff-customers.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
          </form>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Customer</th>
              <th>Email</th>
              <th>Login Method</th>
              <th>Verified</th>
              <th>Joined</th>
              <!-- No Actions column for staff -->
            </tr></thead>
            <tbody>
              <?php if (empty($users)): ?>
              <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--muted);">
                <?= $search ? 'No customers match your search.' : 'No customers registered yet.' ?>
              </td></tr>
              <?php else: ?>
              <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($u['name'] ?? '?', 0, 2)) ?></div>
                    <strong><?= htmlspecialchars($u['name'] ?? '—') ?></strong>
                  </div>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <span class="provider-tag provider-<?= htmlspecialchars($u['provider'] ?? 'email') ?>">
                    <i class="fa-<?= $u['provider']==='google' ? 'brands fa-google'
                                  : ($u['provider']==='facebook' ? 'brands fa-facebook' : 'solid fa-envelope') ?>"></i>
                    <?= htmlspecialchars(ucfirst($u['provider'] ?? 'email')) ?>
                  </span>
                </td>
                <td>
                  <span style="display:inline-flex;align-items:center;gap:5px;font-size:0.82rem;
                        color:<?= $u['email_verified'] ? '#22c55e' : '#ef4444' ?>;">
                    <i class="fa-solid fa-<?= $u['email_verified'] ? 'circle-check' : 'circle-xmark' ?>"></i>
                    <?= $u['email_verified'] ? 'Verified' : 'Unverified' ?>
                  </span>
                </td>
                <td><?= $u['created_at'] ? date('M d, Y', strtotime($u['created_at'])) : '—' ?></td>
                <!-- No action buttons — staff is read-only on customers -->
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--line-w);font-size:0.75rem;color:var(--muted);">
          <i class="fa-solid fa-shield-halved" style="margin-right:5px;color:#3498db;"></i>
          Customer account actions (add, suspend, delete) are restricted to <strong style="color:rgba(255,255,255,0.4);">Admin</strong> only.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
</script>
</body>
</html>
