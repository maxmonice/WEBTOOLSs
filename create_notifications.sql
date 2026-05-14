-- =====================================================
--  create_notifications.sql - Create notifications table
--  Run this in phpMyAdmin to add notification functionality
-- =====================================================

USE lukes_seafood;

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'booking', 'order', 'user') NOT NULL DEFAULT 'info',
    target_role ENUM('all', 'admin', 'staff', 'customer') NOT NULL DEFAULT 'all',
    target_user_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_target_role (target_role),
    INDEX idx_target_user (target_user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Add some sample notifications
INSERT INTO notifications (title, message, type, target_role) VALUES
('Welcome Admin', 'Welcome to the admin panel. You can manage users, bookings, and orders from here.', 'info', 'admin'),
('Welcome Staff', 'Welcome to the staff panel. You can manage bookings and orders from here.', 'info', 'staff'),
('Welcome Customer', 'Welcome to your account dashboard. You can view your bookings and orders.', 'info', 'customer');

-- Show the result
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5;
