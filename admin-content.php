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

/* Image source tabs */
.image-source-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 15px;
}

.tab-btn {
  background: var(--card1);
  border: 1px solid var(--line-w);
  color: var(--muted);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.85rem;
  font-weight: 500;
}

.tab-btn:hover {
  background: var(--card2);
  border-color: var(--red);
  color: #fff;
}

.tab-btn.active {
  background: var(--red);
  border-color: var(--red);
  color: #fff;
}

/* File upload area */
.file-upload-area {
  border: 2px dashed var(--line-w);
  border-radius: 8px;
  padding: 30px;
  text-align: center;
  background: var(--card1);
  cursor: pointer;
  transition: all 0.2s;
}

.file-upload-area:hover {
  border-color: var(--red);
  background: rgba(194, 38, 38, 0.05);
}

.file-upload-area i {
  font-size: 2rem;
  color: var(--muted);
  margin-bottom: 10px;
  display: block;
}

.file-upload-area p {
  color: var(--muted);
  margin: 0;
}

.file-upload-area small {
  color: var(--muted);
  opacity: 0.7;
  margin-top: 5px;
  display: block;
}

/* Progress bar */
.progress-bar {
  width: 100%;
  height: 8px;
  background: var(--card1);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--red), #c22626);
  width: 0%;
  transition: width 0.3s;
  border-radius: 4px;
}

#uploadStatus {
  color: var(--muted);
  font-size: 0.85rem;
  margin-top: 8px;
  text-align: center;
}

/* Image preview */
#imagePreview {
  border: 1px solid var(--line-w);
  border-radius: 8px;
  padding: 10px;
  background: var(--card1);
  text-align: center;
}

#previewImg {
  border-radius: 4px;
  max-width: 120px;
  max-height: 120px;
  cursor: pointer;
  transition: all 0.2s;
  display: block;
}

#previewImg:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Image preview overlay */
.image-preview-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(to bottom, rgba(0,0,0,0.7), rgba(0,0,0,0.7));
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}

.image-preview-overlay:hover {
  opacity: 1;
}

#imagePreview:hover .image-preview-overlay {
  opacity: 1;
  pointer-events: all;
}

.preview-edit-btn,
.preview-remove-btn {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #fff;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.preview-edit-btn:hover {
  background: rgba(46, 204, 113, 0.8);
  border-color: rgba(46, 204, 113, 0.9);
  transform: scale(1.1);
}

.preview-remove-btn:hover {
  background: rgba(231, 76, 60, 0.8);
  border-color: rgba(231, 76, 60, 0.9);
  transform: scale(1.1);
}

/* Image viewer modal */
.image-viewer-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s, visibility 0.3s;
}

.image-viewer-modal.active {
  opacity: 1;
  visibility: visible;
}

