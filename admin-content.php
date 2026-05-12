<?php

require_once 'admin-config.php';
require_once 'Notifications.php';

requireAdmin();

$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('admin', $_SESSION['user_id'], 5);
$unreadCount = $notifications->getUnreadCount('admin', $_SESSION['user_id']);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// ── Ensure archive columns exist ──────────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE content_items ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
} catch (PDOException $_) {}
try {
    $pdo->exec("ALTER TABLE content_items ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL");
} catch (PDOException $_) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_variations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content_id INT NOT NULL,
        variation_name VARCHAR(255) NOT NULL,
        variation_price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE
    )");
} catch (PDOException $_) {}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Image upload (multipart)
    if (isset($_FILES['image'])) {
        header('Content-Type: application/json');
        $file = $_FILES['image'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid file type. Allowed: JPG, PNG, GIF, WebP']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success'=>false,'message'=>'File size exceeds 5MB limit']);
            exit;
        }
        $upload_dir = 'uploads/content/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filepath = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            echo json_encode(['success'=>true,'image_path'=>$filepath]);
            logAdminActivity($pdo, 'image_uploaded', "Uploaded image: {$filename}");
        } else {
            echo json_encode(['success'=>false,'message'=>'Failed to upload image']);
        }
        exit;
    }

    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    if (empty($data['action'])) { echo json_encode(['success'=>false,'message'=>'No action']); exit; }

    // ── get_content_list ──────────────────────────────────────────────────────
    if ($data['action'] === 'get_content_list') {
        $type = $data['type'] ?? 'menu';
        try {
            if ($type === 'gallery') {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE category='gallery' AND (is_archived IS NULL OR is_archived=0) ORDER BY created_at DESC");
            } else {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE category IN ('Salad','Fusion','A La Carte','Platters','Bento') AND (is_archived IS NULL OR is_archived=0) ORDER BY category ASC, created_at DESC");
            }
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // attach variations
            $varStmt = $pdo->prepare("SELECT * FROM content_variations WHERE content_id=? ORDER BY created_at ASC");
            foreach ($items as &$item) {
                $varStmt->execute([$item['id']]);
                $item['variations'] = $varStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success'=>true,'items'=>$items]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── get_content ───────────────────────────────────────────────────────────
    if ($data['action'] === 'get_content') {
        $id = (int)($data['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT * FROM content_items WHERE id=?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $varStmt = $pdo->prepare("SELECT * FROM content_variations WHERE content_id=? ORDER BY created_at ASC");
                $varStmt->execute([$id]);
                $item['variations'] = $varStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success'=>true,'content'=>$item]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── create_content ────────────────────────────────────────────────────────
    if ($data['action'] === 'create_content') {
        $name        = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price       = $data['price'] ?? 0;
        $category    = $data['category'] ?? '';
        $image       = $data['image'] ?? '';
        $variations  = $data['variations'] ?? [];
        try {
            $stmt = $pdo->prepare("INSERT INTO content_items (name,description,price,category,image,is_archived,created_at) VALUES (?,?,?,?,?,0,NOW())");
            $stmt->execute([$name,$description,$price,$category,$image]);
            $contentId = $pdo->lastInsertId();

            $notif = new Notifications($pdo);
            if (in_array($category, ['Salad','Fusion','A La Carte','Platters','Bento'])) {
                $notif->autoNotify('menu_item_added', ['name'=>$name,'price'=>$price,'description'=>$description]);
            } elseif ($category === 'gallery') {
                $notif->autoNotify('gallery_item_added', ['title'=>$name,'description'=>$description]);
            }

            if (!empty($variations) && is_array($variations)) {
                $varStmt = $pdo->prepare("INSERT INTO content_variations (content_id,variation_name,variation_price,created_at) VALUES (?,?,?,NOW())");
                foreach ($variations as $v) {
                    if (!empty($v['name']) && isset($v['price'])) {
                        $varStmt->execute([$contentId, $v['name'], $v['price']]);
                    }
                }
            }
            logAdminActivity($pdo, 'content_created', "Created '{$name}' (ID:{$contentId}) in {$category}");
            echo json_encode(['success'=>true,'message'=>'Item created successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── update_content ────────────────────────────────────────────────────────
    if ($data['action'] === 'update_content') {
        $id          = (int)($data['id'] ?? 0);
        $name        = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price       = $data['price'] ?? 0;
        $category    = $data['category'] ?? '';
        $image       = $data['image'] ?? '';
        $variations  = $data['variations'] ?? [];
        try {
            $stmt = $pdo->prepare("UPDATE content_items SET name=?,description=?,price=?,category=?,image=? WHERE id=?");
            $stmt->execute([$name,$description,$price,$category,$image,$id]);
            // Replace variations
            $pdo->prepare("DELETE FROM content_variations WHERE content_id=?")->execute([$id]);
            if (!empty($variations) && is_array($variations)) {
                $varStmt = $pdo->prepare("INSERT INTO content_variations (content_id,variation_name,variation_price,created_at) VALUES (?,?,?,NOW())");
                foreach ($variations as $v) {
                    if (!empty($v['name']) && isset($v['price'])) {
                        $varStmt->execute([$id, $v['name'], $v['price']]);
                    }
                }
            }
            logAdminActivity($pdo, 'content_updated', "Updated '{$name}' (ID:{$id})");
            echo json_encode(['success'=>true,'message'=>'Item updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── archive_content (soft delete) ─────────────────────────────────────────
    if ($data['action'] === 'archive_content') {
        $id = (int)($data['id'] ?? 0);
        try {
            $infoStmt = $pdo->prepare("SELECT name,category FROM content_items WHERE id=?");
            $infoStmt->execute([$id]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("UPDATE content_items SET is_archived=1, archived_at=NOW() WHERE id=?")->execute([$id]);
            logAdminActivity($pdo, 'content_archived', "Archived '{$info['name']}' (ID:{$id})");
            echo json_encode(['success'=>true,'message'=>'Item moved to archive']);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── restore_content ───────────────────────────────────────────────────────
    if ($data['action'] === 'restore_content') {
        $id = (int)($data['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE content_items SET is_archived=0, archived_at=NULL WHERE id=?")->execute([$id]);
            logAdminActivity($pdo, 'content_restored', "Restored content item (ID:{$id})");
            echo json_encode(['success'=>true,'message'=>'Item restored successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── get_archived ──────────────────────────────────────────────────────────
    if ($data['action'] === 'get_archived') {
        $type = $data['type'] ?? 'all'; // all | menu | gallery
        try {
            if ($type === 'menu') {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE is_archived=1 AND category IN ('Salad','Fusion','A La Carte','Platters','Bento') ORDER BY archived_at DESC");
            } elseif ($type === 'gallery') {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE is_archived=1 AND category='gallery' ORDER BY archived_at DESC");
            } else {
                $stmt = $pdo->prepare("SELECT * FROM content_items WHERE is_archived=1 ORDER BY archived_at DESC");
            }
            $stmt->execute();
            echo json_encode(['success'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── update_feedback ───────────────────────────────────────────────────────
    if ($data['action'] === 'update_feedback') {
        $id      = (int)($data['id'] ?? 0);
        $status  = $data['status'] ?? 'reviewed';
        $reply   = trim($data['admin_reply'] ?? '');
        $allowed = ['new','reviewed','replied','archived'];
        if ($id <= 0 || !in_array($status, $allowed, true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid feedback update']); exit;
        }
        if ($reply !== '' && $status === 'reviewed') $status = 'replied';
        try {
            $pdo->prepare("UPDATE customer_feedback SET status=?,admin_reply=?,updated_at=NOW() WHERE id=?")->execute([$status,$reply,$id]);
            logAdminActivity($pdo, 'feedback_updated', "Updated feedback #{$id} to {$status}");
            echo json_encode(['success'=>true,'message'=>'Feedback updated']);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action']);
    exit;
}

// ── GET: page data ────────────────────────────────────────────────────────────
$contentItems = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM content_items WHERE (is_archived IS NULL OR is_archived=0) ORDER BY created_at DESC");
    $stmt->execute();
    $contentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS content_items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, description TEXT, price DECIMAL(10,2) NOT NULL DEFAULT 0, category VARCHAR(100) NOT NULL, image VARCHAR(500), is_archived TINYINT(1) DEFAULT 0, archived_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    } catch (PDOException $_) {}
}

$feedbackItems = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM customer_feedback ORDER BY created_at DESC LIMIT 100");
    $stmt->execute();
    $feedbackItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $_) {}

$contentStats = [
    'total'          => count($contentItems),
    'total_value'    => array_sum(array_column($contentItems, 'price')),
    'updated_today'  => 0,
    'missing_images' => 0,
];
try { $contentStats['updated_today']  = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE DATE(created_at)=CURDATE() AND (is_archived IS NULL OR is_archived=0)")->fetchColumn(); } catch (Throwable $_) {}
try { $contentStats['missing_images'] = (int)$pdo->query("SELECT COUNT(*) FROM content_items WHERE (image IS NULL OR image='') AND (is_archived IS NULL OR is_archived=0)")->fetchColumn(); } catch (Throwable $_) {}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Content Management — Luke's Admin</title>
<link rel="stylesheet" href="admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ══════════════════════════════════════════════════════════════════
   MANAGE CONTENT MEGA MODAL
══════════════════════════════════════════════════════════════════ */
.mcm-overlay {
  position: fixed; inset: 0; z-index: 5000;
  background: rgba(0,0,0,0.72);
  backdrop-filter: blur(6px);
  display: none; align-items: center; justify-content: center;
  padding: 20px;
}
.mcm-overlay.open { display: flex; }

.mcm-modal {
  background: var(--card2, #222);
  border: 1px solid rgba(194,38,38,0.25);
  border-radius: 16px;
  width: min(94vw, 1100px);
  max-height: 90vh;
  display: flex; flex-direction: column;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0,0,0,0.6);
}

.mcm-header {
  display: flex; align-items: center; gap: 12px;
  padding: 18px 24px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
  background: rgba(0,0,0,0.2);
  flex-shrink: 0;
}
.mcm-header-back {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1);
  color: #fff; cursor: pointer; display: none; align-items: center;
  justify-content: center; font-size: 0.9rem; transition: background 0.2s;
}
.mcm-header-back:hover { background: rgba(194,38,38,0.4); }
.mcm-header-back.visible { display: flex; }
.mcm-header-title { font-size: 1.1rem; font-weight: 700; color: #fff; flex: 1; }
.mcm-header-actions { display: flex; gap: 10px; }

.mcm-body {
  flex: 1; overflow-y: auto; padding: 24px;
}
.mcm-body::-webkit-scrollbar { width: 6px; }
.mcm-body::-webkit-scrollbar-track { background: transparent; }
.mcm-body::-webkit-scrollbar-thumb { background: rgba(194,38,38,0.4); border-radius: 3px; }

/* ── Home: option cards ─────────────────────────────── */
.mcm-options {
  display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
  max-width: 700px; margin: 20px auto;
}
.mcm-option-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 14px;
  padding: 36px 28px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.25s, background 0.25s, transform 0.2s;
  display: flex; flex-direction: column; align-items: center; gap: 14px;
}
.mcm-option-card:hover {
  border-color: rgba(194,38,38,0.6);
  background: rgba(194,38,38,0.07);
  transform: translateY(-3px);
}
.mcm-option-icon {
  width: 64px; height: 64px; border-radius: 16px;
  background: rgba(194,38,38,0.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.7rem; color: #C22626;
}
.mcm-option-title { font-size: 1.1rem; font-weight: 700; color: #fff; }
.mcm-option-desc  { font-size: 0.82rem; color: var(--muted, rgba(255,255,255,0.45)); line-height: 1.5; }

/* ── CRUD toolbar ───────────────────────────────────── */
.mcm-toolbar {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 16px; flex-wrap: wrap;
}
.mcm-search {
  margin-left: auto;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  padding: 8px 14px;
  color: #fff; font-size: 0.85rem; outline: none;
  width: 200px; transition: border-color 0.2s;
}
.mcm-search:focus { border-color: rgba(194,38,38,0.5); }
.mcm-search::placeholder { color: rgba(255,255,255,0.3); }

/* ── Item list table ────────────────────────────────── */
.mcm-table {
  width: 100%; border-collapse: collapse; font-size: 0.875rem;
}
.mcm-table th {
  text-align: left; padding: 10px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.5); font-weight: 600;
  font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
}
.mcm-table td {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  vertical-align: middle;
}
.mcm-table tr.selected td { background: rgba(194,38,38,0.1); }
.mcm-table tr:hover td { background: rgba(255,255,255,0.03); }
.mcm-table tr.selected:hover td { background: rgba(194,38,38,0.12); }

.mcm-thumb {
  width: 46px; height: 46px; border-radius: 8px;
  object-fit: cover; background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; color: rgba(255,255,255,0.3); font-size: 1rem;
}
.mcm-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

.mcm-badge {
  display: inline-block;
  padding: 3px 9px; border-radius: 20px;
  font-size: 0.72rem; font-weight: 600;
  background: rgba(194,38,38,0.18); color: #C22626;
}

.mcm-radio { cursor: pointer; accent-color: #C22626; width: 16px; height: 16px; }

/* ── Gallery grid ───────────────────────────────────── */
.mcm-gallery-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); gap: 12px;
}
.mcm-gal-card {
  border-radius: 10px; overflow: hidden;
  border: 2px solid rgba(255,255,255,0.06);
  cursor: pointer; transition: border-color 0.2s;
  position: relative;
}
.mcm-gal-card.selected { border-color: #C22626; }
.mcm-gal-card img { width: 100%; height: 130px; object-fit: cover; display: block; }
.mcm-gal-card-info { padding: 8px 10px; background: rgba(0,0,0,0.3); }
.mcm-gal-card-name { font-size: 0.78rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mcm-gal-card-check {
  position: absolute; top: 8px; right: 8px;
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(0,0,0,0.5); border: 2px solid rgba(255,255,255,0.4);
  display: flex; align-items: center; justify-content: center;
  color: transparent; font-size: 0.7rem;
}
.mcm-gal-card.selected .mcm-gal-card-check {
  background: #C22626; border-color: #C22626; color: #fff;
}
.mcm-gal-placeholder {
  width: 100%; height: 130px; display: flex; align-items: center;
  justify-content: center; background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.2); font-size: 2rem;
}

/* ── Item Form ──────────────────────────────────────── */
.mcm-form { max-width: 700px; }
.mcm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.mcm-img-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.mcm-img-tab {
  padding: 7px 16px; border-radius: 6px; cursor: pointer;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5);
  font-size: 0.83rem; font-weight: 500; transition: all 0.2s;
}
.mcm-img-tab.active { background: var(--red,#C22626); border-color: var(--red,#C22626); color: #fff; }

.mcm-upload-area {
  border: 2px dashed rgba(255,255,255,0.15);
  border-radius: 10px; padding: 28px; text-align: center;
  cursor: pointer; transition: all 0.2s; background: rgba(255,255,255,0.03);
}
.mcm-upload-area:hover { border-color: rgba(194,38,38,0.6); background: rgba(194,38,38,0.05); }
.mcm-upload-area i { font-size: 2rem; color: rgba(255,255,255,0.25); display: block; margin-bottom: 8px; }
.mcm-upload-area p { color: rgba(255,255,255,0.4); font-size: 0.85rem; margin: 0; }
.mcm-upload-area small { color: rgba(255,255,255,0.25); font-size: 0.75rem; margin-top: 4px; display: block; }

.mcm-progress { margin-top: 10px; display: none; }
.mcm-progress-bar { height: 6px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; }
.mcm-progress-fill { height: 100%; background: linear-gradient(90deg,#C22626,#e84040); width: 0%; transition: width 0.3s; border-radius: 3px; }
.mcm-progress-text { font-size: 0.78rem; color: rgba(255,255,255,0.4); margin-top: 5px; text-align: center; }

.mcm-img-preview {
  margin-top: 10px; display: none;
  background: rgba(255,255,255,0.04); border-radius: 8px;
  padding: 10px; border: 1px solid rgba(255,255,255,0.08);
}
.mcm-img-preview-inner { display: inline-block; position: relative; }
.mcm-img-preview img {
  max-width: 110px; max-height: 110px; border-radius: 6px;
  cursor: pointer; display: block;
}
.mcm-img-preview-overlay {
  position: absolute; inset: 0; border-radius: 6px;
  background: rgba(0,0,0,0.65);
  display: flex; align-items: center; justify-content: center; gap: 8px;
  opacity: 0; transition: opacity 0.2s;
}
.mcm-img-preview-inner:hover .mcm-img-preview-overlay { opacity: 1; }
.mcm-img-preview-btn {
  width: 32px; height: 32px; border-radius: 50%;
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
  color: #fff; cursor: pointer; font-size: 0.85rem;
  display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.mcm-img-preview-btn.edit:hover  { background: rgba(46,204,113,0.8); }
.mcm-img-preview-btn.del:hover   { background: rgba(231,76,60,0.8); }

/* ── Variants ───────────────────────────────────────── */
.mcm-variants-box {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px; padding: 16px; margin-top: 4px;
}
.mcm-variant-row {
  display: flex; gap: 10px; align-items: flex-end; margin-bottom: 10px;
}
.mcm-variant-row:last-child { margin-bottom: 0; }
.mcm-add-variant-btn {
  background: none; border: 1px dashed rgba(194,38,38,0.4);
  color: rgba(194,38,38,0.8); border-radius: 7px;
  padding: 8px 14px; cursor: pointer; font-size: 0.83rem; font-weight: 600;
  transition: all 0.2s; margin-top: 10px;
  display: inline-flex; align-items: center; gap: 6px;
}
.mcm-add-variant-btn:hover { background: rgba(194,38,38,0.1); border-color: #C22626; color: #fff; }

.mcm-variant-remove {
  width: 34px; height: 34px; border-radius: 7px; flex-shrink: 0;
  background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.25);
  color: #e74c3c; cursor: pointer; font-size: 0.85rem;
  display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.mcm-variant-remove:hover { background: rgba(231,76,60,0.3); }

/* ── Archive modal ──────────────────────────────────── */
.arch-tabs { display: flex; gap: 0; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 20px; }
.arch-tab {
  padding: 11px 22px; cursor: pointer; font-size: 0.88rem; font-weight: 600;
  color: rgba(255,255,255,0.45); border-bottom: 2px solid transparent;
  transition: all 0.2s; margin-bottom: -1px;
}
.arch-tab.active { color: #fff; border-bottom-color: #C22626; }

.arch-sub-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
.arch-sub-tab {
  padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 0.8rem;
  border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.45); transition: all 0.2s;
}
.arch-sub-tab.active { background: rgba(194,38,38,0.2); border-color: rgba(194,38,38,0.4); color: #fff; }

.arch-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.arch-table th { text-align: left; padding: 9px 12px; color: rgba(255,255,255,0.4); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid rgba(255,255,255,0.07); }
.arch-table td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.04); }
.arch-empty { text-align: center; padding: 40px; color: rgba(255,255,255,0.3); font-size: 0.88rem; }

/* ── Image viewer modal (existing, re-scoped) ───────── */
.image-viewer-modal {
  position: fixed; inset: 0; background: rgba(0,0,0,0.9);
  display: flex; align-items: center; justify-content: center;
  z-index: 9000; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s;
}
.image-viewer-modal.active { opacity: 1; visibility: visible; }
.image-viewer-content {
  position: relative; max-width: 90vw; max-height: 90vh;
  display: flex; flex-direction: column; align-items: center;
}
.image-viewer-img { max-width: 100%; max-height: 76vh; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
.image-viewer-controls { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
.image-viewer-btn {
  background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3);
  color: #fff; padding: 9px 18px; border-radius: 6px; cursor: pointer;
  font-size: 0.87rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s;
}
.image-viewer-btn:hover { background: rgba(255,255,255,0.2); }
.image-viewer-close {
  position: absolute; top: -16px; right: -16px;
  background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3);
  color: #fff; width: 38px; height: 38px; border-radius: 50%; cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s;
}
.image-viewer-close:hover { background: rgba(231,76,60,0.7); }
.crop-overlay {
  position: absolute; top: 0; left: 0; border: 2px dashed #fff;
  background: rgba(0,0,0,0.3); display: none; z-index: 10; pointer-events: auto;
}
.crop-overlay.active { display: block; }
.crop-handle {
  position: absolute; width: 12px; height: 12px; background: #2196F3;
  border: 2px solid #fff; border-radius: 50%; cursor: pointer; z-index: 11; transition: transform 0.2s;
}
.crop-handle:hover { transform: scale(1.2); }
.crop-handle.nw { top:-6px; left:-6px; cursor:nw-resize; }
.crop-handle.ne { top:-6px; right:-6px; cursor:ne-resize; }
.crop-handle.sw { bottom:-6px; left:-6px; cursor:sw-resize; }
.crop-handle.se { bottom:-6px; right:-6px; cursor:se-resize; }

/* ── Notification dropdown (keep existing) ─────────── */
.notification-dropdown {
  position:absolute; top:100%; right:0; width:320px;
  background:var(--card2); border:1px solid var(--line-w);
  border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.3);
  z-index:1000; display:none; max-height:400px; overflow-y:auto;
}
.notification-dropdown.show { display:block; }
.notification-header { padding:12px 16px; border-bottom:1px solid var(--line-w); display:flex; justify-content:space-between; align-items:center; }
.notification-header h3 { margin:0; font-size:0.9rem; color:#fff; }
.notification-header .mark-all { font-size:0.75rem; color:var(--red); background:transparent; border:none; cursor:pointer; }
.notification-item { padding:12px 16px; border-bottom:1px solid var(--line-w); cursor:pointer; transition:background 0.2s; }
.notification-item:hover { background:rgba(194,38,38,0.05); }
.notification-item.unread { background:rgba(52,152,219,0.08); border-left:3px solid #3498db; }
.notification-content { display:flex; gap:12px; align-items:flex-start; }
.notification-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.9rem; }
.notification-text { flex:1; }
.notification-title { font-size:0.85rem; font-weight:600; color:#fff; margin-bottom:4px; }
.notification-message { font-size:0.78rem; color:var(--muted); line-height:1.4; }
.notification-time { font-size:0.72rem; color:var(--muted); margin-top:4px; }
.notification-empty { padding:24px; text-align:center; color:var(--muted); font-size:0.85rem; }

/* ── Feedback table styles ──────────────────────────── */
.content-item { background:var(--card2); border:1px solid var(--line-w); border-radius:12px; overflow:hidden; }

/* ── Empty state ────────────────────────────────────── */
.mcm-empty { text-align:center; padding:50px; color:rgba(255,255,255,0.3); }
.mcm-empty i { font-size:2.5rem; display:block; margin-bottom:14px; }
.mcm-empty p { font-size:0.88rem; }
</style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">

  <!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
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
      <a href="admin-archive.php" class="nav-item"><i class="fa-solid fa-box-archive"></i> Archive</a>
      <a href="admin-messages.php" class="nav-item"><i class="fa-solid fa-message"></i> Messages</a>
      <div class="nav-section-label">System</div>
      <a href="admin-logs.php" class="nav-item"><i class="fa-solid fa-shield-halved"></i> Security & Logs</a>
      <a href="admin-settings.php" class="nav-item"><i class="fa-solid fa-cog"></i> Account Settings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="admin-logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </aside>

  <!-- ═══════════════════════ MAIN ═══════════════════════════ -->
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
        <div class="topbar-badge" style="position:relative;" onclick="toggleNotifications()">
          <i class="fa-regular fa-bell"></i>
          <?php if ($unreadCount > 0): ?>
          <span class="badge-dot" style="background:var(--red);"></span>
          <span style="position:absolute;top:-8px;right:-8px;background:var(--red);color:#fff;border-radius:10px;padding:2px 6px;font-size:0.7rem;font-weight:bold;min-width:18px;text-align:center;"><?= $unreadCount ?></span>
          <?php endif; ?>
        </div>
        <div class="notification-dropdown" id="notificationDropdown">
          <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all" onclick="markAllNotificationsRead()">Mark all read</button>
          </div>
          <div id="notificationList">
            <?php if (empty($userNotifications)): ?>
              <div class="notification-empty">No notifications</div>
            <?php else: ?>
              <?php foreach ($userNotifications as $notif): ?>
                <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" onclick="markNotificationRead(<?= $notif['id'] ?>)">
                  <div class="notification-content">
                    <div class="notification-icon" style="background:<?= getNotificationColor($notif['type']) ?>20;color:<?= getNotificationColor($notif['type']) ?>;"><i class="fa-solid <?= getNotificationIcon($notif['type']) ?>"></i></div>
                    <div class="notification-text">
                      <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                      <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                      <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <a href="admin-settings.php" class="admin-avatar" title="Account Settings" style="text-decoration:none;cursor:pointer;"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></a>
        <a href="account-dashboard.php?user_view=true" class="btn btn-success" style="margin-left:12px;padding:10px 18px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600;border:2px solid var(--red);border-radius:6px;background:linear-gradient(135deg,#C22626,#8B0A1E);box-shadow:0 4px 12px rgba(194,38,38,0.4);color:#ff6b6b;">
          <i class="fa-solid fa-user"></i> User View
        </a>
      </div>
    </header>

    <div class="page-content">

      <!-- ── Page header ────────────────────────────────────── -->
      <div class="page-header flex-between">
        <div>
          <h1>Content Management</h1>
          <p>Manage menu items, gallery content, and more.</p>
        </div>
        <div>
          <button class="btn btn-primary" onclick="openManageContent()">
            <i class="fa-solid fa-layer-group"></i> Manage Content
          </button>
        </div>
      </div>

      <!-- ── Stats ──────────────────────────────────────────── -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-layer-group"></i></div>
          <div class="stat-card-value"><?= number_format($contentStats['total']) ?></div>
          <div class="stat-card-label">Total Items</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> All content</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-tag"></i></div>
          <div class="stat-card-value">₱<?= number_format($contentStats['total_value'], 0) ?></div>
          <div class="stat-card-label">Total Value</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Combined worth</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-clock"></i></div>
          <div class="stat-card-value"><?= number_format($contentStats['updated_today']) ?></div>
          <div class="stat-card-label">Added Today</div>
          <div class="stat-card-change up"><i class="fa-solid fa-arrow-up"></i> Recent changes</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon"><i class="fa-solid fa-image"></i></div>
          <div class="stat-card-value"><?= number_format($contentStats['missing_images']) ?></div>
          <div class="stat-card-label">Missing Images</div>
          <div class="stat-card-change <?= $contentStats['missing_images'] > 0 ? 'down' : 'up' ?>"><i class="fa-solid fa-arrow-<?= $contentStats['missing_images'] > 0 ? 'down' : 'up' ?>"></i> <?= $contentStats['missing_images'] > 0 ? 'Needs attention' : 'All set' ?></div>
        </div>
      </div>

      <!-- ── Customer Feedback ─────────────────────────────── -->
      <?php
        $feedbackCount    = count($feedbackItems);
        $averageRating    = $feedbackCount > 0 ? array_sum(array_map(fn($f) => (int)$f['rating'], $feedbackItems)) / $feedbackCount : 0;
        $newFeedbackCount = count(array_filter($feedbackItems, fn($f) => ($f['status'] ?? 'new') === 'new'));
      ?>
      <div class="panel" style="margin-top:24px;">
        <div class="panel-header">
          <span class="panel-title">Customer Feedback &amp; Ratings</span>
          <div class="flex-gap">
            <span class="badge badge-yellow"><?= $newFeedbackCount ?> New</span>
            <span class="badge badge-blue"><?= $feedbackCount ? number_format($averageRating,1) : '0.0' ?> Avg Rating</span>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr><th>Customer</th><th>Rating</th><th>Feedback</th><th>Reply</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (!empty($feedbackItems)): ?>
                <?php foreach ($feedbackItems as $fb): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($fb['customer_name']) ?></strong><br><span style="color:var(--muted);font-size:0.75rem;"><?= htmlspecialchars($fb['customer_email'] ?: 'No email') ?></span></td>
                    <td style="color:#f39c12;"><?= str_repeat('★', max(0,min(5,(int)$fb['rating']))) ?></td>
                    <td style="min-width:220px;"><?php if (!empty($fb['subject'])): ?><strong><?= htmlspecialchars($fb['subject']) ?></strong><br><?php endif; ?><?= htmlspecialchars($fb['message']) ?></td>
                    <td style="min-width:240px;"><textarea class="form-control" id="feedbackReply<?= (int)$fb['id'] ?>" rows="3"><?= htmlspecialchars($fb['admin_reply'] ?? '') ?></textarea></td>
                    <td>
                      <select class="form-control" id="feedbackStatus<?= (int)$fb['id'] ?>" style="width:auto;min-width:120px;">
                        <?php foreach (['new','reviewed','replied','archived'] as $s): ?>
                          <option value="<?= $s ?>" <?= $fb['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><button class="btn btn-outline btn-sm" onclick="updateFeedback(<?= (int)$fb['id'] ?>)"><i class="fa-solid fa-reply"></i> Save</button></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" style="text-align:center;padding:36px;color:var(--muted);"><i class="fa-solid fa-comment-dots" style="font-size:2rem;margin-bottom:10px;display:block;"></i>No customer feedback yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /page-content -->
  </div><!-- /main-content -->
</div><!-- /admin-layout -->


<!-- ══════════════════════════════════════════════════════════════
     MANAGE CONTENT MEGA MODAL
══════════════════════════════════════════════════════════════════ -->
<div class="mcm-overlay" id="manageContentModal">
  <div class="mcm-modal">

    <!-- Header -->
    <div class="mcm-header">
      <button class="mcm-header-back" id="mcmBackBtn" onclick="mcmBack()"><i class="fa-solid fa-arrow-left"></i></button>
      <div class="mcm-header-title" id="mcmTitle">Manage Content</div>
      <div class="mcm-header-actions">
        <button class="btn btn-outline btn-sm" onclick="openArchiveModal()" style="gap:6px;"><i class="fa-solid fa-box-archive"></i> Archive</button>
        <button class="btn btn-outline btn-sm" onclick="closeMCM()"><i class="fa-solid fa-times"></i></button>
      </div>
    </div>

    <!-- Body -->
    <div class="mcm-body" id="mcmBody">

      <!-- ── VIEW: Home ──────────────────────────────────────── -->
      <div id="mcmViewHome">
        <p style="color:rgba(255,255,255,0.4);font-size:0.88rem;margin-bottom:24px;text-align:center;">Choose what you would like to manage:</p>
        <div class="mcm-options">
          <div class="mcm-option-card" onclick="mcmShowItemList()">
            <div class="mcm-option-icon"><i class="fa-solid fa-utensils"></i></div>
            <div class="mcm-option-title">Item List</div>
            <div class="mcm-option-desc">Add, edit, or remove menu items (Salad, Fusion, A La Carte, Platters, Bento). Changes reflect live in menu.php.</div>
          </div>
          <div class="mcm-option-card" onclick="mcmShowGallery()">
            <div class="mcm-option-icon"><i class="fa-solid fa-images"></i></div>
            <div class="mcm-option-title">Gallery Collection</div>
            <div class="mcm-option-desc">Manage photo gallery items. Changes reflect live in gallery.php.</div>
          </div>
        </div>
      </div>

      <!-- ── VIEW: Item List ────────────────────────────────── -->
      <div id="mcmViewItemList" style="display:none;">
        <div class="mcm-toolbar">
          <button class="btn btn-success btn-sm" onclick="mcmOpenItemForm(null)"><i class="fa-solid fa-plus"></i> Add</button>
          <button class="btn btn-outline btn-sm" id="mcmItemEditBtn" onclick="mcmEditSelected('item')" disabled><i class="fa-solid fa-pen"></i> Edit</button>
          <button class="btn btn-danger btn-sm"  id="mcmItemDeleteBtn" onclick="mcmArchiveSelected('item')" disabled><i class="fa-solid fa-box-archive"></i> Delete</button>
          <input type="text" class="mcm-search" id="mcmItemSearch" placeholder="&#xf002;  Search items…" oninput="mcmFilterItems()" />
        </div>
        <div style="overflow-x:auto;">
          <table class="mcm-table" id="mcmItemTable">
            <thead>
              <tr>
                <th style="width:30px;"></th>
                <th style="width:56px;">Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Variants</th>
              </tr>
            </thead>
            <tbody id="mcmItemTbody">
              <tr><td colspan="6" class="mcm-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading…</p></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── VIEW: Gallery ──────────────────────────────────── -->
      <div id="mcmViewGallery" style="display:none;">
        <div class="mcm-toolbar">
          <button class="btn btn-success btn-sm" onclick="mcmOpenGalleryForm(null)"><i class="fa-solid fa-plus"></i> Add</button>
          <button class="btn btn-outline btn-sm" id="mcmGalEditBtn" onclick="mcmEditSelected('gallery')" disabled><i class="fa-solid fa-pen"></i> Edit</button>
          <button class="btn btn-danger btn-sm"  id="mcmGalDeleteBtn" onclick="mcmArchiveSelected('gallery')" disabled><i class="fa-solid fa-box-archive"></i> Delete</button>
        </div>
        <div class="mcm-gallery-grid" id="mcmGalleryGrid">
          <div class="mcm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading…</p></div>
        </div>
      </div>

      <!-- ── VIEW: Item Form (Add / Edit) ──────────────────── -->
      <div id="mcmViewItemForm" style="display:none;">
        <div class="mcm-form">
          <input type="hidden" id="mcmItemId" value="">

          <div class="form-group">
            <label class="form-label">Item Name <span style="color:#C22626;">*</span></label>
            <input type="text" class="form-control" id="mcmItemName" placeholder="e.g. California Maki" required>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="mcmItemDesc" rows="3" placeholder="Brief description of the item…"></textarea>
          </div>

          <div class="mcm-form-row">
            <div class="form-group">
              <label class="form-label">Category <span style="color:#C22626;">*</span></label>
              <select class="form-control" id="mcmItemCategory">
                <option value="Salad">Salad</option>
                <option value="Fusion">Fusion Rolls &amp; Sushi</option>
                <option value="A La Carte">A La Carte</option>
                <option value="Platters">Platters</option>
                <option value="Bento">Bento Boxes</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Base Price (₱)</label>
              <input type="number" class="form-control" id="mcmItemPrice" step="0.01" min="0" placeholder="0.00">
            </div>
          </div>

          <!-- Image Upload -->
          <div class="form-group">
            <label class="form-label">Image</label>
            <div class="mcm-img-tabs">
              <div class="mcm-img-tab active" id="mcmItemUrlTab"  onclick="mcmSwitchImgTab('item','url')"><i class="fa-solid fa-link"></i> URL</div>
              <div class="mcm-img-tab"         id="mcmItemUpTab"   onclick="mcmSwitchImgTab('item','upload')"><i class="fa-solid fa-upload"></i> Upload</div>
            </div>
            <div id="mcmItemUrlInput">
              <input type="url" class="form-control" id="mcmItemImageUrl" placeholder="https://example.com/image.jpg" oninput="mcmUrlPreview('item')">
            </div>
            <div id="mcmItemUpInput" style="display:none;">
              <div class="mcm-upload-area" onclick="document.getElementById('mcmItemFileInput').click();">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Click to browse or drag &amp; drop</p>
                <small>JPG, PNG, WebP — max 5 MB</small>
              </div>
              <input type="file" id="mcmItemFileInput" accept="image/*" style="display:none;" onchange="mcmHandleUpload(event,'item')">
              <div class="mcm-progress" id="mcmItemProgress">
                <div class="mcm-progress-bar"><div class="mcm-progress-fill" id="mcmItemProgressFill"></div></div>
                <div class="mcm-progress-text" id="mcmItemProgressText">Uploading…</div>
              </div>
            </div>
            <!-- Shared preview -->
            <div class="mcm-img-preview" id="mcmItemPreview">
              <div class="mcm-img-preview-inner">
                <img id="mcmItemPreviewImg" src="" alt="Preview">
                <div class="mcm-img-preview-overlay">
                  <button type="button" class="mcm-img-preview-btn edit" onclick="mcmOpenViewer('item')" title="Edit"><i class="fa-solid fa-edit"></i></button>
                  <button type="button" class="mcm-img-preview-btn del"  onclick="mcmClearImage('item')"  title="Remove"><i class="fa-solid fa-trash"></i></button>
                </div>
              </div>
            </div>
            <input type="hidden" id="mcmItemImagePath" value="">
          </div>

          <!-- Price Variants -->
          <div class="form-group">
            <label class="form-label">Price Variants</label>
            <div class="mcm-variants-box">
              <div id="mcmVariantsList"></div>
              <button type="button" class="mcm-add-variant-btn" onclick="mcmAddVariant()">
                <i class="fa-solid fa-plus"></i> Add More Variants
              </button>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px;">
            <button type="button" class="btn btn-outline" onclick="mcmBack()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="mcmSubmitItemForm()"><i class="fa-solid fa-check"></i> Save Item</button>
          </div>
        </div>
      </div>

      <!-- ── VIEW: Gallery Form (Add / Edit) ───────────────── -->
      <div id="mcmViewGalleryForm" style="display:none;">
        <div class="mcm-form">
          <input type="hidden" id="mcmGalId" value="">

          <div class="form-group">
            <label class="form-label">Name <span style="color:#C22626;">*</span></label>
            <input type="text" class="form-control" id="mcmGalName" placeholder="Photo name or title" required>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="mcmGalDesc" rows="3" placeholder="Optional description…"></textarea>
          </div>

          <!-- Image Upload -->
          <div class="form-group">
            <label class="form-label">Image <span style="color:#C22626;">*</span></label>
            <div class="mcm-img-tabs">
              <div class="mcm-img-tab active" id="mcmGalUrlTab" onclick="mcmSwitchImgTab('gal','url')"><i class="fa-solid fa-link"></i> URL</div>
              <div class="mcm-img-tab"         id="mcmGalUpTab"  onclick="mcmSwitchImgTab('gal','upload')"><i class="fa-solid fa-upload"></i> Upload</div>
            </div>
            <div id="mcmGalUrlInput">
              <input type="url" class="form-control" id="mcmGalImageUrl" placeholder="https://example.com/photo.jpg" oninput="mcmUrlPreview('gal')">
            </div>
            <div id="mcmGalUpInput" style="display:none;">
              <div class="mcm-upload-area" onclick="document.getElementById('mcmGalFileInput').click();">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Click to browse or drag &amp; drop</p>
                <small>JPG, PNG, WebP — max 5 MB</small>
              </div>
              <input type="file" id="mcmGalFileInput" accept="image/*" style="display:none;" onchange="mcmHandleUpload(event,'gal')">
              <div class="mcm-progress" id="mcmGalProgress">
                <div class="mcm-progress-bar"><div class="mcm-progress-fill" id="mcmGalProgressFill"></div></div>
                <div class="mcm-progress-text" id="mcmGalProgressText">Uploading…</div>
              </div>
            </div>
            <div class="mcm-img-preview" id="mcmGalPreview">
              <div class="mcm-img-preview-inner">
                <img id="mcmGalPreviewImg" src="" alt="Preview">
                <div class="mcm-img-preview-overlay">
                  <button type="button" class="mcm-img-preview-btn edit" onclick="mcmOpenViewer('gal')" title="Edit"><i class="fa-solid fa-edit"></i></button>
                  <button type="button" class="mcm-img-preview-btn del"  onclick="mcmClearImage('gal')"  title="Remove"><i class="fa-solid fa-trash"></i></button>
                </div>
              </div>
            </div>
            <input type="hidden" id="mcmGalImagePath" value="">
          </div>

          <div style="display:flex;gap:12px;margin-top:8px;">
            <button type="button" class="btn btn-outline" onclick="mcmBack()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="mcmSubmitGalleryForm()"><i class="fa-solid fa-check"></i> Save Photo</button>
          </div>
        </div>
      </div>

    </div><!-- /mcm-body -->
  </div><!-- /mcm-modal -->
</div><!-- /manageContentModal -->


<!-- ══════════════════════════════════════════════════════════════
     ARCHIVE MODAL
══════════════════════════════════════════════════════════════════ -->
<div class="mcm-overlay" id="archiveModal">
  <div class="mcm-modal" style="max-width:780px;">
    <div class="mcm-header">
      <i class="fa-solid fa-box-archive" style="color:#C22626;font-size:1.1rem;"></i>
      <div class="mcm-header-title">Archive</div>
      <button class="btn btn-outline btn-sm" onclick="closeArchiveModal()"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="mcm-body">
      <div class="arch-tabs">
        <div class="arch-tab active" id="archTabUsers"   onclick="archSwitchTab('users')">Users</div>
        <div class="arch-tab"        id="archTabContent" onclick="archSwitchTab('content')">Content</div>
      </div>

      <!-- Users (empty) -->
      <div id="archViewUsers">
        <div class="arch-empty"><i class="fa-solid fa-users" style="font-size:2.5rem;display:block;margin-bottom:14px;"></i><p>No archived users yet. This section will show users that have been archived from User Management.</p></div>
      </div>

      <!-- Content -->
      <div id="archViewContent" style="display:none;">
        <div class="arch-sub-tabs">
          <div class="arch-sub-tab active" id="archSubMenu"    onclick="archSwitchSub('menu')">Item List</div>
          <div class="arch-sub-tab"        id="archSubGallery" onclick="archSwitchSub('gallery')">Gallery Collection</div>
        </div>
        <div id="archContentBody">
          <div class="arch-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading…</p></div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     IMAGE VIEWER MODAL (SHARED — CROP / ROTATE)
══════════════════════════════════════════════════════════════════ -->
<div class="image-viewer-modal" id="imageViewerModal">
  <div class="image-viewer-content">
    <button class="image-viewer-close" onclick="viewerClose()"><i class="fa-solid fa-times"></i></button>
    <img id="viewerImg" class="image-viewer-img" src="">
    <div class="crop-overlay" id="cropOverlay">
      <div><div class="crop-handle nw"></div><div class="crop-handle ne"></div><div class="crop-handle sw"></div><div class="crop-handle se"></div></div>
    </div>
    <div class="image-viewer-controls">
      <button class="image-viewer-btn" onclick="viewerRotate(-90)"><i class="fa-solid fa-rotate-left"></i> Rotate Left</button>
      <button class="image-viewer-btn" onclick="viewerRotate(90)"><i class="fa-solid fa-rotate-right"></i> Rotate Right</button>
      <button class="image-viewer-btn" onclick="viewerToggleCrop()"><i class="fa-solid fa-crop"></i> <span id="cropBtnText">Crop</span></button>
      <button class="image-viewer-btn" id="applyCropBtn" style="display:none;" onclick="viewerApplyCrop()"><i class="fa-solid fa-check"></i> Apply Crop</button>
      <button class="image-viewer-btn" id="cancelCropBtn" style="display:none;" onclick="viewerCancelCrop()"><i class="fa-solid fa-times"></i> Cancel</button>
      <button class="image-viewer-btn" onclick="viewerReset()"><i class="fa-solid fa-undo"></i> Reset</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>


<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════════ -->
<script>
// ════════════════════════════════════════════════════
// UTILITIES
// ════════════════════════════════════════════════════
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

function showToast(msg, type='') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fa-solid fa-${type==='error'?'circle-xmark':'check-circle'}"></i> ${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// Notification helpers
function toggleNotifications() {
  const d = document.getElementById('notificationDropdown');
  d.classList.toggle('show');
  if (!d.dataset.listenerAdded) {
    d.dataset.listenerAdded = 'true';
    document.addEventListener('click', e => {
      if (!d.contains(e.target) && !e.target.closest('.topbar-badge')) d.classList.remove('show');
    });
  }
}
function markNotificationRead(id) {
  fetch('admin-handle-notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read',notification_id:id})})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); });
}
function markAllNotificationsRead() {
  fetch('admin-handle-notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_all_read'})})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); });
}

function updateFeedback(id) {
  const reply  = document.getElementById(`feedbackReply${id}`).value;
  const status = document.getElementById(`feedbackStatus${id}`).value;
  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'update_feedback',id,status,admin_reply:reply})})
    .then(r=>r.json()).then(d=>{
      if(d.success){ showToast('Feedback updated!','success'); setTimeout(()=>location.reload(),700); }
      else showToast(d.message||'Failed','error');
    });
}


// ════════════════════════════════════════════════════
// MANAGE CONTENT MODAL (MCM)
// ════════════════════════════════════════════════════
const MCM = {
  view: 'home',        // home | itemList | gallery | itemForm | galleryForm
  prevView: null,
  selectedItemId: null,
  selectedGalId:  null,
  itemData:   [],
  galleryData: [],
};

function openManageContent() {
  document.getElementById('manageContentModal').classList.add('open');
  mcmSetView('home');
}
function closeMCM() {
  document.getElementById('manageContentModal').classList.remove('open');
}

document.getElementById('manageContentModal').addEventListener('click', e => {
  if (e.target === document.getElementById('manageContentModal')) closeMCM();
});

function mcmSetView(view, title) {
  // hide all
  ['Home','ItemList','Gallery','ItemForm','GalleryForm'].forEach(v => {
    const el = document.getElementById('mcmView'+v);
    if (el) el.style.display = 'none';
  });
  // show target
  const el = document.getElementById('mcmView' + view.charAt(0).toUpperCase() + view.slice(1));
  if (el) el.style.display = '';

  MCM.prevView = MCM.view;
  MCM.view = view;

  // back button
  document.getElementById('mcmBackBtn').classList.toggle('visible', view !== 'home');

  // title
  const titles = {
    home:        'Manage Content',
    itemList:    'Item List',
    gallery:     'Gallery Collection',
    itemForm:    document.getElementById('mcmItemId').value ? 'Edit Item' : 'Add Item',
    galleryForm: document.getElementById('mcmGalId').value  ? 'Edit Photo' : 'Add Photo',
  };
  document.getElementById('mcmTitle').textContent = titles[view] || 'Manage Content';
}

function mcmBack() {
  if (MCM.view === 'itemForm')    { mcmSetView('itemList'); }
  else if (MCM.view === 'galleryForm') { mcmSetView('gallery'); }
  else { mcmSetView('home'); }
}

// ── Item List ────────────────────────────────────────
function mcmShowItemList() {
  mcmSetView('itemList');
  mcmLoadItems();
}

function mcmLoadItems() {
  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'get_content_list',type:'menu'})})
    .then(r=>r.json()).then(d=>{
      if(d.success) {
        MCM.itemData = d.items;
        MCM.selectedItemId = null;
        mcmRenderItems(d.items);
        document.getElementById('mcmItemEditBtn').disabled   = true;
        document.getElementById('mcmItemDeleteBtn').disabled = true;
      }
    }).catch(()=>showToast('Failed to load items','error'));
}

function mcmRenderItems(items) {
  const tbody = document.getElementById('mcmItemTbody');
  if (!items.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="mcm-empty"><i class="fa-solid fa-utensils"></i><p>No menu items yet. Click <strong>Add</strong> to create one.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = items.map(item => {
    const thumb = item.image
      ? `<div class="mcm-thumb"><img src="${escHtml(item.image)}" alt=""></div>`
      : `<div class="mcm-thumb"><i class="fa-solid fa-image"></i></div>`;
    const varCount = (item.variations||[]).length;
    return `<tr data-id="${item.id}" onclick="mcmSelectItem(${item.id}, this)">
      <td><input type="radio" class="mcm-radio" name="mcmItemSel" data-id="${item.id}" onclick="mcmSelectItem(${item.id},this.closest('tr'))"></td>
      <td>${thumb}</td>
      <td style="font-weight:600;color:#fff;">${escHtml(item.name)}</td>
      <td><span class="mcm-badge">${escHtml(item.category)}</span></td>
      <td style="color:#C22626;font-weight:600;">₱${parseFloat(item.price||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
      <td style="color:rgba(255,255,255,0.5);">${varCount} variant${varCount!==1?'s':''}</td>
    </tr>`;
  }).join('');
}

function mcmFilterItems() {
  const q = document.getElementById('mcmItemSearch').value.toLowerCase();
  const filtered = MCM.itemData.filter(i =>
    i.name.toLowerCase().includes(q) || i.category.toLowerCase().includes(q)
  );
  mcmRenderItems(filtered);
}

function mcmSelectItem(id, row) {
  document.querySelectorAll('#mcmItemTbody tr').forEach(r => r.classList.remove('selected'));
  row.classList.add('selected');
  MCM.selectedItemId = id;
  document.getElementById('mcmItemEditBtn').disabled   = false;
  document.getElementById('mcmItemDeleteBtn').disabled = false;
  // sync radio
  const radio = row.querySelector('input[type=radio]');
  if (radio) radio.checked = true;
}

// ── Gallery ──────────────────────────────────────────
function mcmShowGallery() {
  mcmSetView('gallery');
  mcmLoadGallery();
}

function mcmLoadGallery() {
  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'get_content_list',type:'gallery'})})
    .then(r=>r.json()).then(d=>{
      if(d.success) {
        MCM.galleryData = d.items;
        MCM.selectedGalId = null;
        mcmRenderGallery(d.items);
        document.getElementById('mcmGalEditBtn').disabled   = true;
        document.getElementById('mcmGalDeleteBtn').disabled = true;
      }
    }).catch(()=>showToast('Failed to load gallery','error'));
}

function mcmRenderGallery(items) {
  const grid = document.getElementById('mcmGalleryGrid');
  if (!items.length) {
    grid.innerHTML = `<div class="mcm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-images"></i><p>No gallery photos yet. Click <strong>Add</strong> to upload one.</p></div>`;
    return;
  }
  grid.innerHTML = items.map(item => {
    const img = item.image
      ? `<img src="${escHtml(item.image)}" alt="${escHtml(item.name)}">`
      : `<div class="mcm-gal-placeholder"><i class="fa-solid fa-image"></i></div>`;
    return `<div class="mcm-gal-card" data-id="${item.id}" onclick="mcmSelectGal(${item.id},this)">
      ${img}
      <div class="mcm-gal-card-check"><i class="fa-solid fa-check"></i></div>
      <div class="mcm-gal-card-info"><div class="mcm-gal-card-name">${escHtml(item.name)}</div></div>
    </div>`;
  }).join('');
}

function mcmSelectGal(id, card) {
  document.querySelectorAll('.mcm-gal-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  MCM.selectedGalId = id;
  document.getElementById('mcmGalEditBtn').disabled   = false;
  document.getElementById('mcmGalDeleteBtn').disabled = false;
}

// ── Edit/Archive selected ─────────────────────────────
function mcmEditSelected(type) {
  if (type === 'item'    && MCM.selectedItemId) mcmOpenItemForm(MCM.selectedItemId);
  if (type === 'gallery' && MCM.selectedGalId)  mcmOpenGalleryForm(MCM.selectedGalId);
}

function mcmArchiveSelected(type) {
  const id = type === 'item' ? MCM.selectedItemId : MCM.selectedGalId;
  if (!id) return;
  if (!confirm('Move this item to the archive? It can be restored later.')) return;
  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'archive_content',id})})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        showToast('Item moved to archive','success');
        type==='item' ? mcmLoadItems() : mcmLoadGallery();
      } else showToast(d.message||'Failed','error');
    });
}

// ── Item Form ────────────────────────────────────────
let mcmVariants = [];

function mcmOpenItemForm(id) {
  // reset form
  document.getElementById('mcmItemId').value       = '';
  document.getElementById('mcmItemName').value     = '';
  document.getElementById('mcmItemDesc').value     = '';
  document.getElementById('mcmItemPrice').value    = '';
  document.getElementById('mcmItemImageUrl').value = '';
  document.getElementById('mcmItemImagePath').value= '';
  mcmClearImage('item');
  mcmSwitchImgTab('item','url');
  mcmVariants = [];
  mcmRenderVariants();

  if (id) {
    document.getElementById('mcmItemId').value = id;
    fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'get_content',id})})
      .then(r=>r.json()).then(d=>{
        if(d.success && d.content){
          const c = d.content;
          document.getElementById('mcmItemName').value     = c.name     || '';
          document.getElementById('mcmItemDesc').value     = c.description || '';
          document.getElementById('mcmItemPrice').value    = c.price    || '';
          document.getElementById('mcmItemCategory').value = c.category || 'A La Carte';
          if (c.image) {
            document.getElementById('mcmItemImageUrl').value  = c.image;
            document.getElementById('mcmItemImagePath').value = c.image;
            mcmShowPreview('item', c.image);
          }
          // load variants
          mcmVariants = (c.variations || []).map(v => ({
            id:    Date.now() + Math.random(),
            name:  v.variation_name  || '',
            price: v.variation_price || 0,
          }));
          mcmRenderVariants();
        }
      });
  }
  mcmSetView('itemForm');
}

