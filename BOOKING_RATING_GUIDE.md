# Booking System & Rating System Implementation Guide

## Overview
This document covers the implementation of:
1. **Color-coded Calendar Date Picker** for booking system
2. **Rider Rating System** with average ratings
3. **Food Rating System** for menu items
4. **Customer Delivery Confirmation** with ratings

---

## Database Setup

### 1. Run Migration
Execute the following SQL file to set up the required tables:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root lukes_seafood < /Applications/XAMPP/xamppfiles/htdocs/WEBTOOLSs/sqlDatabase/add_rating_system.sql
```

**Tables Created:**
- `rider_ratings` - Stores rider ratings from customers
- `food_ratings` - Stores food item ratings from customers
- `booking_availability` - Tracks booking counts per date

**Columns Added to Existing Tables:**
- `orders`: `delivery_rating_given`, `delivery_completed_at`
- `riders`: `average_rating`, `rating_count`

---

## API Endpoints

### Booking API (`booking-api.php`)

#### 1. Get Booking Availability
**Endpoint:** `GET /booking-api.php?action=availability&start_date=2026-05-13&end_date=2026-06-13`

**Response:**
```json
{
  "success": true,
  "availability": {
    "2026-05-13": { "available": true, "count": 1, "color": "green" },
    "2026-05-14": { "available": false, "count": 2, "color": "red" }
  },
  "start_date": "2026-05-13",
  "end_date": "2026-06-13"
}
```

**Color Legend:**
- `green`: Available (< 2 bookings)
- `red`: Fully booked (2 bookings)

---

#### 2. Create Booking
**Endpoint:** `POST /booking-api.php`

**Payload:**
```json
{
  "action": "create",
  "booking_data": {
    "event_date": "2026-05-25",
    "event_time": "18:00",
    "event_name": "Birthday Party",
    "event_type": "birthday",
    "num_guests": "21-30",
    "full_name": "John Doe",
    "contact_number": "09123456789",
    "email_address": "john@example.com",
    "address": "123 Main St, Taguig",
    "notes": "Special requests..."
  }
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Booking created successfully! Pending admin confirmation.",
  "booking_id": 42
}
```

**Response (Fully Booked):**
```json
{
  "success": false,
  "error": "This date is fully booked. Please select another date.",
  "full_booked": true
}
```

---

### Ratings API (`ratings-api.php`)

#### 1. Get Rider Average Rating
**Endpoint:** `GET /ratings-api.php?action=rider-rating&rider_id=5`

**Response:**
```json
{
  "success": true,
  "rider_id": 5,
  "average_rating": 4.5,
  "rating_count": 12,
  "min_rating": 3,
  "max_rating": 5
}
```

---

#### 2. Get Food Item Average Rating
**Endpoint:** `GET /ratings-api.php?action=food-rating&menu_item_id=15`

**Response:**
```json
{
  "success": true,
  "menu_item_id": 15,
  "average_rating": 4.8,
  "rating_count": 45,
  "min_rating": 3,
  "max_rating": 5
}
```

---

#### 3. Submit Rider Rating
**Endpoint:** `POST /ratings-api.php`

**Payload:**
```json
{
  "action": "rate-rider",
  "order_id": 42,
  "rider_id": 5,
  "rating": 5,
  "comment": "Excellent service and fast delivery!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Rider rating submitted successfully",
  "rider_id": 5,
  "average_rating": 4.5,
  "rating_count": 13
}
```

---

#### 4. Submit Food Rating
**Endpoint:** `POST /ratings-api.php`

**Payload:**
```json
{
  "action": "rate-food",
  "order_id": 42,
  "menu_item_id": 15,
  "rating": 4,
  "comment": "Fresh and delicious!"
}
```

---

#### 5. Mark Delivery as Completed
**Endpoint:** `POST /ratings-api.php`

**Payload:**
```json
{
  "action": "complete-delivery",
  "order_id": 42
}
```

**Response:**
```json
{
  "success": true,
  "message": "Order marked as delivered",
  "order_id": 42,
  "rider_id": 5,
  "requires_rating": true
}
```

---

## Frontend Integration

### 1. Add Required CSS and JS Files

In your HTML file (e.g., `account-dashboard.php`, `carT.php`), add:

```html
<!-- Booking & Rating Styles -->
<link rel="stylesheet" href="css/booking-rating.css">

