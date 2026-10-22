-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 10, 2026 at 02:53 AM
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
-- Database: `ojt_dtr`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `details` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `student_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'auth.login_success', 'email=christianapajes@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-08 02:17:45'),
(2, 1, 'auth.password_reset_requested', 'email_sent=0', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-08 08:56:31'),
(3, 1, 'auth.login_success', 'email=christianapajes@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-08 08:57:52'),
(4, 1, 'auth.login_success', 'email=christianapajes@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-10 00:46:00'),
(5, 1, 'dtr.create_or_replace', 'date=2026-04-10', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-10 00:46:18');

-- --------------------------------------------------------

--
-- Table structure for table `dtr_records`
--

CREATE TABLE `dtr_records` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `break_duration` decimal(4,2) DEFAULT 0.00,
  `raw_hours` decimal(6,2) DEFAULT 0.00,
  `regular_hours` decimal(6,2) DEFAULT 0.00,
  `overtime_hours` decimal(6,2) DEFAULT 0.00,
  `total_hours` decimal(6,2) DEFAULT 0.00,
  `status` enum('valid','incomplete','invalid','absent') DEFAULT 'incomplete',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dtr_records`
--

INSERT INTO `dtr_records` (`id`, `student_id`, `record_date`, `time_in`, `time_out`, `break_duration`, `raw_hours`, `regular_hours`, `overtime_hours`, `total_hours`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-03-02', '09:00:00', '17:30:00', 1.00, 8.50, 7.50, 0.00, 7.50, 'valid', '', '2026-04-06 05:23:36', '2026-04-06 05:23:36'),
(2, 1, '2026-03-03', '08:30:00', '14:30:00', 1.00, 6.00, 5.00, 0.00, 5.00, 'valid', '', '2026-04-06 05:27:36', '2026-04-06 05:27:36'),
(3, 1, '2026-03-04', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:27:59', '2026-04-06 05:27:59'),
(4, 1, '2026-03-05', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:28:27', '2026-04-06 05:28:27'),
(5, 1, '2026-03-09', '08:30:00', '14:30:00', 1.00, 6.00, 5.00, 0.00, 5.00, 'valid', '', '2026-04-06 05:28:47', '2026-04-06 05:30:13'),
(6, 1, '2026-03-06', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:29:40', '2026-04-06 05:29:40'),
(9, 1, '2026-03-10', '11:30:00', '17:30:00', 1.00, 6.00, 5.00, 0.00, 5.00, 'valid', '', '2026-04-06 05:31:03', '2026-04-06 05:31:03'),
(10, 1, '2026-03-11', '09:00:00', '17:30:00', 1.00, 8.50, 7.50, 0.00, 7.50, 'valid', '', '2026-04-06 05:31:26', '2026-04-06 05:31:26'),
(11, 1, '2026-03-12', '09:00:00', '17:30:00', 1.00, 8.50, 7.50, 0.00, 7.50, 'valid', '', '2026-04-06 05:32:03', '2026-04-06 05:32:03'),
(12, 1, '2026-03-13', '08:30:00', '15:00:00', 1.00, 6.50, 5.50, 0.00, 5.50, 'valid', '', '2026-04-06 05:32:25', '2026-04-06 05:32:25'),
(13, 1, '2026-03-16', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:32:57', '2026-04-06 05:32:57'),
(14, 1, '2026-03-17', '09:00:00', '17:30:00', 1.00, 8.50, 7.50, 0.00, 7.50, 'valid', '', '2026-04-06 05:33:26', '2026-04-06 05:33:26'),
(15, 1, '2026-03-18', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:34:27', '2026-04-06 05:34:27'),
(16, 1, '2026-03-19', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:34:40', '2026-04-06 05:34:40'),
(17, 1, '2026-03-23', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:35:05', '2026-04-06 05:35:05'),
(18, 1, '2026-03-24', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:35:18', '2026-04-06 05:35:18'),
(19, 1, '2026-03-25', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:35:36', '2026-04-06 05:35:36'),
(20, 1, '2026-03-26', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:35:58', '2026-04-06 05:35:58'),
(21, 1, '2026-03-27', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:36:20', '2026-04-06 05:36:20'),
(22, 1, '2026-03-30', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:36:36', '2026-04-06 05:36:36'),
(23, 1, '2026-03-31', '08:30:00', '15:00:00', 1.00, 6.50, 5.50, 0.00, 5.50, 'valid', '', '2026-04-06 05:36:48', '2026-04-06 05:37:37'),
(24, 1, '2026-04-01', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:37:10', '2026-04-06 05:37:10'),
(25, 1, '2026-04-06', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-06 05:37:19', '2026-04-06 05:37:19'),
(27, 1, '2026-04-07', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-07 00:57:14', '2026-04-07 00:57:14'),
(28, 1, '2026-04-08', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-08 00:58:42', '2026-04-08 01:43:45'),
(29, 1, '2026-04-10', '08:30:00', '17:30:00', 1.00, 9.00, 8.00, 0.00, 8.00, 'valid', '', '2026-04-10 00:46:18', '2026-04-10 00:46:18');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('regular','special_non_working','special_working') NOT NULL DEFAULT 'regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `student_id`, `holiday_date`, `name`, `type`) VALUES
(1, 1, '2026-04-02', 'Maundy Thursday', 'regular'),
(2, 1, '2026-03-20', 'Eid\'l Fitr (Feast of Ramadhan)', 'regular'),
(3, 1, '2026-04-03', 'Good Friday', 'regular'),
(4, 1, '2026-04-04', 'Black Saturday', 'regular'),
(5, 1, '2026-04-09', 'Araw ng Kagitingan (Day of Valor)', 'regular'),
(6, 1, '2026-05-01', 'Labor Day', 'regular'),
(7, 1, '2026-01-01', 'New Year\'s Day', 'regular'),
(10, 1, '2026-06-12', 'Independence Day', 'regular'),
(11, 1, '2026-08-26', 'National Heroes Day', 'regular'),
(12, 1, '2026-11-30', 'Bonifacio Day', 'regular'),
(13, 1, '2026-12-25', 'Christmas Day', 'regular'),
(14, 1, '2026-12-30', 'Rizal Day', 'regular'),
(15, 1, '2026-02-10', 'Chinese New Year', 'special_non_working'),
(16, 1, '2026-02-25', 'EDSA People Power Revolution', 'special_non_working'),
(17, 1, '2026-08-21', 'Ninoy Aquino Day', 'special_non_working'),
(18, 1, '2026-10-31', 'All Saints\' Day Eve', 'special_non_working'),
(19, 1, '2026-11-01', 'All Saints\' Day', 'special_non_working'),
(20, 1, '2026-11-02', 'All Souls\' Day', 'special_non_working'),
(21, 1, '2026-12-08', 'Feast of the Immaculate Conception', 'special_non_working'),
(22, 1, '2026-12-24', 'Christmas Eve', 'special_non_working'),
(23, 1, '2026-12-31', 'New Year\'s Eve', 'special_non_working');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `student_id`, `token_hash`, `expires_at`, `used_at`, `request_ip`, `user_agent`, `created_at`) VALUES
(1, 1, '774fb03fa294c3693365599074766ebd0dce24faa6f61d9f9bcf0c40a60325d5', '2026-04-08 17:56:29', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-08 08:56:29');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `default_time_in` time NOT NULL DEFAULT '08:00:00',
  `default_time_out` time NOT NULL DEFAULT '17:00:00',
  `break_duration` decimal(4,2) NOT NULL DEFAULT 1.00,
  `max_hours_per_day` decimal(4,2) NOT NULL DEFAULT 8.00,
  `allow_overtime` tinyint(1) NOT NULL DEFAULT 0,
  `max_overtime_per_day` decimal(4,2) NOT NULL DEFAULT 2.00,
  `rest_days` varchar(20) DEFAULT '0,6'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `student_id`, `start_date`, `default_time_in`, `default_time_out`, `break_duration`, `max_hours_per_day`, `allow_overtime`, `max_overtime_per_day`, `rest_days`) VALUES
(1, 1, '2026-04-06', '08:30:00', '17:30:00', 1.00, 8.00, 0, 2.00, '0,6');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `required_hours` int(11) DEFAULT 600,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `email`, `password`, `required_hours`, `created_at`) VALUES
(0, 'System', 'system@ojt-dtr.local', '', 0, '2026-04-06 05:19:09'),
(1, 'Christian A. Pajes', 'christianapajes@gmail.com', '$2y$10$4Fv0sTSZE5eKCTiVhGAg2.NBNFOQMsDnlfJUsFtJkRlTN2fmODvO.', 600, '2026-04-06 05:21:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_student_time` (`student_id`,`created_at`),
  ADD KEY `idx_activity_action_time` (`action`,`created_at`);

--
-- Indexes for table `dtr_records`
--
ALTER TABLE `dtr_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_record` (`student_id`,`record_date`),
  ADD KEY `idx_dtr_student_date` (`student_id`,`record_date`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_holiday` (`student_id`,`holiday_date`),
  ADD KEY `idx_holidays_student_date` (`student_id`,`holiday_date`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_reset_token_hash` (`token_hash`),
  ADD KEY `idx_reset_student_time` (`student_id`,`created_at`),
  ADD KEY `idx_reset_expiry` (`expires_at`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `dtr_records`
--
ALTER TABLE `dtr_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dtr_records`
--
ALTER TABLE `dtr_records`
  ADD CONSTRAINT `dtr_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `holidays`
--
ALTER TABLE `holidays`
  ADD CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_reset_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