function mcmRenderVariants() {
  const box = document.getElementById('mcmVariantsList');
  if (!mcmVariants.length) {
    box.innerHTML = '<p style="color:rgba(255,255,255,0.3);font-size:0.82rem;margin:0 0 4px;">No variants added yet.</p>';
    return;
  }
  box.innerHTML = mcmVariants.map(v => `
    <div class="mcm-variant-row">
      <div class="form-group" style="flex:1;margin:0;">
        <label class="form-label" style="font-size:0.78rem;">Type</label>
        <input type="text" class="form-control" value="${escHtml(v.name)}" placeholder="e.g. Small, Family, Fiesta" oninput="mcmUpdateVariant('${v.id}','name',this.value)">
      </div>
      <div class="form-group" style="width:130px;margin:0;">
        <label class="form-label" style="font-size:0.78rem;">Price (₱)</label>
        <input type="number" class="form-control" value="${v.price}" step="0.01" min="0" placeholder="0.00" oninput="mcmUpdateVariant('${v.id}','price',this.value)">
      </div>
      <button type="button" class="mcm-variant-remove" onclick="mcmRemoveVariant('${v.id}')"><i class="fa-solid fa-times"></i></button>
    </div>
  `).join('');
}

function mcmAddVariant() {
  mcmVariants.push({ id: Date.now() + Math.random(), name:'', price:0 });
  mcmRenderVariants();
}
function mcmRemoveVariant(id) {
  mcmVariants = mcmVariants.filter(v => String(v.id) !== String(id));
  mcmRenderVariants();
}
function mcmUpdateVariant(id, field, value) {
  const v = mcmVariants.find(v => String(v.id) === String(id));
  if (v) v[field] = value;
}

