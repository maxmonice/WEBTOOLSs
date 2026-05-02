<?php
require_once 'admin-config.php';
requireAdmin();

// Handle content operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data['action'] === 'create_content') {
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $price = $data['price'] ?? 0;
        $category = $data['category'] ?? '';
        $image = $data['image'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO content_items (
                name, description, price, category, image, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        try {
            $stmt->execute([$name, $description, $price, $category, $image]);
            echo json_encode(['success' => true, 'message' => 'Content item created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'update_content') {
        $id = $data['id'] ?? 0;
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $price = $data['price'] ?? 0;
        $category = $data['category'] ?? '';
        $image = $data['image'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE content_items 
            SET name = ?, description = ?, price = ?, category = ?, image = ?
            WHERE id = ?
        ");
        
        try {
            $stmt->execute([$name, $description, $price, $category, $image, $id]);
            echo json_encode(['success' => true, 'message' => 'Content item updated successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'delete_content') {
        $id = $data['id'] ?? 0;
        
        $stmt = $pdo->prepare("DELETE FROM content_items WHERE id = ?");
        try {
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Content item deleted successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($data['action'] === 'get_content') {
        $id = $data['id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT * FROM content_items WHERE id = ?");
        try {
            $stmt->execute([$id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'content' => $content]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get content items for display
$contentItems = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM content_items ORDER BY created_at DESC");
    $stmt->execute();
    $contentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist, create it
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS content_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            category VARCHAR(100) NOT NULL,
            image VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableSQL);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Content Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.content-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  margin-top: 20px;
}
.content-item {
  background: var(--card2);
  border: 1px solid var(--line-w);
  border-radius: 12px;
  overflow: hidden;
  transition: transform 0.2s, border-color 0.2s;
}
.content-item:hover {
  transform: translateY(-4px);
  border-color: rgba(194,38,38,0.3);
}
.content-image {
  width: 100%;
  height: 180px;
  object-fit: cover;
  background: var(--card1);
}
.content-body {
  padding: 20px;
}
.content-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: #fff;
  margin-bottom: 8px;
}
.content-category {
  display: inline-block;
  background: rgba(194,38,38,0.2);
  color: var(--red);
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  margin-bottom: 12px;
}
.content-price {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--red);
  margin-bottom: 12px;
}
.content-description {
  color: var(--muted);
  font-size: 0.85rem;
  line-height: 1.5;
  margin-bottom: 16px;
}
.content-actions {
  display: flex;
  gap: 10px;
}
.content-actions .btn {
  flex: 1;
  padding: 8px 16px;
  font-size: 0.8rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}
.image-upload {
  border: 2px dashed var(--line-w);
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s;
}
.image-upload:hover {
  border-color: var(--red);
}
.image-upload i {
  font-size: 2rem;
  color: var(--muted);
  margin-bottom: 10px;
}
.image-upload p {
  color: var(--muted);
  font-size: 0.85rem;
  margin: 0;
}
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
      <a href="admin-bookings.php" class="nav-item"><i class="fa-solid fa-calendar-days"></i> Booking Management</a>
      <a href="admin-orders.php" class="nav-item"><i class="fa-solid fa-bag-shopping"></i> Order Management</a>
      <a href="admin-content.php" class="nav-item active"><i class="fa-solid fa-layer-group"></i> Content Management</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-account.php" class="nav-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="index.php" class="logout-btn" style="background: #22c55e; color: #fff;"><i class="fa-solid fa-home"></i> Home</a>
    </div>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Content Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Content</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i></div>
        <a href="admin-account.php" class="admin-avatar">A</a>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Content Management</h1>
          <p>Manage menu items, products, and content for the website.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('contentModal')"><i class="fa-solid fa-plus"></i> Add Content</button>
      </div>

      <!-- STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-layer-group"></i></div>
          <div class="stat-card-value"><?= count($contentItems) ?></div>
          <div class="stat-card-label">Total Items</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> All content</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-tag"></i></div>
          <div class="stat-card-value">₱<?= number_format(array_sum(array_column($contentItems, 'price')), 0) ?></div>
          <div class="stat-card-label">Total Value</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Combined worth</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= count(array_filter($contentItems, fn($i) => date('Y-m-d', strtotime($i['created_at'])) === date('Y-m-d'))) ?></div>
          <div class="stat-card-label">Updated Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Recent changes</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-image"></i></div>
          <div class="stat-card-value"><?= count(array_filter($contentItems, fn($i) => empty($i['image']))) ?></div>
          <div class="stat-card-label">Missing Images</div>
          <div class="stat-card-change down"><i class="fa-solid fa-arrow-down"></i> Needs attention</div>
        </div>
      </div>

      <!-- CONTENT GRID -->
      <div class="content-grid">
        <?php if (!empty($contentItems)): ?>
          <?php foreach ($contentItems as $item): ?>
            <div class="content-item">
              <?php if ($item['image']): ?>
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="content-image">
              <?php else: ?>
                <div class="content-image" style="display: flex; align-items: center; justify-content: center; background: var(--card1);">
                  <i class="fa-solid fa-image" style="font-size: 2rem; color: var(--muted);"></i>
                </div>
              <?php endif; ?>
              <div class="content-body">
                <div class="content-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="content-category"><?= htmlspecialchars($item['category']) ?></div>
                <div class="content-price">₱<?= number_format($item['price'], 2) ?></div>
                <div class="content-description"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                <div class="content-actions">
                  <button class="btn btn-outline btn-sm" onclick="editContent(<?= $item['id'] ?>)">
                    <i class="fa-solid fa-pen"></i> Edit
                  </button>
                  <button class="btn btn-danger btn-sm" onclick="deleteContent(<?= $item['id'] ?>)">
                    <i class="fa-solid fa-trash"></i> Delete
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--muted); display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <i class="fa-solid fa-layer-group" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
            <h3 style="text-align: center; margin-bottom: 10px;">No content items yet</h3>
            <p style="text-align: center; margin-bottom: 20px;">Start by adding your first menu item or product.</p>
            <button class="btn btn-primary" onclick="openModal('contentModal')">
              <i class="fa-solid fa-plus"></i> Add Your First Item
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Content Modal -->
<div class="modal-overlay" id="contentModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-layer-group" style="color:var(--red);margin-right:8px;"></i>Add Content Item</div>
    <form id="contentForm">
      <div class="form-group">
        <label class="form-label">Item Name</label>
        <input type="text" class="form-control" id="contentName" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Price</label>
          <input type="number" class="form-control" id="contentPrice" step="0.01" min="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select class="form-control" id="contentCategory" required>
            <option value="">Select category</option>
            <option value="Salad">Salad</option>
            <option value="Fusion">Fusion Rolls & Sushi</option>
            <option value="A La Carte">A La Carte</option>
            <option value="Platters">Platters</option>
            <option value="Bento">Bento Boxes</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" id="contentDescription" rows="4" placeholder="Item description..."></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Image URL</label>
        <input type="url" class="form-control" id="contentImage" placeholder="https://example.com/image.jpg">
        <div class="image-upload" onclick="document.getElementById('contentImage').focus();">
          <i class="fa-solid fa-cloud-upload-alt"></i>
          <p>Click to enter image URL</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('contentModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Item</button>
      </div>
    </form>
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
</script>
</body>
</html>
