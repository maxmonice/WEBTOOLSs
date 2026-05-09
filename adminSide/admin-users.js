function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function openModal(id)   { document.getElementById(id).classList.add('open'); }
function closeModal(id)  { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});

function toggleUserStatus(userId, confirmText) {
  if (!confirm(confirmText)) {
    return;
  }
  
  const formData = new FormData();
  formData.append('action', 'toggle_suspend');
  formData.append('user_id', userId);
  
  fetch('admin-users.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      location.reload();
    } else {
      alert('Failed to update user status: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to update user status. Please try again.');
  });
}

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