function mcmSubmitItemForm() {
  const id       = document.getElementById('mcmItemId').value;
  const name     = document.getElementById('mcmItemName').value.trim();
  const desc     = document.getElementById('mcmItemDesc').value.trim();
  const price    = document.getElementById('mcmItemPrice').value || 0;
  const category = document.getElementById('mcmItemCategory').value;
  const image    = document.getElementById('mcmItemImagePath').value || document.getElementById('mcmItemImageUrl').value;

  if (!name) { showToast('Item name is required','error'); return; }

  const payload = {
    action:     id ? 'update_content' : 'create_content',
    id:         id || undefined,
    name, description: desc, price, category, image,
    variations: mcmVariants.map(v => ({name:v.name, price:v.price})).filter(v=>v.name),
  };

  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        showToast(id ? 'Item updated!' : 'Item created!','success');
        mcmBack();
        mcmLoadItems();
      } else showToast(d.message||'Failed to save','error');
    }).catch(()=>showToast('Network error','error'));
}

// ── Gallery Form ─────────────────────────────────────
function mcmOpenGalleryForm(id) {
  document.getElementById('mcmGalId').value        = '';
  document.getElementById('mcmGalName').value      = '';
  document.getElementById('mcmGalDesc').value      = '';
  document.getElementById('mcmGalImageUrl').value  = '';
  document.getElementById('mcmGalImagePath').value = '';
  mcmClearImage('gal');
  mcmSwitchImgTab('gal','url');

  if (id) {
    document.getElementById('mcmGalId').value = id;
    fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'get_content',id})})
      .then(r=>r.json()).then(d=>{
        if(d.success && d.content){
          const c = d.content;
          document.getElementById('mcmGalName').value = c.name || '';
          document.getElementById('mcmGalDesc').value = c.description || '';
          if (c.image) {
            document.getElementById('mcmGalImageUrl').value  = c.image;
            document.getElementById('mcmGalImagePath').value = c.image;
            mcmShowPreview('gal', c.image);
          }
        }
      });
  }
  mcmSetView('galleryForm');
}

