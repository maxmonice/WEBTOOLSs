<?php
require_once 'staff-config.php';
requireStaff();

$successMsg = '';
$errorMsg   = '';

// Staff can confirm or cancel bookings — but NOT delete them
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bid    = (int)($_POST['booking_id'] ?? 0);

    if ($bid > 0 && in_array($action, ['confirm_booking', 'cancel_booking'], true)) {
        $newStatus = $action === 'confirm_booking' ? 'confirmed' : 'cancelled';
        try {
            $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')
                ->execute([$newStatus, $bid]);
            $successMsg = 'Booking <strong>#BK-' . str_pad($bid, 3, '0', STR_PAD_LEFT) . '</strong> marked as ' . ucfirst($newStatus) . '.';
        } catch (\Throwable $e) {
            $errorMsg = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_booking') {
        // Staff cannot delete — show permission error
        $errorMsg = 'You do not have permission to delete bookings. Please contact an admin.';
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$whereClause = 'WHERE 1=1';
$params      = [];
if ($search !== '') {
    $whereClause .= ' AND u.name LIKE :s';
    $params[':s'] = "%$search%";
}
if ($status !== '') {
    $whereClause .= ' AND b.status = :st';
    $params[':st'] = $status;
}

try {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.status, b.event_date, b.created_at, b.notes,
                COALESCE(u.name, 'Unknown') AS customer_name,
                COALESCE(u.email, '—') AS customer_email
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         $whereClause
         ORDER BY b.created_at DESC"
    );
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (\Throwable $_) {
    $bookings = [];
}

$stats    = getStaffStats($pdo);
$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Bookings — Luke's Staff</title>
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
    opacity: 0.38; pointer-events: none; cursor: not-allowed;
    position: relative;
}
.nav-item.locked::after {
    content: '\f023'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
    font-size: 0.65rem; margin-left: auto; color: rgba(255,255,255,0.3);
}
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.action-btn {
    width:30px; height:30px; border-radius:6px; border:1px solid var(--line-w);
    background:transparent; color:var(--muted); font-size:0.8rem;
    display:inline-grid; place-items:center; cursor:pointer; transition:all 0.2s;
}
.action-btn:hover { border-color:var(--red); color:#ff6b6b; background:rgba(194,38,38,0.1); }
.action-btn.confirm:hover { border-color:#2ecc71; color:#2ecc71; background:rgba(46,204,113,0.1); }
.alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.86rem; }
.alert-success { background:rgba(46,204,113,0.12); color:#2ecc71; border:1px solid rgba(46,204,113,0.25); }
.alert-error   { background:rgba(194,38,38,0.12);  color:#ff6b6b; border:1px solid rgba(194,38,38,0.25); }
.permission-note {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; color: rgba(243,156,18,0.7);
    background: rgba(243,156,18,0.07); border: 1px solid rgba(243,156,18,0.15);
    border-radius: 6px; padding: 3px 8px;
}

/* Calendar styles */
.calendar-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; margin-top:10px; }
.cal-header { text-align:center; font-size:0.68rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--muted); padding:6px 0; }
.cal-day { background:var(--card2); border:1px solid var(--line-w); border-radius:8px; padding:8px 6px; min-height:64px; font-size:0.75rem; cursor:pointer; transition:border-color 0.2s; }
.cal-day:hover { border-color:rgba(194,38,38,0.4); }
.cal-day.today { border-color:var(--red); background:rgba(194,38,38,0.08); }
.cal-day.empty { background:transparent; border-color:transparent; cursor:default; }
.cal-day-num { font-weight:700; color:rgba(255,255,255,0.6); margin-bottom:4px; font-size:0.8rem; }
.cal-day.today .cal-day-num { color:var(--red); }
.cal-event { font-size:0.62rem; padding:2px 5px; border-radius:4px; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:600; }
.cal-event.confirmed { background:rgba(46,204,113,0.2); color:#2ecc71; }
.cal-event.pending   { background:rgba(243,156,18,0.2);  color:#f39c12; }
.cal-event.cancelled { background:rgba(194,38,38,0.2);   color:#ff6b6b; }
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
      <a href="staff-bookings.php" class="nav-item active"><i class="fa-solid fa-calendar-days"></i> Bookings</a>
      <a href="staff-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Orders</a>
      <div class="nav-section-label">View Only</div>
      <a href="staff-customers.php" class="nav-item"><i class="fa-solid fa-users"></i> Customers</a>
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
          <div class="topbar-title">Booking Management</div>
          <div class="topbar-breadcrumb">Staff <span>/</span> Bookings</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i>
          <?php if ($stats['pending_bookings'] > 0): ?><span class="badge-dot"></span><?php endif; ?>
        </div>
        <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);">
          <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
        </div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Booking Management</h1>
          <p>Confirm or cancel bookings. Contact an admin to delete or create bookings.</p>
        </div>
        <span class="permission-note"><i class="fa-solid fa-lock"></i> View & update only — no delete</span>
      </div>

      <?php if ($successMsg): ?>
      <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $successMsg ?></div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
      <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errorMsg ?></div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value"><?= $stats['active_bookings'] ?></div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> confirmed</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= $stats['pending_bookings'] ?></div>
          <div class="stat-card-label">Pending Confirmation</div>
          <div class="stat-card-change <?= $stats['pending_bookings'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-arrow-down"></i> <?= $stats['pending_bookings'] > 0 ? 'needs action' : 'all handled' ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div class="stat-card-value"><?= $stats['my_bookings_today'] ?></div>
          <div class="stat-card-label">Today's Events</div>
          <div class="stat-card-change up"><i class="fa-solid fa-clock"></i> scheduled today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-list-check"></i></div>
          <div class="stat-card-value"><?= count($bookings) ?></div>
          <div class="stat-card-label">Total Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-database"></i> in system</div>
        </div>
      </div>

      <!-- CALENDAR -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title"><i class="fa-solid fa-calendar" style="color:var(--red);margin-right:8px;"></i><?= date('F Y') ?> — Calendar View</span>
          <div class="flex-gap">
            <span class="badge badge-green">● Confirmed</span>
            <span class="badge badge-yellow">● Pending</span>
            <span class="badge badge-red">● Cancelled</span>
          </div>
        </div>
        <div class="panel-body">
          <?php
          $today   = (int)date('j');
          $month   = (int)date('n');
          $year    = (int)date('Y');
          $start   = (int)date('w', mktime(0,0,0,$month,1,$year)); // day of week for 1st
          $daysInMonth = (int)date('t');

          // Build booking map by day
          $bookingMap = [];
          foreach ($bookings as $b) {
              if (!$b['event_date']) continue;
              $d = (int)date('j', strtotime($b['event_date']));
              $bookingMap[$d][] = $b;
          }
          ?>
          <div class="calendar-grid">
            <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $h): ?>
              <div class="cal-header"><?= $h ?></div>
            <?php endforeach; ?>
            <?php for($i = 0; $i < $start; $i++): ?>
              <div class="cal-day empty"></div>
            <?php endfor; ?>
            <?php for($d = 1; $d <= $daysInMonth; $d++): ?>
              <div class="cal-day <?= $d === $today ? 'today' : '' ?>">
                <div class="cal-day-num"><?= $d ?></div>
                <?php foreach(($bookingMap[$d] ?? []) as $ev): ?>
                  <div class="cal-event <?= htmlspecialchars($ev['status']) ?>">
                    <?= htmlspecialchars(substr($ev['customer_name'], 0, 8)) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <!-- BOOKINGS TABLE -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Bookings
            <span style="color:var(--muted);font-weight:400;font-size:0.82rem;margin-left:8px;">(<?= count($bookings) ?> shown)</span>
          </span>
          <div class="filter-bar">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="search-input" name="search" placeholder="Search customer…" value="<?= htmlspecialchars($search) ?>"/>
              </div>
              <select class="form-control" name="status" style="width:auto;padding:8px 12px;font-size:0.82rem;">
                <option value="">All Status</option>
                <option value="pending"   <?= $status==='pending'   ?'selected':'' ?>>Pending</option>
                <option value="confirmed" <?= $status==='confirmed' ?'selected':'' ?>>Confirmed</option>
                <option value="cancelled" <?= $status==='cancelled' ?'selected':'' ?>>Cancelled</option>
              </select>
              <button type="submit" class="btn btn-outline btn-sm">Filter</button>
              <?php if ($search || $status): ?>
              <a href="staff-bookings.php" class="btn btn-outline btn-sm">Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Booking ID</th><th>Customer</th><th>Email</th><th>Event Date</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php if (empty($bookings)): ?>
              <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--muted);">
                <?= $search || $status ? 'No bookings match your filters.' : 'No bookings found.' ?>
              </td></tr>
              <?php else: ?>
              <?php foreach ($bookings as $b): ?>
              <tr>
                <td style="color:var(--red);font-weight:700;">#BK-<?= str_pad($b['id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="flex-gap">
                    <div class="user-avatar"><?= strtoupper(substr($b['customer_name'], 0, 2)) ?></div>
                    <strong><?= htmlspecialchars($b['customer_name']) ?></strong>
                  </div>
                </td>
                <td><?= htmlspecialchars($b['customer_email']) ?></td>
                <td><?= $b['event_date'] ? date('M d, Y', strtotime($b['event_date'])) : '—' ?></td>
                <td><?= statusBadge($b['status']) ?></td>
                <td>
                  <div class="flex-gap">
                    <?php if ($b['status'] === 'pending'): ?>
                    <!-- Confirm -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="confirm_booking">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="action-btn confirm" title="Confirm Booking"
                              onclick="return confirm('Confirm this booking?')">
                        <i class="fa-solid fa-check"></i>
                      </button>
                    </form>
                    <!-- Cancel -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="cancel_booking">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="action-btn" title="Cancel Booking"
                              onclick="return confirm('Cancel this booking?')">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    </form>
                    <?php else: ?>
                    <!-- View only for non-pending -->
                    <button class="action-btn" title="No actions available for this status" style="opacity:0.35;cursor:default;">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <?php endif; ?>
                    <!-- NO delete button for staff -->
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

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
</script>
</body>
</html>
