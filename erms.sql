-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 03:12 AM
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
-- Table structure for table `account_invites`
--

CREATE TABLE `account_invites` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_invites`
--

INSERT INTO `account_invites` (`id`, `employee_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 'ADMIN-002', '489f547da54df8068a05ac43bf0b3968825989040b52ee9a8aeb4c24505ccd8e', '2026-03-17 23:36:39', NULL, '2026-03-17 23:06:39'),
(2, 'ADMIN-002', '5f245305562f9f42467d298d84416f6e7e82ca55309357a2a97ab9aca86a2768', '2026-03-18 00:15:23', NULL, '2026-03-17 23:45:23'),
(3, 'ADMIN-002', '585a103588a705b623389118c49ee48aafb0d28188aa8a9cd05a72841f20986b', '2026-03-18 00:15:54', NULL, '2026-03-17 23:45:54'),
(4, 'ADMIN-002', '0d61b008c816afe0fcfaa2edde0b76213741a8a6082ae8bc3ce46a7d6b41ea3c', '2026-03-18 00:16:59', NULL, '2026-03-17 23:46:59'),
(5, 'ADMIN-002', 'f4d4eca8228690a9d79d8150abb8a74e1bc0c6a006ff0ba529fa8ea2736bbf5a', '2026-03-18 00:21:26', NULL, '2026-03-17 23:51:26'),
(6, 'ADMIN-002', '9d0f5ddb7a1601aa4dd6f31ac5b8aeb31f62fb2e58fc29f1c4acea04b129fccd', '2026-03-18 00:28:35', NULL, '2026-03-17 23:58:35'),
(7, 'ADMIN-002', '8f78aea28c835530be6362a9c4db558ae57093f40944ad79899051dd919e5c32', '2026-03-18 00:10:34', NULL, '2026-03-18 00:05:34'),
(8, 'ADMIN-002', '3cc39caf69f6e303a012b85781e89ddb4b9560a71ce2f776e4064faf6d08c128', '2026-03-18 00:15:19', NULL, '2026-03-18 00:10:19'),
(9, 'SO-003', '49f3a576b85bbf3759bdc8a50a95e294f41dff96c8609835ba68483e0f717447', '2026-03-18 00:18:41', NULL, '2026-03-18 00:13:41'),
(10, 'SO-003', 'f9712fc2a9e3c912d6fca482981bee90c7d362c01a64b2f89d01616cc83a6994', '2026-03-18 00:16:31', '2026-03-18 00:16:31', '2026-03-18 00:16:03'),
(11, 'SO-003', 'dad92b27a7e588cb5270662150ed94a3ea99c162d84e097b32b3d7b8c143ed08', '2026-03-18 00:38:06', '2026-03-18 00:38:06', '2026-03-18 00:37:00'),
(12, 'EMP-002', '02526d414d260dd0b31866ff1def0c0a3dd5e42c76fb3fb9c186ebcf65f7f74a', '2026-03-18 01:02:00', '2026-03-18 01:02:00', '2026-03-18 01:01:38'),
(13, 'SO-004', 'c2b08303c14f746961da62d75aadab981322b9714b3ae9e037001ebe5f15cb4d', '2026-03-18 05:28:29', '2026-03-18 05:28:29', '2026-03-18 05:25:35'),
(14, 'EMP-003', '2f0e49dd070f0dacfe4691e485a80277f0e714dc056ba521b3d94a97224ab5de', '2026-04-20 01:09:52', '2026-04-20 01:09:52', '2026-04-20 01:09:29'),
(15, 'SO-004', 'c5223c22de2dd0434a92164a3987629b75d95d3c7b55e7a1f9d0cb4f86269e3f', '2026-04-20 01:41:52', '2026-04-20 01:41:52', '2026-04-20 01:41:32'),
(16, 'EMP-003', '528c35577c5402f508485bcc3e7630f147da07ac7ee21280278f413794cb85d6', '2026-04-20 04:03:22', NULL, '2026-04-20 03:58:22'),
(17, 'EMP-003', 'b05a717fdbfef8715c98be71141bb7ca6f5330e68de18f0191c5f1aa9ec5d450', '2026-04-20 04:04:19', NULL, '2026-04-20 03:59:19'),
(18, 'EMP-003', 'bab673eeba66d4a8614d1e4ee4e928114cc324aae4ceb5fb534cfa85f0d1519e', '2026-04-20 04:06:11', NULL, '2026-04-20 04:01:11'),
(19, 'EMP-003', '4e1c01cbc277287a642cf301c5457fb04a4f518818149ff7559c1d4d7e4d03de', '2026-04-20 04:08:15', NULL, '2026-04-20 04:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company` varchar(40) NOT NULL,
  `folder_name` varchar(120) NOT NULL DEFAULT '',
  `document_type` enum('neuro','drug_test') NOT NULL,
  `document_date` date DEFAULT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(40) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `home_address` varchar(200) DEFAULT NULL,
  `agency` varchar(120) DEFAULT NULL,
  `detachment` varchar(120) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `signature` varchar(120) DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by_employee_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `company`, `folder_name`, `document_type`, `document_date`, `first_name`, `middle_name`, `last_name`, `full_name`, `home_address`, `agency`, `detachment`, `birth_date`, `gender`, `signature`, `created_by_user_id`, `created_by_employee_id`, `created_at`) VALUES