function mcmSubmitGalleryForm() {
  const id    = document.getElementById('mcmGalId').value;
  const name  = document.getElementById('mcmGalName').value.trim();
  const desc  = document.getElementById('mcmGalDesc').value.trim();
  const image = document.getElementById('mcmGalImagePath').value || document.getElementById('mcmGalImageUrl').value;

  if (!name)  { showToast('Name is required','error');  return; }
  if (!image) { showToast('Image is required','error'); return; }

  const payload = {
    action: id ? 'update_content' : 'create_content',
    id: id || undefined,
    name, description: desc, price: 0, category:'gallery', image,
  };

  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        showToast(id ? 'Photo updated!' : 'Photo added!','success');
        mcmBack();
        mcmLoadGallery();
      } else showToast(d.message||'Failed to save','error');
    }).catch(()=>showToast('Network error','error'));
}

// ════════════════════════════════════════════════════
// IMAGE UPLOAD & PREVIEW (generic, prefix=item|gal)
// ════════════════════════════════════════════════════
function mcmSwitchImgTab(prefix, mode) {
  document.getElementById('mcm'+cap(prefix)+'UrlTab').classList.toggle('active', mode==='url');
  document.getElementById('mcm'+cap(prefix)+'UpTab').classList.toggle('active',  mode==='upload');
  document.getElementById('mcm'+cap(prefix)+'UrlInput').style.display = mode==='url' ? '' : 'none';
  document.getElementById('mcm'+cap(prefix)+'UpInput').style.display  = mode==='upload' ? '' : 'none';
}

