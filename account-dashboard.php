<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>My Account – Luke's Seafood Trading</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      <!-- FIX: Removed speech bubble span and onclick handler -->
      <a href="account.php" class="nav-account-icon active" title="Account" id="accountIconLink">
        <i class="fas fa-user-circle"></i>
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
          <div class="avatar" id="avatarContainer">
            <span id="avatarInitial">?</span>
            <img id="avatarImg" style="width:100%; height:100%; border-radius:50%; object-fit:cover; display:none;" />
          </div>
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
    <div class="section-block" id="trackingBlock" style="display:none;">
      <div class="section-label"><i class="fa-solid fa-location-dot"></i> Track Your Order</div>
      <div id="orderTrackingSection">
        <!-- Populated by loadOrderTracking() -->
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

    <!-- My Event Bookings -->
    <div class="section-block flush" id="bookingsBlock">
      <div class="section-label"><i class="fa-solid fa-calendar-check"></i> My Event Bookings</div>
      <div id="eventBookingsSection">
        <div class="booking-empty" style="text-align:center; padding: 40px 20px; color:rgba(255,255,255,0.4);">
          <i class="fa-solid fa-calendar-day" style="font-size:2rem; margin-bottom:15px; display:block; opacity:0.3;"></i>
          No active bookings found. <a href="bookbar.php" style="color:var(--red); font-weight:700;">Book now!</a>
        </div>
      </div>
    </div>

    <!-- History Section -->
    <div class="section-block">
      <div class="section-label"><i class="fa-solid fa-clock-rotate-left"></i> Transaction History</div>
      <div class="menu-rows">
        <a class="menu-row" href="#" onclick="openHistory(); return false;">
          <div class="mr-left">
            <div class="mr-icon"><i class="fa-solid fa-receipt"></i></div>
            <div class="mr-text">
              <div class="mr-title">View History</div>
              <div class="mr-sub">See your past orders and completed event bookings</div>
            </div>
          </div>
          <i class="fa-solid fa-chevron-right mr-arrow"></i>
        </a>
      </div>
    </div>

    <!-- Promo Section -->
    <div class="section-block" id="promoDashboardBlock">
      <div class="section-label"><i class="fa-solid fa-ticket"></i> Promo & Rewards</div>
      <div class="menu-rows">
        <a class="menu-row" href="#" onclick="openPromos(); return false;">
          <div class="mr-left">
            <div class="mr-icon"><i class="fa-solid fa-tags"></i></div>
            <div class="mr-text">
              <div class="mr-title">Promo Codes</div>
              <div class="mr-sub">Claim discounts and special offers</div>
            </div>
          </div>
          <i class="fa-solid fa-chevron-right mr-arrow"></i>
        </a>
      </div>
    </div>


    <!-- Rate Your Food Section (Inline, not a modal) -->
    <div id="foodRatingSection" style="display:none;" class="section-block">
      <div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
        <span><i class="fas fa-utensils"></i> Rate your Food</span>
        <button class="modal-close" onclick="ignoreFoodRating()" style="width:24px; height:24px; font-size:0.7rem; border:none; background:rgba(255,255,255,0.05);"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body" style="text-align:center; padding: 25px 22px;">
        <p class="food-rating-desc" style="margin-bottom:20px; font-size:0.9rem; color:var(--text); font-weight:600;">How was the quality and taste of your seafood today?</p>
        <div class="rating-stars" id="foodStars" style="margin-bottom:25px;">
          <button class="star-btn" data-val="1"><i class="fas fa-star"></i></button>
          <button class="star-btn" data-val="2"><i class="fas fa-star"></i></button>
          <button class="star-btn" data-val="3"><i class="fas fa-star"></i></button>
          <button class="star-btn" data-val="4"><i class="fas fa-star"></i></button>
          <button class="star-btn" data-val="5"><i class="fas fa-star"></i></button>
        </div>
        <div id="foodRatingItems" style="margin-bottom:20px; font-size:0.78rem; color:rgba(255,255,255,0.5); line-height:1.6; text-align:left; background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.06);">
          <!-- Item list here -->
        </div>
        
        <div style="display:flex; gap:10px; margin-top:10px;">
          <button class="btn-save" style="flex:2; padding:14px; border-radius:10px;" onclick="submitFoodRating()">Submit Feedback</button>
          <button class="btn-cancel" style="flex:1; padding:14px; border-radius:10px;" onclick="ignoreFoodRating()">No Thanks</button>
        </div>
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

    <!-- Admin Support Section -->
    <div class="section-block">
      <div class="section-label"><i class="fa-solid fa-circle-question"></i> Help &amp; Support</div>
      <div class="menu-rows">
        <a class="menu-row" href="#" onclick="openAdminChatFullscreen(); return false;">
          <div class="mr-left">
            <div class="mr-icon"><i class="fa-solid fa-headset"></i></div>
            <div class="mr-text">
              <div class="mr-title">Chat with Admin</div>
              <div class="mr-sub">Reach out to our support team for any concerns</div>
            </div>
          </div>
          <i class="fa-solid fa-chevron-right mr-arrow"></i>
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

