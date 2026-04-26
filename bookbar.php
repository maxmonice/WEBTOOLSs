<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Luke's Seafood Trading - Book Bar</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="bookbar.css">

    <style>
        /* ── AUTH GUARD MODAL ── */
        .auth-modal-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 5000;
            background: rgba(0,0,0,0.82);
            backdrop-filter: blur(8px);
            align-items: center; justify-content: center; padding: 24px;
        }
        .auth-modal-overlay.open {
            display: flex;
            animation: authFadeIn 0.22s ease;
        }
        @keyframes authFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .auth-modal {
            background: #222;
            border: 1px solid rgba(194,38,38,0.4);
            border-radius: 20px;
            width: 100%; max-width: 400px;
            overflow: hidden;
            animation: authSlideUp 0.25s cubic-bezier(.16,1,.3,1);
            position: relative;
        }
        /* Red top accent */
        .auth-modal::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(to right, #8B0A1E, #C22626);
            border-radius: 20px 20px 0 0;
        }
        @keyframes authSlideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .auth-modal-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(194,38,38,0.12);
            border: 1px solid rgba(194,38,38,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #C22626;
            margin: 36px auto 20px;
        }
        .auth-modal-body {
            text-align: center;
            padding: 0 30px 10px;
        }
        .auth-modal-body h3 {
            font-family: 'Aclonica', sans-serif;
            font-size: 1.25rem; color: #fff; margin-bottom: 10px;
        }
        .auth-modal-body p {
            font-size: 0.87rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.65;
        }
        .auth-modal-body p strong {
            color: rgba(255,255,255,0.75);
            font-weight: 700;
        }

        .auth-modal-foot {
            display: flex; flex-direction: column; gap: 10px;
            padding: 24px 28px 28px;
        }
        .auth-btn-signin {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #C22626, #8B0A1E);
            color: #fff; border: none; border-radius: 10px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 0.9rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.03em;
            transition: opacity 0.2s, box-shadow 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .auth-btn-signin:hover {
            opacity: 0.9;
            box-shadow: 0 6px 24px rgba(194,38,38,0.45);
        }
        .auth-btn-cancel {
            width: 100%; padding: 12px;
            background: transparent;
            color: rgba(255,255,255,0.45);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 0.88rem; font-weight: 600;
            cursor: pointer; transition: color 0.2s, border-color 0.2s;
        }
        .auth-btn-cancel:hover {
            color: #fff; border-color: rgba(255,255,255,0.3);
        }

        /* ── SUBMIT BUTTON LOCKED STATE ── */
        .submit-btn.locked {
            position: relative;
            cursor: pointer;
        }
        .submit-btn.locked::after {
            content: '\f023';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: 8px;
            font-size: 0.85em;
        }

        /* ── AUTH STATUS BANNER ── */
        .auth-status-bar {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 18px; border-radius: 10px;
            font-size: 0.82rem; font-weight: 600;
            margin-bottom: 24px; letter-spacing: 0.01em;
        }
        .auth-status-bar.signed-in {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.25);
            color: #86efac;
        }
        .auth-status-bar.signed-out {
            background: rgba(194,38,38,0.1);
            border: 1px solid rgba(194,38,38,0.3);
            color: #fca5a5;
        }
        .auth-status-bar i { font-size: 0.9rem; }
        .auth-status-bar a {
            color: #fff; font-weight: 700;
            text-decoration: underline; text-underline-offset: 2px;
            margin-left: 4px;
        }
        .auth-status-bar a:hover { opacity: 0.8; }
    </style>
</head>

