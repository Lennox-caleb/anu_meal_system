-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 04, 2026 at 07:04 PM
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
-- Database: `anu_meal_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('pending','approved','rejected','consumed') DEFAULT 'pending',
  `validated_by` int(11) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `code`, `user_id`, `menu_id`, `date`, `status`, `validated_by`, `validated_at`, `created_at`) VALUES
(1, 'ANU-029CAE97', 8, 4, '2026-02-24', 'consumed', 5, '2026-02-24 18:50:28', '2026-02-24 17:47:09'),
(2, 'ANU-231E1473', 8, 5, '2026-02-24', 'consumed', 4, '2026-02-25 16:32:47', '2026-02-24 17:53:47'),
(3, 'ANU-611CDFCC', 8, 9, '2026-02-24', 'consumed', 4, '2026-02-25 16:32:02', '2026-02-24 18:16:16'),
(4, 'ANU-0D901EA0', 9, 8, '2026-02-25', 'consumed', 5, '2026-02-25 08:57:32', '2026-02-25 07:56:02'),
(5, 'ANU-6E8BC91F', 7, 8, '2026-02-25', 'consumed', 4, '2026-02-25 16:32:17', '2026-02-25 09:59:31'),
(7, 'ANU-A537004B', 8, 2, '2026-02-26', 'approved', NULL, NULL, '2026-02-26 10:45:44'),
(8, 'ANU-EA120A6B', 8, 14, '2026-02-26', 'approved', NULL, NULL, '2026-02-26 10:46:04'),
(9, 'ANU-FBB3BDA5', 8, 4, '2026-02-26', 'consumed', 5, '2026-03-02 07:30:37', '2026-02-26 10:46:23'),
(10, 'ANU-564C496E', 10, 17, '2026-02-27', 'consumed', 5, '2026-03-01 09:16:35', '2026-02-27 05:33:00'),
(11, 'ANU-32B54D31', 10, 20, '2026-02-27', 'consumed', 5, '2026-03-01 09:16:20', '2026-02-27 05:33:17'),
(12, 'ANU-F7DC560D', 10, 21, '2026-02-27', 'consumed', 5, '2026-02-27 06:35:14', '2026-02-27 05:33:27'),
(13, 'ANU-2519AF53', 10, 23, '2026-02-27', 'consumed', 5, '2026-03-01 09:15:36', '2026-02-27 05:38:01'),
(14, 'ANU-4CC8D9C1', 11, 17, '2026-03-01', 'consumed', 5, '2026-03-01 09:11:35', '2026-03-01 08:09:29'),
(15, 'ANU-47657BD7', 11, 22, '2026-03-01', 'consumed', 5, '2026-03-01 09:11:14', '2026-03-01 08:09:42'),
(16, 'ANU-041AEA5A', 11, 24, '2026-03-01', 'consumed', 5, '2026-03-01 09:10:59', '2026-03-01 08:09:53'),
(17, 'ANU-C7A74A29', 10, 22, '2026-03-01', 'consumed', 5, '2026-03-01 09:21:03', '2026-03-01 08:18:01'),
(18, 'ANU-9BFBA2D4', 7, 17, '2026-03-02', 'consumed', 5, '2026-03-02 07:49:51', '2026-03-02 06:47:56'),
(19, 'ANU-8FE1624F', 7, 25, '2026-03-02', 'pending', NULL, NULL, '2026-03-02 06:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('Breakfast','Lunch','Dinner') NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(8,2) DEFAULT 0.00,
  `available` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `name`, `type`, `date`, `description`, `price`, `available`, `created_by`, `created_at`) VALUES
(2, 'Ugali & Beef Stew', 'Lunch', '2026-02-26', 'Traditional ugali with slow-cooked beef stew and kachumbari', 150.00, 1, NULL, '2026-02-23 16:29:36'),
(4, 'Porridge', 'Breakfast', '2026-02-26', 'Nutritious maize porridge sweetened with sugar', 60.00, 1, NULL, '2026-02-23 16:29:36'),
(5, 'Chapati & Beans', 'Lunch', '2026-02-24', 'Soft layered chapati with seasoned beans', 120.00, 1, NULL, '2026-02-23 16:29:36'),
(6, 'Githeri', 'Dinner', '2026-02-24', 'Mixed maize and beans, a Kenyan classic', 100.00, 1, NULL, '2026-02-23 16:29:36'),
(7, 'Egg & Toast', 'Breakfast', '2026-02-26', 'Scrambled eggs with buttered toast', 90.00, 1, NULL, '2026-02-23 16:29:36'),
(8, 'Pilau & Kachumbari', 'Lunch', '2026-02-26', 'Spiced Kenyan pilau with fresh tomato kachumbari', 180.00, 1, NULL, '2026-02-23 16:29:36'),
(9, 'Mandazi & Milk Tea', 'Breakfast', '2026-02-24', 'Freshly fried mandazi', 80.00, 1, 5, '2026-02-24 06:35:04'),
(10, 'Smokie& Milk Tea', 'Breakfast', '2026-02-26', 'Beef Smokie', 100.00, 1, 4, '2026-02-25 15:35:04'),
(11, 'Pilau & Kachumbari', 'Dinner', '2026-02-26', 'Spiced Kenyan pilau with fresh tomato kachumbari', 180.00, 1, 4, '2026-02-25 15:37:01'),
(12, 'Chapati & Beans', 'Lunch', '2026-02-26', 'Soft layered chapati with seasoned beans', 110.00, 1, 4, '2026-02-25 15:38:10'),
(13, 'Githeri', 'Lunch', '2026-02-26', 'Mixed maize and beans, a Kenyan classic', 90.00, 1, 4, '2026-02-25 15:38:51'),
(14, 'Chicken Briyani', 'Lunch', '2026-02-26', 'Coastal well prep dish', 150.00, 1, 4, '2026-02-25 15:41:04'),
(15, 'Sunrise Combo', 'Breakfast', '2026-02-26', 'Two fried or scrambled eggs served with toasted bread, grilled tomato, and a cup of tea or coffee', 200.00, 1, 5, '2026-02-25 15:45:32'),
(16, 'Sausage & Egg Roll', 'Breakfast', '2026-02-26', 'Grilled sausage and fried egg wrapped in soft bread. Convenient grab-and-go option.', 110.00, 1, 5, '2026-02-25 15:46:08'),
(17, 'Mandazi & Tea', 'Breakfast', '2026-03-02', 'Freshly prepared mandazi served with hot milk tea.', 80.00, 1, 5, '2026-02-25 15:46:47'),
(18, 'Fried Rice & Vegetable Stir Fry', 'Dinner', '2026-02-26', 'Seasoned rice stir-fried with carrots, peas, and green beans.', 120.00, 1, 5, '2026-02-25 15:47:29'),
(19, 'Grilled Chicken & Chips', 'Dinner', '2026-02-26', 'Marinated grilled chicken quarter served with crispy fries and fresh coleslaw.', 200.00, 1, 5, '2026-02-25 15:48:00'),
(20, 'Spaghetti Bolognese', 'Lunch', '2026-03-02', 'Pasta tossed in savory minced beef and tomato sauce, lightly seasoned with herbs.', 130.00, 1, 5, '2026-02-25 15:48:34'),
(21, 'Vegetable Curry & Rice', 'Dinner', '2026-02-27', 'Mixed vegetables cooked in mild curry sauce served with steamed white rice.', 100.00, 1, 5, '2026-02-25 15:49:05'),
(22, 'Fish Fillet & Ugali', 'Lunch', '2026-03-02', 'Pan-fried fish fillet served with ugali and sukuma wiki. Rich in protein and omega nutrients.', 250.00, 1, 5, '2026-02-25 15:49:34'),
(23, 'Githeri Special', 'Lunch', '2026-03-02', 'Boiled maize and beans mixed with vegetables and light seasoning.', 80.00, 1, 5, '2026-02-25 15:50:11'),
(24, 'Chips Masala', 'Dinner', '2026-03-02', 'French fries tossed in mild tomato and onion masala sauce.', 150.00, 1, 5, '2026-02-25 15:51:10'),
(25, 'Samosa (Beef or Vegetable)', 'Lunch', '2026-03-02', 'Crispy pastry filled with spiced minced beef or vegetables', 120.00, 1, 5, '2026-02-25 15:51:50'),
(26, 'Chicken Wrap', 'Dinner', '2026-03-02', 'Grilled chicken strips, lettuce, and sauce wrapped in soft tortilla.', 150.00, 1, 5, '2026-02-25 15:52:21'),
(27, 'Porridge', 'Breakfast', '2026-03-01', 'Sweet and soft', 80.00, 1, 5, '2026-02-27 05:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `notifications_log`
--

CREATE TABLE `notifications_log` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('approved','rejected','consumed','pending','reminder','cancelled') NOT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications_log`
--

