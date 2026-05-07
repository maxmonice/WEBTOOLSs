-- ============================================================
-- Migration: Align orders table with application code
-- Run once against lukes_seafood database
-- ============================================================

-- 1. Add user_name / user_email (stored directly for guest orders)
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `user_name`  VARCHAR(255) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `user_email` VARCHAR(255) DEFAULT NULL AFTER `user_name`;

-- 2. Add notes (JSON blob storing items, payment details, etc.)
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `notes` LONGTEXT DEFAULT NULL AFTER `payment_method`;

-- 3. Add total_amount (alias used by the app alongside legacy `total`)
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `total_amount` DECIMAL(10,2) DEFAULT NULL AFTER `total`;

-- 4. Add eta (estimated prep time set by staff, e.g. "20-30 mins")
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `eta` VARCHAR(100) DEFAULT NULL AFTER `status`;

-- 5. Add rider_id (set when a rider accepts the order)
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `rider_id` VARCHAR(50) DEFAULT NULL AFTER `eta`;

-- 6. Add updated_at if it doesn't already exist
ALTER TABLE `orders`
  MODIFY COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 7. Expand the status enum to include 'confirmed' (Ready for Rider)
--    Full workflow: pending → processing → confirmed → shipped → delivered | cancelled
ALTER TABLE `orders`
  MODIFY COLUMN `status` ENUM(
    'pending',
    'processing',
    'confirmed',
    'shipped',
    'delivered',
    'cancelled'
  ) NOT NULL DEFAULT 'pending';

-- Done
SELECT 'Migration complete' AS result;