(3, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Marodin', 'A.', 'Omar', 'Omar, Marodin A.', 'None', 'None', NULL, '2001-08-14', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:03:29'),
(4, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Chrisitian', 'F.', 'Feolog', 'Feolog, Chrisitian F.', 'None', 'None', NULL, '1990-05-04', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:07:00'),
(5, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Mark Angelo', 'F.', 'Barredo', 'Barredo, Mark Angelo F.', 'None', 'None', NULL, '2003-08-03', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:09:07'),
(6, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Irwin', 'M.', 'Remolano', 'Remolano, Irwin M.', 'None', 'None', NULL, '1990-02-22', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:10:19'),
(7, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Romnick', 'P.', 'Alvasan', 'Alvasan, Romnick P.', 'None', 'None', NULL, '1993-03-01', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:11:43'),
(8, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Peter John', 'T.', 'Educalan', 'Educalan, Peter John T.', 'None', 'None', NULL, '1995-11-27', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:13:05'),
(9, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Mark Anthony', 'C.', 'Esquierdo', 'Esquierdo, Mark Anthony C.', 'None', 'None', NULL, '1997-08-05', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:14:46'),
(10, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Jennifer', 'Z.', 'Capacio', 'Capacio, Jennifer Z.', 'None', 'None', NULL, '1987-07-23', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:15:25'),
(11, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Carmel', 'A.', 'Bico', 'Bico, Carmel A.', 'None', 'None', NULL, '1978-04-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:16:18'),
(12, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Manuel', 'A.', 'Aniban', 'Aniban, Manuel A.', 'None', 'None', NULL, '1979-02-28', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:16:57'),
(13, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Jerico', 'D.', 'Ruiz', 'Ruiz, Jerico D.', 'None', 'None', NULL, '1999-07-27', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:17:35'),
(14, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'Antonio', 'S.', 'Silva', 'Silva, Antonio S.', 'None', 'None', NULL, '1978-05-02', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:18:23'),
(15, 'brainmaster', 'UTOPIA TOYOTA TAYTAY', 'drug_test', '2026-04-17', 'John Francis', 'V.', 'Arguelles', 'Arguelles, John Francis V.', 'None', 'None', NULL, '1995-01-22', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 07:19:15'),
(16, 'brainmaster', 'BATANGAS 04-20-26', 'neuro', '2026-04-20', 'Rotcher', 'A.', 'Cadorna', 'Cadorna, Rotcher A.', 'norzagarya bulacan', 'Silver Point', 'Batangas', '2004-06-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 08:21:34'),
(17, 'brainmaster', 'BATANGAS 04-20-26', 'drug_test', '2026-04-20', 'Rotcher', 'A.', 'Cadorna', 'Cadorna, Rotcher A.', 'norzagarya bulacan', 'Silver Point', 'Batangas', '2004-06-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 08:21:35'),
(18, 'brainmaster', 'BATANGAS 04-21-26', 'neuro', '2026-04-20', 'Dadsas', 'A.', 'Asdasda', 'Asdasda, Dadsas A.', 'ASDASDASDADA', 'ASDDS', 'ADSA', '2004-06-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 08:25:07'),
(19, 'brainmaster', 'BATANGAS', 'neuro', '2004-06-16', 'Asdad', 'A.', 'Asdasdd', 'Asdasdd, Asdad A.', 'ADAsdasdadaASDASdasd', 'Security Guard', 'sdadaASdads', '2004-06-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 08:40:20'),
(20, 'brainmaster', 'JANUARY', 'drug_test', '2025-06-05', 'Rotcher', 'A.', 'Cadorna', 'Cadorna, Rotcher A. Jr', NULL, 'Silver Point', 'NGH', '2004-06-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 09:48:13'),
(21, 'brainmaster', 'JAN', 'drug_test', '2025-06-12', 'Rotcher', 'A.', 'Cadorna', 'Cadorna, Rotcher A. Jr', NULL, 'Silver Point', 'DTG', '2004-06-16', 'Male', NULL, 1, 'ADMIN-001', '2026-04-20 10:09:21');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_employee_id` varchar(50) DEFAULT NULL,
  `actor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `target_type` varchar(40) DEFAULT NULL,
  `target_id` varchar(80) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_employee_id`, `actor_user_id`, `action`, `target_type`, `target_id`, `detail`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'ADMIN-001', 1, 'toggle_employee_active', 'employee', 'ADMIN-002', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 03:50:24'),
(2, 'ADMIN-001', 1, 'delete_user', 'user', '8', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 03:50:34'),
(3, 'ADMIN-001', 1, 'delete_user', 'user', '9', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 03:54:16'),
(4, 'ADMIN-001', 1, 'toggle_employee_active', 'employee', 'SO-003', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 03:54:24'),
(5, 'ADMIN-001', 1, 'toggle_employee_active', 'employee', 'EMP-002', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 03:54:29'),
(6, 'yyy', NULL, 'login_failed', 'auth', 'yyy', '{\"reason\":\"invalid_credentials_or_inactive\"}', '192.168.254.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-18 03:58:38'),
(7, 'ADMIN-001', NULL, 'login_failed', 'auth', 'ADMIN-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '192.168.254.140', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-18 03:59:02'),
(8, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:03:39'),
(9, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:17:35'),
(10, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:19:25'),
(11, 'ADMIN-001', 1, 'create_employee', 'employee', 'SO-004', '{\"role\":\"security_operation\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:24:56'),
(12, 'SO-004', NULL, 'account_setup_requested', 'account_setup', 'SO-004', '{\"email_sent\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:25:40'),
(13, 'SO-004', NULL, 'account_password_set', 'account_setup', 'SO-004', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:28:29'),
(14, 'SO-004', 10, 'login_success', 'auth', 'SO-004', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:32:11'),
(15, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-18 05:55:45'),
(16, 'admin', NULL, 'login_failed', 'auth', 'admin', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 00:16:33'),
(17, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 00:16:40'),
(18, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 06:19:48'),
(19, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-24 00:18:10'),
(20, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-17 02:39:52'),
(21, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:11:42'),
(22, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.125', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/557.0.0.39.107;FBBV/938924828;FBDV/iPhone13,3;FBMD/iPhone;FBSN/iOS;FBSV/18.5;FBSS/3;FBCR/;FBID/phone;FBLC/en_US;FBOP/80]', '2026-04-20 00:37:47'),
(23, 'ADMIN-001', NULL, 'login_failed', 'auth', 'ADMIN-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:40:58'),
(24, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:41:08'),
(25, 'EMP-001', NULL, 'login_failed', 'auth', 'EMP-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:56:02'),
(26, 'EMP-001', NULL, 'login_failed', 'auth', 'EMP-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:56:12'),
(27, 'EMP-001', NULL, 'login_failed', 'auth', 'EMP-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:57:31'),
(28, 'EMP-001', NULL, 'login_failed', 'auth', 'EMP-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:57:54'),
(29, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 00:59:00'),
(30, 'EMP-001', NULL, 'account_setup_request_failed', 'account_setup', 'EMP-001', '{\"reason\":\"account_exists\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:00:02'),
(31, 'EMP-001', NULL, 'account_setup_request_failed', 'account_setup', 'EMP-001', '{\"reason\":\"not_found_inactive_or_missing_email\",\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:03:04'),
(32, 'EMP-001', NULL, 'account_setup_request_failed', 'account_setup', 'EMP-001', '{\"reason\":\"not_found_inactive_or_missing_email\",\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:03:41'),
(33, 'EMP-001', NULL, 'account_setup_request_failed', 'account_setup', 'EMP-001', '{\"reason\":\"not_found_inactive_or_missing_email\",\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:05:38'),
(34, 'EMP-001', NULL, 'account_setup_request_failed', 'account_setup', 'EMP-001', '{\"reason\":\"not_found_inactive_or_missing_email\",\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:06:00'),
(35, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:08:36'),
(36, 'ADMIN-001', 1, 'create_employee', 'employee', 'EMP-003', '{\"role\":\"employee\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:09:15'),
(37, 'EMP-003', NULL, 'account_setup_requested', 'account_setup', 'EMP-003', '{\"email_sent\":1,\"intent\":\"create\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:09:33'),
(38, 'EMP-003', NULL, 'account_password_set', 'account_setup', 'EMP-003', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:09:52'),
(39, 'EMP-003', 11, 'login_success', 'auth', 'EMP-003', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:09:57'),
(40, 'EMP-003', 11, 'login_success', 'auth', 'EMP-003', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:10:30'),
(41, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:13:57'),
(42, 'ADMIN-001', 1, 'delete_user', 'user', '5', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:19:50'),
(43, 'ADMIN-001', 1, 'delete_user', 'user', '3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:19:53'),
(44, 'ADMIN-001', 1, 'delete_user', 'user', '2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:19:57'),
(45, 'SO-004', NULL, 'login_failed', 'auth', 'SO-004', '{\"reason\":\"invalid_credentials_or_inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:41:26'),
(46, 'SO-004', NULL, 'account_setup_requested', 'account_setup', 'SO-004', '{\"email_sent\":1,\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:41:37'),
(47, 'SO-004', NULL, 'account_password_set', 'account_setup', 'SO-004', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:41:52'),
(48, 'SO-004', 10, 'login_success', 'auth', 'SO-004', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:41:59'),
(49, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 01:47:51'),
(50, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.140', 'Mozilla/5.0 (Linux; Android 13; Infinix X6711 Build/TP1A.220624.014; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/147.0.7727.55 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/557.0.0.53.76;]', '2026-04-20 02:21:08'),
(51, 'EMP-003', 11, 'login_success', 'auth', 'EMP-003', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 02:36:43'),
(52, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 03:41:43'),
(53, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 03:48:22'),
(54, 'EMP-003', 11, 'login_success', 'auth', 'EMP-003', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 03:52:28'),
(55, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 03:53:09'),
(56, 'EMP-003', NULL, 'account_setup_requested', 'account_setup', 'EMP-003', '{\"email_sent\":1,\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 03:58:27'),
(57, 'EMP-003', NULL, 'account_setup_requested', 'account_setup', 'EMP-003', '{\"email_sent\":1,\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 03:59:23'),
(58, 'EMP-003', NULL, 'account_setup_requested', 'account_setup', 'EMP-003', '{\"email_sent\":1,\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 04:01:16'),
(59, 'EMP-003', NULL, 'account_setup_requested', 'account_setup', 'EMP-003', '{\"email_sent\":1,\"intent\":\"reset\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 04:03:20'),
(60, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 04:16:05'),
(61, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 04:29:55'),
(62, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 05:41:12'),
(63, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 05:43:18'),
(64, 'EMP-001', NULL, 'login_failed', 'auth', 'EMP-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 06:59:45'),
(65, 'EMP-001', NULL, 'login_failed', 'auth', 'EMP-001', '{\"reason\":\"invalid_credentials_or_inactive\"}', '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 06:59:55'),
(66, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 07:00:02'),
(67, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 08:18:25'),
(68, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 09:43:32'),
(69, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 09:58:25'),
(70, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 10:07:13'),
(71, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 10:08:27'),
(72, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 22:19:46'),
(73, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 22:27:23'),
(74, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 23:17:15'),
(75, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 23:48:50'),
(76, 'ADMIN-001', 1, 'toggle_employee_active', 'employee', 'EMP-003', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 23:50:54'),
(77, 'ADMIN-001', 1, 'toggle_employee_active', 'employee', 'SO-004', '{\"is_active\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-20 23:57:44'),
(78, 'ADMIN-001', 1, 'login_success', 'auth', 'ADMIN-001', NULL, '192.168.254.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-04-21 00:15:22'),
(79, 'ADMIN-001', 1, 'toggle_guard_active', 'guard', '408', '{\"status\":\"inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-21 00:19:56'),
(80, 'ADMIN-001', 1, 'toggle_guard_active', 'guard', '408', '{\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-21 00:20:03'),
(81, 'ADMIN-001', 1, 'toggle_guard_active', 'guard', '408', '{\"status\":\"inactive\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-21 00:20:25'),
(82, 'ADMIN-001', 1, 'toggle_guard_active', 'guard', '408', '{\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-21 00:41:29');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `starting_date` date DEFAULT NULL,
  `role` enum('admin','security_operation','employee') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `full_name`, `email`, `starting_date`, `role`, `is_active`, `deactivated_at`, `created_at`, `updated_at`) VALUES
(1, 'ADMIN-001', 'Administrator', NULL, NULL, 'admin', 1, NULL, '2026-03-02 00:23:55', '2026-03-12 19:08:37'),
(2, 'EMP-001', 'Employee', NULL, NULL, 'employee', 0, '2026-03-18 06:54:32', '2026-03-02 00:23:55', '2026-03-18 00:54:32'),
(3, 'SO-0001', 'Juan Dela Cruz', NULL, NULL, 'security_operation', 0, '2026-03-18 06:54:26', '2026-03-16 00:57:37', '2026-03-18 00:54:26'),
(4, 'SO-0002', 'Security Operation 2', NULL, NULL, 'security_operation', 0, '2026-03-18 06:54:23', '2026-03-16 01:03:56', '2026-03-18 00:54:23'),
(5, 'ADMIN-002', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2004-06-16', 'admin', 0, '2026-03-18 09:50:24', '2026-03-17 23:06:20', '2026-03-18 03:50:24'),
(6, 'SO-003', 'CADORNA JEJE', 'cadornajeje@gmail.com', '1995-05-12', 'security_operation', 0, '2026-03-18 09:54:24', '2026-03-18 00:13:23', '2026-03-18 03:54:24'),
(7, 'EMP-002', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-03-01', 'employee', 0, '2026-03-18 09:54:29', '2026-03-18 01:01:15', '2026-03-18 03:54:29'),
(8, 'SO-004', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-01-08', 'security_operation', 0, '2026-04-20 23:57:44', '2026-03-18 05:24:56', '2026-04-20 23:57:44'),
(9, 'EMP-003', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-05-16', 'employee', 0, '2026-04-20 23:50:54', '2026-04-20 01:09:15', '2026-04-20 23:50:54');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generated_documents`
--

CREATE TABLE `generated_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company` varchar(40) NOT NULL,
  `document_type` enum('neuro','drug_test') NOT NULL,
  `document_date` date DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `purpose` varchar(40) DEFAULT NULL,
  `purpose_specify` varchar(120) DEFAULT NULL,
  `folder_name` varchar(120) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by_employee_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `generated_documents`
--

INSERT INTO `generated_documents` (`id`, `company`, `document_type`, `document_date`, `full_name`, `purpose`, `purpose_specify`, `folder_name`, `file_name`, `file_path`, `created_by_user_id`, `created_by_employee_id`, `created_at`) VALUES
(246, 'brainmaster', 'drug_test', '2026-04-17', 'Omar, Marodin A.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'OMAR, MARODIN A. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/OMAR, MARODIN A. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:03:29'),
(247, 'brainmaster', 'drug_test', '2026-04-17', 'Feolog, Chrisitian F.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'FEOLOG, CHRISITIAN F. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/FEOLOG, CHRISITIAN F. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:07:00'),
(248, 'brainmaster', 'drug_test', '2026-04-17', 'Barredo, Mark Angelo F.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'BARREDO, MARK ANGELO F. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/BARREDO, MARK ANGELO F. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:09:07'),
(249, 'brainmaster', 'drug_test', '2026-04-17', 'Remolano, Irwin M.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'REMOLANO, IRWIN M. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/REMOLANO, IRWIN M. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:10:19'),
(250, 'brainmaster', 'drug_test', '2026-04-17', 'Alvasan, Romnick P.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'ALVASAN, ROMNICK P. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/ALVASAN, ROMNICK P. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:11:43'),
(251, 'brainmaster', 'drug_test', '2026-04-17', 'Educalan, Peter John T.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'EDUCALAN, PETER JOHN T. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/EDUCALAN, PETER JOHN T. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:13:05'),
(252, 'brainmaster', 'drug_test', '2026-04-17', 'Esquierdo, Mark Anthony C.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'ESQUIERDO, MARK ANTHONY C. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/ESQUIERDO, MARK ANTHONY C. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:14:46'),
(253, 'brainmaster', 'drug_test', '2026-04-17', 'Capacio, Jennifer Z.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'CAPACIO, JENNIFER Z. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/CAPACIO, JENNIFER Z. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:15:25'),
(254, 'brainmaster', 'drug_test', '2026-04-17', 'Bico, Carmel A.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'BICO, CARMEL A. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/BICO, CARMEL A. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:16:18'),
(255, 'brainmaster', 'drug_test', '2026-04-17', 'Aniban, Manuel A.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'ANIBAN, MANUEL A. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/ANIBAN, MANUEL A. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:16:57'),
(256, 'brainmaster', 'drug_test', '2026-04-17', 'Ruiz, Jerico D.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'RUIZ, JERICO D. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/RUIZ, JERICO D. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:17:35'),
(257, 'brainmaster', 'drug_test', '2026-04-17', 'Silva, Antonio S.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'SILVA, ANTONIO S. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/SILVA, ANTONIO S. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:18:23'),
(258, 'brainmaster', 'drug_test', '2026-04-17', 'Arguelles, John Francis V.', NULL, NULL, 'UTOPIA TOYOTA TAYTAY', 'ARGUELLES, JOHN FRANCIS V. - Drug Test.docx', 'export_nuero/UTOPIA TOYOTA TAYTAY/ARGUELLES, JOHN FRANCIS V. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 07:19:15'),
(259, 'brainmaster', 'neuro', '2026-04-20', 'Cadorna, Rotcher A.', 'security', NULL, 'BATANGAS 04-20-26', 'CADORNA, ROTCHER A. - Neuro Document.docx', 'export_nuero/BATANGAS 04-20-26/CADORNA, ROTCHER A. - Neuro Document.docx', 1, 'ADMIN-001', '2026-04-20 08:21:34'),
(260, 'brainmaster', 'drug_test', '2026-04-20', 'Cadorna, Rotcher A.', 'security', NULL, 'BATANGAS 04-20-26', 'CADORNA, ROTCHER A. - Drug Test.docx', 'export_nuero/BATANGAS 04-20-26/CADORNA, ROTCHER A. - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 08:21:35'),
(261, 'brainmaster', 'neuro', '2026-04-20', 'Asdasda, Dadsas A.', 'security', NULL, 'BATANGAS 04-21-26', 'ASDASDA, DADSAS A. - Neuro Document.docx', 'export_nuero/BATANGAS 04-21-26/ASDASDA, DADSAS A. - Neuro Document.docx', 1, 'ADMIN-001', '2026-04-20 08:25:07'),
(262, 'brainmaster', 'neuro', '2004-06-16', 'Asdasdd, Asdad A.', 'security', NULL, 'BATANGAS', 'ASDASDD, ASDAD A. - Neuro Document.docx', 'export_nuero/BATANGAS/ASDASDD, ASDAD A. - Neuro Document.docx', 1, 'ADMIN-001', '2026-04-20 08:40:20'),
(263, 'brainmaster', 'drug_test', '2025-06-05', 'Cadorna, Rotcher A. Jr', NULL, NULL, 'JANUARY', 'CADORNA, ROTCHER A. JR - Drug Test.docx', 'export_nuero/JANUARY/CADORNA, ROTCHER A. JR - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 09:48:13'),
(264, 'brainmaster', 'drug_test', '2025-06-12', 'Cadorna, Rotcher A. Jr', NULL, NULL, 'JAN', 'CADORNA, ROTCHER A. JR - Drug Test.docx', 'export_nuero/JAN/CADORNA, ROTCHER A. JR - Drug Test.docx', 1, 'ADMIN-001', '2026-04-20 10:09:21');

-- --------------------------------------------------------

--
-- Table structure for table `guards`
--

CREATE TABLE `guards` (
  `id` int(10) UNSIGNED NOT NULL,
  `guard_no` varchar(50) DEFAULT NULL,
  `last_name` varchar(60) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `age` smallint(5) UNSIGNED DEFAULT NULL,
  `agency` varchar(120) DEFAULT NULL,
  `full_name` varchar(180) NOT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `deployed` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guards`
--

INSERT INTO `guards` (`id`, `guard_no`, `last_name`, `first_name`, `middle_name`, `suffix`, `birthdate`, `age`, `agency`, `full_name`, `contact_no`, `deployed`, `status`, `created_at`, `updated_at`) VALUES
(408, 'JG-000408', 'Cadorna', 'Rotcher', 'Asinas', 'Jr.', '2004-06-16', 22, 'Silver Point', 'Cadorna, Rotcher Asinas Jr.', '09557850712', '2026-05-23', 'active', '2026-04-20 10:29:30', '2026-04-21 00:41:29');

-- --------------------------------------------------------

--
-- Table structure for table `guard_requirements`
--

CREATE TABLE `guard_requirements` (
  `id` int(10) UNSIGNED NOT NULL,
  `guard_id` int(10) UNSIGNED NOT NULL,
  `requirement_type_id` int(10) UNSIGNED NOT NULL,
  `document_no` varchar(120) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `document_original_name` varchar(255) DEFAULT NULL,
  `document_mime` varchar(120) DEFAULT NULL,
  `document_size` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guard_requirements`
--

INSERT INTO `guard_requirements` (`id`, `guard_id`, `requirement_type_id`, `document_no`, `issued_date`, `expiry_date`, `document_path`, `document_original_name`, `document_mime`, `document_size`, `created_at`, `updated_at`) VALUES
(516, 408, 1, NULL, NULL, NULL, 'uploads/guard_requirements/g408_t1_4d516e2bad1e33bb6de9.png', '7.PNG', 'image/png', 79383, '2026-04-21 00:50:24', '2026-04-21 00:50:24');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_03_13_000351_create_personal_access_tokens_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requirement_types`
--

CREATE TABLE `requirement_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(120) NOT NULL,
  `expires` tinyint(1) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `requirement_types`
--

INSERT INTO `requirement_types` (`id`, `code`, `name`, `expires`, `is_required`, `created_at`, `updated_at`) VALUES
(1, 'SSS', 'SSS', 0, 1, '2026-03-12 19:53:01', '2026-03-12 19:53:01'),
(2, 'PAGIBIG', 'PAG-IBIG', 0, 1, '2026-03-12 19:53:01', '2026-03-12 19:53:01'),
(3, 'PHILHEALTH', 'PhilHealth', 0, 1, '2026-03-12 19:53:01', '2026-03-12 19:53:01'),
(4, 'SECURITY_LICENSE', 'Security License', 1, 1, '2026-03-12 19:53:01', '2026-03-12 19:53:01');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(10, 'SO-004', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-01-08', 'security_operation', '$2y$10$I4/maCo4MgQfxWFVJTb5uu6W4jV2dClAMW3eG7lojHyasCIxIz1Ci', 0, '2026-04-20 23:57:44', '2026-03-18 05:28:29', '2026-04-20 23:57:44'),
(11, 'EMP-003', 'Rotcher A. Cadorna Jr.', 'rotchercadorna16@gmail.com', '2026-05-16', 'employee', '$2y$10$XU.0U2zP9DC4hZxunK2DEudsHc0SXOo3apYxlEEOPSH3IzJZBHJUi', 0, '2026-04-20 23:50:54', '2026-04-20 01:09:52', '2026-04-20 23:50:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_invites`
--
ALTER TABLE `account_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_account_invites_token_hash` (`token_hash`),
  ADD KEY `idx_account_invites_employee_id` (`employee_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_company` (`company`),
  ADD KEY `idx_attendance_type` (`document_type`),
  ADD KEY `idx_attendance_doc_date` (`document_date`),
  ADD KEY `idx_attendance_created_at` (`created_at`),
  ADD KEY `idx_attendance_name` (`last_name`,`first_name`),
  ADD KEY `idx_attendance_folder` (`folder_name`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_created_at` (`created_at`),
  ADD KEY `idx_audit_logs_actor_employee_id` (`actor_employee_id`),
  ADD KEY `idx_audit_logs_action` (`action`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employees_employee_id` (`employee_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_generated_documents_file` (`company`,`file_path`),
  ADD KEY `idx_generated_documents_company` (`company`),
  ADD KEY `idx_generated_documents_doc_date` (`document_date`),
  ADD KEY `idx_generated_documents_type` (`document_type`),
  ADD KEY `idx_generated_documents_created_at` (`created_at`);

--
-- Indexes for table `guards`
--
ALTER TABLE `guards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_guards_guard_no` (`guard_no`);

--
-- Indexes for table `guard_requirements`
--
ALTER TABLE `guard_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_guard_requirements_guard_type` (`guard_id`,`requirement_type_id`),
  ADD KEY `idx_guard_requirements_requirement_type_id` (`requirement_type_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_reserved_at_available_at_index` (`queue`,`reserved_at`,`available_at`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `requirement_types`
--
ALTER TABLE `requirement_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requirement_types_code` (`code`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

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
-- AUTO_INCREMENT for table `account_invites`
--
ALTER TABLE `account_invites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generated_documents`
--
ALTER TABLE `generated_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- AUTO_INCREMENT for table `guards`
--
ALTER TABLE `guards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=409;

--
-- AUTO_INCREMENT for table `guard_requirements`
--
ALTER TABLE `guard_requirements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=517;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requirement_types`
--
ALTER TABLE `requirement_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `guard_requirements`
--
ALTER TABLE `guard_requirements`
  ADD CONSTRAINT `fk_guard_requirements_guard_id` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_guard_requirements_requirement_type_id` FOREIGN KEY (`requirement_type_id`) REFERENCES `requirement_types` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