function mcmUrlPreview(prefix) {
  const url = document.getElementById('mcm'+cap(prefix)+'ImageUrl').value.trim();
  if (url) {
    document.getElementById('mcm'+cap(prefix)+'ImagePath').value = url;
    mcmShowPreview(prefix, url);
  } else {
    mcmClearImage(prefix);
  }
}

function mcmShowPreview(prefix, src) {
  const preview = document.getElementById('mcm'+cap(prefix)+'Preview');
  const img     = document.getElementById('mcm'+cap(prefix)+'PreviewImg');
  preview.style.display = '';
  img.src = src;
}

function mcmClearImage(prefix) {
  document.getElementById('mcm'+cap(prefix)+'Preview').style.display = 'none';
  document.getElementById('mcm'+cap(prefix)+'PreviewImg').src = '';
  document.getElementById('mcm'+cap(prefix)+'ImagePath').value = '';
}

function mcmHandleUpload(event, prefix) {
  const file = event.target.files[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) { showToast('Please select an image file','error'); return; }
  if (file.size > 5*1024*1024) { showToast('File must be under 5 MB','error'); return; }

  const prog     = document.getElementById('mcm'+cap(prefix)+'Progress');
  const fill     = document.getElementById('mcm'+cap(prefix)+'ProgressFill');
  const txt      = document.getElementById('mcm'+cap(prefix)+'ProgressText');
  prog.style.display = '';
  fill.style.width = '0%';
  txt.textContent = 'Uploading…';

  const fd = new FormData();
  fd.append('image', file);
  const xhr = new XMLHttpRequest();
  xhr.upload.addEventListener('progress', e => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded/e.total)*100);
      fill.style.width = pct+'%';
      txt.textContent = `Uploading… ${pct}%`;
    }
  });
  xhr.addEventListener('load', () => {
    prog.style.display = 'none';
    try {
      const res = JSON.parse(xhr.responseText);
      if (res.success) {
        document.getElementById('mcm'+cap(prefix)+'ImagePath').value = res.image_path;
        mcmShowPreview(prefix, res.image_path);
        showToast('Image uploaded!','success');
      } else { showToast(res.message||'Upload failed','error'); }
    } catch(e) { showToast('Upload response error','error'); }
  });
  xhr.addEventListener('error', ()=>{ prog.style.display='none'; showToast('Upload error','error'); });
  xhr.open('POST','admin-content.php',true);
  xhr.send(fd);
}