<body>

    <!-- Grain overlay -->
    <div class="grain-overlay"></div>

    <!-- Header -->
    <header>
        <div class="container header-container">
            <div class="logo">Luke's Seafood Trading</div>
            <div class="menu-toggle" id="mobile-menu"><i class="fa-solid fa-bars"></i></div>
            <nav class="nav-menu" id="navMenu">
                <a href="index.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="bookBar.php" class="active">Book Bar</a>
                <a href="gallery.php">Gallery</a>
                <a href="aboutUs.php">About Us</a>
                                <a href="account-dashboard.php" class="nav-account-icon" title="Account">
                    <i class="fas fa-user-circle"></i>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <section class="hero-section">

            <!-- Banner -->
            <div class="banner-container">
                <img src="https://github.com/maxmonice/WEBTOOLS/blob/2d17035d2195524857c2d553525d08579e0f6373/images/sushibar.webp?raw=true" class="banner-top-image" alt="Sushi bar" />
                <img src="https://github.com/maxmonice/WEBTOOLS/blob/2d17035d2195524857c2d553525d08579e0f6373/images/bylukes2.webp?raw=true" class="banner-bottom-image" alt="By Lukes" />
            </div>

            <!-- Event Booking Form -->
            <div class="form-container">
                <div style="text-align: center; margin: 40px 0;">
                    <h1 style="font-family: 'Aclonica', sans-serif; font-size: 2.5rem; font-weight: bold;">
                        Event Booking Form
                    </h1>
                    <img src="https://github.com/maxmonice/WEBTOOLS/blob/2d17035d2195524857c2d553525d08579e0f6373/images/redline.webp?raw=true" style="width: 250px; height: auto; margin-top: 20px;" alt="redline" />
                </div>

                <div class="form-box">

                    <!-- Auth status banner (dynamically shown) -->
                    <div id="authStatusBar" class="auth-status-bar" style="display:none;"></div>

                    <form id="bookingForm">
                        <!-- Event Details -->
                        <h2 class="form-title">Event Details</h2>

                        <div class="form-group">
                            <label class="form-label">
                                Event Name:
                                <input type="text" class="form-input" id="eventName" required>
                            </label>

                            <label class="form-label">
                                Address:
                                <input type="text" class="form-input" id="address" required>
                            </label>

                            <div class="form-row">
                                <label class="form-label">
                                    Event Date:
                                    <input type="text" id="eventDate" class="form-input" placeholder="Select date" readonly required>
                                </label>
                                <label class="form-label">
                                    Event Time:
                                    <input type="text" id="eventTime" class="form-input" placeholder="Select time" readonly required>
                                </label>
                            </div>

                            <label class="form-label">
                                Event Type:
                                <select class="form-select" id="eventType" required>
                                    <option value="" disabled selected>Select event type</option>
                                    <option value="wedding">Wedding</option>
                                    <option value="birthday">Birthday Party</option>
                                    <option value="corporate">Corporate Event</option>
                                    <option value="anniversary">Anniversary</option>
                                    <option value="graduation">Graduation</option>
                                    <option value="reunion">Reunion</option>
                                    <option value="conference">Conference</option>
                                    <option value="seminar">Seminar/Workshop</option>
                                    <option value="teambuilding">Team Building</option>
                                    <option value="holiday">Holiday Party</option>
                                    <option value="other">Other (Type in the notes)</option>
                                </select>
                            </label>

                            <label class="form-label">
                                Number of Guests:
                                <select class="form-select" id="numGuests" required>
                                    <option value="" disabled selected>Select number of guests</option>
                                    <option value="1-10">1-10 pax</option>
                                    <option value="11-20">11-20 pax</option>
                                    <option value="21-30">21-30 pax</option>
                                    <option value="31-40">31-40 pax</option>
                                    <option value="41-50">41-50 pax</option>
                                    <option value="51-60">51-60 pax</option>
                                    <option value="61-70">61-70 pax</option>
                                    <option value="71-80">71-80 pax</option>
                                    <option value="81-90">81-90 pax</option>
                                    <option value="91-100">91-100 pax</option>
                                </select>
                            </label>
                        </div>

                        <!-- Contact Details -->
                        <h2 class="form-title" style="margin-top: 40px;">Contact Details</h2>

                        <div class="form-group">
                            <label class="form-label">
                                Full Name:
                                <input type="text" class="form-input" id="fullName" required>
                            </label>

                            <div class="form-row">
                                <label class="form-label">
                                    Contact Number:
                                    <input type="tel" class="form-input" id="contactNumber" placeholder="09XX XXX XXXX" maxlength="11" required>
                                    <span class="error-message" id="phoneError"></span>
                                </label>
                                <label class="form-label">
                                    Email Address:
                                    <input type="email" class="form-input" id="emailAddress" placeholder="example@gmail.com" required>
                                    <span class="error-message" id="emailError"></span>
                                </label>
                            </div>

                            <label class="form-label">
                                Notes / Request:
                                <textarea rows="4" class="form-textarea" id="notes"></textarea>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div style="text-align: center;">
                            <button type="submit" class="submit-btn" id="submitBtn">Submit</button>
                        </div>
                    </form>
                </div>
            </div>

        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid desktop-view">
                <div class="footer-col">
                    <h4>Socials</h4>
                    <a href="https://facebook.com/lukeseafoodtrading" target="_blank" class="social-item">
                        <i class="fab fa-facebook"></i> Luke's Seafood Taguig
                    </a>
                    <a href="https://www.instagram.com/luke_seafoods/" target="_blank" class="social-item">
                        <i class="fab fa-instagram"></i> luke_seafoods
                    </a>
                </div>
                <div class="footer-col">
                    <h4>About Us</h4>
                    <p>At Luke's Seafood Trading, we specialize in sourcing and delivering the freshest, highest-quality seafood from ocean to market.</p>
                </div>
                <div class="footer-col">
                    <h4>Location</h4>
                    <a href="https://maps.google.com/?q=Vulcan+St+cor+C5+Road+Taguig" target="_blank" class="social-item location-text">
                        <i class="fa-solid fa-location-pin"></i>
                        <span>vulcan st. cor c5 road, Taguig, Philippines</span>
                    </a>
                </div>
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <a href="mailto:lukeseafoods28@gmail.com" class="social-item">
                        <i class="fas fa-envelope"></i> lukeseafoods28@gmail.com
                    </a>
                    <a href="tel:09392999912" class="social-item">
                        <i class="fa-solid fa-phone"></i> 09392999912
                    </a>
                </div>
            </div>

            <div class="mobile-footer-view">
                <p class="mobile-info">
                    <a href="https://www.instagram.com/luke_seafoods/" target="_blank"><i class="fab fa-instagram"></i> luke_seafoods</a>
                    <span class="pipe">|</span>
                    <a href="mailto:lukeseafoods28@gmail.com"><i class="fas fa-envelope"></i> lukeseafoods28@gmail.com</a>
                    <span class="pipe">|</span>
                    <a href="https://facebook.com/lukeseafoodtrading" target="_blank"><i class="fab fa-facebook"></i> Luke's Seafood Taguig</a>
                    <span class="pipe">|</span>
                    <span class="mobile-hours"><i class="fas fa-clock"></i> 9:00 AM TO 8:00 PM</span>
                    <span class="pipe">|</span>
                    <a href="https://maps.google.com/?q=Vulcan+St+cor+C5+Road+Taguig" target="_blank"><i class="fa-solid fa-location-pin"></i> VULCAN ST. COR C5 ROAD, TAGUIG</a>
                    <span class="pipe">|</span>
                    <a href="tel:09392999912"><i class="fa-solid fa-phone"></i> 09392999912</a>
                </p>
                <p class="mobile-menu">About Us: At Luke's Seafood Trading, we specialize in sourcing and delivering the freshest, highest-quality seafood from ocean to market.</p>
                <p class="mobile-copyright">© 2025 Luke's Seafood Trading</p>
            </div>

            <div class="copyright desktop-view">© 2025 Luke's Seafood Trading | All Rights Reserved</div>
        </div>
    </footer>

    <!-- ══ AUTH GUARD MODAL ══ -->
    <div class="auth-modal-overlay" id="authModal">
        <div class="auth-modal">
            <div class="auth-modal-icon">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div class="auth-modal-body">
                <h3>Sign In Required</h3>
                <p>You need to be signed in to submit a booking.<br>
                <strong>Please log in to your account</strong> to continue.</p>
            </div>
            <div class="auth-modal-foot">
                <button class="auth-btn-signin" onclick="goToSignIn()">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In to My Account
                </button>
                <button class="auth-btn-cancel" onclick="closeAuthModal()">Maybe Later</button>
            </div>
        </div>
    </div>

    <!-- Flatpickr JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- ══ AUTH GUARD SCRIPT — must load BEFORE bookbar.js ══ -->
    <script>
        // Track login state
        window.__isLoggedIn = false;

        // Check session from PHP on page load
        (async function checkAuth() {
            try {
                const res = await fetch('Auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'check_session' })
                });
                const data = await res.json();
                window.__isLoggedIn = !!data.success;

                
                if (data.success) {
                    // Sync latest session data locally
                    sessionStorage.setItem('user_name',  data.name  || '');
                    sessionStorage.setItem('user_email', data.email || '');
                    sessionStorage.setItem('is_admin', data.is_admin ? '1' : '0');
                }
            } catch (e) {
                window.__isLoggedIn = false;
            }

            renderAuthUI();
            const adminBtn = document.getElementById('adminPortalBtn');
            if (adminBtn) {
                adminBtn.style.display = (window.__isLoggedIn && sessionStorage.getItem('is_admin') === '1') ? 'inline-flex' : 'none';
            }
        })();

        function renderAuthUI() {
            const bar    = document.getElementById('authStatusBar');
            const btn    = document.getElementById('submitBtn');

            if (window.__isLoggedIn) {
                const name = sessionStorage.getItem('user_name') || 'User';
                bar.className  = 'auth-status-bar signed-in';
                bar.innerHTML  = `<i class="fa-solid fa-circle-check"></i> Signed in as <strong style="margin-left:4px;color:#fff;">${name}</strong>`;
                bar.style.display = 'flex';
                btn.classList.remove('locked');
            } else {
                bar.className  = 'auth-status-bar signed-out';
                bar.innerHTML  = `<i class="fa-solid fa-triangle-exclamation"></i> You're not signed in — you must <a href="account.php">log in</a> to submit a booking.`;
                bar.style.display = 'flex';
                btn.classList.add('locked');
            }
        }

        // Capture-phase listener — fires BEFORE bookbar.js submit handler
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('bookingForm');

            form.addEventListener('submit', function (e) {
                if (!window.__isLoggedIn) {
                    // Stop everything — bookbar.js never sees this event
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    openAuthModal();
                }
            }, true); // ← capture: true is the key
        });

        // Modal controls
        function openAuthModal() {
            document.getElementById('authModal').classList.add('open');
        }
        function closeAuthModal() {
            document.getElementById('authModal').classList.remove('open');
        }
        function goToSignIn() {
            // Store intended destination so account page can redirect back
            sessionStorage.setItem('redirect_after_login', 'bookbar.php');
            window.location.href = 'account.php';
        }

        // Close modal on overlay click
        document.getElementById('authModal').addEventListener('click', function (e) {
            if (e.target === this) closeAuthModal();
        });

        // Mobile nav toggle
        document.getElementById('mobile-menu').addEventListener('click', function () {
            document.getElementById('navMenu').classList.toggle('active');
        });
    </script>

    <!-- bookbar.js loads AFTER the capture listener is registered -->
    <script src="bookbar.js"></script>
</body>
</html>