<!-- FULLSCREEN PROMO OVERLAY -->
<div class="map-fullscreen-overlay" id="promoFullscreen" style="z-index:10001; background: #0a0a0a;">
  <div class="map-fs-header" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
    <span class="map-fs-title"><i class="fa-solid fa-ticket" style="color:#C22626;margin-right:8px;"></i>Promo Codes</span>
    <button class="map-fs-close" onclick="closePromos()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  
  <div class="promo-portal-body" style="padding: 30px 20px; max-width: 700px; margin: 0 auto;">
    <div class="promo-search-box" style="position: relative; margin-bottom: 30px;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
        <input type="text" id="promoSearchInput" placeholder="Enter promo code (e.g. N3WUS3R)" 
               style="width: 100%; padding: 18px 18px 18px 50px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; color: #fff; font-size: 1rem; outline: none; transition: border-color 0.3s;"
               oninput="handlePromoSearch()">
    </div>

    <div id="promoResultsList" class="promo-list" style="display: flex; flex-direction: column; gap: 15px;">
        <!-- Populated via JS -->
    </div>
  </div>
</div>

<!-- NEW USER PROMO MODAL -->
<div class="modal-overlay" id="newUserPromoModal" style="z-index:10005;">
  <div class="modal" style="background:rgba(18,18,18,0.95); backdrop-filter:blur(15px); border:1px solid rgba(255,255,255,0.08); box-shadow:0 25px 50px rgba(0,0,0,0.5); width:90%; max-width:400px;">
    <div class="modal-body" style="padding:40px 25px; text-align:center;">
      <div class="promo-gift-icon" style="background:rgba(194,38,38,0.1); color:#C22626; width:80px; height:80px; font-size:2.5rem; margin:0 auto 25px; border-radius:50%; display:grid; place-items:center; animation: bounce 2s infinite;">
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
<div class="map-fullscreen-overlay" id="historyFullscreen" style="z-index:10001; background: #0a0a0a;">
  <div class="map-fs-header" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
    <span class="map-fs-title"><i class="fa-solid fa-clock-rotate-left" style="color:#C22626;margin-right:8px;"></i>Transaction History</span>
    <button class="map-fs-close" onclick="closeHistory()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  
  <!-- Tabs Header -->
  <div class="history-tabs-header">
    <button class="history-tab active" data-tab="past-orders" onclick="switchHistoryTab('past-orders')">
      <i class="fa-solid fa-bag-shopping"></i> Past Orders
    </button>
    <button class="history-tab" data-tab="past-bookings" onclick="switchHistoryTab('past-bookings')">
      <i class="fa-solid fa-calendar-check"></i> Past Events
    </button>
  </div>

  <div class="history-body-container">
    <!-- Past Orders Tab -->
    <div id="past-orders" class="history-tab-content active">
      <div id="pastOrdersList" class="history-list">
        <!-- Populated via JS -->
        <div class="loading-state"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div>
      </div>
    </div>

    <!-- Past Bookings Tab -->
    <div id="past-bookings" class="history-tab-content">
      <div id="pastBookingsList" class="history-list">
        <!-- Populated via JS -->
        <div class="loading-state"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div>
      </div>
    </div>
  </div>
</div>