// Drag & drop for upload areas
document.querySelectorAll('.mcm-upload-area').forEach(area => {
  area.addEventListener('dragover', e => { e.preventDefault(); area.style.borderColor='rgba(194,38,38,0.7)'; });
  area.addEventListener('dragleave', e => { e.preventDefault(); area.style.borderColor=''; });
  area.addEventListener('drop', e => {
    e.preventDefault(); area.style.borderColor='';
    const files = e.dataTransfer.files;
    if (!files.length) return;
    // figure out which prefix
    const inputId = area.nextElementSibling ? area.nextElementSibling.id : '';
    const prefix = inputId.includes('Gal') ? 'gal' : 'item';
    const fi = document.getElementById('mcm'+cap(prefix)+'FileInput');
    if (fi) {
      // create fake event
      Object.defineProperty(fi, 'files', { value: files, writable: false });
      mcmHandleUpload({target:{files}}, prefix);
    }
  });
});

// ════════════════════════════════════════════════════
// IMAGE VIEWER (CROP / ROTATE)
// ════════════════════════════════════════════════════
let viewerRotation = 0;
let viewerCropMode = false;
let viewerCropArea = {x:50,y:50,width:200,height:200};
let viewerActivePrefix = null;
let viewerOriginalSrc  = null;

function mcmOpenViewer(prefix) {
  viewerActivePrefix = prefix;
  const img = document.getElementById('mcm'+cap(prefix)+'PreviewImg');
  viewerOriginalSrc = img.src;
  document.getElementById('viewerImg').src = img.src;
  viewerRotation = 0;
  viewerCropMode = false;
  document.getElementById('imageViewerModal').classList.add('active');
  document.getElementById('cropOverlay').classList.remove('active');
  document.getElementById('applyCropBtn').style.display  = 'none';
  document.getElementById('cancelCropBtn').style.display = 'none';
  document.getElementById('cropBtnText').textContent = 'Crop';
}
function viewerClose() {
  document.getElementById('imageViewerModal').classList.remove('active');
  viewerCropMode = false; viewerRotation = 0;
}
function viewerRotate(deg) {
  viewerRotation += deg;
  document.getElementById('viewerImg').style.transform = `rotate(${viewerRotation}deg)`;
}
function viewerToggleCrop() {
  viewerCropMode = !viewerCropMode;
  document.getElementById('cropOverlay').classList.toggle('active', viewerCropMode);
  document.getElementById('applyCropBtn').style.display  = viewerCropMode ? 'inline-flex' : 'none';
  document.getElementById('cancelCropBtn').style.display = viewerCropMode ? 'inline-flex' : 'none';
  document.getElementById('cropBtnText').textContent = viewerCropMode ? 'Cancel Crop' : 'Crop';
  if (viewerCropMode) viewerInitCrop();
}
function viewerInitCrop() {
  const img  = document.getElementById('viewerImg');
  const rect = img.getBoundingClientRect();
  const parent = img.parentElement.getBoundingClientRect();
  viewerCropArea = {
    x: rect.left-parent.left + rect.width*.25,
    y: rect.top -parent.top  + rect.height*.25,
    width:  rect.width*.5,
    height: rect.height*.5,
  };
  viewerUpdateCrop();
}
function viewerUpdateCrop() {
  const c = document.getElementById('cropOverlay');
  c.style.left=viewerCropArea.x+'px'; c.style.top=viewerCropArea.y+'px';
  c.style.width=viewerCropArea.width+'px'; c.style.height=viewerCropArea.height+'px';
}
function viewerApplyCrop() {
  const img = document.getElementById('viewerImg');
  const canvas = document.createElement('canvas');
  canvas.width  = viewerCropArea.width;
  canvas.height = viewerCropArea.height;
  canvas.getContext('2d').drawImage(img,
    viewerCropArea.x, viewerCropArea.y, viewerCropArea.width, viewerCropArea.height,
    0, 0, viewerCropArea.width, viewerCropArea.height);
  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  img.src = dataUrl;
  // apply back to form
  if (viewerActivePrefix) {
    const prefix = viewerActivePrefix;
    document.getElementById('mcm'+cap(prefix)+'PreviewImg').src = dataUrl;
    document.getElementById('mcm'+cap(prefix)+'ImagePath').value = dataUrl;
  }
  showToast('Crop applied','success');
  viewerCancelCrop();
}
function viewerCancelCrop() {
  viewerCropMode = false;
  document.getElementById('cropOverlay').classList.remove('active');
  document.getElementById('applyCropBtn').style.display  = 'none';
  document.getElementById('cancelCropBtn').style.display = 'none';
  document.getElementById('cropBtnText').textContent = 'Crop';
}
function viewerReset() {
  viewerRotation = 0;
  document.getElementById('viewerImg').style.transform = 'rotate(0deg)';
  if (viewerCropMode) viewerCancelCrop();
}
document.getElementById('imageViewerModal').addEventListener('click', e => {
  if (e.target === document.getElementById('imageViewerModal')) viewerClose();
});
document.addEventListener('keydown', e => {
  if (e.key==='Escape' && document.getElementById('imageViewerModal').classList.contains('active')) viewerClose();
});

