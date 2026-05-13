<?php
// =====================================================
//  example-integration.php
//  Shows how to integrate booking calendar and ratings
//  into your pages. Copy relevant sections to your pages.
// =====================================================

require_once 'Db.php';
session_start();

// Get database connection
$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking & Rating System - Integration Example</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Booking & Rating Styles -->
    <link rel="stylesheet" href="css/booking-rating.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            font-family: 'Be Vietnam Pro', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 60px;
            padding: 40px 0;
            border-bottom: 2px solid rgba(194, 38, 38, 0.3);
        }

        header h1 {
            font-family: 'Aclonica', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #C22626, #8B0A1E);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        header p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.1rem;
        }

        .example-section {
            background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .example-section h2 {
            font-family: 'Aclonica', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .example-section > p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .code-block {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(194, 38, 38, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            overflow-x: auto;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.85rem;
            line-height: 1.5;
            color: #22c55e;
        }

        .feature-list {
            list-style: none;
            margin: 20px 0;
            padding: 0;
        }

        .feature-list li {
            padding: 12px 0;
            padding-left: 32px;
            position: relative;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
        }

        .feature-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #22c55e;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .demo-area {
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(194, 38, 38, 0.3);
            border-radius: 12px;
            padding: 30px;
            margin-top: 24px;
        }

        .demo-title {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
        }

        .auth-required {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1));
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            color: #93c5fd;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .auth-required i {
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .endpoint {
            background: rgba(0, 0, 0, 0.4);
            border-left: 4px solid #C22626;
            padding: 16px;
            margin: 16px 0;
            border-radius: 4px;
        }

        .endpoint-method {
            display: inline-block;
            padding: 4px 8px;
            background: #C22626;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 8px;
        }

        .endpoint-path {
            font-family: 'Monaco', monospace;
            color: #22c55e;
            font-size: 0.9rem;
        }

        footer {
            text-align: center;
            padding: 30px 0;
            color: rgba(255, 255, 255, 0.5);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 60px;
        }

        .nav-link {
            display: inline-block;
            margin: 0 16px;
            color: #C22626;
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .nav-link:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🎫 Booking & Rating System</h1>
            <p>Complete integration guide and API documentation</p>
        </header>

        <!-- ========================================
             SECTION 1: BOOKING CALENDAR
             ======================================== -->
        <section class="example-section">
            <h2><i class="fas fa-calendar"></i> Booking Calendar System</h2>
            <p>
                Color-coded calendar with availability tracking. Dates turn red when 2 bookings are reached,
                and become unselectable to enforce the hard limit.
            </p>

            <ul class="feature-list">
                <li><strong>Green dates:</strong> Available (fewer than 2 bookings)</li>
                <li><strong>Red dates:</strong> Fully booked (exactly 2 bookings)</li>
                <li><strong>Visual booking count:</strong> Shows current bookings per date</li>
                <li><strong>Month navigation:</strong> Browse forward and backward</li>
                <li><strong>Real-time availability:</strong> Updates from database</li>
            </ul>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">Quick Start</h3>

            <div class="code-block"><!-- HTML -->
&lt;!-- Add to your HTML file --&gt;
&lt;div id="booking-calendar"&gt;&lt;/div&gt;

&lt;link rel="stylesheet" href="css/booking-rating.css"&gt;
&lt;script src="js/booking-calendar.js"&gt;&lt;/script&gt;
&lt;script&gt;
    // Initialize calendar
    const bookingCalendar = new BookingCalendar('booking-calendar', {
        apiUrl: 'booking-api.php',
        onDateSelect: (dateStr, bookingCount) => {
            console.log(`Selected: ${dateStr}`);
            console.log(`Bookings: ${bookingCount}/2`);
            document.getElementById('eventDate').value = dateStr;
        }
    });
&lt;/script&gt;</div>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">API Endpoint</h3>

            <div class="endpoint">
                <div>
                    <span class="endpoint-method">GET</span>
                    <span class="endpoint-path">/booking-api.php?action=availability&start_date=2026-05-13&end_date=2026-06-13</span>
                </div>
            </div>

            <p style="color: rgba(255, 255, 255, 0.6); font-size: 0.9rem;">
                Returns availability data for all dates in range. Automatically called by calendar component.
            </p>
        </section>

        <!-- ========================================
             SECTION 2: DELIVERY CONFIRMATION
             ======================================== -->
        <section class="example-section">
            <h2><i class="fas fa-box"></i> Delivery Confirmation & Ratings</h2>
            <p>
                Allow customers to mark orders as delivered and rate both the rider and food items
                in a single convenient modal.
            </p>

            <ul class="feature-list">
                <li>Mark order as delivered with one click</li>
                <li>Automatic rating prompt for rider</li>
                <li>Individual ratings for each food item</li>
                <li>Optional comments for both rider and food</li>
                <li>Real-time average rating calculation</li>
            </ul>

            <div class="auth-required">
                <i class="fas fa-info-circle"></i>
                <span>User must be logged in to rate deliveries</span>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">Implementation Example</h3>

            <div class="code-block">// JavaScript
function handleDeliveryConfirmation(orderId, riderId, riderName, orderItems) {
    // Mark as delivered and show rating modal
    fetch('ratings-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
            action: 'complete-delivery',
            order_id: orderId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.requires_rating) {
            const ratingModal = new DeliveryRatingModal(
                orderId,
                riderId,
                riderName,
                () => alert('Thank you for rating!')
            );
            ratingModal.show(orderItems);
        }
    });
}