<!-- FULLSCREEN MAP (Absolute Fixed overlay) -->
<div class="map-fullscreen-overlay" id="mapFullscreen" style="z-index:10001;">

  <div class="map-fs-header">
    <span class="map-fs-title"><i class="fas fa-motorcycle" style="color:#C22626;margin-right:8px;"></i>Live Tracking</span>
    <button class="map-fs-close" onclick="closeMapFullscreen()"><i class="fa-solid fa-x" style="color: rgb(255, 255, 255);"></i></button>
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
    <button class="chat-with-rider-btn" id="customerChatBtn" style="width:100%; margin-top:15px; background:var(--red); color:#fff; border:none; padding:12px; border-radius:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;">
      <i class="fas fa-comment-dots"></i> Chat with Rider
    </button>
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
      <!-- Profile Picture Section -->
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

<!-- RIDER RATING MODAL -->
<div class="modal-overlay rating-modal-overlay" id="riderRatingModal">
  <div class="modal">

    <div class="modal-head" style="padding-bottom: 10px;">
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
        <textarea id="riderComment" placeholder="How was the service? (e.g. polite, fast, etc.)" style="width:100%; height:70px; background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:12px; color:#fff; font-family:inherit; font-size:0.85rem; resize:none; outline:none; transition:border-color 0.2s;"></textarea>
      </div>
      <button class="btn-save" style="width:100%; margin-top:20px; padding:14px; border-radius:10px;" onclick="submitRiderRating()">Submit Rider Feedback</button>
    </div>
    <div class="modal-foot col" style="padding-top:0;">
      <button class="rating-ignore" onclick="ignoreRiderRating()" style="background:transparent; border:none; color:var(--muted); font-size:0.85rem; cursor:pointer; text-decoration:underline; padding:10px; transition:color 0.2s;">No Thanks</button>
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

<!-- CANCEL ORDER MODAL -->
<div class="modal-overlay" id="cancelOrderModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Cancel Order</h3>
      <button class="modal-close" onclick="closeCancelModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="logout-confirm-body">
      <div class="logout-confirm-icon" style="background:rgba(239,68,68,0.1); border:none; color:#ef4444;">
        <i class="fa-solid fa-circle-exclamation"></i>
      </div>
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
<div class="modal-overlay" id="chatModal" style="z-index: 10010;">
  <div class="modal" style="width: 100%; max-width: 400px; height: 80vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; background: #0d0204;">
    <div class="modal-head" style="padding: 15px 20px; background: var(--red-deep); border: none;">
      <h3 style="color:#fff; font-size: 1rem;"><i class="fas fa-comment-dots" style="margin-right:8px;"></i> Chat with Rider</h3>
      <button class="modal-close" onclick="closeChatModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="customer-chat-messages" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background: rgba(0,0,0,0.2);">
      <!-- Messages here -->
    </div>
    <div style="padding:15px; border-top:1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.02);">
      <div style="display:flex; gap:10px; background:rgba(255,255,255,0.05); border-radius:24px; padding:5px 5px 5px 15px; border:1px solid rgba(255,255,255,0.1);">
        <input type="text" id="customer-chat-input" placeholder="Type a message..." style="flex:1; background:none; border:none; color:#fff; padding:8px 0; outline:none; font-size:0.9rem;">
        <button id="customer-send-btn" style="width:36px; height:36px; border-radius:50%; background:var(--red); color:#fff; border:none; display:flex; align-items:center; justify-content:center; cursor:pointer;">
          <i class="fas fa-paper-plane"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ADMIN CHAT FULLSCREEN OVERLAY -->
