-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `provider` enum('email','google','facebook') NOT NULL DEFAULT 'email',
  `role` enum('customer','staff','admin','rider') NOT NULL DEFAULT 'customer',
  `provider_id` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `avatar_url` varchar(500) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `provider`, `role`, `provider_id`, `email_verified`, `otp_code`, `otp_expires_at`, `otp_attempts`, `avatar_url`, `remember_token`, `created_at`, `updated_at`, `reset_token`, `token_expiry`) VALUES
(25, 'Administrator', 'admin@gmail.com', '$2y$12$KBNsBLoOobrK.T8zx9KeNehmWHB4Suij0IhHQ7hX/4hDMswZDl5xu', 'email', 'admin', NULL, 1, '048488', '2026-04-26 15:56:20', 0, NULL, NULL, '2026-04-25 10:26:23', '2026-05-06 18:15:01', NULL, NULL),
(33, 'maxmonice', 'maxmonice@gmail.com', NULL, 'google', 'customer', '101025808367823489831', 1, NULL, NULL, 0, 'https://lh3.googleusercontent.com/a/ACg8ocKRAHUqrjiYUueSr4s1zWjP73Z_MKquWA5B-URHNmLyuiu5nenHFg=s96-c', NULL, '2026-04-25 11:24:41', '2026-04-25 11:24:41', NULL, NULL),
(35, 'RAFAEL CARLOS III', 'rcarlosiii.9721@umak.edu.ph', NULL, 'google', 'customer', '117209866365537447580', 1, NULL, NULL, 0, 'https://lh3.googleusercontent.com/a/ACg8ocLhthyk08Qn2EBJdknhKu8oBU-4nG2sJeKVOMX3HcIej-FHLg=s96-c', NULL, '2026-04-25 11:26:37', '2026-04-25 11:26:37', NULL, NULL),
(40, 'bogart', 'bogart70000@gmail.com', '$2y$10$sQ7eLFyUlqxm8UuA0cyoB.lE2O95.5reJvalX4kQqM07in3hmHodO', 'email', 'customer', NULL, 1, '723479', '2026-05-02 05:02:03', 0, NULL, NULL, '2026-04-26 21:03:43', '2026-05-02 10:52:03', NULL, NULL),
(42, 'alecs', 'bog@gmail.com', '$2y$12$cALJqc8cdWtQO4.Vnr.tD.l4MjWD5KMidEUPLavinSOS26bFcVn/S', 'email', 'customer', NULL, 0, '209251', '2026-05-02 05:04:59', 0, NULL, NULL, '2026-05-02 10:54:59', '2026-05-02 10:54:59', NULL, NULL),
(43, 'alecs', 'cyrilcmanalo02@gmail.com', '$2y$12$Ib/ols9ykDJt.xeEX96Fgux./KLbnHiZ57/o5ciDNjkQ/gjZ.u7Em', 'email', 'customer', NULL, 1, NULL, NULL, 0, NULL, NULL, '2026-05-02 10:57:00', '2026-05-02 11:05:32', NULL, NULL),
(44, 'alecs', 'joms@gmail.com', '$2y$12$UrKgmK4TSxKSjuXWoHKAAuGnXJQn2VzngcL6qQ5sBlK9mbAtNpxhG', 'email', 'customer', NULL, 1, NULL, NULL, 0, NULL, NULL, '2026-05-02 11:11:16', '2026-05-02 11:11:16', NULL, NULL),
(45, 'staff', 'staff@gmail.com', '$2y$12$QD2pXYfhndBX0CvsCtWQeOAvQ5CvyvAsMkjHaZmx3ThMyNlc6t2o.', 'email', 'staff', NULL, 1, NULL, NULL, 0, NULL, NULL, '2026-05-06 20:42:32', '2026-05-06 20:42:32', NULL, NULL);