// ── Crop handle dragging ─────────────────────────────
(function(){
  let dragging=false, handle=null, sx,sy, sc={};
  document.addEventListener('mousedown',e=>{
    const h=e.target.closest('.crop-handle');
    if(!h) return;
    dragging=true; handle=h; sx=e.clientX; sy=e.clientY; sc={...viewerCropArea};
    e.preventDefault();
  });
  document.addEventListener('mousemove',e=>{
    if(!dragging||!handle) return;
    const dx=e.clientX-sx, dy=e.clientY-sy;
    const cls=Array.from(handle.classList).find(c=>['nw','ne','sw','se'].includes(c));
    const nc={...sc};
    if(cls==='nw'){ nc.x=sc.x+dx; nc.y=sc.y+dy; nc.width=Math.max(40,sc.width-dx); nc.height=Math.max(40,sc.height-dy); }
    if(cls==='ne'){ nc.y=sc.y+dy; nc.width=Math.max(40,sc.width+dx); nc.height=Math.max(40,sc.height-dy); }
    if(cls==='sw'){ nc.x=sc.x+dx; nc.width=Math.max(40,sc.width-dx); nc.height=Math.max(40,sc.height+dy); }
    if(cls==='se'){ nc.width=Math.max(40,sc.width+dx); nc.height=Math.max(40,sc.height+dy); }
    viewerCropArea=nc; viewerUpdateCrop();
  });
  document.addEventListener('mouseup',()=>{ dragging=false; handle=null; });
})();