INSERT INTO `notifications_log` (`id`, `booking_id`, `user_id`, `type`, `status`, `error_message`, `sent_at`) VALUES
(1, 9, 8, 'consumed', 'failed', 'SMTP delivery failed', '2026-03-02 06:30:37'),
(2, 18, 7, 'approved', 'failed', 'SMTP delivery failed', '2026-03-02 06:49:19'),
(3, 18, 7, 'consumed', 'failed', 'SMTP delivery failed', '2026-03-02 06:49:51');

-- --------------------------------------------------------

--
-- Table structure for table `reports_audit`
--

CREATE TABLE `reports_audit` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `export_format` varchar(20) NOT NULL,
  `filters_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `record_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports_audit`
--

INSERT INTO `reports_audit` (`id`, `user_id`, `export_format`, `filters_json`, `ip_address`, `record_count`, `created_at`) VALUES
(1, 4, 'csv_summary', '{\"from\":\"2026-03-01\",\"to\":\"2026-03-01\",\"status\":\"\",\"meal_type\":\"\",\"department\":\"\",\"validated\":\"\"}', '::1', 0, '2026-03-01 08:03:41'),
(2, 4, 'csv', '{\"from\":\"2026-02-26\",\"to\":\"2026-03-01\",\"status\":\"\",\"meal_type\":\"\",\"department\":\"\",\"validated\":\"\"}', '::1', 7, '2026-03-01 08:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'org_name', 'Africa Nazarene University', '2026-02-23 16:29:36'),
(2, 'org_email', 'support@anu.ac.ke', '2026-02-23 16:29:36'),
(3, 'timezone', 'Africa/Nairobi', '2026-02-23 16:29:36'),
(4, 'booking_open', '06:00', '2026-02-23 16:29:36'),
(5, 'booking_close', '11:23', '2026-03-01 08:22:55'),
(6, 'auto_reset', '0', '2026-03-01 08:22:55'),
(7, 'maintenance_mode', '0', '2026-03-02 06:28:51'),
(8, 'meal_alerts', '1', '2026-02-23 16:29:36'),
(9, 'email_reports', '1', '2026-02-24 06:16:02'),
(172, 'smtp_host', 'smtp.gmail.com', '2026-03-02 06:24:02'),
(173, 'smtp_port', '587', '2026-03-02 06:24:02'),
(174, 'smtp_user', 'caleblennox39@gmail.com', '2026-03-02 06:51:23'),
(175, 'smtp_pass', '', '2026-03-02 06:24:02'),
(176, 'smtp_from', 'caleblennox39@gmail.com', '2026-03-02 06:51:23'),
(177, 'smtp_from_name', 'ANU Meal Booking System', '2026-03-02 06:24:02');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `details`, `ip`, `created_at`) VALUES
(1, NULL, 'Database Installed', 'ANU Meal Booking System database was set up successfully.', '127.0.0.1', '2026-02-23 16:29:36'),
(2, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 06:12:08'),
(3, 5, 'Menu Updated', 'Updated menu #7: Egg & Toast', '::1', '2026-02-24 06:12:28'),
(4, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 06:12:39'),
(5, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 06:12:52'),
(6, 4, 'User Created', 'Created user: Caleb (student)', '::1', '2026-02-24 06:13:57'),
(7, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-24 06:16:02'),
(8, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 06:16:11'),
(9, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-24 06:16:28'),
(10, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-24 06:30:15'),
(11, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 06:30:25'),
(12, 5, 'Menu Deleted', 'Deleted menu #3', '::1', '2026-02-24 06:33:14'),
(13, 5, 'Menu Deleted', 'Deleted menu #1', '::1', '2026-02-24 06:33:53'),
(14, 5, 'Menu Added', 'Added menu: Mandazi & Milk Tea (Breakfast) on 2026-02-24', '::1', '2026-02-24 06:35:04'),
(15, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 06:36:30'),
(16, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-24 06:36:40'),
(17, 7, 'Profile Updated', 'Student #7 updated their profile.', '::1', '2026-02-24 11:10:05'),
(18, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-24 11:10:36'),
(19, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-24 11:10:43'),
(20, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-24 11:11:32'),
(21, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 11:11:50'),
(22, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-24 11:12:16'),
(23, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-24 11:12:45'),
(24, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 11:13:01'),
(25, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-24 11:13:10'),
(26, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-24 11:13:38'),
(27, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 11:13:48'),
(28, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-24 11:14:03'),
(29, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 11:14:07'),
(30, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-24 11:14:13'),
(31, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-24 11:14:54'),
(32, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-24 11:19:47'),
(33, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-24 11:19:56'),
(34, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 11:20:04'),
(35, 4, 'User Updated', 'Updated user #5: admin', '::1', '2026-02-24 11:20:30'),
(36, 4, 'User Updated', 'Updated user #6: John', '::1', '2026-02-24 11:21:15'),
(37, 4, 'User Updated', 'Updated user #5: admin', '::1', '2026-02-24 11:21:36'),
(38, 4, 'User Created', 'Created user: Marube (student)', '::1', '2026-02-24 11:27:13'),
(39, 4, 'User Updated', 'Updated user #6: John', '::1', '2026-02-24 11:27:31'),
(40, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 11:28:05'),
(41, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-24 11:28:12'),
(42, 8, 'Profile Updated', 'Student #8 updated their profile.', '::1', '2026-02-24 11:28:57'),
(43, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-24 11:29:01'),
(44, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 11:29:12'),
(45, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 11:34:58'),
(46, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 11:35:13'),
(47, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 11:35:50'),
(48, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-24 11:36:16'),
(49, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-24 13:03:33'),
(50, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 13:03:46'),
(51, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 13:05:08'),
(52, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 13:05:31'),
(53, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 13:07:17'),
(54, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-24 13:07:23'),
(55, 8, 'Meal Booked', 'Booked Porridge on 2026-02-24. Code: ANU-029CAE97', '::1', '2026-02-24 17:47:09'),
(56, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-24 17:49:44'),
(57, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 17:50:04'),
(58, 5, 'Meal Validated', 'Validated booking ANU-029CAE97 for Lilian Marube (Porridge)', '::1', '2026-02-24 17:50:28'),
(59, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 17:52:08'),
(60, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-24 17:52:15'),
(61, 8, 'Meal Booked', 'Booked Chapati & Beans on 2026-02-24. Code: ANU-231E1473', '::1', '2026-02-24 17:53:47'),
(62, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-24 17:54:57'),
(63, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 17:55:06'),
(64, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 17:56:35'),
(65, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-24 18:09:37'),
(66, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-24 18:09:50'),
(67, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-24 18:09:56'),
(68, 8, 'Meal Booked', 'Booked Mandazi & Milk Tea on 2026-02-24. Code: ANU-611CDFCC', '::1', '2026-02-24 18:16:16'),
(69, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-24 18:17:04'),
(70, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-24 18:17:33'),
(71, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-24 18:18:24'),
(72, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-24 18:41:52'),
(73, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-24 18:41:58'),
(74, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-24 20:20:18'),
(75, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 05:21:03'),
(76, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 07:45:33'),
(77, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-25 07:45:48'),
(78, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-25 07:52:36'),
(79, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-25 07:52:43'),
(80, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-25 07:53:19'),
(81, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 07:53:28'),
(82, 4, 'User Created', 'Created user: Moses (student)', '::1', '2026-02-25 07:55:04'),
(83, 4, 'User Updated', 'Updated user #9: Moses', '::1', '2026-02-25 07:55:22'),
(84, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 07:55:44'),
(85, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 07:55:50'),
(86, 9, 'Meal Booked', 'Booked Pilau & Kachumbari on 2026-02-25. Code: ANU-0D901EA0', '::1', '2026-02-25 07:56:02'),
(87, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 07:56:35'),
(88, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-25 07:56:48'),
(89, 5, 'Booking Approved', 'Booking #2 approved.', '::1', '2026-02-25 07:57:08'),
(90, 5, 'Booking Approved', 'Booking #3 approved.', '::1', '2026-02-25 07:57:09'),
(91, 5, 'Booking Approved', 'Booking #4 approved.', '::1', '2026-02-25 07:57:11'),
(92, 5, 'Meal Validated', 'Validated booking ANU-0D901EA0 for Moses Samwel (Pilau & Kachumbari)', '::1', '2026-02-25 07:57:32'),
(93, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-25 08:01:16'),
(94, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 08:01:22'),
(95, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 09:27:02'),
(96, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 09:27:15'),
(97, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 09:48:30'),
(98, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 09:48:36'),
(99, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 09:48:56'),
(100, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 09:49:04'),
(101, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 09:49:53'),
(102, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 09:49:55'),
(103, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-25 09:50:17'),
(104, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 09:50:27'),
(105, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 09:50:32'),
(106, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 09:50:55'),
(107, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 09:50:58'),
(108, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 09:51:05'),
(109, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-25 09:51:19'),
(110, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-25 09:51:42'),
(111, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 09:51:49'),
(112, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 09:53:08'),
(113, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 09:54:11'),
(114, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 09:58:25'),
(115, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 09:58:39'),
(116, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-25 09:58:52'),
(117, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-25 09:59:04'),
(118, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 09:59:10'),
(119, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-02-25 09:59:18'),
(120, 7, 'Meal Booked', 'Booked Pilau & Kachumbari on 2026-02-25. Code: ANU-6E8BC91F', '::1', '2026-02-25 09:59:31'),
(121, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-02-25 10:12:23'),
(122, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 10:12:35'),
(123, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 10:13:08'),
(124, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 10:13:13'),
(125, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 10:13:29'),
(126, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 10:13:38'),
(127, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-25 10:13:49'),
(128, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 10:13:52'),
(129, 9, 'User Login', 'User Moses (student) logged in.', '::1', '2026-02-25 10:13:57'),
(130, 9, 'User Logout', 'User Moses logged out.', '::1', '2026-02-25 10:14:13'),
(131, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-25 10:14:26'),
(132, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-25 10:15:56'),
(133, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 10:16:05'),
(134, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-25 10:16:27'),
(135, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 10:16:31'),
(136, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 10:16:39'),
(137, 8, 'Meal Booked', 'Booked Pilau & Kachumbari on 2026-02-25. Code: ANU-46B25E8F', '::1', '2026-02-25 10:16:45'),
(138, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 10:18:18'),
(139, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-25 10:18:28'),
(140, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-25 10:20:37'),
(141, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 10:20:49'),
(142, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 11:00:01'),
(143, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 11:00:06'),
(144, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 11:00:57'),
(145, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 11:48:37'),
(146, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 11:48:39'),
(147, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 12:07:09'),
(148, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 12:07:26'),
(149, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 12:43:09'),
(150, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 13:04:26'),
(151, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 13:08:23'),
(152, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 13:08:28'),
(153, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 13:08:33'),
(154, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 13:20:27'),
(155, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 15:19:48'),
(156, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 15:25:20'),
(157, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 15:25:35'),
(158, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 15:31:33'),
(159, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-25 15:31:43'),
(160, 4, 'Meal Validated', 'Validated booking ANU-611CDFCC for Lilian Marube (Mandazi & Milk Tea)', '::1', '2026-02-25 15:32:02'),
(161, 4, 'Meal Validated', 'Validated booking ANU-6E8BC91F for Lennox Caleb (Pilau & Kachumbari)', '::1', '2026-02-25 15:32:17'),
(162, 4, 'Meal Validated', 'Validated booking ANU-231E1473 for Lilian Marube (Chapati & Beans)', '::1', '2026-02-25 15:32:47'),
(163, 4, 'Menu Updated', 'Updated menu #2: Ugali & Beef Stew', '::1', '2026-02-25 15:33:35'),
(164, 4, 'Menu Added', 'Added menu: Smokie& Milk Tea (Breakfast) on 2026-02-25', '::1', '2026-02-25 15:35:04'),
(165, 4, 'Menu Updated', 'Updated menu #4: Porridge', '::1', '2026-02-25 15:35:40'),
(166, 4, 'Menu Added', 'Added menu: Pilau & Kachumbari (Dinner) on 2026-02-25', '::1', '2026-02-25 15:37:01'),
(167, 4, 'Menu Updated', 'Updated menu #8: Pilau & Kachumbari', '::1', '2026-02-25 15:37:31'),
(168, 4, 'Menu Added', 'Added menu: Chapati & Beans (Lunch) on 2026-02-26', '::1', '2026-02-25 15:38:10'),
(169, 4, 'Menu Added', 'Added menu: Githeri (Lunch) on 2026-02-26', '::1', '2026-02-25 15:38:51'),
(170, 4, 'Menu Updated', 'Updated menu #10: Smokie& Milk Tea', '::1', '2026-02-25 15:39:17'),
(171, 4, 'Menu Updated', 'Updated menu #7: Egg & Toast', '::1', '2026-02-25 15:39:29'),
(172, 4, 'Menu Added', 'Added menu: Chicken Briyani (Lunch) on 2026-02-26', '::1', '2026-02-25 15:41:04'),
(173, 4, 'Menu Updated', 'Updated menu #11: Pilau & Kachumbari', '::1', '2026-02-25 15:41:37'),
(174, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-25 15:41:59'),
(175, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 15:42:10'),
(176, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 15:44:35'),
(177, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-25 15:44:45'),
(178, 5, 'Menu Added', 'Added menu: Sunrise Combo (Breakfast) on 2026-02-26', '::1', '2026-02-25 15:45:32'),
(179, 5, 'Menu Added', 'Added menu: Sausage & Egg Roll (Breakfast) on 2026-02-26', '::1', '2026-02-25 15:46:08'),
(180, 5, 'Menu Added', 'Added menu: Mandazi & Tea (Breakfast) on 2026-02-27', '::1', '2026-02-25 15:46:47'),
(181, 5, 'Menu Added', 'Added menu: Fried Rice & Vegetable Stir Fry (Dinner) on 2026-02-26', '::1', '2026-02-25 15:47:29'),
(182, 5, 'Menu Added', 'Added menu: Grilled Chicken & Chips (Dinner) on 2026-02-26', '::1', '2026-02-25 15:48:00'),
(183, 5, 'Menu Added', 'Added menu: Spaghetti Bolognese (Lunch) on 2026-02-27', '::1', '2026-02-25 15:48:34'),
(184, 5, 'Menu Added', 'Added menu: Vegetable Curry & Rice (Dinner) on 2026-02-27', '::1', '2026-02-25 15:49:05'),
(185, 5, 'Menu Added', 'Added menu: Fish Fillet & Ugali (Lunch) on 2026-02-27', '::1', '2026-02-25 15:49:34'),
(186, 5, 'Menu Added', 'Added menu: Githeri Special (Lunch) on 2026-02-27', '::1', '2026-02-25 15:50:11'),
(187, 5, 'Menu Added', 'Added menu: Chips Masala (Dinner) on 2026-02-27', '::1', '2026-02-25 15:51:10'),
(188, 5, 'Menu Added', 'Added menu: Samosa (Beef or Vegetable) (Lunch) on 2026-02-27', '::1', '2026-02-25 15:51:50'),
(189, 5, 'Menu Added', 'Added menu: Chicken Wrap (Dinner) on 2026-02-27', '::1', '2026-02-25 15:52:21'),
(190, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-25 15:52:41'),
(191, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-25 15:52:48'),
(192, 8, 'Profile Updated', 'Student #8 updated their profile.', '::1', '2026-02-25 15:54:42'),
(193, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-25 17:00:33'),
(194, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-26 10:44:30'),
(195, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-26 10:44:54'),
(196, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-26 10:45:00'),
(197, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-26 10:45:19'),
(198, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-26 10:45:21'),
(199, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-02-26 10:45:27'),
(200, 8, 'Meal Booked', 'Booked Ugali & Beef Stew on 2026-02-26. Code: ANU-A537004B', '::1', '2026-02-26 10:45:44'),
(201, 8, 'Meal Booked', 'Booked Chicken Briyani on 2026-02-26. Code: ANU-EA120A6B', '::1', '2026-02-26 10:46:04'),
(202, 8, 'Meal Booked', 'Booked Porridge on 2026-02-26. Code: ANU-FBB3BDA5', '::1', '2026-02-26 10:46:23'),
(203, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-02-26 10:46:28'),
(204, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-26 10:46:36'),
(205, 4, 'Booking Approved', 'Booking #7 approved.', '::1', '2026-02-26 19:27:38'),
(206, 4, 'Booking Approved', 'Booking #8 approved.', '::1', '2026-02-26 19:27:40'),
(207, 4, 'Booking Approved', 'Booking #9 approved.', '::1', '2026-02-26 19:27:42'),
(208, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-26 19:28:46'),
(209, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-27 05:28:29'),
(210, 5, 'Menu Added', 'Added menu: Porridge (Breakfast) on 2026-02-27', '::1', '2026-02-27 05:30:29'),
(211, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-27 05:30:34'),
(212, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-27 05:30:52'),
(213, 4, 'User Created', 'Created user: njoki (student)', '::1', '2026-02-27 05:31:55'),
(214, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-27 05:32:00'),
(215, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-02-27 05:32:22'),
(216, 10, 'Meal Booked', 'Booked Mandazi & Tea on 2026-02-27. Code: ANU-564C496E', '::1', '2026-02-27 05:33:00'),
(217, 10, 'Meal Booked', 'Booked Spaghetti Bolognese on 2026-02-27. Code: ANU-32B54D31', '::1', '2026-02-27 05:33:17'),
(218, 10, 'Meal Booked', 'Booked Vegetable Curry & Rice on 2026-02-27. Code: ANU-F7DC560D', '::1', '2026-02-27 05:33:28'),
(219, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-02-27 05:34:17'),
(220, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-27 05:34:39'),
(221, 5, 'Booking Approved', 'Booking #10 approved.', '::1', '2026-02-27 05:34:58'),
(222, 5, 'Booking Approved', 'Booking #11 approved.', '::1', '2026-02-27 05:34:59'),
(223, 5, 'Booking Approved', 'Booking #12 approved.', '::1', '2026-02-27 05:35:01'),
(224, 5, 'Meal Validated', 'Validated booking ANU-F7DC560D for Mitchelle Njoki (Vegetable Curry & Rice)', '::1', '2026-02-27 05:35:14'),
(225, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-27 05:35:31'),
(226, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-02-27 05:35:42'),
(227, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-02-27 05:36:25'),
(228, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-27 05:36:34'),
(229, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-27 05:37:21'),
(230, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-27 05:37:26'),
(231, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-02-27 05:37:38'),
(232, 10, 'Meal Booked', 'Booked Githeri Special on 2026-02-27. Code: ANU-2519AF53', '::1', '2026-02-27 05:38:01'),
(233, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-02-27 05:38:21'),
(234, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-27 05:38:28'),
(235, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-27 05:38:43'),
(236, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-27 05:38:52'),
(237, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-02-27 05:39:22'),
(238, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-02-27 05:40:10'),
(239, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-27 05:40:17'),
(240, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-02-27 05:40:32'),
(241, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-27 05:40:34'),
(242, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-02-27 05:40:47'),
(243, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-02-27 05:41:09'),
(244, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-27 05:41:23'),
(245, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-28 18:40:13'),
(246, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-28 18:41:20'),
(247, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-28 19:12:05'),
(248, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-28 19:12:18'),
(249, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-02-28 19:26:11'),
(250, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-02-28 19:26:29'),
(251, 5, 'User Logout', 'User admin logged out.', '::1', '2026-02-28 19:26:55'),
(252, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-02-28 19:27:05'),
(253, 4, 'User Created', 'Created user: Lenny (student)', '::1', '2026-03-01 08:06:01'),
(254, 4, 'User Updated', 'Updated user #11: Lenny', '::1', '2026-03-01 08:06:43'),
(255, 4, 'Menu Updated', 'Updated menu #17: Mandazi & Tea', '::1', '2026-03-01 08:07:05'),
(256, 4, 'Menu Updated', 'Updated menu #27: Porridge', '::1', '2026-03-01 08:07:15'),
(257, 4, 'Menu Updated', 'Updated menu #20: Spaghetti Bolognese', '::1', '2026-03-01 08:07:25'),
(258, 4, 'Menu Updated', 'Updated menu #22: Fish Fillet & Ugali', '::1', '2026-03-01 08:07:32'),
(259, 4, 'Menu Updated', 'Updated menu #24: Chips Masala', '::1', '2026-03-01 08:07:40'),
(260, 4, 'Menu Updated', 'Updated menu #26: Chicken Wrap', '::1', '2026-03-01 08:07:49'),
(261, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-01 08:07:53'),
(262, 11, 'User Login', 'User Lenny (student) logged in.', '::1', '2026-03-01 08:08:09'),
(263, 11, 'User Logout', 'User Lenny logged out.', '::1', '2026-03-01 08:08:17'),
(264, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-01 08:08:24'),
(265, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-01 08:09:05'),
(266, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-01 08:09:11'),
(267, 11, 'User Login', 'User Lenny (student) logged in.', '::1', '2026-03-01 08:09:19'),
(268, 11, 'Meal Booked', 'Booked Mandazi & Tea on 2026-03-01. Code: ANU-4CC8D9C1', '::1', '2026-03-01 08:09:30'),
(269, 11, 'Meal Booked', 'Booked Fish Fillet & Ugali on 2026-03-01. Code: ANU-47657BD7', '::1', '2026-03-01 08:09:42'),
(270, 11, 'Meal Booked', 'Booked Chips Masala on 2026-03-01. Code: ANU-041AEA5A', '::1', '2026-03-01 08:09:53'),
(271, 11, 'User Logout', 'User Lenny logged out.', '::1', '2026-03-01 08:10:13'),
(272, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-01 08:10:19'),
(273, 5, 'Booking Approved', 'Booking #14 approved.', '::1', '2026-03-01 08:10:34'),
(274, 5, 'Booking Approved', 'Booking #15 approved.', '::1', '2026-03-01 08:10:35'),
(275, 5, 'Booking Approved', 'Booking #16 approved.', '::1', '2026-03-01 08:10:36'),
(276, 5, 'Meal Validated', 'Validated booking ANU-041AEA5A for Caleb\'s Inspiration (Chips Masala)', '::1', '2026-03-01 08:10:59'),
(277, 5, 'Meal Validated', 'Validated booking ANU-47657BD7 for Caleb\'s Inspiration (Fish Fillet & Ugali)', '::1', '2026-03-01 08:11:14'),
(278, 5, 'Meal Validated', 'Validated booking ANU-4CC8D9C1 for Caleb\'s Inspiration (Mandazi & Tea)', '::1', '2026-03-01 08:11:35'),
(279, 5, 'Booking Approved', 'Booking #13 approved.', '::1', '2026-03-01 08:12:25'),
(280, 5, 'Meal Validated', 'Validated booking ANU-2519AF53 for Mitchelle Njoki (Githeri Special)', '::1', '2026-03-01 08:15:36'),
(281, 5, 'Meal Validated', 'Validated booking ANU-32B54D31 for Mitchelle Njoki (Spaghetti Bolognese)', '::1', '2026-03-01 08:16:20'),
(282, 5, 'Meal Validated', 'Validated booking ANU-564C496E for Mitchelle Njoki (Mandazi & Tea)', '::1', '2026-03-01 08:16:35'),
(283, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-01 08:17:26'),
(284, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-03-01 08:17:38'),
(285, 10, 'Meal Booked', 'Booked Fish Fillet & Ugali on 2026-03-01. Code: ANU-C7A74A29', '::1', '2026-03-01 08:18:01'),
(286, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-03-01 08:18:29'),
(287, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-01 08:18:38'),
(288, 5, 'Booking Approved', 'Booking #17 approved.', '::1', '2026-03-01 08:18:47'),
(289, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-01 08:18:54'),
(290, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-03-01 08:19:09'),
(291, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-03-01 08:19:27'),
(292, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-01 08:19:37'),
(293, 5, 'Meal Validated', 'Validated booking ANU-C7A74A29 for Mitchelle Njoki (Fish Fillet & Ugali)', '::1', '2026-03-01 08:21:03'),
(294, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-01 08:21:42'),
(295, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-01 08:22:02'),
(296, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-01 08:22:27'),
(297, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-01 08:22:55'),
(298, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-01 08:23:08'),
(299, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-03-01 08:23:25'),
(300, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-03-01 08:24:00'),
(301, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-01 08:24:09'),
(302, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-01 08:24:26'),
(303, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-01 08:24:32'),
(304, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-03-01 08:24:45'),
(305, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-03-01 08:25:04'),
(306, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-02 06:25:47'),
(307, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-02 06:26:20'),
(308, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-02 06:26:37'),
(309, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-02 06:28:13'),
(310, 10, 'User Login', 'User njoki (student) logged in.', '::1', '2026-03-02 06:28:25'),
(311, 10, 'User Logout', 'User njoki logged out.', '::1', '2026-03-02 06:28:39'),
(312, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-02 06:28:45'),
(313, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-02 06:28:51'),
(314, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-02 06:29:05'),
(315, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-02 06:29:18'),
(316, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-03-02 06:29:31'),
(317, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-03-02 06:29:48'),
(318, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-02 06:29:57'),
(319, 5, 'Meal Validated', 'Code ANU-FBB3BDA5 for Lilian Marube Gechemba (Porridge)', '::1', '2026-03-02 06:30:37'),
(320, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-02 06:31:02'),
(321, 8, 'User Login', 'User Marube (student) logged in.', '::1', '2026-03-02 06:31:13'),
(322, 8, 'User Logout', 'User Marube logged out.', '::1', '2026-03-02 06:36:34'),
(323, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-02 06:36:59'),
(324, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-02 06:37:41'),
(325, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-02 06:37:49'),
(326, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-02 06:44:36'),
(327, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-03-02 06:44:48'),
(328, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-03-02 06:45:19'),
(329, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-02 06:45:27'),
(330, 5, 'Menu Updated', 'Updated menu #17: Mandazi & Tea', '::1', '2026-03-02 06:45:44'),
(331, 5, 'Menu Updated', 'Updated menu #20: Spaghetti Bolognese', '::1', '2026-03-02 06:45:55'),
(332, 5, 'Menu Updated', 'Updated menu #22: Fish Fillet & Ugali', '::1', '2026-03-02 06:46:07'),
(333, 5, 'Menu Updated', 'Updated menu #26: Chicken Wrap', '::1', '2026-03-02 06:46:17'),
(334, 5, 'Menu Updated', 'Updated menu #24: Chips Masala', '::1', '2026-03-02 06:46:28'),
(335, 5, 'Menu Updated', 'Updated menu #23: Githeri Special', '::1', '2026-03-02 06:46:50'),
(336, 5, 'Menu Updated', 'Updated menu #25: Samosa (Beef or Vegetable)', '::1', '2026-03-02 06:47:22'),
(337, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-02 06:47:25'),
(338, 7, 'User Login', 'User Caleb (student) logged in.', '::1', '2026-03-02 06:47:40'),
(339, 7, 'Meal Booked', 'Booked Mandazi & Tea on 2026-03-02. Code: ANU-9BFBA2D4', '::1', '2026-03-02 06:47:56'),
(340, 7, 'Meal Booked', 'Booked Samosa (Beef or Vegetable) on 2026-03-02. Code: ANU-8FE1624F', '::1', '2026-03-02 06:48:35'),
(341, 7, 'User Logout', 'User Caleb logged out.', '::1', '2026-03-02 06:48:42'),
(342, 5, 'User Login', 'User admin (admin) logged in.', '::1', '2026-03-02 06:48:59'),
(343, 5, 'Booking Approved', 'Booking #18 approved.', '::1', '2026-03-02 06:49:19'),
(344, 5, 'Meal Validated', 'Code ANU-9BFBA2D4 for Lennox Caleb (Mandazi & Tea)', '::1', '2026-03-02 06:49:51'),
(345, 5, 'User Logout', 'User admin logged out.', '::1', '2026-03-02 06:50:02'),
(346, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-02 06:50:20'),
(347, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-02 06:50:43'),
(348, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-02 06:51:23'),
(349, 4, 'Settings Updated', 'System settings were modified.', '::1', '2026-03-02 06:51:28'),
(350, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-02 07:16:12'),
(351, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-02 07:16:38'),
(352, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-03 07:56:25'),
(353, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-04 06:04:52'),
(354, 4, 'User Login', 'User superadmin (super_admin) logged in.', '::1', '2026-03-04 07:14:27'),
(355, 4, 'User Logout', 'User superadmin logged out.', '::1', '2026-03-04 12:56:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','student') NOT NULL DEFAULT 'student',
  `student_id` varchar(30) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `role`, `student_id`, `department`, `phone`, `created_at`, `updated_at`) VALUES
(4, 'superadmin', '$2y$10$yHwvcMp1BV.59YqtdaTtbu0G9l5r9Ab.dvGm8q9X/4kBOALbCXms2', 'Super Administrator', 'superadmin@anu.ac.ke', 'super_admin', 'SA001', NULL, NULL, '2026-02-24 06:11:48', '2026-02-24 06:11:48'),
(5, 'admin', '$2y$10$.SLfzj5oucRUA9f6j7ykA.25bnrZrfXqJQ.zb9d5Xd3EBW56H/NR.', 'Manager', 'admin@anu.ac.ke', 'admin', 'AD001', NULL, '', '2026-02-24 06:11:48', '2026-02-24 11:21:36'),
(6, 'John', '$2y$10$qZB4jf0sGmt.CYURCm6ABOp6jdl3nd67Zyb4PMgrXgTrnv2wISoQG', 'John David', 'johndavid@anu.ac.ke', 'student', 'ANU/2026/003', NULL, '0748771607', '2026-02-24 06:11:48', '2026-02-24 11:27:31'),
(7, 'Caleb', '$2y$10$siDFnqNyqlMvO3GUO7bhO.TgxD0Xsca.JJxUJHTzcAZzcH9S9.SGq', 'Lennox Caleb', 'caleblennox39@gmail.com', 'student', 'ANU/2026/001', NULL, '+254748771607', '2026-02-24 06:13:57', '2026-02-24 06:13:57'),
(8, 'Marube', '$2y$10$DX27giT01Yfo7jVeFXzcLed8XcbZOYtMvjs4MgvymQHm/d3Q1loUK', 'Lilian Marube Gechemba', 'lilian@anu.ac.ke', 'student', 'ANU/2026/002', NULL, '+254740348993', '2026-02-24 11:27:13', '2026-02-25 15:54:42'),
(9, 'Moses', '$2y$10$PYAoel8XchDBhL7yqvTi.eAkJ3arHmgJU9fv7/GV.pMtprVEvdkQO', 'Moses Samwel', 'sam@anu.ac.ke', 'student', 'ANU/2026/004', NULL, '+254741447407', '2026-02-25 07:55:04', '2026-02-25 07:55:22'),
(10, 'njoki', '$2y$10$ARAjeHQrpw2RINGF0kAe2OjTECri5AYX5293eVfI19jUQInEszYZ6', 'Mitchelle Njoki', 'njoki@anu.ac.ke', 'student', 'ANU/2026/005', NULL, '+254720771607', '2026-02-27 05:31:55', '2026-02-27 05:31:55'),
(11, 'Lenny', '$2y$10$MUv2zb.P7GjMu4JpA/hMWu4FbtPS5wnd03iQl2PoSPvhqOaVkjc2u', 'Caleb\'s Inspiration', 'lennox@anu.ac.ke', 'student', 'ANU/2026/006', NULL, '+254748771607', '2026-03-01 08:06:01', '2026-03-01 08:06:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `validated_by` (`validated_by`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_menu_id` (`menu_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_date_status` (`date`,`status`),
  ADD KEY `idx_bk_date` (`date`),
  ADD KEY `idx_bk_status` (`status`),
  ADD KEY `idx_bk_created` (`created_at`),
  ADD KEY `idx_bk_date_status` (`date`,`status`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nl_ratelimit` (`booking_id`,`type`,`status`,`sent_at`),
  ADD KEY `idx_nl_user` (`user_id`),
  ADD KEY `idx_nl_sent_at` (`sent_at`);

--
-- Indexes for table `reports_audit`
--
ALTER TABLE `reports_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_format` (`export_format`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `notifications_log`
--
ALTER TABLE `notifications_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reports_audit`
--
ALTER TABLE `reports_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=255;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=356;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `menus`
--
ALTER TABLE `menus`
  ADD CONSTRAINT `menus_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD CONSTRAINT `notifications_log_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports_audit`
--
ALTER TABLE `reports_audit`
  ADD CONSTRAINT `reports_audit_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
