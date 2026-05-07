CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `num_guests` varchar(50) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `event_name`, `address`, `event_date`, `event_time`, `event_type`, `num_guests`, `full_name`, `contact_number`, `email_address`, `notes`, `user_email`, `user_name`, `status`, `created_at`) VALUES
(1, 'bday', 'BLOCK 92 LOT 2', '2026-12-27', '12:23:00', 'Birthday', '12', 'Rafael MeÑozA CARLOS', '09935641473', 'maxmonice@gmail.com', 'hello', 'admin@lukesseafood.com', 'Admin', 'confirmed', '2026-04-29 11:56:10'),
(2, 'Darel Party', 'BLOCK 92 LOT 2', '2026-04-29', '00:00:00', 'seminar', '11-20', 'Rafael MeÑozA CARLOS', '09935641473', 'maxmonice@gmail.com', 'awdwad', 'admin@gmail.com', 'Administrator', 'cancelled', '2026-04-29 11:57:09'),
(3, 'bday', 'UMAK', '2026-05-31', '12:12:00', 'Birthday', '12', 'sir lecs', '09935641473', 'maxmonice@gmail.com', 'UMAK ONLY', 'admin@lukesseafood.com', 'Admin', 'cancelled', '2026-05-02 00:26:00'),
(4, 'bday', 'umak', '2026-05-31', '12:03:00', 'Corporate', '2', 'Rafael MeÑozA CARLOS', '09935641473', 'maxmonice@gmail.com', 'mak', 'admin@lukesseafood.com', 'Admin', 'confirmed', '2026-05-02 00:37:53'),
(5, 'bday', 'umak admin building', '2026-05-03', '12:12:00', 'Birthday', '10', 'Rafael MeÑozA CARLOS', '09935641473', 'maxmonice@gmail.com', 'ccis', 'admin@lukesseafood.com', 'Admin', 'cancelled', '2026-05-02 03:07:27');