<!-- Booking Calendar & Rating Components -->
<script src="js/booking-calendar.js"></script>
```

---

### 2. Initialize Booking Calendar

```javascript
// Create container in your HTML
<div id="calendar" style="margin-bottom: 30px;"></div>

// Initialize calendar
const bookingCalendar = new BookingCalendar('calendar', {
    apiUrl: 'booking-api.php',
    onDateSelect: (dateStr, bookingCount) => {
        console.log(`Selected: ${dateStr} (${bookingCount} bookings)`);
        document.getElementById('eventDate').value = dateStr;
        document.getElementById('bookingAvailability').textContent = 
            `${2 - bookingCount} slot(s) available`;
    }
});
```

---

### 3. Display Delivery Confirmation & Ratings (in Order/Cart Page)

```javascript
// Example: Mark order as delivered and show rating modal
function handleDeliveryConfirmation(orderId, riderId, riderName, orderItems) {
    // First, mark as delivered
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
            // Show rating modal
            const ratingModal = new DeliveryRatingModal(
                orderId,
                riderId,
                riderName,
                () => {
                    alert('Ratings submitted!');
                    location.reload();
                }
            );
            ratingModal.show(orderItems);
        }
    });
}

// Call from order row:
// <button onclick="handleDeliveryConfirmation(42, 5, 'John Rider', [{id: 1, name: 'Sushi Set'}])">
//   Mark as Delivered
// </button>
```

---

### 4. Display Rider Ratings in Profile/Dashboard

```javascript
// Get rider's average rating
fetch('ratings-api.php?action=rider-rating&rider_id=5')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const stars = '★'.repeat(Math.round(data.average_rating)) + 
                         '☆'.repeat(5 - Math.round(data.average_rating));
            document.getElementById('rider-rating').innerHTML = `
                <span class="stars">${stars}</span>
                <span class="rating-value">${data.average_rating} (${data.rating_count} ratings)</span>
            `;
        }
    });
```

---

## CSS Classes Reference

### Calendar
- `.booking-calendar` - Main calendar container
- `.booking-calendar-header` - Header with month navigation
- `.booking-calendar-weekdays` - Weekday row
- `.booking-calendar-days` - Days grid
- `.day.color-green` - Available date (styling)
- `.day.color-red` - Fully booked date (styling)
- `.day.selected` - Selected date (styling)
- `.nav-btn` - Navigation buttons

### Rating
- `.star-rating` - Star rating container
- `.star.filled` - Filled star
- `.star.empty` - Empty star
- `.delivery-rating-modal` - Rating modal
- `.rider-profile-card` - Rider profile display
- `.order-status-card` - Order status with actions
- `.rating-display` - Rating display component

---

## Integration Examples

### Example 1: In Cart Page (`carT.php`)

```html
<div class="order-container">
    <div class="order-status-card">
        <div class="order-status-header">
            <h3>Order #42</h3>
            <span class="order-status-badge delivered">Delivered</span>
        </div>
        <div class="order-status-actions">
            <button class="btn-mark-delivered" 
                onclick="handleDeliveryConfirmation(42, 5, 'John Rider', 
                    [{id: 1, name: 'Spicy Tuna Roll'}, {id: 2, name: 'California Maki'}])">
                Mark as Delivered & Rate
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="css/booking-rating.css">
<script src="js/booking-calendar.js"></script>
```

---

### Example 2: In Booking Form (`bookbar.php`)

```html
<div class="booking-form-container">
    <h3>Select Event Date</h3>
    <div id="calendar"></div>
    
    <!-- Rest of form... -->
    <input type="hidden" id="eventDate" value="" required>
