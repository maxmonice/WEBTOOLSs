-- =====================================================
--  add_user_roles.sql - Add role column to users table
--  Run this in phpMyAdmin to add user role functionality
-- =====================================================

USE lukes_seafood;

-- Add role column to users table
ALTER TABLE users 
ADD COLUMN role ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer' 
AFTER provider;

-- Add index for faster role-based queries
CREATE INDEX idx_role ON users (role);

-- Update existing users to have appropriate roles
-- Set first user as admin (assuming this is the main admin)
UPDATE users SET role = 'admin' WHERE id = 1 LIMIT 1;

-- Set any existing OAuth users as customers by default
UPDATE users SET role = 'customer' WHERE role IS NULL OR role = '';

-- Show the result
SELECT id, name, email, role, provider, created_at FROM users ORDER BY id;
