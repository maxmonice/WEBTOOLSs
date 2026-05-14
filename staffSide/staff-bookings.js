(function () {
  'use strict';

  var overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    var icon = document.querySelector('.sidebar-toggle i');
    if (icon) icon.className = 'fa-solid fa-xmark';
  }

  function closeSidebarMenu() {
    document.getElementById('sidebar').classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    var icon = document.querySelector('.sidebar-toggle i');
    if (icon) icon.className = 'fa-solid fa-bars';
  }

  window.toggleSidebar = function () {
    document.getElementById('sidebar').classList.contains('open') ? closeSidebarMenu() : openSidebar();
  };

  overlay.addEventListener('click', closeSidebarMenu);
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.nav-item:not(.locked)').forEach(function (item) {
      item.addEventListener('click', function () {
        if (window.innerWidth <= 900) closeSidebarMenu();
      });
    });
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) closeSidebarMenu();
  });

  var context = window.STAFF_BOOKINGS_CONTEXT || {};

  function escHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
    });
  }

  function statusClass(status) {
    if (status === 'confirmed') return 'green';
    if (status === 'cancelled') return 'red';
    return 'yellow';
  }

  function formatDate(value) {
    if (!value) return 'N/A';
    var parts = String(value).split('-').map(Number);
    var date = parts.length === 3 ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date(value);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function formatTime(value) {
    if (!value) return 'N/A';
    var parts = String(value).split(':');
    if (parts.length < 2) return value;
    var date = new Date();
    date.setHours(Number(parts[0]), Number(parts[1]), 0, 0);
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  }

  window.openModal = function (id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('open');
  };

  window.closeModal = function (id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('open');
  };

  window.showToast = function (msg, type) {
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || '');
    toast.innerHTML = '<i class="fa-solid fa-circle-info"></i> ' + escHtml(msg);
    container.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 3500);
  };

  window.updateBookingStatus = function (bookingId, status) {
    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', booking_id: bookingId, status: status })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.success) {
          showToast('Booking ' + (status === 'confirmed' ? 'confirmed' : 'cancelled') + ' successfully.', 'success');
          setTimeout(function () { location.reload(); }, 500);
        } else {
          showToast(data.message || 'Failed to update booking.', 'error');
        }
      })
      .catch(function () {
        showToast('Failed to update booking. Please try again.', 'error');
      });
  };

  window.showBookingDetails = function (bookingId) {
    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_booking', id: bookingId })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.success || !data.booking) {
          showToast(data.message || 'Failed to load booking details.', 'error');
          return;
        }
        var booking = data.booking;
        document.getElementById('bookingDetailId').textContent = '#BK-' + String(booking.id).padStart(3, '0');
        document.getElementById('bookingDetailName').textContent = booking.full_name || booking.user_name || 'Guest';
        document.getElementById('bookingDetailEvent').textContent = booking.event_name || 'N/A';
        document.getElementById('bookingDetailDate').textContent = formatDate(booking.event_date);
        document.getElementById('bookingDetailTime').textContent = formatTime(booking.event_time);
        document.getElementById('bookingDetailType').textContent = booking.event_type || 'N/A';
        document.getElementById('bookingDetailGuests').textContent = booking.num_guests || 'N/A';
        document.getElementById('bookingDetailAddress').textContent = booking.address || 'N/A';
        document.getElementById('bookingDetailContact').textContent = booking.contact_number || 'N/A';
        document.getElementById('bookingDetailEmail').textContent = booking.email_address || booking.user_email || 'N/A';
        document.getElementById('bookingDetailNotes').textContent = booking.notes || 'No notes';
        var badge = document.getElementById('bookingDetailStatus');
        badge.className = 'badge badge-' + statusClass(booking.status);
        badge.textContent = booking.status || 'pending';
        openModal('bookingDetailModal');
      })
      .catch(function () {
        showToast('Failed to load booking details. Please try again.', 'error');
      });
  };

  window.toggleView = function () {
    var viewMode = document.getElementById('viewToggle').value;
    var params = new URLSearchParams(window.location.search);
    window.location.href = 'staff-bookings.php?month=' + (params.get('month') || context.month || new Date().getMonth() + 1) +
      '&year=' + (params.get('year') || context.year || new Date().getFullYear()) +
      '&view=' + encodeURIComponent(viewMode);
  };

  window.changeYear = function () {
    var selectedYear = document.getElementById('yearSelect').value;
    var params = new URLSearchParams(window.location.search);
    window.location.href = 'staff-bookings.php?month=' + (params.get('month') || context.month || new Date().getMonth() + 1) +
      '&year=' + selectedYear +
      '&view=' + (params.get('view') || context.view || 'calendar');
  };

  window.navigateMonth = function (direction) {
    var params = new URLSearchParams(window.location.search);
    var month = Number(params.get('month') || context.month || new Date().getMonth() + 1);
    var year = Number(params.get('year') || context.year || new Date().getFullYear());
    var view = params.get('view') || context.view || 'calendar';
    if (direction === 'prev') {
      month -= 1;
      if (month < 1) { month = 12; year -= 1; }
    } else {
      month += 1;
      if (month > 12) { month = 1; year += 1; }
    }
    window.location.href = 'staff-bookings.php?month=' + month + '&year=' + year + '&view=' + encodeURIComponent(view);
  };

  window.showDayBookings = function (day) {
    var month = Number(context.month || new Date().getMonth() + 1);
    var year = Number(context.year || new Date().getFullYear());
    var dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');

    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_day_bookings', date: dateStr })
    })
      .then(function (response) {
        return response.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (e) {
            console.error('Invalid JSON response for day bookings:', text);
            throw new Error('Invalid server response.');
          }
        });
      })
      .then(function (data) {
        if (!data.success) {
          showToast(data.message || 'Failed to load day bookings.', 'error');
          return;
        }
        var modal = document.getElementById('dayBookingsModal');
        var title = modal.querySelector('.modal-title');
        var content = modal.querySelector('.modal-body');
        var dateObj = new Date(year, month - 1, day);
        title.innerHTML = '<i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for ' +
          dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        if (!data.bookings.length) {
          content.innerHTML = '<p style="text-align:center;color:var(--muted);padding:20px;">No bookings for this day.</p>';
        } else {
          content.innerHTML = data.bookings.map(function (booking) {
            return '<div class="booking-card">' +
              '<div class="booking-id">#BK-' + String(booking.id).padStart(3, '0') + '</div>' +
              '<div class="booking-name">' + escHtml(booking.full_name || booking.user_name || 'Guest') + '</div>' +
              '<div class="booking-detail"><i class="fa-solid fa-clock"></i> ' + escHtml(formatTime(booking.event_time)) + ' · ' + escHtml(booking.event_type || 'N/A') + '</div>' +
              '<div class="booking-detail"><i class="fa-solid fa-users"></i> ' + escHtml(booking.num_guests || 'N/A') + ' guests</div>' +
              '<div class="booking-detail"><i class="fa-solid fa-location-dot"></i> ' + escHtml(booking.address || 'N/A') + '</div>' +
              '<div class="booking-footer">' +
              '<span class="badge badge-' + statusClass(booking.status) + '">' + escHtml(booking.status || 'pending') + '</span>' +
              '<button class="btn btn-outline btn-sm" onclick="showBookingDetails(' + Number(booking.id) + ')">View Details</button>' +
              '</div>' +
              '</div>';
          }).join('');
        }
        openModal('dayBookingsModal');
      })
      .catch(function () {
        showToast('Failed to load day bookings. Please try again.', 'error');
      });
  };

  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) modal.classList.remove('open');
    });
  });
})();