</div>

<link rel="stylesheet" href="css/booking-rating.css">
<script src="js/booking-calendar.js"></script>
<script>
    const bookingCalendar = new BookingCalendar('calendar', {
        onDateSelect: (dateStr) => {
            document.getElementById('eventDate').value = dateStr;
        }
    });
</script>
```

---

### Example 3: In Rider Profile (`rider-dashboard.php`)

```html
<div class="rider-profile-card">
    <div class="rider-info">
        <div class="rider-avatar">🏍️</div>
        <div class="rider-details">
            <h3>John Rider</h3>
            <p>Deliveries: 125</p>
            <div class="rider-rating-display">
                <span class="stars">★★★★★</span>
                <span class="rating-text" id="rider-avg-rating">Loading...</span>
            </div>
        </div>
    </div>
</div>

<script src="js/booking-calendar.js"></script>
<script>
    fetch('ratings-api.php?action=rider-rating&rider_id=5')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.average_rating) {
                const filled = Math.round(data.average_rating);
                const empty = 5 - filled;
                const stars = '★'.repeat(filled) + '☆'.repeat(empty);
                document.getElementById('rider-avg-rating').innerHTML = 
                    `<span class="stars">${stars}</span> ${data.average_rating} (${data.rating_count})`;
            }
        });
</script>
```

---

## Features Summary

### ✅ Booking System
- [x] Color-coded calendar (Green/Red)
- [x] Hard limit of 2 bookings per day
- [x] Date becomes unselectable when fully booked
- [x] Real-time availability checking
- [x] Month navigation
- [x] Visual booking count per date

### ✅ Rider Rating System
- [x] 1-5 star rating
- [x] Optional comments
- [x] Average rating calculation
- [x] Rating count tracking
- [x] Automatic rider profile update

### ✅ Food Rating System
- [x] Individual item ratings
- [x] Per-order food item ratings
- [x] Average rating per menu item
- [x] Optional comments

### ✅ Delivery Confirmation
- [x] Mark order as delivered
- [x] Automatic rating prompt
- [x] Single modal for both rider and food ratings
- [x] Data validation
- [x] User authentication check

---

## Testing

### Test Booking System
```bash
# Test availability endpoint
curl "http://localhost/WEBTOOLSs/booking-api.php?action=availability&start_date=2026-05-13&end_date=2026-06-13"

# Test create booking
curl -X POST http://localhost/WEBTOOLSs/booking-api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"create","booking_data":{"event_date":"2026-05-25","event_time":"18:00","event_name":"Birthday","event_type":"birthday","num_guests":"21-30","full_name":"John","contact_number":"09123456789","email_address":"john@example.com","address":"123 St"}}'
```

### Test Rating System
```bash
# Get rider rating
curl "http://localhost/WEBTOOLSs/ratings-api.php?action=rider-rating&rider_id=5"

# Get food rating
curl "http://localhost/WEBTOOLSs/ratings-api.php?action=food-rating&menu_item_id=15"
```

---

## Troubleshooting

### Issue: Calendar not showing colors
- Ensure `css/booking-rating.css` is linked
- Check browser console for errors
- Verify API endpoint returns data

### Issue: Ratings not saving
- Check if user is logged in (session required)
- Verify order belongs to user
- Check rider_id matches order

### Issue: Booking limit not enforced
- Ensure booking status is 'pending' or 'confirmed'
- Check database migration ran successfully
- Verify date format matches: YYYY-MM-DD

---

## Next Steps

1. ✅ Integrate calendar into `bookbar.php`
2. ✅ Add delivery confirmation button to `carT.php`
3. ✅ Display rider ratings in rider profile
4. ✅ Add food item ratings to menu item pages
5. Test all endpoints with sample data
6. Monitor ratings and adjust business logic as needed
