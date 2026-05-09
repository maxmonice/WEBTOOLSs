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

function updateBookingStatus(bookingId, status) {
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'update_status',
      booking_id: bookingId,
      status: status
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(`Booking ${status === 'confirmed' ? 'confirmed' : 'cancelled'} successfully!`, 'success');
      location.reload();
    } else {
      showToast(data.message || 'Failed to update booking', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to update booking. Please try again.', 'error');
  });
}

function showBookingDetails(bookingId) {
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_booking', id: bookingId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const booking = data.booking;
      // Parse booking details from notes field
      const bookingDetails = booking.notes ? JSON.parse(booking.notes) : {};
      
      document.getElementById('bookingDetailId').textContent = '#BK-' + booking.id;
      document.getElementById('bookingDetailName').textContent = bookingDetails.full_name || 'Guest';
      document.getElementById('bookingDetailEvent').textContent = bookingDetails.event_name || 'N/A';
      document.getElementById('bookingDetailDate').textContent = new Date(booking.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      document.getElementById('bookingDetailTime').textContent = bookingDetails.event_time || 'N/A';
      document.getElementById('bookingDetailType').textContent = bookingDetails.event_type || 'N/A';
      document.getElementById('bookingDetailGuests').textContent = bookingDetails.num_guests || 'N/A';
      document.getElementById('bookingDetailAddress').textContent = bookingDetails.address || 'N/A';
      document.getElementById('bookingDetailContact').textContent = bookingDetails.contact_number || 'N/A';
      document.getElementById('bookingDetailEmail').textContent = bookingDetails.email_address || 'N/A';
      document.getElementById('bookingDetailNotes').textContent = bookingDetails.original_notes || 'No notes';
      document.getElementById('bookingDetailStatus').className = 'badge badge-' + (booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow'));
      document.getElementById('bookingDetailStatus').textContent = booking.status;
      
      openModal('bookingDetailModal');
    } else {
      showToast(data.message || 'Failed to load booking details', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to load booking details. Please try again.', 'error');
  });
}

function toggleView() {
  const viewMode = document.getElementById('viewToggle').value;
  const urlParams = new URLSearchParams(window.location.search);
  const currentMonth = urlParams.get('month') || new Date().getMonth() + 1;
  const currentYear = urlParams.get('year') || new Date().getFullYear();
  window.location.href = `admin-bookings.php?month=${currentMonth}&year=${currentYear}&view=${viewMode}`;
}

function changeYear() {
  const selectedYear = document.getElementById('yearSelect').value;
  const urlParams = new URLSearchParams(window.location.search);
  const currentMonth = urlParams.get('month') || new Date().getMonth() + 1;
  window.location.href = `admin-bookings.php?month=${currentMonth}&year=${selectedYear}&view=${urlParams.get('view') || 'calendar'}`;
}

function navigateMonth(direction) {
  const urlParams = new URLSearchParams(window.location.search);
  let currentMonth = parseInt(urlParams.get('month')) || new Date().getMonth() + 1;
  let currentYear = parseInt(urlParams.get('year')) || new Date().getFullYear();
  
  let newMonth = currentMonth;
  let newYear = currentYear;
  
  if (direction === 'prev') {
    newMonth = currentMonth - 1;
    if (newMonth < 1) {
      newMonth = 12;
      newYear = currentYear - 1;
    }
  } else if (direction === 'next') {
    newMonth = currentMonth + 1;
    if (newMonth > 12) {
      newMonth = 1;
      newYear = currentYear + 1;
    }
  }
  
  // Navigate to new month
  window.location.href = `admin-bookings.php?month=${newMonth}&year=${newYear}&view=<?= $viewMode ?>`;
}

function showDayBookings(day) {
  // Use the same month/year that PHP is using to display the calendar
  // These are embedded by PHP to ensure they match what's displayed
  const month = <?= $currentMonth ?>;
  const year = <?= $currentYear ?>;
  const dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
  
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_day_bookings', date: dateStr })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const bookings = data.bookings;
      const modal = document.getElementById('dayBookingsModal');
      const title = modal.querySelector('.modal-title');
      const content = modal.querySelector('.modal-body');
      
      // Create date object properly to avoid timezone issues
      const [year, month, day] = dateStr.split('-').map(Number);
      const dateObj = new Date(year, month - 1, day); // month-1 because JS months are 0-indexed
      
      title.innerHTML = `<i class="fa-solid fa-calendar-day" style="color:var(--red);margin-right:8px;"></i>Bookings for ${dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}>`;
      
      if (bookings.length === 0) {
        content.innerHTML = '<p style="text-align: center; color: var(--muted); padding: 20px;">No bookings for this day.</p>';
      } else {
        content.innerHTML = bookings.map(booking => {
          // Parse booking details from notes field
          const bookingDetails = booking.notes ? JSON.parse(booking.notes) : {};
          const fullName = bookingDetails.full_name || 'Guest';
          const eventTime = bookingDetails.event_time || 'N/A';
          const eventType = bookingDetails.event_type || 'N/A';
          const numGuests = bookingDetails.num_guests || 'N/A';
          const address = bookingDetails.address || 'N/A';
          
          return `
          <div class="booking-card">
            <div class="booking-id">#BK-${booking.id}</div>
            <div class="booking-name">${fullName}</div>
            <div class="booking-detail"><i class="fa-solid fa-clock"></i> ${eventTime} · ${eventType}</div>
            <div class="booking-detail"><i class="fa-solid fa-users"></i> ${numGuests} guests</div>
            <div class="booking-detail"><i class="fa-solid fa-location-dot"></i> ${address}</div>
            <div class="booking-footer">
              <span class="badge badge-${booking.status === 'confirmed' ? 'green' : (booking.status === 'cancelled' ? 'red' : 'yellow')}">${booking.status}</span>
              <button class="btn btn-outline btn-sm" onclick="showBookingDetails(${booking.id})">View Details</button>
            </div>
          </div>
        `;
        }).join('');
      }
      
      openModal('dayBookingsModal');
    } else {
      showToast(data.message || 'Failed to load day bookings', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to load day bookings. Please try again.', 'error');
  });
}

