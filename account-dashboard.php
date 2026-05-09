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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="account-dashboard.css">
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
<a href="account.php" class="nav-account-icon active" title="Account" id="accountIconLink">
                    <i class="fas fa-user-circle"></i>
                    <span class="account-bubble" id="orderTrackBubble">track your order here</span>
                </a>
            </nav>
        </div>
    </header>

    <main>
        <div class="page">
            <p class="page-eyebrow">Logged In</p>
            <h1 class="page-title">My Account</h1>

            <!-- Profile -->
            <div class="section-block">
                <div class="section-label"><i class="fa-solid fa-user"></i> Profile &amp; Account Info</div>
                <div class="profile-card">
                    <div class="avatar-wrap">
                        <div class="avatar" id="avatarInitial">?</div>
                        <div class="avatar-badge"></div>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name" id="displayName">Loading...</div>
                        <div class="profile-email" id="displayEmail">Loading...</div>
                    </div>
                    <button class="edit-btn" onclick="openEdit()">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                    </button>
                </div>
                <div class="detail-rows">
                    <div class="detail-row"><span class="dr-label">Full Name</span><span class="dr-value" id="detailName">—</span></div>
                    <div class="detail-row"><span class="dr-label">Email Address</span><span class="dr-value" id="detailEmail">—</span></div>
                    <div class="detail-row"><span class="dr-label">Member Since</span><span class="dr-value" id="memberSince">—</span></div>
                </div>
            </div>

<!-- Track Your Order -->
            <div class="section-block" id="trackingBlock">
                <div class="section-label"><i class="fa-solid fa-location-dot"></i> Track Your Order</div>
                <div id="orderTrackingSection">
                    <!-- Populated by loadOrderTracking() -->
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
                    <div class="logout-label">Log Out<small>Signed in as <span id="logoutEmail">—</span></small></div>
                    <i class="fa-solid fa-chevron-right" style="color:rgba(194,38,38,0.4);font-size:0.75rem;"></i>
                </button>
            </div>
        </div>
    </main>

    <!-- FULLSCREEN MAP OVERLAY -->
    <div class="map-fullscreen-overlay" id="mapFullscreen">
        <div class="map-fs-header">
            <span class="map-fs-title"><i class="fas fa-motorcycle" style="color:#C22626;margin-right:8px;"></i>Live Tracking</span>
            <button class="map-fs-close" onclick="closeMapFullscreen()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="map-fs-body">
            <div id="customer-map-view"></div>
        </div>
        <!-- Bottom card -->
        <div class="map-fs-card">
            <div class="map-fs-addr">Delivering to</div>
            <div class="map-fs-addr-val" id="fsAddress">—</div>
            <div class="map-fs-progress track-progress">
                <div class="track-step"><div class="track-dot done" id="fsDot1"><i class="fas fa-check" style="font-size:0.55rem"></i></div><div class="track-label done">Order<br>Placed</div></div>
                <div class="track-line done" id="fsLine1"></div>
                <div class="track-step"><div class="track-dot active" id="fsDot2"><i class="fas fa-motorcycle" style="font-size:0.6rem"></i></div><div class="track-label active">On the<br>Way</div></div>
                <div class="track-line" id="fsLine2"></div>
                <div class="track-step"><div class="track-dot" id="fsDot3"><i class="fas fa-box" style="font-size:0.55rem"></i></div><div class="track-label">Delivered</div></div>
            </div>
            <div class="map-fs-eta"><i class="fas fa-clock" style="color:#C22626"></i> Estimated arrival: <strong id="fsEta">~15 mins</strong></div>
        </div>
    </div>

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

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script src="http://localhost:3000/socket.io/socket.io.js"></script>

    <script src="account-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>