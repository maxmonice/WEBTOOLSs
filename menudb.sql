SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables to prevent conflicts
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `categories`;

-- ── CATEGORIES TABLE ──
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─ MENU ITEMS TABLE ──
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `pieces` varchar(50) DEFAULT NULL,
  `variations` json DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 5.0,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_menu_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── INSERT CATEGORIES ──
INSERT INTO `categories` (`id`, `name`, `slug`, `display_order`) VALUES
(1, 'Salad', 'salad-section', 1),
(2, 'Fusion Rolls & Sushi', 'fusion-section', 2),
(3, 'A La Carte', 'a-la-carte-section', 3),
(4, 'Platters', 'platters-section', 4),
(5, 'Bento Boxes', 'bento-section', 5);

-- ── INSERT MENU ITEMS ──
INSERT INTO `menu_items` (`id`, `category_id`, `name`, `price`, `image_path`, `pieces`, `variations`, `rating`) VALUES
(1, 1, 'Kani Mango Salad', 175.00, 'images/kani.webp', NULL, '["150 Grams","300 Grams"]', 5.0),
(2, 2, 'California Maki', 169.00, 'images/california.webp', 'NULL', '["8 Pieces","16 Pieces","24 Pieces","50 Pieces"]', 5.0),
(3, 2, 'Crazy Maki', 195.00, 'images/crazy.webp', '8 Pcs', NULL, 5.0),
(4, 2, 'Mango Roll', 195.00, 'images/mango.webp', '8 Pcs', NULL, 5.0),
(5, 2, 'Spicy Salmon', 195.00, 'images/spicysalmon.webp', '8 Pcs', NULL, 5.0),
(6, 2, 'Ebi Tempura Maki', 150.00, 'images/ebi.webp', '8 Pcs', NULL, 5.0),
(7, 2, 'Avocado Dragon Roll', 312.00, 'images/avocado.webp', '8 Pcs', NULL, 5.0),
(8, 2, 'Ebi Sushi', 208.00, 'images/ebisushi.webp', '4 Pcs', NULL, 5.0),
(9, 2, 'Tamago Sushi', 169.00, 'images/tamago.webp', '6 Pcs', NULL, 5.0),
(10, 3, 'Tempura', 221.00, 'images/tempura.webp', NULL, '["150 Grams","300 Grams"]', 5.0),
(11, 4, 'Square Platter A', 539.00, 'images/a.webp', '14 Pcs', NULL, 5.0),
(12, 4, 'Square Platter B', 487.00, 'images/b.webp', '18 Pcs', NULL, 5.0),
(13, 4, 'Sushi Boat A', 1558.00, 'images/boata.webp', '58 Pcs', NULL, 5.0),
(14, 4, 'Sushi Boat B', 2078.00, 'images/boatb.webp', '57 Pcs', NULL, 5.0),
(15, 4, 'Tempura Boat', 1168.00, 'images/tempuraboat.webp', '20 Pcs', NULL, 5.0),
(16, 4, 'Cali Maki', 312.00, 'images/calimaki.png', NULL, '["16 Pieces","24 Pieces","50 Pieces"]', 5.0),
(17, 4, 'Mixed Maki Platter', 1168.00, 'images/mixedmaki.webp', '48 Pcs', NULL, 5.0),
(18, 5, 'Tuna Steak in Oyster Sauce', 379.00, 'images/tunasteak.webp', NULL, NULL, 5.0),
(19, 5, 'Garlic Buttered Salmon', 239.00, 'images/garlic.webp', NULL, NULL, 5.0),
(20, 5, 'Combo E', 125.00, 'images/e.webp', NULL, NULL, 5.0),
(21, 5, 'Combo K', 199.00, 'images/k.webp', NULL, NULL, 5.0),
(22, 5, 'Combo L', 269.00, 'images/l.webp', NULL, NULL, 5.0),
(23, 5, 'Combo U', 245.00, 'images/u.webp', NULL, NULL, 5.0);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;