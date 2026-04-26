<!DOCTYPE html>
<html lang="en">
<head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse the full menu at Luke's Seafood Trading — sushi, maki, bento boxes, platters and more.">
    <title>Luke's Seafood - Menu</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="menu.css">
    <link rel="stylesheet" href="carT.css">
    <style>
        /* Auth Status Banner */
        .auth-status-bar {
            display: flex; 
            align-items: center; 
            gap: 10px;
            padding: 12px 20px; 
            border-radius: 8px;
            font-size: 0.85rem; 
            font-weight: 600;
            letter-spacing: 0.01em;
            margin: 0 0 20px 0;
            max-width: 100%;
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
            color: #fff; 
            font-weight: 700;
            text-decoration: underline; 
            text-underline-offset: 2px;
            margin-left: 4px;
        }
        .auth-status-bar a:hover { opacity: 0.8; }
        
        .cart-btn.locked {
            opacity: 0.5;
            pointer-events: none;
            filter: grayscale(100%);
        }
        
        .cart-auth-notice {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .cart-auth-notice.signed-out {
            background: rgba(194,38,38,0.1);
            border: 1px solid rgba(194,38,38,0.3);
            color: #fca5a5;
        }
        .cart-auth-notice a {
            color: #fff;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
            margin-left: 4px;
        }
        .cart-auth-notice a:hover { opacity: 0.8; }
        
        /* Balanced banner size fix - override original menu.css */
        .menu-section .hero-banner {
            height: 120px !important;
            margin-bottom: 25px !important;
            max-height: 180px !important;
        }
        .menu-section .hero-banner .banner-image-placeholder {
            height: 120px !important;
            max-height: 180px !important;
        }
        .menu-section .hero-banner .banner-text {
            font-size: 1.4rem !important;
        }
        
        @media (max-width: 768px) {
            .cart-auth-notice {
                font-size: 0.8rem;
                padding: 10px 12px;
            }
            .menu-section .hero-banner {
                height: 80px !important;
                margin-bottom: 20px !important;
                max-height: 120px !important;
            }
            .menu-section .hero-banner .banner-image-placeholder {
                height: 80px !important;
                max-height: 120px !important;
            }
            .menu-section .hero-banner .banner-text {
                font-size: 1.2rem !important;
            }
        }
    </style>
</head>
<body>

    <!-- Grain overlay -->
    <div class="grain-overlay"></div>

    <header>
        <div class="container header-container">
            <div class="logo">Luke's Seafood Trading</div>
            <div class="menu-toggle" id="mobile-menu"><i class="fa-solid fa-bars"></i></div>
            <nav class="nav-menu" id="navMenu">
                <a href="index.php">Home</a>
                <a href="menu.php" class="active">Menu</a>
                <a href="bookbar.php">Book Bar</a>
                <a href="gallery.php">Gallery</a>
                <a href="aboutUs.php">About Us</a>
                                <a href="account-dashboard.php" class="nav-account-icon" title="Account">
                    <i class="fas fa-user-circle"></i>
                </a>
            </nav>
        </div>
    </header>

    <main class="content-wrapper">
        <aside class="left-sidebar" role="navigation" aria-label="Menu Categories">
            <div class="sidebar-item" data-target="salad-section">Salad</div>
            <div class="sidebar-item" data-target="fusion-section">Fusion</div>
            <div class="sidebar-item" data-target="a-la-carte-section">A La Carte</div>
            <div class="sidebar-item" data-target="platters-section">Platters</div>
            <div class="sidebar-item" data-target="bento-section">Bento</div>
        </aside>

        <section class="menu-section">
            <div class="hero-banner" role="banner">
                <div class="banner-image-placeholder" aria-label="Decorative image showing various sushi dishes"></div>
                <div class="banner-text">Excellence Served Daily</div>
            </div>

            <h1 class="main-menu-heading">Menu</h1>

            <!-- ── SEARCH BAR ── -->
            <div class="search-wrapper">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="menuSearch" class="search-input" placeholder="Search">
                    <button class="search-clear" id="searchClear" style="display:none;">×</button>
                </div>
                <div class="search-dropdown" id="searchDropdown"></div>
            </div>

            <!-- ── SALAD ── -->
            <h2 class="category-heading" id="salad-section">Salad</h2>
            <div class="menu-grid single-column"> 
                <div class="menu-item" data-item='{"name":"Kani Mango Salad","price":"₱175","image":"images/kani.webp","variations":["150 Grams","300 Grams"]}'>
                    <img src="images/kani.webp" alt="Kani Mango Salad" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Kani Mango Salad</div>
                        <div class="item-price">₱175</div>
                    </div>
                    <div class="item-rating" aria-label="Five stars">★★★★★</div>
                </div>
            </div>

            <!-- ── FUSION ROLLS & SUSHI ── -->
            <h2 class="category-heading" id="fusion-section">Fusion Rolls & Sushi</h2>
            <div class="menu-grid">
                <div class="menu-item" data-item='{"name":"California Maki","price":"₱169","image":"images/california.webp","variations":["8 Pieces","16 Pieces","24 Pieces","50 Pieces"]}'>
                    <img src="images/california.webp" alt="California Maki" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">California Maki</div>
                        <div class="item-price">₱169</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Crazy Maki","price":"₱195","pieces":"8 Pcs","image":"images/crazy.webp"}'>
                    <img src="images/crazy.webp" alt="Crazy Maki" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Crazy Maki</div>
                        <div class="item-price">₱195</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Mango Roll","price":"₱195","pieces":"8 Pcs","image":"images/mango.webp"}'>
                    <img src="images/mango.webp" alt="Mango Roll" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Mango Roll</div>
                        <div class="item-price">₱195</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Spicy Salmon","price":"₱195","pieces":"8 Pcs","image":"images/spicysalmon.webp"}'>
                    <img src="images/spicysalmon.webp" alt="Spicy Salmon Roll" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Spicy Salmon</div>
                        <div class="item-price">₱195</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Ebi Tempura Maki","price":"₱150","pieces":"8 Pcs","image":"images/ebi.webp"}'>
                    <img src="images/ebi.webp" alt="Ebi Tempura Maki" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Ebi Tempura Maki</div>
                        <div class="item-price">₱150</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Avocado Dragon Roll","price":"₱312","pieces":"8 Pcs","image":"images/avocado.webp"}'>
                    <img src="images/avocado.webp" alt="Avocado Dragon Roll" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Avocado Dragon Roll</div>
                        <div class="item-price">₱312</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Ebi Sushi","price":"₱208","pieces":"4 Pcs","image":"images/ebisushi.webp"}'>
                    <img src="images/ebisushi.webp" alt="Ebi Sushi" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Ebi Sushi</div>
                        <div class="item-price">₱208</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Tamago Sushi","price":"₱169","pieces":"6 Pcs","image":"images/tamago.webp"}'>
                    <img src="images/tamago.webp" alt="Tamago Sushi" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Tamago Sushi</div>
                        <div class="item-price">₱169</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
            </div>

            <!-- ── A LA CARTE ── -->
            <h2 class="category-heading" id="a-la-carte-section">A La Carte</h2>
            <div class="menu-grid single-column">
                <div class="menu-item" data-item='{"name":"Tempura","price":"₱221","image":"images/tempura.webp","variations":["150 Grams","300 Grams"]}'>
                    <img src="images/tempura.webp" alt="Tempura" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Tempura</div>
                        <div class="item-price">₱221</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
            </div>

            <!-- ── PLATTERS ── -->
            <h2 class="category-heading" id="platters-section">Platters</h2>
            <div class="menu-grid">
                <div class="menu-item" data-item='{"name":"Square Platter A","price":"₱539.50","pieces":"14 Pcs","image":"images/a.webp"}'>
                    <img src="images/a.webp" alt="Square Platter A" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Square Platter A</div>
                        <div class="item-price">₱539.50</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Square Platter B","price":"₱487.50","pieces":"18 Pcs","image":"images/b.webp"}'>
                    <img src="images/b.webp" alt="Square Platter B" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Square Platter B</div>
                        <div class="item-price">₱487.50</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Sushi Boat A","price":"₱1,558.70","pieces":"58 Pcs","image":"images/boata.webp"}'>
                    <img src="images/boata.webp" alt="Sushi Boat A" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Sushi Boat A</div>
                        <div class="item-price">₱1,558.70</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Sushi Boat B","price":"₱2,078.70","pieces":"57 Pcs","image":"images/boatb.webp"}'>
                    <img src="images/boatb.webp" alt="Sushi Boat B" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Sushi Boat B</div>
                        <div class="item-price">₱2,078.70</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Tempura Boat","price":"₱1,168.70","pieces":"20 Pcs","image":"images/tempuraboat.webp"}'>
                    <img src="images/tempuraboat.webp" alt="Tempura Boat" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Tempura Boat</div>
                        <div class="item-price">₱1,168.70</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Cali Maki","price":"₱312","image":"images/calimaki.png","variations":["16 Pieces","24 Pieces","50 Pieces"]}'>
                    <img src="images/calimaki.png" alt="Cali Maki Platter" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Cali Maki</div>
                        <div class="item-price">₱312</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Mixed Maki Platter","price":"₱1,168.70","pieces":"48 Pcs","image":"images/mixedmaki.webp"}'>
                    <img src="images/mixedmaki.webp" alt="Mixed Maki Platter" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Mixed Maki Platter</div>
                        <div class="item-price">₱1,168.70</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
            </div>

            <!-- ── BENTO BOXES ── -->
            <h2 class="category-heading" id="bento-section">Bento Boxes</h2>
            <div class="menu-grid three-columns">
                <div class="menu-item" data-item='{"name":"Tuna Steak in Oyster Sauce","price":"₱379","image":"images/tunasteak.webp"}'>
                    <img src="images/tunasteak.webp" alt="Tuna Steak in Oyster Sauce Bento" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Tuna Steak in Oyster Sauce</div>
                        <div class="item-price">₱379</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Garlic Buttered Salmon","price":"₱239","image":"images/garlic.webp"}'>
                    <img src="images/garlic.webp" alt="Garlic Buttered Salmon Bento" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Garlic Buttered Salmon</div>
                        <div class="item-price">₱239</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Combo E","price":"₱125","image":"images/e.webp"}'>
                    <img src="images/e.webp" alt="Combo E Bento Box" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Combo E</div>
                        <div class="item-price">₱125</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Combo K","price":"₱199","image":"images/k.webp"}'>
                    <img src="images/k.webp" alt="Combo K Bento Box" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Combo K</div>
                        <div class="item-price">₱199</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Combo L","price":"₱269","image":"images/l.webp"}'>
                    <img src="images/l.webp" alt="Combo L Bento Box" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Combo L</div>
                        <div class="item-price">₱269</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
                <div class="menu-item" data-item='{"name":"Combo U","price":"₱245","image":"images/u.webp"}'>
                    <img src="images/u.webp" alt="Combo U Bento Box" class="item-image-placeholder">
                    <div class="item-details">
                        <div class="item-name">Combo U</div>
                        <div class="item-price">₱245</div>
                    </div>
                    <div class="item-rating">★★★★★</div>
                </div>
            </div>
        </section>
    </main>

    <!-- Item Modal Popup -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <button class="modal-close" id="modalClose">&#x2715;</button>
            <img src="" alt="" class="modal-image" id="modalImage">
            <div class="modal-body">

                <div class="modal-top-row">
                    <div class="modal-name-block">
                        <h2 class="modal-title" id="modalTitle"></h2>
                        <div class="modal-price" id="modalPrice"></div>
                    </div>
                    <div class="modal-variation-block" id="variationSection" style="display:none;">
                        <span class="modal-variation-label" id="variationLabel">Available Variation:</span>
                        <div class="modal-variation-info" id="variationInfo"></div>
                        <div class="modal-dropdown-wrapper">
                            <button class="modal-dropdown-btn" id="dropdownBtn">
                                Select a variation <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="modal-dropdown-list" id="dropdownList"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-rating-row">
                    <span class="modal-ratings-label">Ratings:</span>
                    <span class="modal-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                </div>

                <div class="modal-actions-row">
                    <div class="modal-qty-controls">
                        <button class="modal-qty-btn" id="decreaseQty">
                            <i class="fa-solid fa-circle-minus" style="color: rgb(255, 255, 255);"></i>
                        </button>
                        <span class="modal-qty-value" id="quantityValue">1</span>
                        <button class="modal-qty-btn" id="increaseQty">
                            <i class="fa-solid fa-circle-plus" style="color: rgb(255, 255, 255);"></i>
                        </button>
                    </div>
                    <button class="modal-cart-icon-btn" id="addToCartBtn" title="Add to Cart">
                        <i class="fa-solid fa-cart-arrow-down" style="color: rgb(255, 255, 255);"></i>
                    </button>
                    <button class="modal-order-btn" id="orderNowBtn">Order Now</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Cart Button - Links to separate Cart.html -->
    <div class="cart-btn" id="cartBtn" role="button" tabindex="0">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-count" id="cartCount">0</span>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid desktop-view">
                <div class="footer-col">
                    <h4>Socials</h4>
                    <a href="https://www.facebook.com/lukeseafoodtrading" target="_blank" class="social-item">
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
                    <a href="https://www.facebook.com/lukeseafoodtrading" target="_blank"><i class="fab fa-facebook"></i> Luke's Seafood Taguig</a>
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

    <!-- ══ CART OVERLAY ══ -->
    <div class="cart-overlay" id="cartOverlay">
        <div class="cart-panel">
            <div class="cart-left">
                <button type="button" class="cart-back-btn" id="cartBackBtn">
                    <i class="fas fa-chevron-left"></i> Back to menu
                </button>
                <div class="cart-divider"></div>
                <div class="cart-heading">
                    Shopping cart
                    <span class="cart-subheading" id="cartSubheading">You have 0 items in your cart</span>
                </div>
                <div class="cart-items-list" id="cartItemsList"></div>
                <div class="cart-empty" id="cartEmpty">
                    <i class="fas fa-shopping-basket"></i>
                    <p>Your cart is empty</p>
                    <small>Add some items from the menu!</small>
                </div>
            </div>

            <div class="cart-right">
                <!-- Auth Status Notification for Checkout -->
                <div id="cartAuthNotice" class="cart-auth-notice" style="display:none;"></div>
                
                <div class="cart-right-section">
                    <h3 class="cart-right-title">Address</h3>
                    <input type="text" class="cart-input" placeholder="Street, Barangay, and City" id="cartAddress">
                </div>
                <div class="cart-right-section">
                    <h3 class="cart-right-title">Payment Details</h3>
                    <p class="cart-label">Type of payment</p>
                    <div class="payment-methods">
                        <button class="payment-method-btn active" data-method="mastercard">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/1280px-Mastercard-logo.svg.png" alt="MasterCard">
                        </button>
                        <button class="payment-method-btn" data-method="cod">
                            <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/cashondeliveryLogo.png" alt="COD">
                        </button>
                        <button class="payment-method-btn" data-method="gcash">
                            <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/gcashLogo.png" alt="GCash">
                        </button>
                    </div>
                    <div id="cardFields">
                        <p class="cart-label" style="margin-top:14px;">Name on card</p>
                        <input type="text" class="cart-input dark" placeholder="Name" id="cardName">
                        <p class="cart-label" style="margin-top:12px;">Card Number</p>
                        <input type="text" class="cart-input dark" placeholder="1111 2222 3333 4444" id="cardNumber" maxlength="19">
                        <div class="card-row">
                            <div>
                                <p class="cart-label">Expiration date</p>
                                <input type="text" class="cart-input dark" placeholder="mm/yy" id="cardExpiry" maxlength="5">
                            </div>
                            <div>
                                <p class="cart-label">CVV</p>
                                 <input type="text" class="cart-input dark" placeholder="123" id="cardCvv" maxlength="3">
                             </div>
                         </div>
                     </div>
                    <div id="gcashFields" style="display:none;">
                        <p class="cart-label" style="margin-top:14px;">Send to this number:</p>
                        <input type="text" class="cart-input dark" value="0966 173 8269" readonly style="background:rgba(255,255,255,0.08);">
                        <div class="gcash-qr-section" style="margin-top:14px; text-align:center;">
                            <p class="cart-label">Scan to pay</p>
                            <div style="background:white; padding:10px; border-radius:8px; display:inline-block;">
                                <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/gcashQR.png" alt="GCash QR" style="width:120px; height:120px;">
                            </div>
                        </div>
                        <p class="cart-label" style="margin-top:14px;">GCash Reference Number</p>
                        <input type="text" class="cart-input dark" placeholder="09XX XXX XXXX" id="gcashNumber" maxlength="13">
                        <p class="cart-label" style="margin-top:12px;">Account Name</p>
                        <input type="text" class="cart-input dark" placeholder="Name on GCash" id="gcashName">
                    </div>
                </div>
                <div class="cart-totals">
                    <div class="cart-total-row">
                        <span>Subtotal</span>
                        <span id="cartSubtotal">₱0</span>
                    </div>
                    <div class="cart-total-row">
                        <span>Shipping</span>
                        <span>₱50</span>
                    </div>
                    <div class="cart-total-row total">
                        <span>Total (Tax incl.)</span>
                        <span id="cartTotal">₱50</span>
                    </div>
                </div>
                <button class="checkout-btn" id="checkoutBtn">
                    <span id="checkoutTotal">₱50</span>
                    <span>Checkout <i class="fas fa-arrow-right"></i><i class="fas fa-lock lock-icon"></i></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ══ AUTH GUARD MODAL ══ -->
    <div class="auth-modal-overlay" id="authModal">
        <div class="auth-modal">
            <div class="auth-modal-icon"><i class="fa-solid fa-lock"></i></div>
            <div class="auth-modal-body">
                <h3>Sign In Required</h3>
                <p>You need to be signed in to place an order.<br>
                <strong>Please log in to your account</strong> to continue.</p>
            </div>
            <div class="auth-modal-foot">
                <button class="auth-btn-signin" onclick="goToSignIn()">
              `      <i class="fa-solid fa-right-to-bracket"></i> Sign In to My Account
                </button>
                <button class="auth-btn-cancel" onclick="closeAuthModal()">Maybe Later</button>
            </div>
        </div>
    </div>


    <script src="carT.js"></script>
    <script src="menu.js"></script>
    <script>
        
        // Auth status notification logic - only show in cart when trying to checkout
        (async function renderCartAuthNotice() {
            const notice = document.getElementById('cartAuthNotice');
            const checkoutBtn = document.getElementById('checkoutBtn');

            // Check login status when page loads and when checkout is clicked
            async function checkLoginStatus() {
                try {
                    const res = await fetch('Auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ action: 'check_session' })
                    });
                    const data = await res.json();
                    window.__isLoggedIn = data.success;
                    return data.success;
                } catch (e) {
                    window.__isLoggedIn = false;
                    return false;
                }
            }

            // Initial check
            await checkLoginStatus();

            // Show notice only when user tries to checkout but isn't logged in
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', async function(e) {
                    const isLoggedIn = await checkLoginStatus();
                    
                    if (!isLoggedIn) {
                        e.preventDefault();
                        notice.className = 'cart-auth-notice signed-out';
                        notice.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> You must <a href="account.php">log in</a> to place orders.`;
                        notice.style.display = 'flex';
                        
                        // Hide notice after 5 seconds
                        setTimeout(() => {
                            notice.style.display = 'none';
                        }, 5000);
                    }
                });
            }
        })();
    </script>
</body>
</html>