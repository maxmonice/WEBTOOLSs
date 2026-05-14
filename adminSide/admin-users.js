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