<div class="map-fullscreen-overlay" id="adminChatFullscreen" style="z-index:10011;">
  <div class="admin-chat-header" style="background: var(--red-deep); padding: 15px 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); position: relative; z-index: 10;">
    <button class="back-btn" onclick="closeAdminChatFullscreen()" style="background: none; border: none; color: #fff; font-size: 1.1rem; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;"><i class="fas fa-chevron-left"></i></button>
    <div class="admin-avatar" style="width: 40px; height: 40px; border-radius: 12px; background: #fff; display: flex; align-items: center; justify-content: center; color: var(--red); font-size: 1.2rem;"><i class="fas fa-user-shield"></i></div>
    <div class="admin-info">
      <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: #fff;">Admin Support</h3>
      <p style="font-size: 0.7rem; opacity: 0.7; margin: 0; color: #fff;">Always active for you</p>
    </div>
  </div>
  
  <div class="chat-container" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #0d0204;">
    <div class="chat-messages" id="admin-chat-messages" style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; background-image: radial-gradient(rgba(194, 38, 38, 0.05) 1px, transparent 1px); background-size: 20px 20px;">
      <div class="message customer" style="align-self: flex-start; max-width: 80%; padding: 10px 14px; border-radius: 18px; font-size: 0.9rem; line-height: 1.4; background: rgba(255, 255, 255, 0.08); color: #fff; border-bottom-left-radius: 4px; border: 1px solid rgba(255, 255, 255, 0.05);">
        Hello! How can we help you today?
        <span class="message-time" style="font-size: 0.65rem; opacity: 0.6; margin-top: 4px; display: block; text-align:right;">System</span>
      </div>
    </div>

    <div class="chat-input-area" style="padding: 15px; background: rgba(0, 0, 0, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.05);">
      <div class="input-wrapper" style="display: flex; align-items: flex-end; gap: 10px; background: rgba(255, 255, 255, 0.05); border-radius: 24px; padding: 5px 5px 5px 15px; border: 1px solid rgba(255,255,255,0.1);">
        <button class="attach-btn" onclick="document.getElementById('admin-chat-file').click()" style="background: none; border: none; color: rgba(255,255,255,0.5); font-size: 1.1rem; padding: 10px 5px; cursor: pointer;"><i class="fas fa-paperclip"></i></button>
        <input type="file" id="admin-chat-file" style="display:none;" accept="image/*" onchange="handleAdminChatFile(this)">
        <textarea id="admin-chat-input" placeholder="Type your concern..." rows="1" style="flex: 1; background: none; border: none; color: #fff; padding: 10px 0; font-family: inherit; font-size: 0.9rem; outline: none; resize: none; max-height: 100px;"></textarea>
        <button id="admin-send-btn" class="send-btn" onclick="sendAdminMessage()" style="width: 40px; height: 40px; border-radius: 50%; background: var(--red); color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer;"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- BOOKING DETAIL FULLSCREEN MODAL -->
