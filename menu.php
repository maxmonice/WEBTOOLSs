<?php
require_once __DIR__ . '/menuDb.php';
$pdo = getDBConnection();

// Fetch categories for sidebar
$categories = $pdo->query("SELECT * FROM categories ORDER BY display_order ASC")->fetchAll();

// Fetch menu items
$stmt = $pdo->prepare("
    SELECT m.*, c.slug as category_slug 
    FROM menu_items m 
    LEFT JOIN categories c ON m.category_id = c.id 
    WHERE m.is_available = 1 
    ORDER BY c.display_order ASC, m.name ASC
");
$stmt->execute();
$allItems = $stmt->fetchAll();

// Group items by category
$menuByCategory = [];
foreach ($allItems as $item) {
    $menuByCategory[$item['category_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse the full menu at Luke's Seafood Trading — sushi, maki, bento boxes, platters and more.">
    <title>Luke's Seafood - Menu</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="menu.css">
    <link rel="stylesheet" href="carT.css">
</head>
<body>

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
                <a href="account.php" class="nav-account-icon" id="navAccountIcon" title="Account">
                    <i class="fas fa-user-circle"></i>
                </a>
                <div class="order-speech-bubble" id="orderSpeechBubble" onclick="window.location.href='account.php'">Track your order here</div>
            </nav>
        </div>
    </header>

    <main class="content-wrapper">
        <!-- Dynamic Sidebar -->
        <aside class="left-sidebar" role="navigation" aria-label="Menu Categories">
            <?php foreach($categories as $cat): ?>
                <div class="sidebar-item" data-target="<?= htmlspecialchars($cat['slug']) ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </div>
            <?php endforeach; ?>
        </aside>

        <section class="menu-section">
            <div class="hero-banner" role="banner">
                <div class="banner-image-placeholder" aria-label="Decorative image showing various sushi dishes"></div>
                <div class="banner-text">Excellence Served Daily</div>
            </div>

            <h1 class="main-menu-heading">Menu</h1>

            <div class="search-wrapper">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="menuSearch" class="search-input" placeholder="Search">
                    <button class="search-clear" id="searchClear" style="display:none;">×</button>
                </div>
                <div class="search-dropdown" id="searchDropdown"></div>
            </div>

            <!-- Dynamic Menu Grids -->
            <?php foreach($categories as $cat): 
                $items = $menuByCategory[$cat['id']] ?? [];
                if (empty($items)) continue;
                
                $gridClass = count($items) === 1 ? 'single-column' : (count($items) <= 3 ? 'three-columns' : '');
            ?>
                <h2 class="category-heading" id="<?= htmlspecialchars($cat['slug']) ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </h2>
                
                <div class="menu-grid <?= $gridClass ?>">
                    <?php foreach($items as $item): 
                        // Build EXACT JSON format your existing menu.js expects
                        $data = [
                            'name' => $item['name'],
                            'price' => '₱' . number_format($item['price'], 2),
                            'image' => $item['image_path']
                        ];
                        if ($item['pieces']) $data['pieces'] = $item['pieces'];
                        if ($item['variations']) $data['variations'] = json_decode($item['variations'], true);
                        
                        $jsonAttr = htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                    ?>
                        <div class="menu-item" data-item='<?= $jsonAttr ?>'>
                            <img src="<?= htmlspecialchars($item['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="item-image-placeholder">
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="item-price">₱<?= number_format($item['price'], 2) ?></div>
                            </div>
                            <div class="item-rating" aria-label="<?= $item['rating'] ?> stars">
                                <?= str_repeat('★', round($item['rating'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </main>

    <!-- Item Modal Popup (UNCHANGED) -->
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
                        <button class="modal-qty-btn" id="decreaseQty"><i class="fa-solid fa-circle-minus" style="color:#fff;"></i></button>
                        <span class="modal-qty-value" id="quantityValue">1</span>
                        <button class="modal-qty-btn" id="increaseQty"><i class="fa-solid fa-circle-plus" style="color:#fff;"></i></button>
                    </div>
                    <button class="modal-cart-icon-btn" id="addToCartBtn" title="Add to Cart"><i class="fa-solid fa-cart-arrow-down" style="color:#fff;"></i></button>
                    <button class="modal-order-btn" id="orderNowBtn">Order Now</button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-grid desktop-view">
                <div class="footer-col">
                    <h4>Socials</h4>
                    <a href="https://www.facebook.com/lukeseafoodtrading" target="_blank" class="social-item"><i class="fab fa-facebook"></i> Luke's Seafood Taguig</a>
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

    <?php include 'carT.php'; ?>
    <script src="carT.js"></script>
    <script src="menu.js"></script>
</body>
</html>