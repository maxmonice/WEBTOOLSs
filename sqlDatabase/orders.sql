--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL, -- Orders must be linked to a user
  -- `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)), -- Removed, replaced by order_items table
  `address` text NOT NULL,
  `delivery_latitude` DECIMAL(10, 8) DEFAULT NULL, -- For map integration
  `delivery_longitude` DECIMAL(11, 8) DEFAULT NULL, -- For map integration
  `payment_method` varchar(50) NOT NULL,
  `payment_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_details`)),
  `transaction_reference` varchar(100) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `estimated_arrival_time` datetime DEFAULT NULL, -- Added to allow staff to set/edit ETA
  `status` enum('pending','accepted','preparing','ready_for_pickup','on_route','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `preparing_at` datetime DEFAULT NULL,
  `ready_for_pickup_at` datetime DEFAULT NULL,
  `on_route_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `assigned_staff_id` int(10) UNSIGNED DEFAULT NULL, -- Staff member preparing the order
  `assigned_rider_id` int(10) UNSIGNED DEFAULT NULL, -- Rider assigned for delivery
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT, -- An order should always have a user
  CONSTRAINT `fk_order_assigned_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_assigned_rider` FOREIGN KEY (`assigned_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;