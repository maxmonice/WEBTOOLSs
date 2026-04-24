<?php
session_start();
// Dashboard logic here
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.activity-item {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
}
.activity-item:last-child { border-bottom: none; }
.activity-dot {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: grid; place-items: center; font-size: 0.8rem;
    margin-top: 2px;
}
.activity-dot.red   { background: rgba(194,38,38,0.15); color: var(--red); }
.activity-dot.green { background: rgba(46,204,113,0.12); color: var(--success); }
.activity-dot.blue  { background: rgba(52,152,219,0.12); color: var(--info); }
.activity-dot.yellow{ background: rgba(243,156,18,0.12); color: var(--warning); }
.activity-meta { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }
.activity-text { font-size: 0.84rem; color: rgba(255,255,255,0.82); }

.quick-action {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; padding: 20px; background: var(--card2);
    border: 1px solid var(--line-w); border-radius: 10px; text-decoration: none;
    color: var(--muted); font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em;
    text-transform: uppercase; text-align: center;
    transition: all 0.25s; cursor: pointer;
}
.quick-action i { font-size: 1.4rem; color: var(--red); }
.quick-action:hover { border-color: var(--red); color: #fff; background: rgba(194,38,38,0.08); transform: translateY(-2px); }

.top-orders-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }

.mini-order {
    background: var(--card2); border: 1px solid var(--line-w);
    border-radius: 10px; padding: 14px;
    transition: border-color 0.2s;
}
.mini-order:hover { border-color: rgba(194,38,38,0.3); }
.mini-order-id { font-size: 0.7rem; color: var(--red); font-weight: 700; letter-spacing: 0.1em; margin-bottom: 4px; }
.mini-order-name { font-size: 0.84rem; color: #fff; font-weight: 500; margin-bottom: 8px; }
.mini-order-footer { display: flex; align-items: center; justify-content: space-between; }
.mini-order-amount { font-family: 'Aclonica', sans-serif; font-size: 0.9rem; color: #fff; }

@media (max-width: 900px) { .top-orders-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 480px) { .top-orders-grid { grid-template-columns: 1fr; } }
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
      <a href="admin-dashboard.php" class="nav-item active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>

      <div class="nav-section-label">Management</div>
      <a href="admin-users.php" class="nav-item"><i class="fa-solid fa-users"></i> User Management</a>
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

  <!-- MAIN -->
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Dashboard</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Overview</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i><span class="badge-dot"></span></div>
        <div class="admin-avatar">A</div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Welcome back, Admin 👋</h1>
        <p>Here's what's happening at Luke's Seafood Trading today.</p>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-users"></i></div>
          <div class="stat-card-value">1,248</div>
          <div class="stat-card-label">Registered Customers</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +12 this week</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value">34</div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +5 today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="stat-card-value">87</div>
          <div class="stat-card-label">Pending Orders</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs attention</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="stat-card-value">₱84.2K</div>
          <div class="stat-card-label">Revenue This Month</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +18% vs last month</div>
        </div>
      </div>

      <!-- ROW 1 -->
      <div class="grid-2">
        <!-- Recent Activity -->
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Activity</span>
            <span class="badge badge-blue">Live</span>
          </div>
          <div class="panel-body" style="padding: 0 20px;">
            <div class="activity-item">
              <div class="activity-dot green"><i class="fa-solid fa-user-plus"></i></div>
              <div>
                <div class="activity-text">New customer <strong>Maria Santos</strong> registered</div>
                <div class="activity-meta">2 minutes ago</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-dot blue"><i class="fa-solid fa-bag-shopping"></i></div>
              <div>
                <div class="activity-text">Order <strong>#ORD-0091</strong> marked as Shipped</div>
                <div class="activity-meta">14 minutes ago</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-dot yellow"><i class="fa-solid fa-calendar-days"></i></div>
              <div>
                <div class="activity-text">Booking <strong>#BK-044</strong> confirmed for June 12</div>
                <div class="activity-meta">1 hour ago</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-dot red"><i class="fa-solid fa-user-slash"></i></div>
              <div>
                <div class="activity-text">Account <strong>jdelacruz99</strong> was suspended</div>
                <div class="activity-meta">2 hours ago</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-dot green"><i class="fa-solid fa-star"></i></div>
              <div>
                <div class="activity-text">New 5-star review posted on <strong>Salmon Sashimi</strong></div>
                <div class="activity-meta">3 hours ago</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="panel">
          <div class="panel-header"><span class="panel-title">Quick Actions</span></div>
          <div class="panel-body">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <a href="admin-users.php" class="quick-action"><i class="fa-solid fa-user-plus"></i>View Users</a>
              <a href="admin-bookings.php" class="quick-action"><i class="fa-solid fa-calendar-plus"></i>New Booking</a>
              <a href="admin-orders.php" class="quick-action"><i class="fa-solid fa-clipboard-list"></i>View Orders</a>
              <a href="admin-content.php" class="quick-action"><i class="fa-solid fa-plus"></i>Add Product</a>
              <a href="admin-orders.php" class="quick-action"><i class="fa-solid fa-chart-line"></i>Sales Report</a>
              <a href="admin-logs.php" class="quick-action"><i class="fa-solid fa-shield-halved"></i>Audit Logs</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">Recent Orders</span>
          <a href="admin-orders.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-0091</td>
                <td><div class="flex-gap"><div class="user-avatar">MS</div>Maria Santos</div></td>
                <td>Salmon Sashimi x2</td>
                <td>₱1,200</td>
                <td><span class="badge badge-blue">Shipped</span></td>
                <td>Jun 10, 2025</td>
              </tr>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-0090</td>
                <td><div class="flex-gap"><div class="user-avatar">JR</div>Juan Reyes</div></td>
                <td>Tuna Belly x1, Shrimp x3</td>
                <td>₱980</td>
                <td><span class="badge badge-yellow">Pending</span></td>
                <td>Jun 10, 2025</td>
              </tr>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-0089</td>
                <td><div class="flex-gap"><div class="user-avatar">AL</div>Ana Lim</div></td>
                <td>Oysters x10</td>
                <td>₱650</td>
                <td><span class="badge badge-green">Delivered</span></td>
                <td>Jun 9, 2025</td>
              </tr>
              <tr>
                <td style="color:var(--red);font-weight:700;">#ORD-0088</td>
                <td><div class="flex-gap"><div class="user-avatar">RC</div>Rico Cruz</div></td>
                <td>Squid x2, Crab x1</td>
                <td>₱1,540</td>
                <td><span class="badge badge-green">Delivered</span></td>
                <td>Jun 9, 2025</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>