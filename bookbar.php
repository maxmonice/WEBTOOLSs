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
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <link rel="stylesheet" href="bookbar.css">

    <script>
        window.LOCATIONIQ_TOKEN = '<?php 
            require_once __DIR__ . "/env-bootstrap.php";
            webtools_load_env(__DIR__);
            echo getenv("LOCATIONIQ_TOKEN") ?: ""; 
        ?>';
    </script>
    <!-- LocationIQ Geocoder -->
    <link rel="stylesheet" href="https://tiles.locationiq.com/v3/libs/leaflet-geocoder/1.9.6/leaflet-geocoder-locationiq.min.css">
    <script src="https://tiles.locationiq.com/v3/libs/leaflet-geocoder/1.9.6/leaflet-geocoder-locationiq.min.js"></script>
    <!-- Leaflet Routing Machine -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>



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
                <a href="account.php" class="nav-account-icon" title="Account">
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

                    <!-- Help Icon -->
                    <div class="form-help-icon" id="formHelpIcon" title="Booking Info">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>

                    <form id="bookingForm">
                        <!-- Event Details -->
                        <h2 class="form-title">Event Details</h2>

                        <div class="form-group">
                            <label class="form-label">
                                Event Name:
                                <input type="text" class="form-input" id="eventName" placeholder="Enter event name" required>
                                <span class="error-message" id="eventNameError">Event name is required</span>
                            </label>

                            <label class="form-label">
                                Address:
                                <div style="position:relative; display:flex; gap:10px; margin-top:10px;">
                                    <input type="text" class="form-input" id="address" style="flex:1; margin-top:0;" placeholder="Enter address or select on map" required>
                                    <button type="button" class="map-select-btn" onclick="initLeafletMap()" title="Select on Map">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>
                                </div>
                                <span class="error-message" id="addressError">Please provide or select an address</span>
                            </label>



                            <div class="form-row">
                                <label class="form-label">
                                    Event Date:
                                    <input type="text" id="eventDate" class="form-input" placeholder="Select date" readonly required>
                                    <span class="error-message" id="eventDateError">Please select a date</span>
                                </label>
                                <label class="form-label">
                                    Event Time:
                                    <input type="text" id="eventTime" class="form-input" placeholder="Select time" readonly required>
                                    <span class="error-message" id="eventTimeError">Please select a time</span>
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
                                    <option value="other">Other</option>
                                </select>
                                <span class="error-message" id="eventTypeError">Please select an event type</span>
                            </label>

                            <label class="form-label">
                                Number of Guests:
                                <select class="form-select" id="numGuests" required>
                                    <option value="" disabled selected>Select number of guests</option>
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
                                <span class="error-message" id="numGuestsError">Please select the number of guests</span>
                            </label>




                        </div>

                        <!-- Contact Details -->
                        <h2 class="form-title" style="margin-top: 40px;">Contact Details</h2>

                        <div class="form-group">
                            <label class="form-label">
                                Full Name:
                                <input type="text" class="form-input" id="fullName" placeholder="Enter your full name" required>
                                <span class="error-message" id="fullNameError">Full name is required</span>
                            </label>

                            <div class="form-row">
                                <label class="form-label">
                                    Contact Number:
                                    <input type="text" class="form-input" id="contactNumber" placeholder="09XX XXX XXXX" required>
                                    <span class="error-message" id="contactNumberError">Valid 11-digit mobile number required</span>
                                </label>
                                <label class="form-label">
                                    Email Address:
                                    <input type="email" class="form-input" id="emailAddress" placeholder="example@email.com" required>
                                    <span class="error-message" id="emailAddressError">Valid email address required</span>
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

    <!-- ══ INFO MODAL ══ -->
    <div class="info-modal-overlay" id="infoModal">
        <div class="info-modal">
            <div class="info-modal-header">
                <i class="fa-solid fa-circle-info"></i>
                <h3>WE CUSTOMIZE ACCORDING TO YOUR PREFERENCE AND BUDGET</h3>
            </div>
            <div class="info-modal-body">
                <p>After booking, our team will call you to confirm your preferences, total budget, and booking details. If you have any questions before booking, please contact us at <strong>09392999912</strong>.</p>
            </div>
            <div class="info-modal-foot">
                <button class="info-btn-okay" onclick="closeInfoModal()">Okay, I Understand</button>
            </div>
        </div>
    </div>

    <!-- Flatpickr & Leaflet JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


    <!-- ══ AUTH GUARD SCRIPT — must load BEFORE bookbar.js ══ -->
    <script src="bookbar-auth.js?v=<?= time() ?>"></script>

    <!-- bookbar.js loads AFTER the capture listener is registered -->
    <script src="bookbar.js"></script>
<!-- DUPLICATE BOOKING WARNING MODAL -->
<div class="modal-overlay" id="duplicateBookingModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:20000; backdrop-filter:blur(10px); align-items:center; justify-content:center;">
    <div class="modal-content" style="background:#111; border:1px solid rgba(255,255,255,0.08); border-radius:24px; padding:40px; max-width:450px; text-align:center; box-shadow:0 25px 50px rgba(0,0,0,0.5);">
        <div style="width:80px; height:80px; background:rgba(245,158,11,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#f59e0b; margin:0 auto 25px; font-size:2.5rem;">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <h2 style="font-family:'Aclonica',sans-serif; color:#fff; font-size:1.5rem; margin-bottom:15px;">Active Booking Found</h2>
        <p style="color:rgba(255,255,255,0.6); line-height:1.6; margin-bottom:30px;">It looks like you already have an active event booking. Would you like to proceed with another one?</p>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <button onclick="continueBooking()" style="background:linear-gradient(90deg, #9B0A1E 0%, #BE2225 40%, #C22626 100%); color:#fff; border:none; padding:16px; border-radius:12px; font-weight:700; cursor:pointer; font-size:1rem; transition: transform 0.2s;">Book Anyway</button>
            <button onclick="closeDuplicateModal()" style="background:rgba(255,255,255,0.03); color:#fff; border:1px solid rgba(255,255,255,0.08); padding:16px; border-radius:12px; font-weight:700; cursor:pointer; font-size:1rem;">Cancel</button>
        </div>
    </div>
</div>