// Usage in HTML:
// &lt;button onclick="handleDeliveryConfirmation(42, 5, 'John Rider', 
//     [{id: 1, name: 'Spicy Tuna Roll'}])"&gt;
//   Mark as Delivered &amp; Rate
// &lt;/button&gt;</div>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">API Endpoints</h3>

            <div class="endpoint">
                <div>
                    <span class="endpoint-method">POST</span>
                    <span class="endpoint-path">/ratings-api.php</span>
                </div>
                <p style="margin-top: 8px; color: rgba(255, 255, 255, 0.6); font-size: 0.9rem;">
                    Action: <code style="color: #22c55e;">complete-delivery</code>
                </p>
            </div>

            <div class="endpoint">
                <div>
                    <span class="endpoint-method">POST</span>
                    <span class="endpoint-path">/ratings-api.php</span>
                </div>
                <p style="margin-top: 8px; color: rgba(255, 255, 255, 0.6); font-size: 0.9rem;">
                    Action: <code style="color: #22c55e;">rate-rider</code>
                </p>
            </div>

            <div class="endpoint">
                <div>
                    <span class="endpoint-method">POST</span>
                    <span class="endpoint-path">/ratings-api.php</span>
                </div>
                <p style="margin-top: 8px; color: rgba(255, 255, 255, 0.6); font-size: 0.9rem;">
                    Action: <code style="color: #22c55e;">rate-food</code>
                </p>
            </div>
        </section>

        <!-- ========================================
             SECTION 3: RIDER RATINGS
             ======================================== -->
        <section class="example-section">
            <h2><i class="fas fa-star"></i> Rider Rating System</h2>
            <p>
                Display average rider ratings in their profile or dashboard. Ratings are automatically
                calculated and updated when customers submit feedback.
            </p>

            <ul class="feature-list">
                <li>1-5 star rating system</li>
                <li>Automatic average calculation</li>
                <li>Rating count tracking</li>
                <li>Comments for feedback</li>
                <li>Real-time updates</li>
            </ul>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">Display Rider Rating</h3>

            <div class="code-block">// JavaScript
fetch('ratings-api.php?action=rider-rating&rider_id=5')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.average_rating) {
            const filled = Math.round(data.average_rating);
            const empty = 5 - filled;
            const stars = '★'.repeat(filled) + '☆'.repeat(empty);
            
            document.getElementById('rider-rating').innerHTML = `
                &lt;span class="stars"&gt;${stars}&lt;/span&gt;
                &lt;span&gt;${data.average_rating} (${data.rating_count} ratings)&lt;/span&gt;
            `;
        }
    });</div>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">API Endpoint</h3>

            <div class="endpoint">
                <div>
                    <span class="endpoint-method">GET</span>
                    <span class="endpoint-path">/ratings-api.php?action=rider-rating&rider_id=5</span>
                </div>
            </div>
        </section>

        <!-- ========================================
             SECTION 4: FOOD RATINGS
             ======================================== -->
        <section class="example-section">
            <h2><i class="fas fa-utensils"></i> Food Rating System</h2>
            <p>
                Track quality of menu items through customer ratings. Customers can rate individual items
                after delivery.
            </p>

            <ul class="feature-list">
                <li>Per-menu-item ratings</li>
                <li>Customer feedback collection</li>
                <li>Average rating calculation</li>
                <li>Rating count per item</li>
                <li>Quality tracking over time</li>
            </ul>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">Display Food Rating</h3>

            <div class="code-block">// JavaScript
fetch('ratings-api.php?action=food-rating&menu_item_id=15')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.average_rating) {
            const filled = Math.round(data.average_rating);
            const stars = '★'.repeat(filled) + '☆'.repeat(5 - filled);
            
            document.getElementById('item-rating').innerHTML = `
                &lt;span class="stars"&gt;${stars}&lt;/span&gt;
                &lt;span&gt;${data.average_rating} / 5.0&lt;/span&gt;
            `;
        }
    });</div>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">API Endpoint</h3>

            <div class="endpoint">
                <div>
                    <span class="endpoint-method">GET</span>
                    <span class="endpoint-path">/ratings-api.php?action=food-rating&menu_item_id=15</span>
                </div>
            </div>
        </section>

        <!-- ========================================
             SECTION 5: FILES REFERENCE
             ======================================== -->
        <section class="example-section">
            <h2><i class="fas fa-folder"></i> Files Reference</h2>
            <p>All files created for this system are listed below:</p>

            <ul class="feature-list">
                <li><code>booking-api.php</code> - Booking management API</li>
                <li><code>ratings-api.php</code> - Rating submission API</li>
                <li><code>js/booking-calendar.js</code> - Calendar and rating components</li>
                <li><code>css/booking-rating.css</code> - Styling for all components</li>
                <li><code>sqlDatabase/add_rating_system.sql</code> - Database migration</li>
                <li><code>BOOKING_RATING_GUIDE.md</code> - Complete documentation</li>
            </ul>

            <h3 style="margin-top: 30px; margin-bottom: 16px; color: rgba(255, 255, 255, 0.8);">CSS Classes Available</h3>

            <div class="code-block">/* Calendar */
.booking-calendar
.booking-calendar-header
.day.color-green
.day.color-red
.day.selected

/* Ratings */
.star-rating
.delivery-rating-modal
.rider-profile-card
.order-status-card</div>
        </section>

        <footer>
            <p>&copy; 2026 Luke's Seafood Trading - Booking & Rating System</p>
            <nav style="margin-top: 16px;">
                <a href="BOOKING_RATING_GUIDE.md" class="nav-link">📖 Full Documentation</a>
                <a href="booking-api.php?action=availability&start_date=2026-05-13&end_date=2026-06-13" class="nav-link">🔗 Test API</a>
            </nav>
        </footer>
    </div>
</body>
</html>
