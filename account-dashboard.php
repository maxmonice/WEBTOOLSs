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

function getNotificationIcon(string $type): string {
    $icons = [
        'info' => 'fa-info-circle',
        'success' => 'fa-check-circle',
        'warning' => 'fa-exclamation-triangle',
        'error' => 'fa-times-circle',
        'booking' => 'fa-calendar-days',
        'order' => 'fa-bag-shopping',
        'user' => 'fa-user-plus',
    ];
    return $icons[$type] ?? 'fa-info-circle';
}

function getNotificationColor(string $type): string {
    $colors = [
        'info' => '#3498db',
        'success' => '#2ecc71',
        'warning' => '#f39c12',
        'error' => '#e74c3c',
        'booking' => '#9b59b6',
        'order' => '#e67e22',
        'user' => '#1abc9c',
    ];
    return $colors[$type] ?? '#3498db';
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
                <a href="bookbar.php">Book Bar</a>
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
