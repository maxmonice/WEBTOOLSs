<?php
require_once 'Db.php';
require_once 'Notifications.php';

// Get database connection
$pdo = getDB();

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in or allowed to view as admin/staff
$isUserViewMode      = isset($_GET['user_view']) && $_GET['user_view'] === 'true';

if (!isset($_SESSION['user_id'])) {
    if ($isUserViewMode && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'staff'])) {
        // Allow admin/staff to view the customer dashboard area without forcing a logout
    } else {
        header('Location: account.php');
        exit;
    }
}

// Prepare user data for immediate server-side rendering (no JS loading placeholders)
$renderName  = htmlspecialchars($_SESSION['user_name'] ?? 'Guest');
$renderEmail = htmlspecialchars($_SESSION['user_email'] ?? '');
$renderRole  = $_SESSION['user_role'] ?? '';

$viewBannerText = '';
$exitButtonText = 'Back to Dashboard';
if ($isUserViewMode) {
    $viewBannerText  = 'USER VIEW - Viewing Customer Side as ' . ucfirst($_SESSION['user_role']);
}

$notifications = new Notifications($pdo);
$userNotifications = $notifications->getForUser('customer', $_SESSION['user_id'], 10);
$unreadCount = $notifications->getUnreadCount('customer', $_SESSION['user_id']);