.image-viewer-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.image-viewer-img {
  max-width: 100%;
  max-height: 80vh;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.image-viewer-controls {
  margin-top: 20px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

.image-viewer-btn {
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #fff;
  padding: 10px 20px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.image-viewer-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: translateY(-2px);
}

.image-viewer-close {
  position: absolute;
  top: 20px;
  right: 20px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #fff;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.image-viewer-close:hover {
  background: rgba(255, 67, 54, 0.8);
  transform: scale(1.1);
}

/* Crop overlay */
.crop-overlay {
  position: absolute;
  top: 0;
  left: 0;
  pointer-events: auto;
  border: 2px dashed #fff;
  background: rgba(0, 0, 0, 0.3);
  display: none;
  z-index: 10;
}

.crop-overlay.active {
  display: block;
}

.crop-handles {
  position: absolute;
  width: 100%;
  height: 100%;
  pointer-events: none;
}

.crop-handle {
  position: absolute;
  width: 12px;
  height: 12px;
  background: #2196F3;
  border: 2px solid #fff;
  border-radius: 50%;
  cursor: pointer;
  pointer-events: auto;
  z-index: 11;
  transition: transform 0.2s;
}

.crop-handle:hover {
  transform: scale(1.2);
  background: #1976D2;
}

.crop-handle.nw { top: -6px; left: -6px; cursor: nw-resize; }
.crop-handle.ne { top: -6px; right: -6px; cursor: ne-resize; }
.crop-handle.sw { bottom: -6px; left: -6px; cursor: sw-resize; }
.crop-handle.se { bottom: -6px; right: -6px; cursor: se-resize; }
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
          <div class="topbar-title">Content Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Content</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="topbar-badge"><i class="fa-regular fa-bell"></i></div>
        <div class="admin-avatar">A</div>
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
          <div class="stat-card-value">12</div>
          <div class="stat-card-label">Updated Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Recent changes</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-image"></i></div>
          <div class="stat-card-value">8</div>
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
        <label class="form-label">Image Source</label>
        <div class="image-source-tabs">
          <button type="button" class="tab-btn active" onclick="switchImageTab('url')" id="urlTab">
            <i class="fa-solid fa-link"></i> URL
          </button>
          <button type="button" class="tab-btn" onclick="switchImageTab('upload')" id="uploadTab">
            <i class="fa-solid fa-upload"></i> Upload
          </button>
        </div>
      </div>
      
      <div class="form-group" id="urlInput">
        <label class="form-label">Image URL</label>
        <input type="url" class="form-control" id="contentImage" placeholder="https://example.com/image.jpg">
        <div class="image-upload" onclick="document.getElementById('contentImage').focus();">
          <i class="fa-solid fa-cloud-upload-alt"></i>
          <p>Click to enter image URL</p>
        </div>
      </div>
      
      <div class="form-group" id="uploadInput" style="display: none;">
        <label class="form-label">Upload Image</label>
        <div class="file-upload-area" onclick="document.getElementById('imageFileInput').click();">
          <i class="fa-solid fa-cloud-arrow-up"></i>
          <p>Click to browse or drag & drop image here</p>
          <small>Supports: JPG, PNG, GIF (Max 5MB)</small>
        </div>
        <input type="file" id="imageFileInput" accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
        <div id="uploadProgress" style="display: none; margin-top: 10px;">
          <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
          </div>
          <p id="uploadStatus">Uploading...</p>
        </div>
        <div id="imagePreview" style="display: none; margin-top: 10px; position: relative;">
          <div style="position: relative; display: inline-block;">
            <img id="previewImg" onclick="openImageViewer()" title="Click to edit image">
            <div class="image-preview-overlay">
              <button type="button" class="preview-edit-btn" onclick="openImageViewer()" title="Edit Image">
                <i class="fa-solid fa-edit"></i>
              </button>
              <button type="button" class="preview-remove-btn" onclick="removeUploadedImage()" title="Remove Image">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('contentModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Item</button>
      </div>
    </form>
  </div>
</div>

<!-- Image Viewer Modal -->
<div class="image-viewer-modal" id="imageViewerModal">
  <div class="image-viewer-content">
    <button class="image-viewer-close" onclick="closeImageViewer()">
      <i class="fa-solid fa-times"></i>
    </button>
    <img id="viewerImg" class="image-viewer-img" src="">
    <div class="crop-overlay" id="cropOverlay">
      <div class="crop-handles">
        <div class="crop-handle nw"></div>
        <div class="crop-handle ne"></div>
        <div class="crop-handle sw"></div>
        <div class="crop-handle se"></div>
      </div>
    </div>
    <div class="image-viewer-controls">
      <button class="image-viewer-btn" onclick="rotateImage(-90)">
        <i class="fa-solid fa-rotate-left"></i> Rotate Left
      </button>
      <button class="image-viewer-btn" onclick="rotateImage(90)">
        <i class="fa-solid fa-rotate-right"></i> Rotate Right
      </button>
      <button class="image-viewer-btn" onclick="toggleCropMode()">
        <i class="fa-solid fa-crop"></i> <span id="cropBtnText">Crop</span>
      </button>
      <button class="image-viewer-btn" onclick="applyCrop()" id="applyCropBtn" style="display: none;">
        <i class="fa-solid fa-check"></i> Apply Crop
      </button>
      <button class="image-viewer-btn" onclick="cancelCrop()" id="cancelCropBtn" style="display: none;">
        <i class="fa-solid fa-times"></i> Cancel
      </button>
      <button class="image-viewer-btn" onclick="resetImage()">
        <i class="fa-solid fa-undo"></i> Reset
      </button>
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

let currentImageSource = 'url';
let uploadedImagePath = null;

// Switch between URL and upload tabs
function switchImageTab(source) {
  currentImageSource = source;
  
  // Update tab buttons
  document.getElementById('urlTab').classList.toggle('active', source === 'url');
  document.getElementById('uploadTab').classList.toggle('active', source === 'upload');
  
  // Show/hide input sections
  document.getElementById('urlInput').style.display = source === 'url' ? 'block' : 'none';
  document.getElementById('uploadInput').style.display = source === 'upload' ? 'block' : 'none';
  
  // Update form data
  if (source === 'upload' && uploadedImagePath) {
    document.getElementById('contentImage').value = uploadedImagePath;
  }
}

// Handle file upload
function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  // Validate file
  if (!file.type.startsWith('image/')) {
    showToast('Please select an image file (JPG, PNG, GIF)', 'error');
    return;
  }
  
  if (file.size > 5 * 1024 * 1024) { // 5MB
    showToast('File size must be less than 5MB', 'error');
    return;
  }
  
  // Show progress
  document.getElementById('uploadProgress').style.display = 'block';
  document.getElementById('uploadStatus').textContent = 'Uploading...';
  document.getElementById('progressFill').style.width = '0%';
  
  // Create FormData for file upload
  const formData = new FormData();
  formData.append('image', file);
  
  // Simulate upload progress (in real implementation, this would be handled by the server)
  let progress = 0;
  const progressInterval = setInterval(() => {
    progress += Math.random() * 20;
    if (progress > 90) progress = 90;
    document.getElementById('progressFill').style.width = progress + '%';
    document.getElementById('uploadStatus').textContent = `Uploading... ${Math.round(progress)}%`;
  }, 200);
  
  // For demo purposes, we'll simulate a successful upload
  // In production, this would be an actual fetch call to upload endpoint
  setTimeout(() => {
    clearInterval(progressInterval);
    document.getElementById('progressFill').style.width = '100%';
    document.getElementById('uploadStatus').textContent = 'Upload complete!';
    
    // Create a temporary URL for the uploaded image (in production, this would come from server)
    const reader = new FileReader();
    reader.onload = function(e) {
      const imageUrl = e.target.result;
      uploadedImagePath = imageUrl; // Store the base64 or temp URL
      
      // Update the image input field
      document.getElementById('contentImage').value = imageUrl;
      
      // Show preview
      document.getElementById('imagePreview').style.display = 'block';
      document.getElementById('previewImg').src = imageUrl;
      
      // Hide progress after a delay
      setTimeout(() => {
        document.getElementById('uploadProgress').style.display = 'none';
        showToast('Image uploaded successfully!', 'success');
      }, 1000);
    };
    reader.readAsDataURL(file);
  }, 2000);
}

// Remove uploaded image
function removeUploadedImage() {
  uploadedImagePath = null;
  document.getElementById('imagePreview').style.display = 'none';
  document.getElementById('previewImg').src = '';
  document.getElementById('contentImage').value = '';
  document.getElementById('imageFileInput').value = '';
}

// Add drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
  const uploadArea = document.querySelector('.file-upload-area');
  
  if (uploadArea) {
    uploadArea.addEventListener('dragover', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = 'var(--red)';
      this.style.background = 'rgba(194, 38, 38, 0.05)';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = 'var(--line-w)';
      this.style.background = 'var(--card1)';
    });
    
    uploadArea.addEventListener('drop', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = 'var(--line-w)';
      this.style.background = 'var(--card1)';
      
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        document.getElementById('imageFileInput').files = files;
        handleImageUpload({ target: { files: files } });
      }
    });
  }
  
  // Initialize crop handles
  initializeCropHandles();
});