// ════════════════════════════════════════════════════
// ARCHIVE MODAL
// ════════════════════════════════════════════════════
let archiveCurrentTab    = 'users';
let archiveCurrentSub    = 'menu';
let archiveContentCache  = {};

function openArchiveModal() {
  document.getElementById('archiveModal').classList.add('open');
  archSwitchTab('users');
}
function closeArchiveModal() {
  document.getElementById('archiveModal').classList.remove('open');
}
document.getElementById('archiveModal').addEventListener('click', e => {
  if (e.target === document.getElementById('archiveModal')) closeArchiveModal();
});

function archSwitchTab(tab) {
  archiveCurrentTab = tab;
  document.getElementById('archTabUsers').classList.toggle('active', tab==='users');
  document.getElementById('archTabContent').classList.toggle('active', tab==='content');
  document.getElementById('archViewUsers').style.display   = tab==='users'   ? '' : 'none';
  document.getElementById('archViewContent').style.display = tab==='content' ? '' : 'none';
  if (tab==='content') archSwitchSub(archiveCurrentSub);
}

function archSwitchSub(sub) {
  archiveCurrentSub = sub;
  document.getElementById('archSubMenu').classList.toggle('active',    sub==='menu');
  document.getElementById('archSubGallery').classList.toggle('active', sub==='gallery');
  archLoadContent(sub);
}

function archLoadContent(type) {
  const body = document.getElementById('archContentBody');
  body.innerHTML = '<div class="arch-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading…</p></div>';
  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'get_archived',type})})
    .then(r=>r.json()).then(d=>{
      if(!d.success){ body.innerHTML='<div class="arch-empty"><p>Failed to load archive.</p></div>'; return; }
      const items = d.items || [];
      if(!items.length){
        body.innerHTML='<div class="arch-empty"><i class="fa-solid fa-box-archive"></i><p>No archived '+(type==='gallery'?'gallery photos':'menu items')+' yet.</p></div>';
        return;
      }
      body.innerHTML = `<table class="arch-table">
        <thead><tr><th>Name</th><th>Category</th><th>Archived</th><th>Actions</th></tr></thead>
        <tbody>${items.map(item=>{
          const d = item.archived_at ? new Date(item.archived_at).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : 'Unknown';
          return `<tr>
            <td style="font-weight:600;color:#fff;">${escHtml(item.name)}</td>
            <td><span class="mcm-badge">${escHtml(item.category)}</span></td>
            <td style="color:rgba(255,255,255,0.45);font-size:0.82rem;">${d}</td>
            <td><button class="btn btn-outline btn-sm" onclick="archRestore(${item.id})"><i class="fa-solid fa-rotate-left"></i> Restore</button></td>
          </tr>`;
        }).join('')}</tbody>
      </table>`;
    }).catch(()=>{ body.innerHTML='<div class="arch-empty"><p>Network error.</p></div>'; });
}

function archRestore(id) {
  if(!confirm('Restore this item? It will reappear on the website.')) return;
  fetch('admin-content.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'restore_content',id})})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        showToast('Item restored!','success');
        archLoadContent(archiveCurrentSub);
      } else showToast(d.message||'Failed','error');
    });
}

// ════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════
function cap(str) { return str.charAt(0).toUpperCase() + str.slice(1); }
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>