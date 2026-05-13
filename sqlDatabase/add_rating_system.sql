-- =====================================================
--  Migration: Add rating tables for riders, food, and bookings
--  Adds support for:
--  1. Rider ratings from customers
--  2. Food ratings from customers
--  3. Booking availability tracking
-- =====================================================

USE lukes_seafood;

-- =====================================================
--  RIDER RATINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    vehicle_plate VARCHAR(30) DEFAULT NULL,
    average_rating DECIMAL(3,2) DEFAULT NULL,
    rating_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rider_ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    rider_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_rider_rating (order_id, rider_id),
    INDEX idx_rider_id (rider_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  FOOD RATINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS food_ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_item_rating (order_id, menu_item_id),
    INDEX idx_menu_item_id (menu_item_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  BOOKING AVAILABILITY TABLE
--  Tracks booking counts per date to enforce 2-booking limit
-- =====================================================
CREATE TABLE IF NOT EXISTS booking_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_date DATE NOT NULL UNIQUE,
    booking_count INT NOT NULL DEFAULT 0 CHECK (booking_count >= 0 AND booking_count <= 2),
    status ENUM('available', 'full') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  ADD COLUMNS TO EXISTING TABLES
-- =====================================================

-- Add delivery_rating_given column to orders if not exists
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS delivery_rating_given TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS delivered_at DATETIME DEFAULT NULL;

-- Keep both rider assignment columns available because older rider screens use
-- rider_id while newer/customer screens can read assigned_rider_id.
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS rider_id VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS assigned_rider_id INT UNSIGNED DEFAULT NULL;

-- Add average rating columns to riders table if not exists
ALTER TABLE riders 
ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS rating_count INT UNSIGNED DEFAULT 0;

-- =====================================================
--  CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Index for faster booking date lookups
CREATE INDEX IF NOT EXISTS idx_bookings_event_date ON bookings(event_date);
CREATE INDEX IF NOT EXISTS idx_bookings_user_id ON bookings(user_id);

-- Index for faster order status lookups
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_rider_id ON orders(rider_id);
CREATE INDEX IF NOT EXISTS idx_orders_assigned_rider_id ON orders(assigned_rider_id);