function initializeCropHandles() {
  // Re-initialize handles every time crop mode is toggled
  setupCropDrag();
}

function setupCropDrag() {
  const handles = document.querySelectorAll('.crop-handle');
  let isDragging = false;
  let currentHandle = null;
  let startX, startY;
  let startCropArea = {};
  
  // Remove existing listeners to prevent duplicates
  handles.forEach(handle => {
    const newHandle = handle.cloneNode(true);
    handle.parentNode.replaceChild(newHandle, handle);
  });
  
  // Add fresh listeners
  const freshHandles = document.querySelectorAll('.crop-handle');
  
  freshHandles.forEach(handle => {
    handle.addEventListener('mousedown', function(e) {
      e.preventDefault();
      e.stopPropagation();
      isDragging = true;
      currentHandle = this;
      startX = e.clientX;
      startY = e.clientY;
      startCropArea = { ...cropArea };
      
      // Add visual feedback
      this.style.transform = 'scale(1.3)';
      this.style.background = '#1976D2';
    });
  });
  
  document.addEventListener('mousemove', function(e) {
    if (!isDragging || !currentHandle) return;
    
    const deltaX = e.clientX - startX;
    const deltaY = e.clientY - startY;
    const viewerImg = document.getElementById('viewerImg');
    const imgRect = viewerImg.getBoundingClientRect();
    const modalContent = viewerImg.parentElement;
    const modalRect = modalContent.getBoundingClientRect();
    const relativeX = imgRect.left - modalRect.left;
    const relativeY = imgRect.top - modalRect.top;
    
    const handleClass = currentHandle.className.split(' ')[1];
    let newCropArea = { ...startCropArea };
    
    switch(handleClass) {
      case 'nw':
        newCropArea.x = Math.max(relativeX, startCropArea.x + deltaX);
        newCropArea.y = Math.max(relativeY, startCropArea.y + deltaY);
        newCropArea.width = Math.max(50, startCropArea.width - deltaX);
        newCropArea.height = Math.max(50, startCropArea.height - deltaY);
        break;
      case 'ne':
        newCropArea.y = Math.max(relativeY, startCropArea.y + deltaY);
        newCropArea.width = Math.max(50, startCropArea.width + deltaX);
        newCropArea.height = Math.max(50, startCropArea.height - deltaY);
        break;
      case 'sw':
        newCropArea.x = Math.max(relativeX, startCropArea.x + deltaX);
        newCropArea.width = Math.max(50, startCropArea.width - deltaX);
        newCropArea.height = Math.max(50, startCropArea.height + deltaY);
        break;
      case 'se':
        newCropArea.width = Math.max(50, startCropArea.width + deltaX);
        newCropArea.height = Math.max(50, startCropArea.height + deltaY);
        break;
    }
    
    // Ensure crop area stays within image bounds
    newCropArea.x = Math.max(relativeX, Math.min(newCropArea.x, relativeX + imgRect.width - newCropArea.width));
    newCropArea.y = Math.max(relativeY, Math.min(newCropArea.y, relativeY + imgRect.height - newCropArea.height));
    newCropArea.width = Math.min(newCropArea.width, relativeX + imgRect.width - newCropArea.x);
    newCropArea.height = Math.min(newCropArea.height, relativeY + imgRect.height - newCropArea.y);
    
    cropArea = newCropArea;
    updateCropOverlay();
  });
  
  document.addEventListener('mouseup', function() {
    if (isDragging && currentHandle) {
      currentHandle.style.transform = 'scale(1)';
      currentHandle.style.background = '#2196F3';
    }
    isDragging = false;
    currentHandle = null;
  });
}

