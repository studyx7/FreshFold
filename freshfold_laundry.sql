-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2025 at 11:58 AM
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
-- Database: `freshfold_laundry`
--

-- --------------------------------------------------------

--
-- Table structure for table `issues`
--

CREATE TABLE `issues` (
  `issue_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `issue_type` enum('damaged_item','missing_item','wrong_item','poor_cleaning','delay','pickup_issue','staff_behavior','billing','other') NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `contact_preference` enum('email','phone','sms','in_person') NOT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laundry_items`
--

CREATE TABLE `laundry_items` (
  `item_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `item_description` varchar(200) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price_per_item` decimal(8,2) DEFAULT 0.00,
  `status` enum('received','washing','damaged','missing','completed') DEFAULT 'received'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laundry_items`
--

INSERT INTO `laundry_items` (`item_id`, `request_id`, `item_type`, `item_description`, `quantity`, `price_per_item`, `status`) VALUES
(1, 1, 'Shirt', NULL, 10, 0.00, 'received'),
(2, 1, 'Pants', NULL, 8, 0.00, 'received'),
(3, 1, 'Jeans', NULL, 2, 0.00, 'received');

-- --------------------------------------------------------

--
-- Table structure for table `laundry_requests`
--

CREATE TABLE `laundry_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `bag_number` varchar(20) NOT NULL,
  `pickup_date` date NOT NULL,
  `expected_delivery` date NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `status` enum('submitted','processing','delivered','cancelled') DEFAULT 'submitted',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `payment_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laundry_requests`
--

INSERT INTO `laundry_requests` (`request_id`, `student_id`, `bag_number`, `pickup_date`, `expected_delivery`, `special_instructions`, `total_items`, `status`, `payment_status`, `payment_amount`, `created_at`, `updated_at`) VALUES
(1, 2, 'FL20257217', '2025-08-18', '2025-08-21', '', 0, 'submitted', 'pending', 0.00, '2025-08-14 03:54:07', '2025-08-14 03:54:07');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'price_per_kg', '25.00', 'Price per kilogram of laundry', '2025-06-25 03:49:31'),
(2, 'pickup_time_slots', '09:00-12:00,14:00-17:00', 'Available pickup time slots', '2025-06-25 03:49:31'),
(3, 'delivery_days', '3', 'Standard delivery time in days', '2025-06-25 03:49:31'),
(4, 'max_bag_weight', '10', 'Maximum bag weight in kg', '2025-06-25 03:49:31'),
(5, 'notification_email', 'notifications@freshfold.com', 'Email for system notifications', '2025-06-25 03:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `status_history`
--

CREATE TABLE `status_history` (
  `history_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `user_type` enum('student','staff','admin') NOT NULL,
  `hostel_block` varchar(10) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `gender`, `user_type`, `hostel_block`, `room_number`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'admin', 'admin@freshfold.com', '$2y$10$mTbafK4nXVt.gr.MvmPIouareTFIPSLFprjtoz9HsN5zCC.I6T.hC', 'Admin', NULL, NULL, 'admin', NULL, NULL, '2025-06-25 03:49:30', '2025-07-09 17:02:01', 1),
(2, 'Don', 'donsajikumily@gmail.com', '$2y$10$0XTFk38t.yNa4hHH4g.I3eXcWVZVxydpjClZZgr/rNzlQJfJdFOle', 'Don K Saji', '8281184246', 'male', 'student', '4th Floor', '44', '2025-06-25 04:47:52', '2025-08-14 06:06:57', 1),
(5, 'jebin', 'solo@gmail.com', '$2y$10$zdW13cjClNZoImxRqRPgQ.6atmoHgMgfgXvsPGoMB8ioeEjfDyvNi', 'jebin philip', '9074692499', NULL, 'student', '3rd Floor', 'fs35', '2025-07-17 04:15:02', '2025-07-17 04:15:02', 1),
(6, 'jmathews', 'jmathews@freshfold.com', '$2y$10$fIB30zYFrl3YbWI89MTa1u4amzgaw5iYYM.Ct3srJWCmq9bjDmZy.', 'John Mathews', '9876543210', NULL, 'staff', '', '', '2025-07-30 03:39:09', '2025-07-30 05:37:32', 1),
(7, 'pnair', 'pnair@freshfold.com', '$2y$10$examplehash2', 'Priya Nair', '9123456789', NULL, 'staff', NULL, NULL, '2025-07-30 03:39:09', '2025-07-30 03:42:25', 1),
(8, 'akumar', 'akumar@freshfold.com', '$2y$10$examplehash3', 'Arun Kumar', '9988776655', NULL, 'staff', NULL, NULL, '2025-07-30 03:39:09', '2025-07-30 03:42:25', 1),
(9, 'sjoseph', 'sjoseph@freshfold.com', '$2y$10$examplehash4', 'Sneha Joseph', '9871234567', NULL, 'staff', NULL, NULL, '2025-07-30 03:39:09', '2025-07-30 03:42:25', 1),
(10, 'rmenon', 'rmenon@freshfold.com', '$2y$10$examplehash5', 'Rakesh Menon', '9123987654', NULL, 'staff', NULL, NULL, '2025-07-30 03:39:09', '2025-07-30 03:42:25', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `issues`
--
ALTER TABLE `issues`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `idx_issue_student` (`student_id`),
  ADD KEY `idx_issue_status` (`status`),
  ADD KEY `idx_issue_request` (`request_id`);

--
-- Indexes for table `laundry_items`
--
ALTER TABLE `laundry_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `laundry_requests`
--
ALTER TABLE `laundry_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `bag_number` (`bag_number`),
  ADD KEY `idx_request_status` (`status`),
  ADD KEY `idx_request_student` (`student_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notification_user` (`user_id`,`is_read`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `status_history`
--
ALTER TABLE `status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `issues`
--
ALTER TABLE `issues`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laundry_items`
--
ALTER TABLE `laundry_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `laundry_requests`
--
ALTER TABLE `laundry_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `status_history`
--
ALTER TABLE `status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `issues`
--
ALTER TABLE `issues`
  ADD CONSTRAINT `issues_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `issues_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `laundry_requests` (`request_id`);

--
-- Constraints for table `laundry_items`
--
ALTER TABLE `laundry_items`
  ADD CONSTRAINT `laundry_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `laundry_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `laundry_requests`
--
ALTER TABLE `laundry_requests`
  ADD CONSTRAINT `laundry_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `status_history`
--
ALTER TABLE `status_history`
  ADD CONSTRAINT `status_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `laundry_requests` (`request_id`),
  ADD CONSTRAINT `status_history_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