// Helper function for PHP-side time formatting
function timeAgoPhp(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return (int)($diff / 60) . ' min ago';
    if ($diff < 86400) return (int)($diff / 3600) . ' hour' . ((int)($diff / 3600) > 1 ? 's' : '') . ' ago';
    return (int)($diff / 86400) . ' day' . ((int)($diff / 86400) > 1 ? 's' : '') . ' ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Account – Luke's Seafood Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/booking-rating.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --red:        #C22626;
            --red-dark:   #8B0A1E;
            --red-glow:   rgba(194,38,38,0.25);
            --bg:         #191919;
            --surface:    #222222;
            --surface2:   #2a2a2a;
            --border:     rgba(255,255,255,0.07);
            --border-red: rgba(194,38,38,0.35);
            --text:       #f0ece6;
            --muted:      rgba(255,255,255,0.45);
        }

        body { font-family: 'Be Vietnam Pro', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }

        .grain-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 9990; opacity: 0.03; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }

        header { background: linear-gradient(90deg, #9B0A1E 0%, #BE2225 40%, #C22626 100%); height: 70px; display: flex; align-items: center; position: fixed; top: 0; left: 0; right: 0; width: 100%; z-index: 1000; box-shadow: 0 4px 24px rgba(0,0,0,0.5), 0 1px 0 rgba(255,255,255,0.06); }
        .container { max-width: 1200px; width: 100%; margin: 0 auto; padding: 0 20px; }
        .header-container { display: flex; justify-content: space-between; align-items: center; width: 100%; height: 100%; }
        .logo { font-family: 'Aclonica', sans-serif; font-size: 1.4rem; color: white; text-decoration: none; white-space: nowrap; letter-spacing: 0.02em; text-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .menu-toggle { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
        .nav-menu { display: flex; align-items: center; gap: 30px; }
        .nav-menu a { color: rgba(255,255,255,0.88); text-decoration: none; font-weight: 500; font-size: 0.95rem; letter-spacing: 0.03em; position: relative; transition: color 0.3s; }
        .nav-menu a:hover { color: #fff; }
        .nav-account-icon { display: flex !important; align-items: center; font-size: 1.35rem !important; color: white !important; transition: transform 0.2s, opacity 0.2s !important; opacity: 0.9; }
        .nav-account-icon:hover { transform: scale(1.15); opacity: 1 !important; }
        .nav-account-icon.active::after { display: none !important; }

        main { margin-top: 70px; flex: 1; }

        .page { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; width: 100%; padding: 48px 24px 80px; }
        .page-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--red); margin-bottom: 6px; }
        .page-title { font-family: 'Aclonica', sans-serif; font-size: clamp(1.7rem, 4vw, 2.4rem); line-height: 1.15; margin-bottom: 40px; }

        .section-block { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; margin-bottom: 20px; transition: border-color 0.3s; }
        .section-block:hover { border-color: var(--border-red); }
        .section-label { display: flex; align-items: center; gap: 10px; padding: 14px 22px; background: var(--surface2); border-bottom: 1px solid var(--border); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--muted); }
        .section-label i { color: var(--red); font-size: 0.85rem; }

        .profile-card { display: flex; align-items: center; gap: 20px; padding: 26px 22px; }
        .avatar-wrap { position: relative; flex-shrink: 0; }
        .avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--red-dark), var(--red)); display: flex; align-items: center; justify-content: center; font-family: 'Aclonica', sans-serif; font-size: 1.6rem; color: #fff; box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px var(--border-red); }
        .avatar-badge { position: absolute; bottom: 2px; right: 2px; width: 16px; height: 16px; border-radius: 50%; background: #22c55e; border: 2px solid var(--surface); }
        .profile-info { flex: 1; min-width: 0; }
        .profile-name { font-size: 1.15rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
        .profile-email { font-size: 0.83rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .edit-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: 8px; background: transparent; border: 1px solid var(--border-red); color: var(--red); font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.04em; cursor: pointer; transition: background 0.2s, color 0.2s, box-shadow 0.2s; white-space: nowrap; flex-shrink: 0; }
        .edit-btn:hover { background: var(--red); color: #fff; box-shadow: 0 0 14px var(--red-glow); }

        .detail-rows { padding: 0 22px 6px; }
        .detail-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border); font-size: 0.87rem; }
        .detail-row:last-child { border-bottom: none; }
        .dr-label { color: var(--muted); font-weight: 500; }
        .dr-value { font-weight: 600; }

        .menu-rows { padding: 8px 0; }
        .menu-row { display: flex; align-items: center; justify-content: space-between; padding: 15px 22px; cursor: pointer; text-decoration: none; color: var(--text); transition: background 0.15s; border-left: 3px solid transparent; }
        .menu-row:hover { background: rgba(255,255,255,0.03); border-left-color: var(--red); }
        .mr-left { display: flex; align-items: center; gap: 14px; }
        .mr-icon { width: 36px; height: 36px; border-radius: 9px; background: var(--surface2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--red); font-size: 0.85rem; flex-shrink: 0; transition: background 0.2s, box-shadow 0.2s; }
        .menu-row:hover .mr-icon { background: var(--red-dark); box-shadow: 0 0 10px var(--red-glow); color: #fff; }
        .mr-title { font-size: 0.88rem; font-weight: 700; margin-bottom: 1px; }
        .mr-sub { font-size: 0.75rem; color: var(--muted); }
        .mr-arrow { color: var(--muted); font-size: 0.75rem; transition: transform 0.2s, color 0.2s; }
        .menu-row:hover .mr-arrow { transform: translateX(3px); color: var(--red); }
        .menu-divider { margin: 0 22px; border: none; border-top: 1px solid var(--border); }

        .logout-block { background: var(--surface); border: 1px solid rgba(194,38,38,0.2); border-radius: 16px; overflow: hidden; margin-top: 8px; }
        .logout-btn { width: 100%; display: flex; align-items: center; gap: 14px; padding: 18px 22px; background: transparent; border: none; cursor: pointer; color: var(--red); font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.95rem; font-weight: 700; transition: background 0.2s; }
        .logout-btn:hover { background: rgba(194,38,38,0.1); }
        .logout-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(194,38,38,0.12); border: 1px solid var(--border-red); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; transition: background 0.2s; }
        .logout-btn:hover .logout-icon { background: var(--red); color: #fff; }
        .logout-label { flex: 1; text-align: left; }
        .logout-label small { display: block; font-size: 0.72rem; font-weight: 500; color: rgba(194,38,38,0.6); margin-top: 1px; }

        /* ── MODALS ── */
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 500; background: rgba(0,0,0,0.72); backdrop-filter: blur(6px); align-items: center; justify-content: center; padding: 24px; }
        .modal-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        .modal { background: var(--surface); border: 1px solid var(--border-red); border-radius: 20px; width: 100%; max-width: 440px; overflow: hidden; animation: slideUp 0.25s ease; }
        @keyframes slideUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
        .modal-head { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--border); }
        .modal-head h3 { font-size: 1rem; font-weight: 800; }
        .modal-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface2); color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; transition: color 0.2s, border-color 0.2s; }
        .modal-close:hover { color: var(--text); border-color: var(--text); }
        .modal-body { padding: 22px 24px; }
        .field-group { margin-bottom: 18px; }
        .field-group label { display: block; font-size: 0.73rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
        .field-group input { width: 100%; padding: 11px 42px 11px 14px; background: var(--surface2); border: 1px solid var(--border); border-radius: 9px; color: var(--text); font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.88rem; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        .field-group input:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); }

        /* Password wrap with toggle */
        .pw-wrap { position: relative; }
        .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); font-size: 0.82rem; padding: 4px; transition: color 0.2s; }
        .pw-toggle:hover { color: var(--text); }

        /* Strength meter */
        .pw-strength { margin-top: 8px; }
        .pw-strength-bars { display: flex; gap: 4px; margin-bottom: 4px; }
        .pw-strength-bar { flex: 1; height: 3px; border-radius: 2px; background: rgba(255,255,255,0.1); transition: background 0.3s; }
        .pw-strength-label { font-size: 0.71rem; font-weight: 600; }

        .modal-foot { display: flex; gap: 10px; padding: 0 24px 22px; }
        .btn-cancel { flex: 1; padding: 11px; border: 1px solid var(--border); border-radius: 9px; background: transparent; color: var(--muted); font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: color 0.2s, border-color 0.2s; }
        .btn-cancel:hover { color: var(--text); border-color: var(--text); }
        .btn-save { flex: 1; padding: 11px; border: none; border-radius: 9px; background: var(--red); color: #fff; font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: background 0.2s, box-shadow 0.2s, opacity 0.2s; }
        .btn-save:hover:not(:disabled) { background: var(--red-dark); box-shadow: 0 0 14px var(--red-glow); }
        .btn-save:disabled { opacity: 0.65; cursor: not-allowed; }

        .logout-confirm-body { padding: 28px 24px 8px; text-align: center; }
        .logout-confirm-icon { width: 56px; height: 56px; border-radius: 50%; background: rgba(194,38,38,0.12); border: 1px solid var(--border-red); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: var(--red); margin: 0 auto 16px; }
        .logout-confirm-body h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; }
        .logout-confirm-body p { font-size: 0.84rem; color: var(--muted); line-height: 1.6; }
        .modal-foot.col { flex-direction: column; padding-top: 20px; }
        .btn-logout-confirm { width: 100%; padding: 13px; border: none; border-radius: 9px; background: var(--red); color: #fff; font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .btn-logout-confirm:hover { background: var(--red-dark); }

        /* ── TOAST ── */
        .toast { position: fixed; bottom: 28px; right: 28px; z-index: 9999; background: var(--surface); border: 1px solid var(--border-red); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; font-size: 0.84rem; font-weight: 600; box-shadow: 0 8px 30px rgba(0,0,0,0.4); transform: translateY(80px); opacity: 0; transition: transform 0.3s ease, opacity 0.3s ease; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast i { color: #22c55e; }
        .toast.error i { color: #ef4444; }

        /* ── FOOTER ── */
        footer { background: linear-gradient(135deg, #8B0A1E 0%, #C22626 60%, #9B0A1E 100%); padding: 60px 0 20px; color: white; font-size: 0.9rem; box-shadow: 0 -4px 24px rgba(0,0,0,0.3); }
        footer a { color: white; text-decoration: none; }
        footer a:hover { opacity: 0.85; }
        .footer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px 80px; max-width: 900px; margin: 0 auto 50px; }
        .footer-col h4 { font-family: 'Aclonica', sans-serif; font-size: 1.2rem; font-weight: 400; margin-bottom: 14px; letter-spacing: 0.04em; }
        .social-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 0.95rem; transition: opacity 0.2s; }
        .social-item:hover { opacity: 0.8; }
        .social-item i { font-size: 1.1rem; }
        .location-text { align-items: flex-start; }
        .location-text i { margin-top: 4px; }
        .copyright { text-align: center; font-size: 0.82rem; margin-top: 20px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.15); opacity: 0.8; }
        .mobile-footer-view { display: none; }

        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .nav-menu { display: none; position: absolute; top: 100%; right: 0; width: 200px; background: #a31e1e; flex-direction: column; padding: 10px 0; gap: 0; z-index: 200; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
            .nav-menu.active { display: flex; }
            .nav-menu a { padding: 12px 20px; width: 100%; text-align: right; }
            .nav-account-icon { justify-content: flex-end; }
            .desktop-view { display: none; }
            .mobile-footer-view { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 20px; }
            .mobile-info { font-size: 0.82rem; line-height: 1.7; color: white; text-transform: uppercase; max-width: 90%; font-weight: 400; }
            .mobile-info a { color: white; border-bottom: 1px dotted rgba(255,255,255,0.4); }
            .mobile-info i { margin-right: 5px; }
            .pipe { font-weight: 800; margin: 0 4px; }
            .mobile-menu-text { font-size: 0.88rem; color: white; }
            .mobile-copyright { font-size: 0.78rem; color: white; font-weight: 500; }
        }
        @media (max-width: 540px) {
            .profile-card { flex-wrap: wrap; }
            .edit-btn { width: 100%; justify-content: center; }
            .page { padding: 32px 16px 60px; }
        }

        /* Notification Styles */
        .nav-notifications {
            position: relative; cursor: pointer; color: white; 
            display: flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,0.1); transition: all 0.3s;
        }
        .nav-notifications:hover { background: rgba(255,255,255,0.2); }
        .notification-badge {
            position: absolute; top: -5px; right: -5px; 
            background: var(--red); color: white; border-radius: 10px; 
            padding: 2px 5px; font-size: 0.7rem; font-weight: bold; 
            min-width: 18px; text-align: center; line-height: 1;
        }
        .notification-dropdown {
            position: absolute; top: 100%; right: 0; width: 320px;
            background: #2a2a2a; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            z-index: 1000; display: none; max-height: 400px; overflow-y: auto;
        }
        .notification-dropdown.show { display: block; }
        .notification-header {
            padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center;
        }
        .notification-header h3 { margin: 0; font-size: 0.9rem; color: white; }
        .notification-header .mark-all {
            font-size: 0.75rem; color: var(--red); text-decoration: none;
            background: transparent; border: none; cursor: pointer;
        }
        .notification-header .mark-all:hover { text-decoration: underline; }
        .notification-item {
            padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer; transition: background 0.2s;
        }
        .notification-item:hover { background: rgba(255,255,255,0.05); }
        .notification-item:last-child { border-bottom: none; }
        .notification-item.unread {
            background: rgba(52,152,219,0.1); border-left: 3px solid #3498db;
        }
        .notification-content {
            display: flex; gap: 12px; align-items: flex-start;
        }
        .notification-icon {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 0.9rem;
        }
        .notification-text { flex: 1; }
        .notification-title {
            font-size: 0.85rem; font-weight: 600; color: white; margin-bottom: 4px;
        }
        .notification-message {
            font-size: 0.78rem; color: rgba(255,255,255,0.7); line-height: 1.4;
        }
        .notification-time {
            font-size: 0.72rem; color: rgba(255,255,255,0.5); margin-top: 4px;
        }
        .notification-empty {
            padding: 24px; text-align: center; color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }

        /* ── MY ORDERS ── */
        .orders-list { padding: 12px 22px 22px; }
        .order-card {
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: 12px; padding: 18px; margin-bottom: 14px;
            transition: border-color 0.2s;
        }
        .order-card:hover { border-color: var(--border-red); }
        .order-card-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 12px; flex-wrap: wrap; gap: 8px;
        }
        .order-card-id { font-weight: 800; font-size: 0.92rem; color: #fff; }
        .order-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 6px; font-size: 0.7rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        }
        .order-badge.pending { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .order-badge.accepted, .order-badge.preparing { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .order-badge.ready_for_pickup { background: rgba(168,85,247,0.15); color: #a855f7; }
        .order-badge.on_route { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .order-badge.delivered { background: rgba(34,197,94,0.15); color: #4ade80; }
        .order-badge.cancelled { background: rgba(239,68,68,0.15); color: #f87171; }
        .order-card-items {
            font-size: 0.82rem; color: rgba(255,255,255,0.55);
            margin-bottom: 10px; line-height: 1.6;
        }
        .order-card-items .item-line { display: flex; justify-content: space-between; }
        .order-card-footer {
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px solid var(--border); padding-top: 12px; margin-top: 4px;
            flex-wrap: wrap; gap: 8px;
        }
        .order-card-total { font-weight: 800; color: var(--red); font-size: 0.95rem; }
        .order-card-date { font-size: 0.75rem; color: var(--muted); }
        .order-card-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-deliver {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer;
            font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.78rem; font-weight: 700;
            background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff;
            transition: all 0.2s; letter-spacing: 0.02em;
        }
        .btn-deliver:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34,197,94,0.3); }
        .btn-rate {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px; border: none; cursor: pointer;
            font-family: 'Be Vietnam Pro', sans-serif; font-size: 0.78rem; font-weight: 700;
            background: linear-gradient(135deg, #C22626, #8B0A1E); color: #fff;
            transition: all 0.2s; letter-spacing: 0.02em;
        }
        .btn-rate:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(194,38,38,0.3); }
        .order-rated {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.78rem; color: #FFD700; font-weight: 600;
        }
        .orders-empty {
            text-align: center; padding: 32px 20px; color: var(--muted); font-size: 0.88rem;
        }
        .orders-empty i { font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.4; }
        .orders-loading {
            text-align: center; padding: 28px; color: var(--muted); font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <div class="grain-overlay"></div>

    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">Luke's Seafood Trading</a>
            <div class="menu-toggle" id="mobile-menu"><i class="fa-solid fa-bars"></i></div>
            <nav class="nav-menu" id="navMenu">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="bookBar.php">Book Bar</a>
                <a href="gallery.php">Gallery</a>
                <a href="aboutUs.php">About Us</a>
                <a href="account.php" class="nav-account-icon active" title="Account">
                    <i class="fas fa-user-circle"></i>
                </a>
                <div class="nav-notifications" style="position: relative;" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge" id="notificationCount"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Notification Dropdown -->
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
                                        <div class="notification-icon" style="background: <?= getNotificationColor($notif['type']) ?>20; color: <?= getNotificationColor($notif['type']) ?>;">
                                            <i class="fa-solid <?= getNotificationIcon($notif['type']) ?>"></i>
                                        </div>
                                        <div class="notification-text">
                                            <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                                            <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                            <div class="notification-time"><?= timeAgoPhp($notif['created_at']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <?php if (!empty($viewBannerText)): ?>
        <div style="background: linear-gradient(90deg, #C22626, #8B0A1E); color: white; padding: 12px 20px; margin: 0 auto; max-width: 900px; border-radius: 8px; font-size: 13px; margin-top: 20px; text-align: center; font-weight: 600; box-shadow: 0 4px 12px rgba(194, 38, 38, 0.4);">
            <i class="fa-solid fa-eye" style="margin-right: 8px;"></i> <?= htmlspecialchars($viewBannerText) ?>
            <button onclick="exitUserViewMode(this)" data-role="<?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>" style="background: white; color: #C22626; border: none; padding: 8px 16px; border-radius: 6px; margin-left: 15px; cursor: pointer; font-weight: 700; font-size: 12px; transition: all 0.2s;">
                <i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i> <?= htmlspecialchars($exitButtonText) ?>
            </button>
        </div>
        <?php endif; ?>
        <div class="page">
            <p class="page-eyebrow">Logged In</p>
            <h1 class="page-title">My Account</h1>

            <!-- Profile -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-user"></i> Profile &amp; Account Info</div>
                <div class="profile-card">
                    <div class="avatar-wrap">
                        <div class="avatar" id="avatarInitial"><?= strtoupper(substr($renderName, 0, 1)) ?></div>
                        <div class="avatar-badge"></div>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name" id="displayName"><?= $renderName ?></div>
                        <div class="profile-email" id="displayEmail"><?= $renderEmail ?></div>
                    </div>
                    <button class="edit-btn" onclick="openEdit()">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                    </button>
                </div>
                <div class="detail-rows">
                    <div class="detail-row"><span class="dr-label">Full Name</span><span class="dr-value" id="detailName"><?= $renderName ?></span></div>
                    <div class="detail-row"><span class="dr-label">Email Address</span><span class="dr-value" id="detailEmail"><?= $renderEmail ?></span></div>
                    <div class="detail-row"><span class="dr-label">Member Since</span><span class="dr-value" id="memberSince"><?= date('F Y') ?></span></div>
                </div>
            </div>

            <!-- Security -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-shield-halved"></i> Security</div>
                <div class="menu-rows">
                    <a class="menu-row" href="#" onclick="openChangePw(); return false;">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-key"></i></div>
                            <div class="mr-text">
                                <div class="mr-title">Change Password</div>
                                <div class="mr-sub">Update your account password</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right mr-arrow"></i>
                    </a>
                </div>
            </div>

            <!-- My Orders -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-bag-shopping"></i> My Orders</div>
                <div class="orders-list" id="ordersContainer">
                    <div class="orders-loading" id="ordersLoading">
                        <i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Loading your orders…
                    </div>
                </div>
            </div>

            <!-- Help & Support -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-circle-question"></i> Help &amp; Support</div>
                <div class="menu-rows">
                    <a class="menu-row" href="#" onclick="return false;">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-book-open"></i></div>
                            <div class="mr-text"><div class="mr-title">Help Center</div><div class="mr-sub">Browse FAQs and guides</div></div>
                        </div>
                        <i class="fa-solid fa-chevron-right mr-arrow"></i>
                    </a>
                    <hr class="menu-divider">
                    <a class="menu-row" href="mailto:lukeseafoods28@gmail.com">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-headset"></i></div>
                            <div class="mr-text"><div class="mr-title">Contact Support</div><div class="mr-sub">Reach out to our admin team</div></div>
                        </div>
                        <i class="fa-solid fa-chevron-right mr-arrow"></i>
                    </a>
                    <hr class="menu-divider">
                    <a class="menu-row" href="https://www.facebook.com/lukeseafoodtrading" target="_blank">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-brands fa-facebook-messenger"></i></div>
                            <div class="mr-text"><div class="mr-title">Message Us on Facebook</div><div class="mr-sub">Chat with us directly</div></div>
                        </div>
                        <i class="fa-solid fa-arrow-up-right-from-square mr-arrow"></i>
                    </a>
                </div>
            </div>

            <!-- Logout -->
            <div class="logout-block">
                <button class="logout-btn" onclick="openLogout()">
                    <div class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                    <div class="logout-label">Log Out<small>Signed in as <span id="logoutEmail"><?= $renderEmail ?></span></small></div>
                    <i class="fa-solid fa-chevron-right" style="color:rgba(194,38,38,0.4);font-size:0.75rem;"></i>
                </button>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="footer-grid desktop-view">
                <div class="footer-col">
                    <h4>Socials</h4>
                    <a href="https://facebook.com/lukeseafoodtrading" target="_blank" class="social-item"><i class="fab fa-facebook"></i> Luke's Seafood Taguig</a>
                    <a href="https://www.instagram.com/luke_seafoods/" target="_blank" class="social-item"><i class="fab fa-instagram"></i> luke_seafoods</a>
                </div>
                <div class="footer-col">
                    <h4>About Us</h4>
                    <p>At Luke's Seafood Trading, we specialize in sourcing and delivering the freshest, highest-quality seafood from ocean to market.</p>
                </div>
                <div class="footer-col">
                    <h4>Location</h4>
                    <a href="https://maps.google.com/?q=Vulcan+St+cor+C5+Road+Taguig" target="_blank" class="social-item location-text"><i class="fa-solid fa-location-pin"></i><span>vulcan st. cor c5 road, Taguig, Philippines</span></a>
                </div>
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <a href="mailto:lukeseafoods28@gmail.com" class="social-item"><i class="fas fa-envelope"></i> lukeseafoods28@gmail.com</a>
                    <a href="tel:09392999912" class="social-item"><i class="fa-solid fa-phone"></i> 09392999912</a>
                </div>
            </div>
            <div class="mobile-footer-view">
                <p class="mobile-info">
                    <a href="https://www.instagram.com/luke_seafoods/" target="_blank"><i class="fab fa-instagram"></i> luke_seafoods</a><span class="pipe">|</span>
                    <a href="mailto:lukeseafoods28@gmail.com"><i class="fas fa-envelope"></i> lukeseafoods28@gmail.com</a><span class="pipe">|</span>
                    <a href="https://facebook.com/lukeseafoodtrading" target="_blank"><i class="fab fa-facebook"></i> Luke's Seafood Taguig</a><span class="pipe">|</span>
                    <a href="https://maps.google.com/?q=Vulcan+St+cor+C5+Road+Taguig" target="_blank"><i class="fa-solid fa-location-pin"></i> VULCAN ST. COR C5 ROAD, TAGUIG</a><span class="pipe">|</span>
                    <a href="tel:09392999912"><i class="fa-solid fa-phone"></i> 09392999912</a>
                </p>
                <p class="mobile-menu-text">About Us: At Luke's Seafood Trading, we specialize in sourcing and delivering the freshest, highest-quality seafood from ocean to market.</p>
                <p class="mobile-copyright">© 2025 Luke's Seafood Trading</p>
            </div>
            <div class="copyright desktop-view">© 2025 Luke's Seafood Trading | All Rights Reserved</div>
        </div>
    </footer>

    <!-- EDIT PROFILE MODAL -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-head">
                <h3><i class="fa-solid fa-pen-to-square" style="color:var(--red);margin-right:8px;"></i>Edit Profile</h3>
                <button class="modal-close" onclick="closeEdit()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="field-group"><label>Full Name</label><input type="text" id="inputName" placeholder="Your full name" /></div>
                <div class="field-group"><label>Email Address</label><input type="email" id="inputEmail" placeholder="Your email" /></div>
            </div>
            <div class="modal-foot">
                <button class="btn-cancel" onclick="closeEdit()">Cancel</button>
                <button class="btn-save" onclick="saveProfile()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div class="modal-overlay" id="changePwModal">
        <div class="modal">
            <div class="modal-head">
                <h3><i class="fa-solid fa-key" style="color:var(--red);margin-right:8px;"></i>Change Password</h3>
                <button class="modal-close" onclick="closeChangePw()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="field-group">
                    <label>Current Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="currentPw" placeholder="Enter your current password" />
                        <button type="button" class="pw-toggle" onclick="togglePwField('currentPw',this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="field-group">
                    <label>New Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="newPw" placeholder="At least 8 characters" />
                        <button type="button" class="pw-toggle" onclick="togglePwField('newPw',this)"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="pw-strength" id="pwStrengthWrap" style="display:none;">
                        <div class="pw-strength-bars">
                            <div class="pw-strength-bar" id="psb1"></div>
                            <div class="pw-strength-bar" id="psb2"></div>
                            <div class="pw-strength-bar" id="psb3"></div>
                            <div class="pw-strength-bar" id="psb4"></div>
                        </div>
                        <span class="pw-strength-label" id="pwStrengthLabel"></span>
                    </div>
                </div>
                <div class="field-group">
                    <label>Confirm New Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="confirmPw" placeholder="Repeat your new password" />
                        <button type="button" class="pw-toggle" onclick="togglePwField('confirmPw',this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn-cancel" onclick="closeChangePw()">Cancel</button>
                <button class="btn-save" id="changePwSaveBtn" onclick="submitChangePassword()">Update Password</button>
            </div>
        </div>
    </div>

    <!-- LOGOUT CONFIRM MODAL -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal">
            <div class="modal-head">
                <h3>Log Out</h3>
                <button class="modal-close" onclick="closeLogout()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="logout-confirm-body">
                <div class="logout-confirm-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                <h3>Are you sure?</h3>
                <p>You'll be signed out of your account. You can always log back in anytime.</p>
            </div>
            <div class="modal-foot col">
                <button class="btn-logout-confirm" onclick="doLogout()"><i class="fa-solid fa-right-from-bracket" style="margin-right:8px;"></i>Yes, Log Me Out</button>
                <button class="btn-cancel" onclick="closeLogout()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast"><i class="fa-solid fa-circle-check"></i><span id="toastMsg">Done!</span></div>

    <script>
        // ── Session guard ──
        (async function guardSession() {
            try {
                const res  = await fetch('auth.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify({action:'check_session'}) });
                const data = await res.json();
                if (!data.success) { sessionStorage.clear(); window.location.href = 'account.php'; return; }
                sessionStorage.setItem('user_name',  data.name  || '');
                sessionStorage.setItem('user_email', data.email || '');
            } catch (e) { 
                // Silent fail - page already has server-rendered data
            }
        })().then(() => initDashboard());

        function initDashboard() {
            // Only override if we have sessionStorage data; otherwise keep server-rendered values
            const sName  = sessionStorage.getItem('user_name');
            const sEmail = sessionStorage.getItem('user_email');
            if (sName)  userData.name  = sName;
            if (sEmail) userData.email = sEmail;
            updateUI();
        }

        let userData = { name:'<?= $renderName ?>', email:'<?= $renderEmail ?>' };

        function updateUI() {
            const initial = userData.name.trim().charAt(0).toUpperCase() || '?';
            document.getElementById('avatarInitial').textContent = initial;
            // Only update if elements still have placeholder values or different values
            const dName = document.getElementById('displayName');
            const dEmail = document.getElementById('displayEmail');
            if (dName && (dName.textContent === 'Loading...' || dName.textContent === '—')) dName.textContent = userData.name;
            if (dEmail && (dEmail.textContent === 'Loading...' || dEmail.textContent === '—')) dEmail.textContent = userData.email;
            
            const deName = document.getElementById('detailName');
            const deEmail = document.getElementById('detailEmail');
            if (deName && deName.textContent === '—') deName.textContent = userData.name;
            if (deEmail && deEmail.textContent === '—') deEmail.textContent = userData.email;
            
            const logoutEmail = document.getElementById('logoutEmail');
            if (logoutEmail && (logoutEmail.textContent === '—' || logoutEmail.textContent === '')) logoutEmail.textContent = userData.email;
            
            // Load notifications
            loadNotifications();
        }

        // Notification functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            
            // Close dropdown when clicking outside
            if (!dropdown.dataset.listenerAdded) {
                dropdown.dataset.listenerAdded = 'true';
                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target) && !e.target.closest('.nav-notifications')) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        }

        function loadNotifications() {
            fetch('account-handle-notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_notifications' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications) {
                    updateNotificationDisplay(data.notifications);
                    updateNotificationCount();
                }
            });
        }

        function updateNotificationDisplay(notifications) {
            const list = document.getElementById('notificationList');
            if (notifications.length === 0) {
                list.innerHTML = '<div class="notification-empty">No notifications</div>';
            } else {
                list.innerHTML = notifications.map(notif => `
                    <div class="notification-item ${!notif.is_read ? 'unread' : ''}" onclick="markNotificationRead(${notif.id})">
                        <div class="notification-content">
                            <div class="notification-icon" style="background: ${getNotificationColor(notif.type)}20; color: ${getNotificationColor(notif.type)};">
                                <i class="fa-solid ${getNotificationIcon(notif.type)}"></i>
                            </div>
                            <div class="notification-text">
                                <div class="notification-title">${notif.title}</div>
                                <div class="notification-message">${notif.message}</div>
                                <div class="notification-time">${timeAgo(notif.created_at)}</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        }

        function markNotificationRead(notificationId) {
            fetch('account-handle-notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[onclick="markNotificationRead(${notificationId})"]`);
                    if (item) {
                        item.classList.remove('unread');
                    }
                    updateNotificationCount();
                }
            });
        }

        function markAllNotificationsRead() {
            fetch('account-handle-notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_read' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    updateNotificationCount();
                }
            });
        }

        function updateNotificationCount() {
            const count = document.querySelectorAll('.notification-item.unread').length;
            const countElement = document.getElementById('notificationCount');
            const badge = document.getElementById('notificationCount');
            
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        function getNotificationIcon(type) {
            const icons = {
                'info': 'fa-info-circle',
                'success': 'fa-check-circle',
                'warning': 'fa-exclamation-triangle',
                'error': 'fa-times-circle',
                'booking': 'fa-calendar-days',
                'order': 'fa-bag-shopping',
                'user': 'fa-user-plus'
            };
            return icons[type] || 'fa-info-circle';
        }

        function getNotificationColor(type) {
            const colors = {
                'info': '#3498db',
                'success': '#2ecc71',
                'warning': '#f39c12',
                'error': '#e74c3c',
                'booking': '#9b59b6',
                'order': '#e67e22',
                'user': '#1abc9c'
            };
            return colors[type] || '#3498db';
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            return Math.floor(seconds / 86400) + ' days ago';
        }

        // ── Edit Profile ──
        function openEdit() { document.getElementById('inputName').value = userData.name; document.getElementById('inputEmail').value = userData.email; document.getElementById('editModal').classList.add('open'); }
        function closeEdit() { document.getElementById('editModal').classList.remove('open'); }
        function saveProfile() {
            const n = document.getElementById('inputName').value.trim();
            const e = document.getElementById('inputEmail').value.trim();
            if (!n || !e) { showToast('Please fill in all fields.', true); return; }
            userData.name = n; userData.email = e;
            sessionStorage.setItem('user_name', n); sessionStorage.setItem('user_email', e);
            updateUI(); closeEdit(); showToast('Profile updated successfully!');
        }

        // ── Change Password ──
        function openChangePw() {
            ['currentPw','newPw','confirmPw'].forEach(id => { document.getElementById(id).value = ''; document.getElementById(id).type = 'password'; });
            document.querySelectorAll('#changePwModal .pw-toggle i').forEach(i => i.className = 'fas fa-eye');
            document.getElementById('pwStrengthWrap').style.display = 'none';
            const btn = document.getElementById('changePwSaveBtn'); btn.disabled = false; btn.textContent = 'Update Password';
            document.getElementById('changePwModal').classList.add('open');
        }
        function closeChangePw() { document.getElementById('changePwModal').classList.remove('open'); }

        function togglePwField(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('newPw').addEventListener('input', function () {
                const val  = this.value;
                const wrap = document.getElementById('pwStrengthWrap');
                if (!val) { wrap.style.display = 'none'; return; }
                wrap.style.display = 'block';
                let score = 0;
                if (val.length >= 8)           score++;
                if (/[A-Z]/.test(val))         score++;
                if (/[0-9]/.test(val))         score++;
                if (/[^A-Za-z0-9]/.test(val))  score++;
                const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
                const labels = ['Weak','Fair','Good','Strong'];
                const color  = colors[score-1] || '#ef4444';
                for (let i = 1; i <= 4; i++) {
                    document.getElementById('psb'+i).style.background = i<=score ? color : 'rgba(255,255,255,0.1)';
                }
                const lbl = document.getElementById('pwStrengthLabel');
                lbl.textContent = labels[score-1] || '';
                lbl.style.color = color;
            });
        });

        async function submitChangePassword() {
            const current = document.getElementById('currentPw').value;
            const newPw   = document.getElementById('newPw').value;
            const confirm = document.getElementById('confirmPw').value;
            if (!current || !newPw || !confirm) { showToast('Please fill in all fields.', true); return; }
            if (newPw.length < 8)               { showToast('New password must be at least 8 characters.', true); return; }
            if (newPw !== confirm)               { showToast('New passwords do not match.', true); return; }

            const btn = document.getElementById('changePwSaveBtn');
            btn.disabled = true; btn.textContent = 'Updating…';
            try {
                const res  = await fetch('auth.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify({ action:'change_password', current_password:current, new_password:newPw, confirm_password:confirm }) });
                const data = await res.json();
                if (data.success) { closeChangePw(); showToast(data.message || 'Password updated successfully!'); }
                else { showToast(data.message || 'Failed to update password.', true); }
            } catch (e) { showToast('Network error. Please try again.', true); }
            finally { btn.disabled = false; btn.textContent = 'Update Password'; }
        }

        // ── User View Mode ──
        function exitUserViewMode(button) {
            const role = button?.dataset?.role || '';
            console.log('Exiting customer view mode, role:', role);

            if (role === 'admin') {
                window.location.href = '/FINAL/WEBTOOLSs/admin-dashboard.php';
            } else if (role === 'staff') {
                window.location.href = '/FINAL/WEBTOOLSs/staff-dashboard.php';
            } else {
                window.location.href = 'account.php';
            }
        }

        // ── Logout ──
        function openLogout()  { document.getElementById('logoutModal').classList.add('open'); }
        function closeLogout() { document.getElementById('logoutModal').classList.remove('open'); }
        async function doLogout() {
            closeLogout(); showToast('Signing out…');
            try { await fetch('auth.php', { method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body:JSON.stringify({action:'logout'}) }); }
            catch (e) { console.warn('Logout failed:', e.message); }
            sessionStorage.clear(); localStorage.clear();
            window.location.href = 'account.php';
        }

        // ── Toast ──
        function showToast(msg, isError = false) {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            t.querySelector('i').className = isError ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-circle-check';
            t.classList.toggle('error', isError);
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3500);
        }

        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
        });

        document.getElementById('mobile-menu').addEventListener('click', () => {
            document.getElementById('navMenu').classList.toggle('active');
        });

        // ═══════════════════════════════════════════════
        //  MY ORDERS — Fetch, render, mark delivered, rate
        // ═══════════════════════════════════════════════
        function loadMyOrders() {
            fetch('customer-orders-api.php?action=my-orders&limit=20', { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('ordersContainer');
                    const loading = document.getElementById('ordersLoading');
                    if (loading) loading.remove();

                    if (!data.success || !data.orders || data.orders.length === 0) {
                        container.innerHTML = `
                            <div class="orders-empty">
                                <i class="fa-solid fa-bag-shopping"></i>
                                <p>No orders yet</p>
                                <small style="color:rgba(255,255,255,0.35);">Your order history will appear here</small>
                            </div>`;
                        return;
                    }

                    container.innerHTML = '';
                    data.orders.forEach(order => {
                        const card = document.createElement('div');
                        card.className = 'order-card';
                        card.id = `order-card-${order.id}`;

                        // Status badge
                        const statusLabels = {
                            'pending': 'Pending', 'accepted': 'Accepted', 'preparing': 'Preparing',
                            'ready_for_pickup': 'Ready for Pickup', 'on_route': 'On the Way',
                            'processing': 'Processing', 'confirmed': 'Ready for Rider', 'shipped': 'On the Way',
                            'delivered': 'Delivered', 'cancelled': 'Cancelled'
                        };
                        const statusLabel = statusLabels[order.status] || order.status;
                        const statusClass = order.status.replace(/_/g, '_');

                        // Items summary
                        let itemsHTML = '';
                        if (order.items && order.items.length > 0) {
                            const maxShow = 3;
                            order.items.slice(0, maxShow).forEach(item => {
                                const qty = item.quantity || 1;
                                const name = item.name || 'Item';
                                itemsHTML += `<div class="item-line"><span>${qty}× ${name}</span></div>`;
                            });
                            if (order.items.length > maxShow) {
                                itemsHTML += `<div style="color:rgba(255,255,255,0.35);font-style:italic;">+${order.items.length - maxShow} more item(s)</div>`;
                            }
                        }

                        // Actions
                        let actionsHTML = '';
                        if (order.can_mark_delivered) {
                            actionsHTML += `<button class="btn-deliver" onclick="handleMarkDelivered(${order.id})"><i class="fa-solid fa-check-circle"></i> Mark as Delivered</button>`;
                        }
                        if (order.can_rate) {
                            actionsHTML += `<button class="btn-rate" onclick="handleRateOrder(${order.id}, ${order.rider_id}, '${(order.rider_name || '').replace(/'/g, "\\'")}')"><i class="fa-solid fa-star"></i> Rate Order</button>`;
                        }
                        if (order.rider_rated) {
                            actionsHTML += `<span class="order-rated"><i class="fa-solid fa-star"></i> Rated</span>`;
                        }

                        // Date
                        const dateObj = new Date(order.created_at);
                        const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                        // Total
                        const total = order.total ? '₱' + Number(order.total).toLocaleString('en-PH', {minimumFractionDigits:2}) : '';

                        // Rider info
                        let riderHTML = '';
                        if (order.rider_name && ['on_route','shipped','delivered'].includes(order.status)) {
                            riderHTML = `<div style="font-size:0.78rem;color:rgba(255,255,255,0.45);margin-bottom:8px;"><i class="fa-solid fa-motorcycle" style="margin-right:4px;color:var(--red);"></i> Rider: <strong style="color:rgba(255,255,255,0.7);">${order.rider_name}</strong></div>`;
                        }

                        card.innerHTML = `
                            <div class="order-card-header">
                                <span class="order-card-id">Order #${order.id}</span>
                                <span class="order-badge ${statusClass}">${statusLabel}</span>
                            </div>
                            ${riderHTML}
                            <div class="order-card-items">${itemsHTML || '<span style="font-style:italic;">No items</span>'}</div>
                            <div class="order-card-footer">
                                <div>
                                    <span class="order-card-total">${total}</span>
                                    <span class="order-card-date" style="margin-left:10px;">${dateStr}</span>
                                </div>
                                <div class="order-card-actions">${actionsHTML}</div>
                            </div>`;

                        container.appendChild(card);
                    });
                })
                .catch(err => {
                    console.error('Failed to load orders:', err);
                    const container = document.getElementById('ordersContainer');
                    const loading = document.getElementById('ordersLoading');
                    if (loading) loading.remove();
                    container.innerHTML = '<div class="orders-empty"><p>Could not load orders</p></div>';
                });
        }

        // Mark order as delivered
        window.handleMarkDelivered = function(orderId) {
            if (!confirm('Confirm that you have received this order?')) return;

            fetch('ratings-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'complete-delivery', order_id: orderId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Order marked as delivered!');
                    if (data.requires_rating && data.rider_id) {
                        // Fetch order details to get items for food rating
                        fetch(`customer-orders-api.php?action=my-orders&limit=50`, { credentials: 'include' })
                            .then(r => r.json())
                            .then(ordersData => {
                                const order = ordersData.orders?.find(o => o.id === orderId);
                                const items = (order?.items || []).map(item => ({
                                    id: item.menu_item_id || item.id,
                                    name: item.name || 'Item'
                                })).filter(item => item.id);
                                showRatingModal(orderId, data.rider_id, '', items);
                            })
                            .catch(() => {
                                // Show rating modal even without item details
                                showRatingModal(orderId, data.rider_id, '', []);
                            });
                    } else {
                        loadMyOrders();
                    }
                } else {
                    showToast(data.error || 'Failed to mark as delivered', true);
                }
            })
            .catch(() => showToast('Network error', true));
        };

        // Rate an order (for already-delivered orders)
        window.handleRateOrder = function(orderId, riderId, riderName) {
            fetch(`customer-orders-api.php?action=my-orders&limit=50`, { credentials: 'include' })
                .then(r => r.json())
                .then(ordersData => {
                    const order = ordersData.orders?.find(o => o.id === orderId);
                    const items = (order?.items || []).map(item => ({
                        id: item.menu_item_id || item.id,
                        name: item.name || 'Item'
                    })).filter(item => item.id);
                    showRatingModal(orderId, riderId, riderName, items);
                })
                .catch(() => {
                    showRatingModal(orderId, riderId, riderName, []);
                });
        };

        // Show the rating modal
        function showRatingModal(orderId, riderId, riderName, items) {
            if (typeof DeliveryRatingModal === 'undefined') {
                showToast('Rating module not loaded', true);
                return;
            }
            const modal = new DeliveryRatingModal(orderId, riderId, riderName, () => {
                showToast('Thank you! Your ratings have been submitted.');
                loadMyOrders();
            });
            modal.show(items);
        }

        // Load orders on page init
        loadMyOrders();
    </script>

    <!-- Booking Calendar & Rating JS (provides DeliveryRatingModal, StarRating classes) -->
    <script src="js/booking-calendar.js"></script>
</body>
</html>