// Image viewer functionality
let currentRotation = 0;
let originalImageData = null;
let cropMode = false;
let cropArea = { x: 50, y: 50, width: 200, height: 200 };

function openImageViewer() {
  const viewerModal = document.getElementById('imageViewerModal');
  const viewerImg = document.getElementById('viewerImg');
  const previewImg = document.getElementById('previewImg');
  
  originalImageData = previewImg.src;
  viewerImg.src = previewImg.src;
  currentRotation = 0;
  cropMode = false;
  
  viewerModal.classList.add('active');
  document.getElementById('cropOverlay').classList.remove('active');
  document.getElementById('applyCropBtn').style.display = 'none';
  document.getElementById('cancelCropBtn').style.display = 'none';
  document.getElementById('cropBtnText').textContent = 'Crop';
}

function closeImageViewer() {
  document.getElementById('imageViewerModal').classList.remove('active');
  currentRotation = 0;
  cropMode = false;
}

function rotateImage(degrees) {
  currentRotation += degrees;
  const viewerImg = document.getElementById('viewerImg');
  viewerImg.style.transform = `rotate(${currentRotation}deg)`;
  showToast(`Image rotated ${degrees > 0 ? 'right' : 'left'}`, 'success');
}

function toggleCropMode() {
  cropMode = !cropMode;
  const cropOverlay = document.getElementById('cropOverlay');
  const applyBtn = document.getElementById('applyCropBtn');
  const cancelBtn = document.getElementById('cancelCropBtn');
  const cropBtnText = document.getElementById('cropBtnText');
  
  if (cropMode) {
    cropOverlay.classList.add('active');
    applyBtn.style.display = 'inline-flex';
    cancelBtn.style.display = 'inline-flex';
    cropBtnText.textContent = 'Cancel Crop';
    initializeCropArea();
    // Small delay to ensure overlay is visible before setting up handles
    setTimeout(() => setupCropDrag(), 100);
  } else {
    cropOverlay.classList.remove('active');
    applyBtn.style.display = 'none';
    cancelBtn.style.display = 'none';
    cropBtnText.textContent = 'Crop';
  }
}