function clearAllBookings() {
  if (confirm('Are you sure you want to delete ALL bookings? This action cannot be undone.')) {
    fetch('admin-bookings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'clear_bookings' })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('All bookings cleared successfully!', 'success');
        location.reload();
      } else {
        showToast(data.message || 'Failed to clear bookings', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('Failed to clear bookings. Please try again.', 'error');
    });
  }
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});

document.getElementById('newBookingForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Get form values
  const eventName = document.getElementById('newEventName').value.trim();
  const fullName = document.getElementById('newFullName').value.trim();
  const contactNumber = document.getElementById('newContactNumber').value.trim();
  const emailAddress = document.getElementById('newEmailAddress').value.trim();
  const eventDate = document.getElementById('newEventDate').value.trim();
  const eventTime = document.getElementById('newEventTime').value.trim();
  const eventType = document.getElementById('newEventType').value.trim();
  const numGuests = document.getElementById('newNumGuests').value.trim();
  const address = document.getElementById('newAddress').value.trim();
  const notes = document.getElementById('newNotes').value.trim() || 'N/A';
  
  // Client-side validation
  if (!eventName || !fullName || !contactNumber || !emailAddress || !eventDate || !eventTime || !eventType || !numGuests || !address) {
    showToast('Please fill in all required fields', 'error');
    return;
  }
  
  // Validate email format
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(emailAddress)) {
    showToast('Please enter a valid email address', 'error');
    return;
  }
  
  // Validate phone number (basic check)
  if (contactNumber.length < 10) {
    showToast('Please enter a valid contact number', 'error');
    return;
  }
  
  const formData = {
    action: 'create_booking',
    eventName: eventName,
    fullName: fullName,
    contactNumber: contactNumber,
    emailAddress: emailAddress,
    eventDate: eventDate,
    eventTime: eventTime,
    eventType: eventType,
    numGuests: numGuests,
    address: address,
    notes: notes,
    userEmail: 'admin@lukesseafood.com',
    userName: 'Admin'
  };
  
  console.log('Submitting booking data:', formData);
  
  fetch('admin-bookings.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(formData)
  })
  .then(response => {
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    return response.json();
  })
  .then(data => {
    console.log('Response data:', data);
    if (data.success) {
      showToast('Booking created successfully!', 'success');
      closeModal('newBookingModal');
      document.getElementById('newBookingForm').reset();
      setTimeout(() => {
        location.reload();
      }, 1000);
    } else {
      showToast(data.message || 'Failed to create booking', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to create booking. Please try again.', 'error');
  });
});

function toggleNotifications() {
  const menu = document.getElementById('notificationMenu');
  menu.classList.toggle('show');
  
  // Close when clicking outside
  document.addEventListener('click', function closeNotifications(e) {
    if (!e.target.closest('.notification-dropdown')) {
      menu.classList.remove('show');
      document.removeEventListener('click', closeNotifications);
    }
  });
}

function removeNotification(element) {
  const item = element.closest('.notification-item');
  item.style.transform = 'translateX(100%)';
  item.style.opacity = '0';
  setTimeout(() => item.remove(), 300);
}

function markAllAsRead() {
  const unreadItems = document.querySelectorAll('.notification-item.unread');
  unreadItems.forEach(item => {
    item.classList.remove('unread');
  });
  
  // Remove badge dot
  const badgeDot = document.querySelector('.badge-dot');
  if (badgeDot) {
    badgeDot.style.display = 'none';
  }
}