<div class="map-fullscreen-overlay" id="bookingDetailFullscreen" style="z-index:10002;">
  <div class="map-fs-header">
    <span class="map-fs-title"><i class="fas fa-calendar-alt" style="color:#fff; margin-right:8px;"></i>Booking Details</span>
    <button class="map-fs-close" onclick="closeBookingDetail()"><i class="fa-solid fa-xmark" style="color:#fff;"></i></button>
  </div>

  
  <div class="booking-fs-body" style="padding: 20px 20px 40px; width: 100%; color: #fff; background: #121212; height: calc(100vh - 70px); overflow-y: auto;">
    <!-- View Mode (Centered but NOT boxed) -->
    <div id="bookingDetailView" style="max-width: 900px; margin: 0 auto;">
        <div class="booking-header" style="text-align:center; margin-bottom:20px;">
            <div id="bookingDetailStatusBadge" style="display:inline-block; padding:6px 16px; border-radius:100px; font-size:0.65rem; font-weight:800; text-transform:uppercase; margin-bottom:10px; letter-spacing: 0.1em; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">PENDING</div>
            <h1 id="vDetailName" style="font-family:'Aclonica',sans-serif; font-size:1.8rem; margin-bottom:4px; color:#fff;">Event Name</h1>
            <p id="vDetailId" style="color:rgba(255,255,255,0.4); font-size:0.8rem; font-weight:500;">Booking #BK-001</p>
        </div>

        <div class="booking-info-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-bottom:15px;">
            <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06);">
                <label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Date & Time</label>
                <div id="vDetailDateTime" style="font-weight:700; font-size:1rem; color:#fff;">Oct 24, 2025 @ 10:00 AM</div>
            </div>
            <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06);">
                <label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Guests</label>
                <div id="vDetailGuests" style="font-weight:700; font-size:1rem; color:#fff;">50 Persons</div>
            </div>
            <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06);">
                <label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Event Type</label>
                <div id="vDetailType" style="font-weight:700; font-size:1rem; color:#fff;">Birthday</div>
            </div>
        </div>

        <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06); margin-bottom:15px;">
            <label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Location Address</label>
            <div id="vDetailAddress" style="font-weight:500; font-size:0.9rem; line-height:1.4; color:#fff;">123 Main St, Barangay Central, Taguig City</div>
        </div>

        <div class="info-item" style="background:rgba(255,255,255,0.03); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.06); margin-bottom:20px;">
            <label style="color:rgba(255,255,255,0.3); font-size:0.6rem; font-weight:800; text-transform:uppercase; display:block; margin-bottom:6px; letter-spacing:0.1em;">Special Notes</label>
            <div id="vDetailNotes" style="color:rgba(255,255,255,0.7); font-style:italic; font-size:0.85rem; line-height:1.4;">No special requests.</div>
        </div>

        <div id="bookingActionButtons" style="display:flex; flex-direction: column; gap:10px; align-items: center; margin-top: 20px;">
            <button id="btnEditBooking" class="submit-btn" style="width: 100%; max-width: 260px; padding:12px; border-radius:100px; font-size:0.9rem;" onclick="toggleEditBooking(true)">
                <i class="fa-solid fa-pen-to-square" style="margin-right:8px;"></i>Edit Details
            </button>
            <button id="btnCancelBooking" class="btn-cancel" style="width: 100%; max-width: 260px; padding: 12px; border-radius: 100px; font-weight: 700; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); cursor: pointer; font-size: 0.9rem;" onclick="openCancelBookingModal()">
                <i class="fa-solid fa-trash-can" style="margin-right:8px;"></i>Cancel Booking
            </button>
        </div>




        <p id="editRestrictionNotice" style="text-align:center; margin-top:20px; font-size:0.85rem; color:rgba(255,255,255,0.4); display:none; font-weight:500;">
            <i class="fa-solid fa-circle-info" style="margin-right:6px;"></i> Editing is locked within 3 days of the event. You can still cancel.
        </p>
    </div>

    <!-- Edit Mode (Wrapped in Form Box) -->
    <div class="form-box" id="bookingEditContainer" style="display:none; max-width: 900px; margin: 0 auto;">
        <div id="bookingEditView">
            <h2 class="form-title" style="margin-bottom: 30px;">Edit Booking</h2>

            <div class="form-group">

                <label class="form-label">
                    Event Name:
                    <input type="text" id="eEventName" class="form-input" placeholder="Enter event name" />
                    <span class="error-message" id="eEventNameError">Please enter an event name</span>
                </label>

                <div class="form-row">
                    <label class="form-label">
                        Event Date:
                        <input type="text" id="eEventDate" class="form-input" placeholder="Select date" readonly />
                        <span class="error-message" id="eEventDateError">Please select a date</span>
                    </label>
                    <label class="form-label">
                        Event Time:
                        <input type="text" id="eEventTime" class="form-input" placeholder="Select time" readonly />
                        <span class="error-message" id="eEventTimeError">Please select a time</span>
                    </label>
                </div>


                <div class="form-row">
                    <label class="form-label">
                        Event Type:
                        <select id="eEventType" class="form-select">
                            <option value="wedding">Wedding</option>
                            <option value="birthday">Birthday Party</option>
                            <option value="corporate">Corporate Event</option>
                            <option value="anniversary">Anniversary</option>
                            <option value="graduation">Graduation</option>
                            <option value="reunion">Reunion</option>
                            <option value="teambuilding">Team Building</option>
                            <option value="holiday">Holiday Party</option>
                            <option value="other">Other</option>
                        </select>
                        <span class="error-message" id="eEventTypeError">Please select an event type</span>
                    </label>
                    <label class="form-label">
                        Number of Guests:
                        <select id="eNumGuests" class="form-select">
                            <option value="10">10 pax</option>
                            <option value="20">20 pax</option>
                            <option value="30">30 pax</option>
                            <option value="40">40 pax</option>
                            <option value="50">50 pax</option>
                            <option value="60">60 pax</option>
                            <option value="70">70 pax</option>
                            <option value="80">80 pax</option>
                            <option value="90">90 pax</option>
                            <option value="100">100 pax</option>
                        </select>
                        <span class="error-message" id="eNumGuestsError">Please select guest count</span>
                    </label>
                </div>

                <label class="form-label">
                    Location Address:
                    <div style="position:relative; display:flex; gap:10px; margin-top:10px;">
                        <input type="text" id="eAddress" class="form-input" style="flex:1; margin-top:0;" placeholder="Enter event address" />
                        <button type="button" class="map-select-btn" onclick="initLeafletMapForEdit()" title="Select on Map">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                    </div>
                    <span class="error-message" id="eAddressError">Please provide an address</span>
                </label>


                <label class="form-label">
                    Special Notes / Requests:
                    <textarea id="eNotes" class="form-textarea" rows="4" placeholder="Any special requests?"></textarea>
                </label>
            </div>

            
            <div style="text-align: center; margin-top: 30px; display: flex; flex-direction: column; gap: 10px; align-items: center;">
                <button class="submit-btn" style="width: 100%; max-width: 260px; padding: 12px; font-size: 0.9rem;" onclick="saveBookingEdits()">
                    <i class="fa-solid fa-floppy-disk" style="margin-right:8px;"></i>Save Changes
                </button>
                <button class="btn-cancel" style="width: 100%; max-width: 260px; padding: 12px; border-radius: 100px; font-weight: 700; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); cursor: pointer; font-size: 0.9rem;" onclick="toggleEditBooking(false)">
                    Cancel
                </button>
            </div>


        </div>
    </div>
  </div> <!-- End FS Body -->