function initializeCropArea() {
  const viewerImg = document.getElementById('viewerImg');
  const cropOverlay = document.getElementById('cropOverlay');
  
  // Wait for image to load then set crop area
  if (viewerImg.complete) {
    setInitialCropArea();
  } else {
    viewerImg.onload = setInitialCropArea;
  }
}

function setInitialCropArea() {
  const viewerImg = document.getElementById('viewerImg');
  const imgRect = viewerImg.getBoundingClientRect();
  const modalContent = viewerImg.parentElement;
  const modalRect = modalContent.getBoundingClientRect();
  
  // Calculate position relative to the modal content
  const relativeX = imgRect.left - modalRect.left;
  const relativeY = imgRect.top - modalRect.top;
  
  // Set initial crop area to center 50% of image
  cropArea = {
    x: relativeX + (imgRect.width * 0.25),
    y: relativeY + (imgRect.height * 0.25),
    width: imgRect.width * 0.5,
    height: imgRect.height * 0.5
  };
  
  updateCropOverlay();
}

function updateCropOverlay() {
  const cropOverlay = document.getElementById('cropOverlay');
  cropOverlay.style.left = cropArea.x + 'px';
  cropOverlay.style.top = cropArea.y + 'px';
  cropOverlay.style.width = cropArea.width + 'px';
  cropOverlay.style.height = cropArea.height + 'px';
}

function applyCrop() {
  const viewerImg = document.getElementById('viewerImg');
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  
  // Get the current rotation
  const rotatedImage = getRotatedImage(viewerImg, currentRotation);
  
  // Set canvas size to crop area
  canvas.width = cropArea.width;
  canvas.height = cropArea.height;
  
  // Draw the cropped portion
  ctx.drawImage(rotatedImage, 
    cropArea.x, cropArea.y, cropArea.width, cropArea.height,
    0, 0, cropArea.width, cropArea.height
  );
  
  // Convert canvas to data URL and update both images
  const croppedImageUrl = canvas.toDataURL('image/jpeg', 0.9);
  viewerImg.src = croppedImageUrl;
  document.getElementById('previewImg').src = croppedImageUrl;
  document.getElementById('contentImage').value = croppedImageUrl;
  uploadedImagePath = croppedImageUrl;
  
  // Reset rotation after crop
  currentRotation = 0;
  viewerImg.style.transform = 'rotate(0deg)';
  
  showToast('Crop applied successfully!', 'success');
  toggleCropMode();
}

function getRotatedImage(img, rotation) {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  
  if (rotation === 0) {
    return img;
  }
  
  const angle = rotation * Math.PI / 180;
  const sin = Math.abs(Math.sin(angle));
  const cos = Math.abs(Math.cos(angle));
  
  canvas.width = img.naturalWidth * cos + img.naturalHeight * sin;
  canvas.height = img.naturalWidth * sin + img.naturalHeight * cos;
  
  ctx.translate(canvas.width / 2, canvas.height / 2);
  ctx.rotate(angle);
  ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
  
  const rotatedImg = new Image();
  rotatedImg.src = canvas.toDataURL();
  return rotatedImg;
}

function cancelCrop() {
  toggleCropMode();
}

function resetImage() {
  currentRotation = 0;
  const viewerImg = document.getElementById('viewerImg');
  viewerImg.style.transform = 'rotate(0deg)';
  
  if (cropMode) {
    toggleCropMode();
  }
  
  showToast('Image reset to original', 'success');
}

// Close image viewer on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const viewerModal = document.getElementById('imageViewerModal');
    if (viewerModal.classList.contains('active')) {
      closeImageViewer();
    }
  }
});

// Close image viewer on background click
document.getElementById('imageViewerModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeImageViewer();
  }
});

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
      uploadedImagePath = null;
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
