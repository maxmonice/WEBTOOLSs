<?php
session_start();
// Orders logic here
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Order Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
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
      <a href="admin-orders.php" class="nav-item active"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
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
          <div class="topbar-title">Order Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Orders</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i><span class="badge-dot"></span></div>
        <div class="admin-avatar">A</div>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1>Order Management</h1>
        <p>View and manage customer orders.</p>
      </div>

      <div class="stats-grid">
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

      <div class="panel">
        <div class="panel-header">
          <span class="panel-title">All Orders</span>
          <a href="admin-dashboard.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
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