<!-- LOGOUT CONFIRMATION MODAL -->
<div class="modal-overlay" id="logoutModal" style="z-index:10005;">
  <div class="modal" style="background:rgba(18,18,18,0.95); backdrop-filter:blur(15px); border:1px solid rgba(255,255,255,0.08); box-shadow:0 25px 50px rgba(0,0,0,0.5); width:90%; max-width:400px;">
    <div class="modal-head" style="border-bottom:1px solid rgba(255,255,255,0.05); padding:20px 25px;">
      <h3 style="font-family:'Aclonica',sans-serif; color:#fff; font-size:1.1rem;">Logout</h3>
      <button class="modal-close" onclick="closeLogout()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="logout-confirm-body" style="padding:40px 25px; text-align:center;">
      <div class="logout-confirm-icon" style="background:rgba(239,68,68,0.1); border:none; color:#ef4444; width:70px; height:70px; font-size:2rem; margin:0 auto 20px; border-radius:50%; display:grid; place-items:center;">
        <i class="fa-solid fa-right-from-bracket"></i>
      </div>
      <h3 style="color:#fff; margin-bottom:12px; font-size:1.4rem;">Leaving so soon?</h3>
      <p style="color:rgba(255,255,255,0.6); line-height:1.6;">Are you sure you want to log out of your account?</p>
    </div>
    <div class="modal-foot col" style="padding:0 25px 25px 25px; border:none; gap:12px; display:flex; flex-direction:column;">
      <button class="btn-logout-confirm btn-danger-gradient" onclick="doLogout()" style="padding:16px; border-radius:12px; width:100%; background:linear-gradient(135deg, #C22626, #8B0A1E); color:#fff; border:none; font-weight:700; cursor:pointer;">Yes, Log Me Out</button>
      <button class="btn-cancel" onclick="closeLogout()" style="padding:16px; border-radius:12px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); color:#fff; width:100%; font-weight:600; cursor:pointer;">No, Stay Logged In</button>
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
      <button class="btn-cancel" onclick="closeCancelBookingModal()" style="padding:16px; border-radius:12px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); color:#fff;">No, Keep it</button>
    </div>
  </div>
</div>













<!-- Toast Notification -->
<div id="toast" class="toast">
  <i class="fa-solid fa-circle-check"></i>
  <span id="toastMsg">Message here</span>
</div>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <!-- ══ CHAT OVERLAY ══ -->
    <div class="chat-overlay" id="chatOverlay">
        <div class="chat-window">
            <div class="chat-header">
                <div class="chat-header-info">
                    <div class="chat-avatar" id="chatAvatar">A</div>
                    <div>
                        <div class="chat-name" id="chatTargetName">Admin Support</div>
                        <div class="chat-status" id="chatTargetStatus">Online</div>
                    </div>
                </div>
                <button class="chat-close" onclick="closeChat()"><i class="fas fa-times"></i></button>
            </div>
            <div class="chat-messages" id="chatMessages">
                <!-- Messages here -->
            </div>
            <div class="chat-input-area">
                <textarea id="chatInput" placeholder="Type a message..." rows="1"></textarea>
                <button id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script src="http://localhost:3000/socket.io/socket.io.js"></script>
    <script src="account-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>