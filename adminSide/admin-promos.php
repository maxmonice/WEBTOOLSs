<?php
require_once 'admin-config.php';
requireAdmin();

// Handle promo operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    
    if (($data['action'] ?? '') === 'create_promo') {
        // Check if promos table exists
        $tableCheck = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promos'");
        if ((int)$tableCheck->fetchColumn() === 0) {
            echo json_encode(['success' => false, 'message' => 'Promos table not found. Please run migration.']);
            exit;
        }
        
        $code = strtoupper(trim($data['code'] ?? ''));
        $discount = intval($data['discount_percent'] ?? 0);
        $duration = !empty($data['duration_days']) ? intval($data['duration_days']) : null;
        $category = $data['applicable_category'] ?? 'All Items';
        
        if ($discount <= 0 || ($duration !== null && $duration < 0)) {
            echo json_encode(['success' => false, 'message' => 'Invalid values.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO promos (code, discount_percent, duration_days, applicable_category) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$code, $discount, $duration, $category]);
            echo json_encode(['success' => true, 'message' => 'Promo created successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if (($data['action'] ?? '') === 'delete_promo') {
        // Check if promos table exists
        $tableCheck = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promos'");
        if ((int)$tableCheck->fetchColumn() === 0) {
            echo json_encode(['success' => false, 'message' => 'Promos table not found.']);
            exit;
        }
        
        $id = intval($data['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM promos WHERE id = ?");
        try {
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Promo deleted successfully']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// Check if promos table exists
$tableCheck = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promos'");
$promosTableExists = (int)$tableCheck->fetchColumn() > 0;

$promos = [];
$categories = [];

if ($promosTableExists) {
    // Fetch promos
    $stmt = $pdo->query("SELECT * FROM promos ORDER BY created_at DESC");
    $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch categories for the dropdown
    try {
        $catStmt = $pdo->query("SELECT name FROM categories");
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $categories = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Promo Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
    .promos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }
    .promo-ticket {
        background: var(--surface);
        border: 2px solid var(--border); /* Pronounced border as requested */
        border-radius: 16px;
        position: relative;
        overflow: hidden;
        display: flex;
        transition: all 0.3s ease;
        min-height: 140px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .promo-ticket::before, .promo-ticket::after {
        content: '';
        position: absolute;
        left: 90px;
        width: 24px;
        height: 24px;
        background: var(--bg);
        border: 2px solid var(--border);
        border-radius: 50%;
        z-index: 2;
    }
    .promo-ticket::before { top: -13px; }
    .promo-ticket::after { bottom: -13px; }

    .ticket-left {
        width: 100px;
        background: linear-gradient(135deg, var(--red) 0%, var(--red-deep) 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #fff;
        border-right: 3px dashed rgba(255,255,255,0.3);
        padding: 15px;
    }
    .ticket-left i { font-size: 1.8rem; margin-bottom: 8px; }
    .ticket-left span { font-weight: 800; font-size: 1rem; }

    .ticket-right {
        flex: 1;
        padding: 20px 20px 20px 35px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .promo-code-title {
        font-family: 'Aclonica', sans-serif;
        font-size: 1.3rem;
        color: var(--red);
        margin-bottom: 6px;
        letter-spacing: 1px;
    }
    .promo-details {
        color: var(--muted);
        font-size: 0.85rem;
        margin-bottom: 4px;
    }
    .promo-applies {
        font-size: 0.75rem;
        color: #22c55e;
        font-weight: 700;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .promo-ticket:hover {
        border-color: var(--red);
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.3);
    }
    .delete-promo-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .delete-promo-btn:hover {
        background: #ef4444;
        color: #fff;
    }
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">
  <aside class="sidebar" id="sidebar">
<?php $adminNavActive = 'promos'; require __DIR__ . '/admin-sidebar-nav.php'; ?>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
          <div class="topbar-title">Promo Management</div>
          <div class="topbar-breadcrumb">Admin <span>/</span> Promos</div>
        </div>
      </div>
      <div class="topbar-right">
        <?php require __DIR__ . '/admin-topbar-right.php'; ?>
      </div>
    </header>

    <div class="page-content">
      <div class="page-header flex-between">
        <div>
          <h1>Promo Management</h1>
          <p>Create and manage promotional codes for your customers.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('promoModal')"><i class="fa-solid fa-plus"></i> Create Promo</button>
      </div>

      <div class="promos-grid">
        <?php foreach ($promos as $promo): ?>
          <div class="promo-ticket">
            <div class="ticket-left">
                <i class="fa-solid fa-ticket"></i>
                <span><?= $promo['discount_percent'] ?>%</span>
            </div>
            <div class="ticket-right">
              <div class="promo-code-title"><?= htmlspecialchars($promo['code']) ?></div>
              <div class="promo-details">
                <i class="fa-regular fa-clock"></i> <?= $promo['duration_days'] ? $promo['duration_days'] . ' Days' : 'No Expiry' ?>
              </div>
              <div class="promo-applies">
                <i class="fa-solid fa-tag"></i> Applies to: <?= htmlspecialchars($promo['applicable_category']) ?>
              </div>
              <button class="delete-promo-btn" onclick="deletePromo(<?= $promo['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Promo Modal -->
<div class="modal-overlay" id="promoModal">
  <div class="modal">
    <div class="modal-title"><i class="fa-solid fa-ticket" style="color:var(--red);margin-right:8px;"></i>Create Promo Ticket</div>
    <form id="promoForm">
      <div class="form-group">
        <label class="form-label">Promo Code</label>
        <input type="text" class="form-control" id="promoCode" placeholder="e.g. SUMMER30" required>
      </div>
      <div class="form-group">
        <label class="form-label">Applicable To</label>
        <select class="form-control" id="promoCategory">
            <option value="All Items">All Items</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Discount (%)</label>
          <input type="number" class="form-control" id="promoDiscount" min="1" max="100" required>
        </div>
        <div class="form-group">
          <label class="form-label">Duration (Days)</label>
          <input type="number" class="form-control" id="promoDuration" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('promoModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Promo</button>
      </div>
    </form>
  </div>
</div>

<script defer src="admin-notifications.js?v=<?= time() ?>"></script>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.getElementById('promoForm').onsubmit = async (e) => {
    e.preventDefault();
    const data = {
        action: 'create_promo',
        code: document.getElementById('promoCode').value,
        discount_percent: document.getElementById('promoDiscount').value,
        duration_days: document.getElementById('promoDuration').value,
        applicable_category: document.getElementById('promoCategory').value
    };
    const res = await fetch('admin-promos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) location.reload();
    else alert(result.message);
};

async function deletePromo(id) {
    if (!confirm('Delete this promo?')) return;
    const res = await fetch('admin-promos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_promo', id: id })
    });
    const result = await res.json();
    if (result.success) location.reload();
    else alert(result.message || 'Could not delete promo');
}
</script>
</body>
</html>
