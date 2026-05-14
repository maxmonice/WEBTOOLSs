-- =====================================================
--  fix_users_table.sql - Recreate users table with all required columns
--  Run this in the terminal or phpMyAdmin
-- =====================================================

USE lukes_seafood;

-- Drop existing users table if it exists
DROP TABLE IF EXISTS users;

-- Create the proper users table with all required columns
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL UNIQUE,
  `password_hash` varchar(255) DEFAULT NULL,
  `provider` enum('email','google','facebook') NOT NULL DEFAULT 'email',
  `role` enum('customer','staff','admin','rider') NOT NULL DEFAULT 'customer',
  `provider_id` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `avatar_url` varchar(500) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_provider` (`provider`,`provider_id`),
  KEY `idx_role` (`role`),
  KEY `idx_users_otp` (`id`,`otp_code`,`otp_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1;
