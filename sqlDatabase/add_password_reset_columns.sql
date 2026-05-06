-- =====================================================
--  Migration: Add password reset columns to users table
--  Run this in phpMyAdmin to enable password reset functionality
-- =====================================================

ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL AFTER remember_token;
ALTER TABLE users ADD COLUMN token_expiry DATETIME DEFAULT NULL AFTER reset_token;

-- Optional: Create index for faster token lookups
CREATE INDEX idx_reset_token ON users (reset_token);
