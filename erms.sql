-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2026 at 07:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `erms`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `starting_date` date DEFAULT NULL,
  `role` enum('admin','security_operation','employee') NOT NULL DEFAULT 'employee',
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `full_name`, `email`, `starting_date`, `role`, `password_hash`, `is_active`, `deactivated_at`, `created_at`, `updated_at`) VALUES
(1, 'ADMIN-001', 'Administrator', NULL, NULL, 'admin', '$2y$10$qRL2f93iw/VKjEPH6Bbivuw9Nlb0X5i4FsZ8v42NxAYZawiWqyXOi', 1, NULL, '2026-03-02 00:23:55', '2026-03-12 19:08:37'),
(10, 'SO-004', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-01-08', 'security_operation', '$2y$10$I4/maCo4MgQfxWFVJTb5uu6W4jV2dClAMW3eG7lojHyasCIxIz1Ci', 1, NULL, '2026-03-18 05:28:29', '2026-04-20 01:41:52'),
(11, 'EMP-003', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-05-16', 'employee', '$2y$10$XU.0U2zP9DC4hZxunK2DEudsHc0SXOo3apYxlEEOPSH3IzJZBHJUi', 1, NULL, '2026-04-20 01:09:52', '2026-04-20 01:15:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
