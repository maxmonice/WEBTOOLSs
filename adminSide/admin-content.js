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

let editingContentId = null;

function editContent(id) {
  editingContentId = id;
  fetch('admin-content.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_content', id: id })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      document.getElementById('contentName').value = data.content.name;
      document.getElementById('contentPrice').value = data.content.price;
      document.getElementById('contentCategory').value = data.content.category;
      document.getElementById('contentDescription').value = data.content.description || '';
      document.getElementById('contentImage').value = data.content.image || '';
      document.querySelector('.modal-title').innerHTML = '<i class="fa-solid fa-layer-group" style="color:var(--red);margin-right:8px;"></i>Edit Content Item';
      openModal('contentModal');
    }
  });
}

function deleteContent(id) {
  if (confirm('Are you sure you want to delete this content item?')) {
    fetch('admin-content.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_content', id: id })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('Content item deleted successfully!', 'success');
        location.reload();
      } else {
        showToast(data.message || 'Failed to delete content item', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('Failed to delete content item. Please try again.', 'error');
    });
  }
}

document.getElementById('contentForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = {
    action: editingContentId ? 'update_content' : 'create_content',
    id: editingContentId,
    name: document.getElementById('contentName').value,
    price: document.getElementById('contentPrice').value,
    category: document.getElementById('contentCategory').value,
    description: document.getElementById('contentDescription').value,
    image: document.getElementById('contentImage').value
  };
  
  fetch('admin-content.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(editingContentId ? 'Content item updated successfully!' : 'Content item created successfully!', 'success');
      closeModal('contentModal');
      document.getElementById('contentForm').reset();
      editingContentId = null;
      location.reload();
    } else {
      showToast(data.message || 'Failed to save content item', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showToast('Failed to save content item. Please try again.', 'error');
  });
});

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});