-- =====================================================
--  setup.sql — Run this once to create your tables
--  In phpMyAdmin: create database 'lukes_seafood'
--  then run this SQL in the SQL tab
-- =====================================================

CREATE DATABASE IF NOT EXISTS lukes_seafood CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lukes_seafood;

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120)        NOT NULL,
    email           VARCHAR(180)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        DEFAULT NULL,   -- NULL for OAuth-only users
    provider        ENUM('email','google','facebook') NOT NULL DEFAULT 'email',
    provider_id     VARCHAR(255)        DEFAULT NULL,   -- Google/Facebook user ID
    avatar_url      VARCHAR(500)        DEFAULT NULL,
    remember_token  VARCHAR(64)         DEFAULT NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for fast email + provider lookups
CREATE INDEX idx_email    ON users (email);
CREATE INDEX idx_provider ON users (provider, provider_id);