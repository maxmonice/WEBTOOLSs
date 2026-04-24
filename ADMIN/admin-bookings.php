<?php
session_start();
// Bookings logic here
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Booking Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
  margin-top: 10px;
}
.cal-header {
  text-align: center;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--muted);
  padding: 6px 0;
}
.cal-day {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 8px;
  padding: 8px 6px;
  min-height: 70px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: border-color 0.2s;
  position: relative;
}
.cal-day:hover { border-color: rgba(194,38,38,0.4); }
.cal-day.today { border-color: var(--red); background: rgba(194,38,38,0.08); }
.cal-day.empty { background: transparent; border-color: transparent; cursor: default; }
.cal-day-num { font-weight: 700; color: rgba(255,255,255,0.6); margin-bottom: 4px; font-size: 0.8rem; }
.cal-day.today .cal-day-num { color: var(--red); }
.cal-event {
  font-size: 0.62rem;
  padding: 2px 5px;
  border-radius: 4px;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 600;
}
.cal-event.confirmed { background: rgba(46,204,113,0.2); color: #2ecc71; }
.cal-event.pending   { background: rgba(243,156,18,0.2); color: #f39c12; }
.cal-event.cancelled { background: rgba(194,38,38,0.2); color: #ff6b6b; }

.booking-card {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 10px;
  padding: 16px;
  transition: border-color 0.2s, transform 0.2s;
}
.booking-card:hover { border-color: rgba(194,38,38,0.3); transform: translateY(-2px); }
.booking-id { font-size: 0.7rem; color: var(--red); font-weight: 700; letter-spacing: 0.1em; margin-bottom: 4px; }
.booking-name { font-size: 0.9rem; color: #fff; font-weight: 600; margin-bottom: 6px; }
.booking-detail { font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
.booking-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; }

.resource-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--line-w); }
.resource-item:last-child { border-bottom: none; }
.resource-name { font-size: 0.84rem; color: #fff; font-weight: 500; }
.resource-sub { font-size: 0.72rem; color: var(--muted); margin-top: 2px; }
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
      <a href="admin-bookings.php" class="nav-item active"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
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
          <div class="topbar-title">Booking Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Bookings</div>
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
          <h1>Booking Management</h1>
          <p>Monitor bookings, assign staff & equipment, and prevent double-booking.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('newBookingModal')"><i class="fa-solid fa-calendar-plus"></i> New Booking</button>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div class="stat-card-value">34</div>
          <div class="stat-card-label">Active Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> +5 today</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value">8</div>
          <div class="stat-card-label">Pending Confirmation</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> needs action</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-circle-xmark"></i></div>
          <div class="stat-card-value">3</div>
          <div class="stat-card-label">Cancelled This Month</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> low cancellation</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div class="stat-card-value">6</div>
          <div class="stat-card-label">Today's Bookings</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> on schedule</div>
        </div>
      </div>

      <div class="grid-2">
        <!-- CALENDAR -->
        <div class="panel" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-calendar" style="color:var(--red);margin-right:8px;"></i>June 2025 — Calendar View</span>
            <div class="flex-gap">
              <span class="badge badge-green">● Confirmed</span>
              <span class="badge badge-yellow">● Pending</span>
              <span class="badge badge-red">● Cancelled</span>
            </div>
          </div>
          <div class="panel-body">
            <div class="calendar-grid">
              <div class="cal-header">Sun</div><div class="cal-header">Mon</div><div class="cal-header">Tue</div>
              <div class="cal-header">Wed</div><div class="cal-header">Thu</div><div class="cal-header">Fri</div><div class="cal-header">Sat</div>
              <!-- Week 1 -->
              <div class="cal-day empty"></div>
              <div class="cal-day empty"></div>
              <div class="cal-day empty"></div>
              <div class="cal-day empty"></div>
              <div class="cal-day empty"></div>
              <div class="cal-day empty"></div>
              <div class="cal-day"><div class="cal-day-num">1</div></div>
              <!-- Week 2 -->
              <div class="cal-day"><div class="cal-day-num">2</div><div class="cal-event confirmed">BK-040 Santos</div></div>
              <div class="cal-day"><div class="cal-day-num">3</div></div>
              <div class="cal-day"><div class="cal-day-num">4</div><div class="cal-event pending">BK-041 Reyes</div></div>
              <div class="cal-day"><div class="cal-day-num">5</div></div>
              <div class="cal-day"><div class="cal-day-num">6</div><div class="cal-event confirmed">BK-042 Lim</div></div>
              <div class="cal-day"><div class="cal-day-num">7</div></div>
              <div class="cal-day"><div class="cal-day-num">8</div><div class="cal-event cancelled">BK-043 Cruz</div></div>
              <!-- Week 3 -->
              <div class="cal-day"><div class="cal-day-num">9</div></div>
              <div class="cal-day today"><div class="cal-day-num">10</div><div class="cal-event confirmed">BK-044 Santos</div><div class="cal-event pending">BK-045 Tan</div></div>
              <div class="cal-day"><div class="cal-day-num">11</div></div>
              <div class="cal-day"><div class="cal-day-num">12</div><div class="cal-event confirmed">BK-044 Confirmed</div></div>
              <div class="cal-day"><div class="cal-day-num">13</div></div>
              <div class="cal-day"><div class="cal-day-num">14</div><div class="cal-event pending">BK-046 Garcia</div></div>
              <div class="cal-day"><div class="cal-day-num">15</div></div>
              <!-- Week 4 -->
              <div class="cal-day"><div class="cal-day-num">16</div></div>
              <div class="cal-day"><div class="cal-day-num">17</div><div class="cal-event confirmed">BK-047 Ramos</div></div>
              <div class="cal-day"><div class="cal-day-num">18</div></div>
              <div class="cal-day"><div class="cal-day-num">19</div></div>
              <div class="cal-day"><div class="cal-day-num">20</div><div class="cal-event pending">BK-048 Torres</div></div>
              <div class="cal-day"><div class="cal-day-num">21</div></div>
              <div class="cal-day"><div class="cal-day-num">22</div></div>
              <!-- Week 5 -->
              <div class="cal-day"><div class="cal-day-num">23</div></div>
              <div class="cal-day"><div class="cal-day-num">24</div></div>
              <div class="cal-day"><div class="cal-day-num">25</div><div class="cal-event confirmed">BK-049 Flores</div></div>
              <div class="cal-day"><div class="cal-day-num">26</div></div>
              <div class="cal-day"><div class="cal-day-num">27</div></div>
              <div class="cal-day"><div class="cal-day-num">28</div></div>
              <div class="cal-day"><div class="cal-day-num">29</div></div>
              <div class="cal-day"><div class="cal-day-num">30</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- BOOKINGS TABLE + RESOURCES -->
      <div class="grid-2" style="margin-top:0;">
        <div class="panel">
          <div class="panel-header">
            <span class="panel-title">Recent Bookings</span>
            <div class="search-wrap">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" class="search-input" placeholder="Search..." style="max-width:160px;"/>
            </div>
          </div>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th><th>Customer</th><th>Date</th><th>Status</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="color:var(--red);font-weight:700;">#BK-044</td>
                  <td><div class="flex-gap"><div class="user-avatar">MS</div>Maria Santos</div></td>
                  <td>Jun 12, 2025</td>
                  <td><span class="badge badge-green">Confirmed</span></td>
                  <td><button class="action-btn edit"><i class="fa-solid fa-pen"></i></button></td>
                </tr>
                <tr>
                  <td style="color:var(--red);font-weight:700;">#BK-045</td>
                  <td><div class="flex-gap"><div class="user-avatar">BT</div>Bea Tan</div></td>
                  <td>Jun 10, 2025</td>
                  <td><span class="badge badge-yellow">Pending</span></td>
                  <td>
                    <div class="flex-gap">
                      <button class="action-btn edit" title="Confirm" onclick="this.closest('tr').querySelector('.badge').className='badge badge-green';this.closest('tr').querySelector('.badge').textContent='Confirmed';showToast('Booking confirmed!','success')"><i class="fa-solid fa-check"></i></button>
                      <button class="action-btn" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="color:var(--red);font-weight:700;">#BK-046</td>
                  <td><div class="flex-gap"><div class="user-avatar">RG</div>Rosa Garcia</div></td>
                  <td>Jun 14, 2025</td>
                  <td><span class="badge badge-yellow">Pending</span></td>
                  <td>
                    <div class="flex-gap">
                      <button class="action-btn edit" title="Confirm" onclick="this.closest('tr').querySelector('.badge').className='badge badge-green';this.closest('tr').querySelector('.badge').textContent='Confirmed';showToast('Booking confirmed!','success')"><i class="fa-solid fa-check"></i></button>
                      <button class="action-btn" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="color:var(--red);font-weight:700;">#BK-043</td>
                  <td><div class="flex-gap"><div class="user-avatar">RC</div>Rico Cruz</div></td>
                  <td>Jun 8, 2025</td>
                  <td><span class="badge badge-red">Cancelled</span></td>
                  <td><button class="action-btn edit"><i class="fa-solid fa-pen"></i></button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- STAFF & EQUIPMENT -->
        <div class="panel">
          <div class="panel-header"><span class="panel-title">Staff & Equipment</span><span class="badge badge-gray">Resource Tracker</span></div>
          <div class="panel-body">
            <p style="font-size:0.75rem;color:var(--muted);margin-bottom:14px;">Assigned resources for active bookings. Prevents double-booking.</p>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>Carlos Mendoza</div>
                <div class="resource-sub">Head Fishmonger · Assigned to BK-044</div>
              </div>
              <span class="badge badge-red">Busy</span>
            </div>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>Lita Navarro</div>
                <div class="resource-sub">Chef · Assigned to BK-045</div>
              </div>
              <span class="badge badge-yellow">Pending</span>
            </div>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-person" style="color:var(--red);margin-right:6px;"></i>Ben Aquino</div>
                <div class="resource-sub">Staff · Available</div>
              </div>
              <span class="badge badge-green">Free</span>
            </div>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-truck" style="color:var(--info);margin-right:6px;"></i>Delivery Van 1</div>
                <div class="resource-sub">Equipment · Assigned to BK-044</div>
              </div>
              <span class="badge badge-red">Busy</span>
            </div>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-truck" style="color:var(--info);margin-right:6px;"></i>Delivery Van 2</div>
                <div class="resource-sub">Equipment · Available</div>
              </div>
              <span class="badge badge-green">Free</span>
            </div>
            <div class="resource-item">
              <div>
                <div class="resource-name"><i class="fa-solid fa-box" style="color:var(--warning);margin-right:6px;"></i>Ice Box Set A</div>
                <div class="resource-sub">Equipment · Assigned to BK-046</div>
              </div>
              <span class="badge badge-yellow">Pending</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- New Booking Modal -->
<div class="modal-overlay" id="newBookingModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--red);margin-right:8px;"></i>New Booking</div>
    <div class="form-group">
      <label class="form-label">Customer Name</label>
      <input type="text" class="form-control" placeholder="e.g. Maria Santos"/>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div class="form-group">
        <label class="form-label">Date</label>
        <input type="date" class="form-control"/>
      </div>
      <div class="form-group">
        <label class="form-label">Time</label>
        <input type="time" class="form-control"/>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Assign Staff</label>
      <select class="form-control">
        <option>Carlos Mendoza (Head Fishmonger)</option>
        <option>Lita Navarro (Chef)</option>
        <option>Ben Aquino (Staff)</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Assign Equipment</label>
      <select class="form-control">
        <option>Delivery Van 2 (Available)</option>
        <option>Ice Box Set B (Available)</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Notes</label>
      <textarea class="form-control" rows="2" placeholder="Special instructions..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('newBookingModal')">Cancel</button>
      <button class="btn btn-primary" onclick="closeModal('newBookingModal');showToast('Booking created successfully!','success')"><i class="fa-solid fa-check"></i> Create Booking</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function showToast(msg, type='') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});

document.querySelectorAll('.cal-day:not(.empty)').forEach(d => {
  d.addEventListener('click', () => {
    const num = d.querySelector('.cal-day-num')?.textContent;
    if(num) showToast(`Selected June ${num}, 2025`);
  });
});
</script>
</body>
</html>