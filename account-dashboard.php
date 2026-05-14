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
$sessionRole         = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
$isAdminPanelSession = !empty($_SESSION['is_admin']) || $sessionRole === 'admin';
$isStaffPanelSession = !empty($_SESSION['is_staff']) || $sessionRole === 'staff';

if (!isset($_SESSION['user_id'])) {
    if ($isUserViewMode && $sessionRole && in_array($sessionRole, ['admin', 'staff'], true)) {
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

$backToPanelHref = '';
$backToPanelLabel = '';
if ($isAdminPanelSession) {
    $backToPanelHref  = 'adminSide/admin-dashboard.php';
    $backToPanelLabel = 'Admin dashboard';
} elseif ($isStaffPanelSession) {
    $backToPanelHref  = 'staffSide/staff-dashboard.php';
    $backToPanelLabel = 'Staff dashboard';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="account-dashboard.css">

<script>
    window.LOCATIONIQ_TOKEN = '<?php 
        require_once __DIR__ . "/env-bootstrap.php";
        webtools_load_env(__DIR__);
        echo getenv("LOCATIONIQ_TOKEN") ?: ""; 
    ?>';
</script>

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
            </nav>

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
        </div>
    </header>

    <main>
        <?php if ($backToPanelHref !== ''): ?>
        <div class="panel-back-row">
            <a href="<?= htmlspecialchars($backToPanelHref) ?>" class="panel-back-btn"><i class="fa-solid fa-arrow-left"></i> Back to <?= htmlspecialchars($backToPanelLabel) ?></a>
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
                        <div class="avatar" id="avatarContainer">
                            <span id="avatarInitial"><?= strtoupper(substr($renderName, 0, 1)) ?></span>
                            <img id="avatarImg" style="width:100%; height:100%; border-radius:50%; object-fit:cover; display:none;" />
                        </div>
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

            <!-- Track Your Order -->
            <div class="section-block" id="trackingBlock" style="display:none;">
                <div class="section-label"><i class="fa-solid fa-route"></i> Track Your Order</div>
                <div id="orderTrackingSection" style="padding:20px 22px;">
                    <div class="orders-loading"><i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Checking active order...</div>
                </div>
            </div>

            <!-- My Messages -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-comments"></i> My Messages</div>
                <div class="messages-list" id="messagesList">
                    <div class="message-thread-item" onclick="openAdminChat()">
                        <div class="thread-avatar admin"><i class="fas fa-headset"></i></div>
                        <div class="thread-info">
                            <div class="thread-header">
                                <span class="thread-name">Luke's Admin Support</span>
                                <span class="thread-time" id="admin-last-time"></span>
                            </div>
                            <div class="thread-preview" id="admin-last-msg">Start a conversation with our team.</div>
                        </div>
                    </div>
                    <div id="riderChatThread"></div>
                </div>
            </div>

            <!-- Event Bookings -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-calendar-days"></i> My Event Bookings</div>
                <div id="eventBookingsSection" style="min-height:92px;">
                    <div class="orders-loading" style="padding:20px 22px;">
                        <i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Loading your bookings...
                    </div>
                </div>
            </div>

            <!-- Account Tools -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-wallet"></i> Account Tools</div>
                <div class="menu-rows">
                    <a class="menu-row" href="#" onclick="openHistory(); return false;">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-receipt"></i></div>
                            <div class="mr-text"><div class="mr-title">Transaction History</div><div class="mr-sub">View past orders and event bookings</div></div>
                        </div>
                        <i class="fa-solid fa-chevron-right mr-arrow"></i>
                    </a>
                    <hr class="menu-divider">
                    <a class="menu-row" href="#" onclick="openPromos(); return false;">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-ticket"></i></div>
                            <div class="mr-text"><div class="mr-title">Promos &amp; Rewards</div><div class="mr-sub">Search and claim available promo codes</div></div>
                        </div>
                        <i class="fa-solid fa-chevron-right mr-arrow"></i>
                    </a>
                </div>
            </div>

            <!-- Rate Your Food -->
            <div id="foodRatingSection" style="display:none;" class="section-block">
                <div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fas fa-utensils"></i> Rate your Food</span>
                    <button class="modal-close" onclick="ignoreFoodRating()" style="width:24px; height:24px; font-size:0.7rem; border:none; background:rgba(255,255,255,0.05);"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body" style="text-align:center; padding:25px 22px;">
                    <p class="food-rating-desc" style="margin-bottom:20px; font-size:0.9rem; color:var(--text); font-weight:600;">How was the quality and taste of your seafood today?</p>
                    <div class="rating-stars" id="foodStars" style="margin-bottom:25px;">
                        <button class="star-btn" data-val="1"><i class="fas fa-star"></i></button>
                        <button class="star-btn" data-val="2"><i class="fas fa-star"></i></button>
                        <button class="star-btn" data-val="3"><i class="fas fa-star"></i></button>
                        <button class="star-btn" data-val="4"><i class="fas fa-star"></i></button>
                        <button class="star-btn" data-val="5"><i class="fas fa-star"></i></button>
                    </div>
                    <div id="foodRatingItems" style="margin-bottom:20px; font-size:0.78rem; color:rgba(255,255,255,0.5); line-height:1.6; text-align:left; background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.06);"></div>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <button class="btn-save" style="flex:2; padding:14px; border-radius:10px;" onclick="submitFoodRating()">Submit Feedback</button>
                        <button class="btn-cancel" style="flex:1; padding:14px; border-radius:10px;" onclick="ignoreFoodRating()">No Thanks</button>
                    </div>
                </div>
            </div>

            <!-- Help & Support -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-circle-question"></i> Help &amp; Support</div>
                <div class="menu-rows">
                    <a class="menu-row" href="helpcenter.php">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-book-open"></i></div>
                            <div class="mr-text"><div class="mr-title">Help Center</div><div class="mr-sub">Browse FAQs and guides</div></div>
                        </div>
                        <i class="fa-solid fa-chevron-right mr-arrow"></i>
                    </a>
                    <hr class="menu-divider">
                    <a class="menu-row" href="#" onclick="openAdminChat(); return false;">
                        <div class="mr-left">
                            <div class="mr-icon"><i class="fa-solid fa-headset"></i></div>
                            <div class="mr-text"><div class="mr-title">Contact Support</div><div class="mr-sub">Admin and staff share this chat. Replies are active during working hours.</div></div>
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
                <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:25px; position:relative;">
                    <div id="editAvatarPreview" style="width:100px; height:100px; border-radius:50%; background:linear-gradient(135deg, var(--red-dark), var(--red)); display:flex; align-items:center; justify-content:center; font-family:'Aclonica',sans-serif; font-size:2.2rem; color:#fff; cursor:pointer; position:relative; overflow:hidden; border:4px solid var(--surface2); box-shadow:0 10px 20px rgba(0,0,0,0.3);" onclick="document.getElementById('inputPhoto').click()">
                        <span id="editAvatarInitial">?</span>
                        <img id="editAvatarImg" style="width:100%; height:100%; object-fit:cover; display:none;" />
                        <div style="position:absolute; inset:0; background:rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                            <i class="fa-solid fa-camera" style="font-size:1.5rem;"></i>
                        </div>
                    </div>
                    <input type="file" id="inputPhoto" style="display:none;" accept="image/*" onchange="previewProfilePhoto(this)" />
                    <p style="font-size:0.75rem; color:var(--muted); margin-top:10px; font-weight:600;">Click to change photo</p>
                </div>
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

    <!-- BOOKING DETAIL MODAL -->
    <div class="modal-overlay" id="bookingDetailModal">
        <div class="modal" style="max-width:560px;">
            <div class="modal-head">
                <h3><i class="fa-solid fa-calendar-check" style="color:var(--red);margin-right:8px;"></i>Event Booking</h3>
                <button class="modal-close" onclick="closeBookingDetail()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="bookingDetailBody"></div>
            <div class="modal-foot">
                <button class="btn-cancel" onclick="closeBookingDetail()">Close</button>
            </div>
        </div>
    </div>

    <!-- BOOKING DETAIL FULLSCREEN MODAL -->
    <div class="map-fullscreen-overlay" id="bookingDetailFullscreen" style="z-index:10002;">
        <div class="map-fs-header">
            <span class="map-fs-title"><i class="fas fa-calendar-alt" style="color:#fff; margin-right:8px;"></i>Booking Details</span>
            <button class="map-fs-close" onclick="closeBookingDetail()"><i class="fa-solid fa-xmark" style="color:#fff;"></i></button>
        </div>
        <div class="booking-fs-body" style="padding:20px 20px 40px; width:100%; color:#fff; background:#121212; height:calc(100vh - 70px); overflow-y:auto;">
            <div id="bookingDetailView" style="max-width:900px; margin:0 auto;">
                <div class="booking-header" style="text-align:center; margin-bottom:20px;">
                    <div id="bookingDetailStatusBadge" style="display:inline-block; padding:6px 16px; border-radius:100px; font-size:0.65rem; font-weight:800; text-transform:uppercase; margin-bottom:10px; letter-spacing:0.1em;">PENDING</div>
                    <h1 id="vDetailName" style="font-family:'Aclonica',sans-serif; font-size:1.8rem; margin-bottom:4px; color:#fff;">Event Name</h1>
                    <p id="vDetailId" style="color:rgba(255,255,255,0.4); font-size:0.8rem; font-weight:500;">Booking #BK-001</p>
                </div>
                <div class="booking-info-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:15px;">
                    <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06);"><label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Date & Time</label><div id="vDetailDateTime" style="font-weight:700; font-size:1rem; color:#fff;"></div></div>
                    <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06);"><label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Guests</label><div id="vDetailGuests" style="font-weight:700; font-size:1rem; color:#fff;"></div></div>
                    <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06);"><label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Event Type</label><div id="vDetailType" style="font-weight:700; font-size:1rem; color:#fff;"></div></div>
                </div>
                <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06); margin-bottom:15px;"><label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Location Address</label><div id="vDetailAddress" style="font-weight:500; font-size:0.9rem; line-height:1.4; color:#fff;"></div></div>
                <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06); margin-bottom:20px;"><label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Special Notes</label><div id="vDetailNotes" style="color:rgba(255,255,255,0.7); font-style:italic; font-size:0.85rem; line-height:1.4;"></div></div>
                <div id="bookingActionButtons" style="display:flex; flex-direction:column; gap:10px; align-items:center; margin-top:20px;">
                    <button id="btnEditBooking" class="submit-btn" style="width:100%; max-width:260px; padding:12px; border-radius:100px; font-size:0.9rem;" onclick="toggleEditBooking(true)"><i class="fa-solid fa-pen-to-square" style="margin-right:8px;"></i>Edit Details</button>
                    <button id="btnCancelBooking" class="btn-cancel" style="width:100%; max-width:260px; padding:12px; border-radius:100px; font-weight:700;" onclick="openCancelBookingModal()"><i class="fa-solid fa-trash-can" style="margin-right:8px;"></i>Cancel Booking</button>
                </div>
                <p id="editRestrictionNotice" style="text-align:center; margin-top:20px; font-size:0.85rem; color:rgba(255,255,255,0.4); display:none; font-weight:500;"><i class="fa-solid fa-circle-info" style="margin-right:6px;"></i> Editing is locked within 3 days of the event. You can still cancel.</p>
            </div>
            <div class="form-box" id="bookingEditContainer" style="display:none; max-width:900px; margin:0 auto;">
                <div id="bookingEditView">
                    <h2 class="form-title" style="margin-bottom:30px;">Edit Booking</h2>
                    <div class="form-group">
                        <label class="form-label">Event Name:<input type="text" id="eEventName" class="form-input" placeholder="Enter event name" /><span class="error-message" id="eEventNameError">Please enter an event name</span></label>
                        <div class="form-row">
                            <label class="form-label">Event Date:<input type="text" id="eEventDate" class="form-input" placeholder="Select date" readonly /><span class="error-message" id="eEventDateError">Please select a date</span></label>
                            <label class="form-label">Event Time:<input type="text" id="eEventTime" class="form-input" placeholder="Select time" readonly /><span class="error-message" id="eEventTimeError">Please select a time</span></label>
                        </div>
                        <div class="form-row">
                            <label class="form-label">Event Type:<select id="eEventType" class="form-select"><option value="wedding">Wedding</option><option value="birthday">Birthday Party</option><option value="corporate">Corporate Event</option><option value="anniversary">Anniversary</option><option value="graduation">Graduation</option><option value="reunion">Reunion</option><option value="teambuilding">Team Building</option><option value="holiday">Holiday Party</option><option value="other">Other</option></select><span class="error-message" id="eEventTypeError">Please select an event type</span></label>
                            <label class="form-label">Number of Guests:<select id="eNumGuests" class="form-select"><option value="10">10 pax</option><option value="20">20 pax</option><option value="30">30 pax</option><option value="40">40 pax</option><option value="50">50 pax</option><option value="60">60 pax</option><option value="70">70 pax</option><option value="80">80 pax</option><option value="90">90 pax</option><option value="100">100 pax</option></select><span class="error-message" id="eNumGuestsError">Please select guest count</span></label>
                        </div>
                        <label class="form-label">Location Address:<div style="position:relative; display:flex; gap:10px; margin-top:10px;"><input type="text" id="eAddress" class="form-input" style="flex:1; margin-top:0;" placeholder="Enter event address" /><button type="button" class="map-select-btn" onclick="initLeafletMapForEdit()" title="Select on Map"><i class="fas fa-map-marker-alt"></i></button></div><span class="error-message" id="eAddressError">Please provide an address</span></label>
                        <label class="form-label">Special Notes / Requests:<textarea id="eNotes" class="form-textarea" rows="4" placeholder="Any special requests?"></textarea></label>
                    </div>
                    <div style="text-align:center; margin-top:30px; display:flex; flex-direction:column; gap:10px; align-items:center;">
                        <button class="submit-btn" style="width:100%; max-width:260px; padding:12px; font-size:0.9rem;" onclick="saveBookingEdits()"><i class="fa-solid fa-floppy-disk" style="margin-right:8px;"></i>Save Changes</button>
                        <button class="btn-cancel" style="width:100%; max-width:260px; padding:12px; border-radius:100px; font-weight:700;" onclick="toggleEditBooking(false)">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FULLSCREEN PROMO OVERLAY -->
    <div class="map-fullscreen-overlay" id="promoFullscreen" style="z-index:10001; background:#0a0a0a;">
        <div class="map-fs-header" style="border-bottom:1px solid rgba(255,255,255,0.05);">
            <span class="map-fs-title"><i class="fa-solid fa-ticket" style="color:#C22626;margin-right:8px;"></i>Promo Codes</span>
            <button class="map-fs-close" onclick="closePromos()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="promo-portal-body" style="padding:30px 20px; max-width:700px; margin:0 auto; width:100%;">
            <div class="promo-search-box" style="position:relative; margin-bottom:30px;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:18px; top:50%; transform:translateY(-50%); color:var(--muted);"></i>
                <input type="text" id="promoSearchInput" placeholder="Enter promo code (e.g. N3WUS3R)" style="width:100%; padding:18px 18px 18px 50px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:14px; color:#fff; font-size:1rem; outline:none;" oninput="handlePromoSearch()">
            </div>
            <div id="promoResultsList" class="promo-list" style="display:flex; flex-direction:column; gap:15px;"></div>
        </div>
    </div>

    <!-- NEW USER PROMO MODAL -->
    <div class="modal-overlay" id="newUserPromoModal" style="z-index:10005;">
        <div class="modal" style="background:rgba(18,18,18,0.95); backdrop-filter:blur(15px); border:1px solid rgba(255,255,255,0.08); box-shadow:0 25px 50px rgba(0,0,0,0.5); width:90%; max-width:400px;">
            <div class="modal-body" style="padding:40px 25px; text-align:center;">
                <div class="promo-gift-icon" style="background:rgba(194,38,38,0.1); color:#C22626; width:80px; height:80px; font-size:2.5rem; margin:0 auto 25px; border-radius:50%; display:grid; place-items:center;">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <h2 style="font-family:'Aclonica',sans-serif; color:#fff; margin-bottom:12px; font-size:1.6rem;">Welcome Gift!</h2>
                <p style="color:rgba(255,255,255,0.7); line-height:1.6; margin-bottom:30px;">Get <strong style="color:var(--red);">30% OFF</strong> on your first order with the code <strong style="color:var(--red);">N3WUS3R</strong>.</p>
                <button class="btn-save" onclick="claimNewUserPromo()" style="width:100%; padding:18px; border-radius:12px; font-weight:800; font-size:1rem; letter-spacing:0.05em; text-transform:uppercase;">Claim Now</button>
                <button class="btn-cancel" onclick="closeModal('newUserPromoModal')" style="width:100%; margin-top:12px; border:none; background:transparent;">Maybe Later</button>
            </div>
        </div>
    </div>

    <!-- FULLSCREEN HISTORY OVERLAY -->
    <div class="map-fullscreen-overlay" id="historyFullscreen" style="z-index:10001; background:#0a0a0a;">
        <div class="map-fs-header" style="border-bottom:1px solid rgba(255,255,255,0.05);">
            <span class="map-fs-title"><i class="fa-solid fa-clock-rotate-left" style="color:#C22626;margin-right:8px;"></i>Transaction History</span>
            <button class="map-fs-close" onclick="closeHistory()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="history-tabs-header">
            <button class="history-tab active" data-tab="past-orders" onclick="switchHistoryTab('past-orders')"><i class="fa-solid fa-bag-shopping"></i> Past Orders</button>
            <button class="history-tab" data-tab="past-bookings" onclick="switchHistoryTab('past-bookings')"><i class="fa-solid fa-calendar-check"></i> Past Events</button>
        </div>
        <div class="history-body-container">
            <div id="past-orders" class="history-tab-content active">
                <div id="pastOrdersList" class="history-list"><div class="loading-state"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div></div>
            </div>
            <div id="past-bookings" class="history-tab-content">
                <div id="pastBookingsList" class="history-list"><div class="loading-state"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div></div>
            </div>
        </div>
    </div>

    <!-- FULLSCREEN MAP -->
    <div class="map-fullscreen-overlay" id="mapFullscreen" style="z-index:10001;">
        <div class="map-fs-header">
            <span class="map-fs-title"><i class="fas fa-motorcycle" style="color:#C22626;margin-right:8px;"></i>Live Tracking</span>
            <button class="map-fs-close" onclick="closeMapFullscreen()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="map-fs-body"><div id="customer-map-view"></div></div>
        <div class="map-fs-card">
            <div class="map-fs-addr">Delivering to</div>
            <div class="map-fs-addr-val" id="fsAddress">-</div>
            <div class="map-fs-progress track-progress">
                <div class="track-step"><div class="track-dot done" id="fsDot1"><i class="fas fa-check" style="font-size:0.55rem"></i></div><div class="track-label done">Order<br>Placed</div></div>
                <div class="track-line done" id="fsLine1"></div>
                <div class="track-step"><div class="track-dot active" id="fsDot2"><i class="fas fa-motorcycle" style="font-size:0.6rem"></i></div><div class="track-label active">On the<br>Way</div></div>
                <div class="track-line" id="fsLine2"></div>
                <div class="track-step"><div class="track-dot" id="fsDot3"><i class="fas fa-box" style="font-size:0.55rem"></i></div><div class="track-label">Delivered</div></div>
            </div>
            <div class="map-fs-eta"><i class="fas fa-clock" style="color:#C22626"></i> Estimated arrival: <strong id="fsEta">~15 mins</strong></div>
            <button class="chat-with-rider-btn" id="customerChatBtn" style="width:100%; margin-top:15px; background:var(--red); color:#fff; border:none; padding:12px; border-radius:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;">
                <i class="fas fa-comment-dots"></i> Chat with Rider
            </button>
        </div>
    </div>

    <!-- RIDER RATING MODAL -->
    <div class="modal-overlay rating-modal-overlay" id="riderRatingModal">
        <div class="modal">
            <div class="modal-head" style="padding-bottom:10px;">
                <h3>Rate your Delivery Rider</h3>
                <button class="modal-close" onclick="closeRiderRating()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="text-align:center; padding-top:15px; padding-bottom:10px;">
                <div class="logout-confirm-icon" style="background:rgba(245,158,11,0.1); border-color:rgba(245,158,11,0.3); color:#f59e0b; width:60px; height:60px; font-size:1.6rem; margin:0 auto 15px;">
                    <i class="fas fa-star"></i>
                </div>
                <h3 style="margin-bottom:12px;">How was the delivery?</h3>
                <div id="riderRatingNameBtn" style="display:inline-block; margin-bottom:15px; padding:8px 20px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:30px; font-size:0.85rem; font-weight:700; color:#fff;">
                    <i class="fas fa-motorcycle" style="color:var(--red); margin-right:8px;"></i><span id="riderNamePlaceholder">Delivery Rider</span>
                </div>
                <p style="font-size:0.85rem; color:var(--muted);">Your feedback helps our riders improve their service.</p>
                <div class="rating-stars" id="riderStars">
                    <button class="star-btn" data-val="1"><i class="fas fa-star"></i></button>
                    <button class="star-btn" data-val="2"><i class="fas fa-star"></i></button>
                    <button class="star-btn" data-val="3"><i class="fas fa-star"></i></button>
                    <button class="star-btn" data-val="4"><i class="fas fa-star"></i></button>
                    <button class="star-btn" data-val="5"><i class="fas fa-star"></i></button>
                </div>
                <div class="rating-comment-wrap" style="margin-top:15px; text-align:left;">
                    <label style="font-size:0.72rem; font-weight:700; color:var(--muted); text-transform:uppercase; margin-bottom:8px; display:block;">Add a comment (Optional)</label>
                    <textarea id="riderComment" placeholder="How was the service?" style="width:100%; height:70px; background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:12px; color:#fff; font-family:inherit; font-size:0.85rem; resize:none; outline:none;"></textarea>
                </div>
                <button class="btn-save" style="width:100%; margin-top:20px; padding:14px; border-radius:10px;" onclick="submitRiderRating()">Submit Rider Feedback</button>
            </div>
            <div class="modal-foot col" style="padding-top:0;">
                <button class="rating-ignore" onclick="ignoreRiderRating()" style="background:transparent; border:none; color:var(--muted); font-size:0.85rem; cursor:pointer; text-decoration:underline; padding:10px;">No Thanks</button>
            </div>
        </div>
    </div>

    <!-- CANCEL ORDER MODAL -->
    <div class="modal-overlay" id="cancelOrderModal">
        <div class="modal">
            <div class="modal-head">
                <h3>Cancel Order</h3>
                <button class="modal-close" onclick="closeCancelModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="logout-confirm-body">
                <div class="logout-confirm-icon" style="background:rgba(239,68,68,0.1); border:none; color:#ef4444;"><i class="fa-solid fa-circle-exclamation"></i></div>
                <h3>Cancel this order?</h3>
                <p>This action cannot be undone. Are you sure you want to stop this order from being prepared?</p>
            </div>
            <div class="modal-foot col">
                <button class="btn-logout-confirm btn-danger-gradient" id="confirmCancelBtn">Yes, Cancel Order</button>
                <button class="btn-cancel" onclick="closeCancelModal()">No, Keep Order</button>
            </div>
        </div>
    </div>

    <!-- CHAT MODAL (Rider) -->
    <div class="modal-overlay" id="chatModal" style="z-index:10010;">
        <div class="modal" style="width:100%; max-width:400px; height:80vh; display:flex; flex-direction:column; padding:0; overflow:hidden; background:#0d0204;">
            <div class="modal-head" style="padding:15px 20px; background:var(--red-dark); border:none;">
                <h3 style="color:#fff; font-size:1rem;"><i class="fas fa-comment-dots" style="margin-right:8px;"></i> Chat with Rider</h3>
                <button class="modal-close" onclick="closeChatModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="customer-chat-messages" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background:rgba(0,0,0,0.2);"></div>
            <div style="padding:15px; border-top:1px solid rgba(255,255,255,0.05); background:rgba(255,255,255,0.02);">
                <div style="display:flex; gap:10px; background:rgba(255,255,255,0.05); border-radius:24px; padding:5px 5px 5px 15px; border:1px solid rgba(255,255,255,0.1);">
                    <input type="text" id="customer-chat-input" placeholder="Type a message..." style="flex:1; background:none; border:none; color:#fff; padding:8px 0; outline:none; font-size:0.9rem;">
                    <button id="customer-send-btn" style="width:36px; height:36px; border-radius:50%; background:var(--red); color:#fff; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- ADMIN CHAT FULLSCREEN OVERLAY -->
    <div class="map-fullscreen-overlay" id="adminChatFullscreen" style="z-index:10011;">
        <div class="admin-chat-header" style="background:var(--red-dark); padding:15px 20px; display:flex; align-items:center; gap:15px; box-shadow:0 4px 20px rgba(0,0,0,0.3); position:relative; z-index:10;">
            <button class="back-btn" onclick="closeAdminChatFullscreen()" style="background:none; border:none; color:#fff; font-size:1.1rem; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fas fa-chevron-left"></i></button>
            <div class="admin-avatar" style="width:40px; height:40px; border-radius:12px; background:#fff; display:flex; align-items:center; justify-content:center; color:var(--red); font-size:1.2rem;"><i class="fas fa-user-shield"></i></div>
            <div class="admin-info"><h3 style="font-size:0.95rem; font-weight:700; margin:0; color:#fff;">Admin Support</h3><p style="font-size:0.7rem; opacity:0.7; margin:0; color:#fff;">Always active for you</p></div>
        </div>
        <div class="chat-container" style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#0d0204;">
            <div class="chat-messages" id="admin-chat-messages" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:15px;"></div>
            <div class="chat-input-area" style="padding:15px; background:rgba(0,0,0,0.2); border-top:1px solid rgba(255,255,255,0.05);">
                <div class="input-wrapper" style="display:flex; align-items:flex-end; gap:10px; background:rgba(255,255,255,0.05); border-radius:24px; padding:5px 5px 5px 15px; border:1px solid rgba(255,255,255,0.1);">
                    <button class="attach-btn" onclick="document.getElementById('admin-chat-file').click()" style="background:none; border:none; color:rgba(255,255,255,0.5); font-size:1.1rem; padding:10px 5px; cursor:pointer;"><i class="fas fa-paperclip"></i></button>
                    <input type="file" id="admin-chat-file" style="display:none;" accept="image/*" onchange="handleAdminChatFile(this)">
                    <textarea id="admin-chat-input" placeholder="Type your concern..." rows="1" style="flex:1; background:none; border:none; color:#fff; padding:10px 0; font-family:inherit; font-size:0.9rem; outline:none; resize:none; max-height:100px;"></textarea>
                    <button id="admin-send-btn" class="send-btn" onclick="sendAdminMessage()" style="width:40px; height:40px; border-radius:50%; background:var(--red); color:#fff; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- CANCEL BOOKING CONFIRMATION MODAL -->
    <div class="modal-overlay" id="cancelBookingConfirmModal" style="z-index:10005;">
        <div class="modal" style="background:rgba(18,18,18,0.95); backdrop-filter:blur(15px); border:1px solid rgba(255,255,255,0.08); box-shadow:0 25px 50px rgba(0,0,0,0.5);">
            <div class="modal-head" style="border-bottom:1px solid rgba(255,255,255,0.05); padding:20px 25px;">
                <h3 style="font-family:'Aclonica',sans-serif; color:#fff; font-size:1.1rem;">Cancel Booking</h3>
                <button class="modal-close" onclick="closeCancelBookingModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="logout-confirm-body" style="padding:40px 25px; text-align:center;">
                <div class="logout-confirm-icon" style="background:rgba(239,68,68,0.1); border:none; color:#ef4444; width:70px; height:70px; font-size:2rem; margin:0 auto 20px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
                <h3 style="color:#fff; margin-bottom:12px; font-size:1.4rem;">Cancel this event?</h3>
                <p style="color:rgba(255,255,255,0.6); line-height:1.6;">Are you sure you want to cancel your event booking? This will notify our staff and free up the schedule.</p>
            </div>
            <div class="modal-foot col" style="padding:0 25px 25px 25px; border:none; gap:12px;">
                <button class="btn-logout-confirm btn-danger-gradient" id="confirmCancelBookingBtn" style="padding:16px; border-radius:12px;">Yes, Cancel Booking</button>
                <button class="btn-cancel" onclick="closeCancelBookingModal()" style="padding:16px; border-radius:12px;">No, Keep it</button>
            </div>
        </div>
    </div>

    <!-- CHAT OVERLAY -->
    <div class="chat-overlay" id="chatOverlay">
        <div class="chat-window">
            <div class="chat-header">
                <div class="chat-header-info">
                    <div class="chat-avatar" id="chatAvatar">A</div>
                    <div>
                        <div class="chat-name" id="chatTargetName">Admin Support</div>
                        <div class="chat-status" id="chatTargetStatus">Admin support is active during working hours</div>
                    </div>
                </div>
                <button class="chat-close" onclick="closeChat()"><i class="fas fa-times"></i></button>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input-area">
                <textarea id="chatInput" placeholder="Type a message..." rows="1"></textarea>
                <button id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

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
            loadOrderTracking();
            loadEventBookings();
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
            const isVisible = dropdown.classList.contains('show');

            // Close all dropdowns first
            document.querySelectorAll('.notification-dropdown').forEach(d => d.classList.remove('show'));

            // Toggle this dropdown
            if (!isVisible) {
                dropdown.classList.add('show');

                // Add click outside listener only once
                if (!dropdown.dataset.listenerAdded) {
                    dropdown.dataset.listenerAdded = 'true';
                    document.addEventListener('click', function handleClickOutside(e) {
                        if (!dropdown.contains(e.target) && !e.target.closest('.nav-notifications')) {
                            dropdown.classList.remove('show');
                            document.removeEventListener('click', handleClickOutside);
                            dropdown.dataset.listenerAdded = 'false';
                        }
                    });
                }
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
            let badge = document.getElementById('notificationCount');

            if (!badge && count > 0) {
                const notificationContainer = document.querySelector('.nav-notifications');
                if (notificationContainer) {
                    badge = document.createElement('span');
                    badge.id = 'notificationCount';
                    badge.className = 'notification-badge';
                    notificationContainer.appendChild(badge);
                }
            }

            if (!badge) {
                return;
            }

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

        function statusLabel(status) {
            const labels = {
                pending: 'Pending',
                confirmed: 'Ready for Rider',
                processing: 'Processing',
                preparing: 'Preparing',
                shipped: 'On the Way',
                on_route: 'On the Way',
                delivered: 'Delivered',
                cancelled: 'Cancelled',
                completed: 'Completed'
            };
            return labels[status] || String(status || '').replace(/_/g, ' ');
        }

        function peso(value) {
            const amount = Number(value || 0);
            return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        async function loadOrderTracking() {
            const block = document.getElementById('trackingBlock');
            const section = document.getElementById('orderTrackingSection');
            if (!block || !section) return;

            try {
                const res = await fetch('get-order.php?t=' + Date.now(), { credentials: 'include', cache: 'no-store' });
                const data = await res.json();
                if (!data.success || !data.order) {
                    block.style.display = 'none';
                    return;
                }

                const order = data.order;
                block.style.display = 'block';
                const isDelivered = order.status === 'delivered';
                const riderLine = order.rider_name
                    ? `<div style="font-size:0.82rem;color:rgba(255,255,255,0.55);margin-top:8px;"><i class="fa-solid fa-motorcycle" style="color:var(--red);margin-right:6px;"></i>Rider: <strong style="color:#fff;">${order.rider_name}</strong></div>`
                    : '';
                section.innerHTML = `
                    <div class="order-track-card" style="padding:0;background:transparent;">
                        <div class="order-track-header">
                            <span class="order-track-badge ${['shipped','on_route'].includes(order.status) ? 'live' : ''}"><span class="dot"></span>${statusLabel(order.status)}</span>
                            <span class="order-track-id">Order #${order.id}</span>
                        </div>
                        <div class="order-track-address"><i class="fas fa-location-dot"></i><span>${order.address || 'Delivery address unavailable'}</span></div>
                        ${riderLine}
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;flex-wrap:wrap;">
                            <div style="font-weight:800;color:#fff;">${peso(order.total_amount)}</div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                ${isDelivered ? `<button class="btn-save" onclick="clearTrackedOrder()" style="padding:10px 14px;">Mark Received</button>` : ''}
                                ${!isDelivered && order.status !== 'cancelled' ? `<button class="btn-cancel" onclick="cancelTrackedOrder(${order.id})" style="padding:10px 14px;">Cancel Order</button>` : ''}
                            </div>
                        </div>
                    </div>`;
            } catch (e) {
                block.style.display = 'none';
            }
        }

        window.clearTrackedOrder = function() {
            localStorage.removeItem('order_pending');
            localStorage.removeItem('order_id');
            document.getElementById('trackingBlock').style.display = 'none';
            showToast('Order tracking cleared.');
        };

        window.cancelTrackedOrder = async function(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) return;
            try {
                const res = await fetch('cancel-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ order_id: orderId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Order cancelled successfully.');
                    loadOrderTracking();
                    loadMyOrders();
                } else {
                    showToast(data.message || 'Could not cancel order.', true);
                }
            } catch (e) {
                showToast('Network error while cancelling.', true);
            }
        };

        function fmtSqlTime12(t) {
            if (!t) return '';
            const s = String(t).trim();
            if (/\s[–—-]\s/.test(s) && /(AM|PM)/i.test(s)) return s;
            const m = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?/);
            if (!m) return s;
            let h = parseInt(m[1], 10);
            const min = m[2];
            const ap = h >= 12 ? 'PM' : 'AM';
            const h12 = h % 12 || 12;
            return `${h12}:${min} ${ap}`;
        }
        function formatDashboardBookingTime(b) {
            if (!b || !b.event_time) return '';
            const en = b.event_time_end;
            if (en && String(en).trim() && en !== '00:00:00' && en !== b.event_time) {
                return `${fmtSqlTime12(b.event_time)} – ${fmtSqlTime12(en)}`;
            }
            return fmtSqlTime12(b.event_time);
        }

        async function loadEventBookings() {
            const section = document.getElementById('eventBookingsSection');
            if (!section) return;
            try {
                const res = await fetch('get-bookings.php', { credentials: 'include', cache: 'no-store' });
                const data = await res.json();
                if (!data.success) {
                    section.innerHTML = `<div class="orders-empty" style="padding:30px 22px;"><p>${data.message || 'Could not load bookings.'}</p></div>`;
                    return;
                }
                const bookings = (data.bookings || []).filter(b => String(b.status || '').toLowerCase() !== 'cancelled');
                window._dashboardBookings = bookings;
                if (!bookings.length) {
                    section.innerHTML = `<div class="orders-empty" style="padding:34px 22px;"><i class="fa-solid fa-calendar-day"></i><p>No active event bookings</p><small style="color:rgba(255,255,255,0.35);"><a href="bookbar.php" style="color:var(--red);font-weight:700;">Book an event</a> to see it here</small></div>`;
                    return;
                }
                section.innerHTML = `<div class="booking-list-container">${bookings.map((b, i) => `
                    <div class="booking-item" onclick="openBookingDetail(${b.id})" style="padding:20px 22px;cursor:pointer;${i < bookings.length - 1 ? 'border-bottom:1px solid rgba(255,255,255,0.06);' : ''}">
                        <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;">
                            <div>
                                <div style="font-weight:800;color:#fff;margin-bottom:5px;">${b.event_name || 'Event Booking'}</div>
                                <div style="font-size:0.82rem;color:rgba(255,255,255,0.5);"><i class="fa-regular fa-calendar" style="margin-right:6px;color:var(--red);"></i>${b.event_date || ''}${formatDashboardBookingTime(b) ? ' @ ' + formatDashboardBookingTime(b) : ''}</div>
                            </div>
                            <span class="order-badge ${String(b.status || '').toLowerCase()}">${statusLabel(b.status)}</span>
                        </div>
                        <div style="margin-top:12px;font-size:0.8rem;color:rgba(255,255,255,0.45);display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <span><i class="fa-solid fa-users" style="margin-right:6px;"></i>${b.num_guests || '-'} guests</span>
                            <span><i class="fa-solid fa-cake-candles" style="margin-right:6px;"></i>${b.event_type || 'Event'}</span>
                            <span style="grid-column:1 / -1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="fa-solid fa-location-dot" style="margin-right:6px;"></i>${b.address || 'No address'}</span>
                        </div>
                    </div>`).join('')}</div>`;
            } catch (e) {
                section.innerHTML = '<div class="orders-empty" style="padding:30px 22px;"><p>Could not load bookings.</p></div>';
            }
        }

        window.openBookingDetail = function(bookingId) {
            const booking = (window._dashboardBookings || []).find(b => String(b.id) === String(bookingId));
            if (!booking) return;
            document.getElementById('bookingDetailBody').innerHTML = `
                <div class="detail-row"><span class="dr-label">Booking ID</span><span class="dr-value">#BK-${String(booking.id).padStart(3, '0')}</span></div>
                <div class="detail-row"><span class="dr-label">Event</span><span class="dr-value">${booking.event_name || 'Event Booking'}</span></div>
                <div class="detail-row"><span class="dr-label">Date & Time</span><span class="dr-value">${booking.event_date || ''}${formatDashboardBookingTime(booking) ? ' @ ' + formatDashboardBookingTime(booking) : ''}</span></div>
                <div class="detail-row"><span class="dr-label">Guests</span><span class="dr-value">${booking.num_guests || '-'}</span></div>
                <div class="detail-row"><span class="dr-label">Type</span><span class="dr-value">${booking.event_type || '-'}</span></div>
                <div class="detail-row"><span class="dr-label">Status</span><span class="dr-value">${statusLabel(booking.status)}</span></div>
                <div style="padding-top:14px;color:rgba(255,255,255,0.65);line-height:1.5;"><strong style="color:#fff;">Address:</strong><br>${booking.address || 'No address'}</div>
                <div style="padding-top:14px;color:rgba(255,255,255,0.65);line-height:1.5;"><strong style="color:#fff;">Notes:</strong><br>${booking.notes || 'No special requests.'}</div>`;
            document.getElementById('bookingDetailModal').classList.add('open');
        };

        window.closeBookingDetail = function() {
            document.getElementById('bookingDetailModal').classList.remove('open');
        };

        window.openHistory = function() {
            document.getElementById('historyFullscreen').classList.add('open');
            switchHistoryTab('past-orders');
        };

        window.closeHistory = function() {
            document.getElementById('historyFullscreen').classList.remove('open');
        };

        window.switchHistoryTab = function(tabId) {
            document.querySelectorAll('.history-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tabId));
            document.querySelectorAll('.history-tab-content').forEach(content => content.classList.toggle('active', content.id === tabId));
            if (tabId === 'past-orders') loadPastOrders();
            if (tabId === 'past-bookings') loadPastBookings();
        };

        async function loadPastOrders() {
            const list = document.getElementById('pastOrdersList');
            list.innerHTML = '<div class="orders-loading">Loading orders...</div>';
            try {
                const res = await fetch('get-order-history.php', { credentials: 'include' });
                const data = await res.json();
                if (!data.success || !data.history?.length) {
                    list.innerHTML = '<div class="empty-state">No past orders found.</div>';
                    return;
                }
                list.innerHTML = data.history.map(order => `
                    <div class="history-item">
                        <div class="hi-left"><div class="hi-icon"><i class="fa-solid fa-box"></i></div><div class="hi-info"><h4>Order #ORD-${String(order.id).padStart(4, '0')}</h4><div class="hi-meta"><span>${new Date(order.created_at).toLocaleDateString()}</span><span>${order.items_summary || ''}</span></div></div></div>
                        <div class="hi-right"><div class="hi-price">${peso(order.total_amount)}</div><span class="hi-status ${String(order.status).toLowerCase()}">${statusLabel(order.status)}</span></div>
                    </div>`).join('');
            } catch (e) {
                list.innerHTML = '<div class="empty-state">Error loading orders.</div>';
            }
        }

        async function loadPastBookings() {
            const list = document.getElementById('pastBookingsList');
            list.innerHTML = '<div class="orders-loading">Loading bookings...</div>';
            try {
                const res = await fetch('get-booking-history.php', { credentials: 'include' });
                const data = await res.json();
                if (!data.success || !data.history?.length) {
                    list.innerHTML = '<div class="empty-state">No past event bookings found.</div>';
                    return;
                }
                list.innerHTML = data.history.map(booking => `
                    <div class="history-item">
                        <div class="hi-left"><div class="hi-icon"><i class="fa-solid fa-champagne-glasses"></i></div><div class="hi-info"><h4>${booking.event_type || 'Event Booking'}</h4><div class="hi-meta"><span>${new Date(booking.event_date).toLocaleDateString()}</span><span>${booking.guests || '-'} Guests</span></div></div></div>
                        <div class="hi-right"><div class="hi-price">${peso(booking.total_amount)}</div><span class="hi-status ${String(booking.status).toLowerCase()}">${statusLabel(booking.status)}</span></div>
                    </div>`).join('');
            } catch (e) {
                list.innerHTML = '<div class="empty-state">Error loading bookings.</div>';
            }
        }

        window.openPromos = function() {
            document.getElementById('promoFullscreen').classList.add('open');
            handlePromoSearch();
        };

        window.closePromos = function() {
            document.getElementById('promoFullscreen').classList.remove('open');
        };

        window.handlePromoSearch = async function() {
            const input = document.getElementById('promoSearchInput');
            const list = document.getElementById('promoResultsList');
            const query = (input?.value || '').trim().toUpperCase();
            list.innerHTML = '<div class="orders-loading">Loading promos...</div>';
            try {
                const res = await fetch('promo-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'search_promos', query })
                });
                const data = await res.json();
                if (!data.success || !data.promos?.length) {
                    list.innerHTML = '<div class="empty-state">No promo codes found.</div>';
                    return;
                }
                list.innerHTML = data.promos.map(promo => `
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:18px;margin-bottom:12px;display:flex;justify-content:space-between;gap:16px;align-items:center;">
                        <div>
                            <div style="font-family:'Aclonica',sans-serif;color:#fff;font-size:1.05rem;margin-bottom:6px;">${promo.code}</div>
                            <div style="color:rgba(255,255,255,0.5);font-size:0.8rem;">${promo.discount_percent}% off ${promo.applicable_category || 'eligible items'}</div>
                            <div style="color:rgba(255,255,255,0.38);font-size:0.74rem;margin-top:4px;">${promo.duration_days ? promo.duration_days + ' days validity' : 'No expiration'}</div>
                        </div>
                        ${Number(promo.is_claimed) ? '<span style="color:#22c55e;font-weight:800;">CLAIMED</span>' : `<button class="btn-save" onclick="claimPromo(${promo.id}, '${String(promo.code).replace(/'/g, "\\'")}')" style="padding:10px 18px;">Claim</button>`}
                    </div>`).join('');
            } catch (e) {
                list.innerHTML = '<div class="empty-state">Error loading promos.</div>';
            }
        };

        window.claimPromo = async function(id, code) {
            try {
                const res = await fetch('promo-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'claim_promo', promo_id: id })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Promo ${code} claimed!`);
                    handlePromoSearch();
                } else {
                    showToast(data.message || 'Could not claim promo.', true);
                }
            } catch (e) {
                showToast('Error claiming promo.', true);
            }
        };

        // Load orders on page init
        loadMyOrders();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script src="http://localhost:3000/socket.io/socket.io.js"></script>

    <!-- Booking Calendar & Rating JS (provides DeliveryRatingModal, StarRating classes) -->
    <script src="js/booking-calendar.js"></script>
    <script src="account-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>
