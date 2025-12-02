-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 02, 2025 at 12:27 PM
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
-- Database: `tms_nhom4`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `ticket_type` enum('one_way','round_trip') DEFAULT 'one_way',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `seat_numbers` varchar(255) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `payment_method` enum('online','counter') DEFAULT 'online',
  `cancel_request` tinyint(1) NOT NULL DEFAULT 0,
  `staff_user_id` int(11) DEFAULT NULL,
  `confirmation_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `trip_id`, `promotion_id`, `ticket_type`, `booking_date`, `quantity`, `total_price`, `seat_numbers`, `status`, `payment_status`, `payment_method`, `cancel_request`, `staff_user_id`, `confirmation_time`) VALUES
(1, 4, 1, NULL, 'one_way', '2025-11-28 15:39:56', 2, 700000.00, '1,2', 'confirmed', 'paid', 'online', 0, NULL, NULL),
(3, 10, 5, NULL, 'one_way', '2025-12-02 10:28:10', 1, 200000.00, 'A1', 'pending', 'pending', 'counter', 0, NULL, NULL),
(4, 10, 5, NULL, 'one_way', '2025-12-02 10:28:18', 1, 200000.00, 'A1', 'pending', 'paid', '', 0, NULL, NULL),
(5, 10, 10, NULL, 'one_way', '2025-12-02 10:29:36', 1, 400000.00, 'A1', 'pending', 'paid', '', 0, NULL, NULL),
(6, 10, 11, NULL, 'one_way', '2025-12-02 10:42:36', 1, 380000.00, 'A1', 'pending', 'paid', '', 0, NULL, NULL),
(7, 10, 14, NULL, 'one_way', '2025-12-02 10:54:43', 1, 300000.00, 'C3', 'pending', 'pending', 'counter', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `address`, `date_of_birth`, `created_at`) VALUES
(1, 4, '123 Đường ABC, Quận 1, TP.HCM', '1990-05-15', '2025-11-26 04:15:16'),
(2, 5, '456 Đường XYZ, Quận 2, TP.HCM', '1985-12-20', '2025-11-26 04:15:16'),
(3, 6, '789 Đường DEF, Quận 3, TP.HCM', '1992-08-10', '2025-11-26 04:15:16'),
(5, 8, NULL, NULL, '2025-11-26 05:39:47'),
(6, 10, NULL, NULL, '2025-11-26 06:42:50'),
(7, 11, NULL, NULL, '2025-11-28 14:21:22');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `promotion_code` varchar(50) NOT NULL,
  `promotion_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`id`, `promotion_code`, `promotion_name`, `description`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `start_date`, `end_date`, `usage_limit`, `used_count`, `status`, `created_at`) VALUES
(1, 'WELCOME2024', 'Khuyến mãi chào năm mới', 'Giảm giá cho khách hàng đặt vé', 'percentage', 10.00, 200000.00, 50000.00, '2024-01-01', '2025-12-31', 100, 15, 'active', '2025-11-26 04:15:16'),
(2, 'SUMMER50K', 'Giảm 50K mùa hè', 'Giảm trực tiếp 50K cho đơn hàng từ 300K', 'fixed', 50000.00, 300000.00, 50000.00, '2024-06-01', '2025-08-31', 200, 45, 'active', '2025-11-26 04:15:16'),
(3, 'FIRSTTRIP', 'Giảm giá chuyến đầu tiên', 'Dành cho khách hàng mới đặt chuyến đầu tiên', 'percentage', 15.00, 100000.00, 75000.00, '2024-01-01', '2025-12-31', NULL, 23, 'active', '2025-11-26 04:15:16'),
(4, 'VIP20', 'Giảm 20% cho khách VIP', 'Chương trình dành cho khách hàng thân thiết', 'percentage', 20.00, 500000.00, 100000.00, '2024-01-01', '2025-12-31', 50, 12, 'active', '2025-11-26 04:15:16'),
(5, 'test1', 'test', '', 'fixed', 36000.00, 3.00, NULL, '2025-12-02', '2025-12-30', NULL, 0, 'active', '2025-12-02 11:04:29');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int(11) NOT NULL,
  `province_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `province_name`, `status`, `created_at`) VALUES
(1, 'Hà Nội', 'active', '2025-11-26 04:15:16'),
(2, 'Huế', 'active', '2025-11-26 04:15:16'),
(3, 'Quảng Ninh', 'active', '2025-11-26 04:15:16'),
(4, 'Cao Bằng', 'active', '2025-11-26 04:15:16'),
(5, 'Lạng Sơn', 'active', '2025-11-26 04:15:16'),
(6, 'Lai Châu', 'active', '2025-11-26 04:15:16'),
(7, 'Điện Biên', 'active', '2025-11-26 04:15:16'),
(8, 'Sơn La', 'active', '2025-11-26 04:15:16'),
(9, 'Thanh Hóa', 'active', '2025-11-26 04:15:16'),
(10, 'Nghệ An', 'active', '2025-11-26 04:15:16'),
(11, 'Hà Tĩnh', 'active', '2025-11-26 04:15:16'),
(12, 'Tuyên Quang', 'active', '2025-11-26 04:15:16'),
(13, 'Lào Cai', 'active', '2025-11-26 04:15:16'),
(14, 'Thái Nguyên', 'active', '2025-11-26 04:15:16'),
(15, 'Phú Thọ', 'active', '2025-11-26 04:15:16'),
(16, 'Bắc Ninh', 'active', '2025-11-26 04:15:16'),
(17, 'Hưng Yên', 'active', '2025-11-26 04:15:16'),
(18, 'Hải Phòng', 'active', '2025-11-26 04:15:16'),
(19, 'Ninh Bình', 'active', '2025-11-26 04:15:16'),
(20, 'Quảng Trị', 'active', '2025-11-26 04:15:16'),
(21, 'Đà Nẵng', 'active', '2025-11-26 04:15:16'),
(22, 'Quảng Ngãi', 'active', '2025-11-26 04:15:16'),
(23, 'Gia Lai', 'active', '2025-11-26 04:15:16'),
(24, 'Khánh Hòa', 'active', '2025-11-26 04:15:16'),
(25, 'Lâm Đồng', 'active', '2025-11-26 04:15:16'),
(26, 'Đắk Lắk', 'active', '2025-11-26 04:15:16'),
(27, 'Thành phố Hồ Chí Minh', 'active', '2025-11-26 04:15:16'),
(28, 'Đồng Nai', 'active', '2025-11-26 04:15:16'),
(29, 'Tây Ninh', 'active', '2025-11-26 04:15:16'),
(30, 'Cần Thơ', 'active', '2025-11-26 04:15:16'),
(31, 'Vĩnh Long', 'active', '2025-11-26 04:15:16'),
(32, 'Đồng Tháp', 'active', '2025-11-26 04:15:16'),
(33, 'Cà Mau', 'active', '2025-11-26 04:15:16'),
(34, 'An Giang', 'active', '2025-11-26 04:15:16');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `hire_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `salary`, `hire_date`, `created_at`) VALUES
(1, 2, 15000000.00, '2023-01-15', '2025-11-26 04:15:16'),
(2, 3, 8000000.00, '2023-06-20', '2025-11-26 04:15:16');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `departure_province_id` int(11) NOT NULL,
  `destination_province_id` int(11) NOT NULL,
  `departure_time` datetime NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `available_seats` int(11) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled','paused','full') NOT NULL DEFAULT 'scheduled',
  `ticket_type` enum('one_way','round_trip') DEFAULT 'one_way',
  `return_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `departure_province_id`, `destination_province_id`, `departure_time`, `price`, `available_seats`, `total_seats`, `status`, `ticket_type`, `return_time`, `created_at`) VALUES
(1, 27, 25, '2026-01-30 07:00:00', 350000.00, 15, 20, 'cancelled', 'one_way', NULL, '2025-11-26 04:15:16'),
(4, 27, 30, '2026-02-02 06:00:00', 180000.00, 18, 25, 'paused', 'round_trip', '2025-02-04 06:00:00', '2025-11-26 04:15:16'),
(5, 27, 29, '2026-02-03 09:00:00', 200000.00, 8, 16, 'scheduled', 'one_way', NULL, '2025-11-26 04:15:16'),
(9, 16, 30, '2025-11-29 06:30:00', 450000.00, 44, 45, 'completed', 'one_way', NULL, '2025-11-28 15:01:43'),
(10, 34, 16, '2025-12-03 06:30:00', 400000.00, 44, 45, 'scheduled', 'one_way', NULL, '2025-12-02 10:29:33'),
(11, 34, 16, '2025-12-02 12:45:00', 380000.00, 44, 45, 'paused', 'one_way', NULL, '2025-12-02 10:42:34'),
(12, 34, 16, '2025-12-02 15:30:00', 380000.00, 45, 45, 'paused', 'one_way', NULL, '2025-12-02 10:45:07'),
(13, 33, 28, '2025-12-02 12:45:00', 370000.00, 45, 45, 'paused', 'one_way', NULL, '2025-12-02 10:48:42'),
(14, 33, 28, '2025-12-02 15:30:00', 300000.00, 44, 45, 'paused', 'round_trip', '2025-12-06 10:29:00', '2025-12-02 10:50:57'),
(15, 23, 1, '2025-12-03 18:04:00', 36000.00, 36, 36, 'scheduled', 'one_way', NULL, '2025-12-02 11:04:58'),
(16, 23, 1, '2025-12-03 18:05:00', 36000.00, 36, 36, 'scheduled', 'round_trip', '2025-12-04 18:05:00', '2025-12-02 11:05:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `gender` enum('Nam','Nữ','Khác') DEFAULT NULL,
  `role` enum('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expire` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `phone`, `address`, `gender`, `role`, `status`, `reset_token`, `reset_expire`, `created_at`, `updated_at`) VALUES
(1, 'admin', '123', 'admin@tms.com', 'System Administrator', '0123456789', NULL, NULL, 'admin', 'active', NULL, NULL, '2025-11-26 04:15:16', '2025-11-28 14:21:01'),
(2, 'staff1', '123', 'staff1@tms.com', 'Nguyễn Văn A', '0987654321', NULL, NULL, 'staff', 'active', NULL, NULL, '2025-11-26 04:15:16', '2025-11-28 14:21:04'),
(3, 'staff2', 'password', 'staff2@tms.com', 'Trần Thị B', '0912345678', NULL, NULL, 'staff', 'active', NULL, NULL, '2025-11-26 04:15:16', '2025-11-26 05:53:28'),
(4, 'customer1', 'password', 'customer1@gmail.com', 'Lê Văn C', '0909123456', NULL, NULL, 'customer', 'active', NULL, NULL, '2025-11-26 04:15:16', '2025-11-26 04:15:16'),
(5, 'customer2', 'password', 'customer2@gmail.com', 'Phạm Thị D', '0918123456', NULL, NULL, 'customer', 'active', NULL, NULL, '2025-11-26 04:15:16', '2025-11-26 04:15:16'),
(6, 'customer3', 'password', 'customer3@gmail.com', 'Hoàng Văn E', '0927123456', NULL, NULL, 'customer', 'active', NULL, NULL, '2025-11-26 04:15:16', '2025-11-26 04:15:16'),
(8, 'user1', '123456', 'a1@gmail.com', 'user1', '012312414', NULL, NULL, 'customer', 'active', '95017da770e2f0c6a8d564abfdfc65dd', '2025-11-27 06:44:33', '2025-11-26 05:39:47', '2025-11-26 05:44:33'),
(10, 'giabao', '123', 'bao@gmail.com', 'giabao', '012312411', NULL, NULL, 'customer', 'active', NULL, NULL, '2025-11-26 06:42:50', '2025-11-28 14:21:07'),
(11, 'khanha', '123456', 'ha@gmail.com', 'khanha', '12312312312', NULL, NULL, 'customer', 'active', NULL, NULL, '2025-11-28 14:21:22', '2025-11-28 15:26:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `promotion_id` (`promotion_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promotion_code` (`promotion_code`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `departure_province_id` (`departure_province_id`),
  ADD KEY `destination_province_id` (`destination_province_id`);

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
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  ADD CONSTRAINT `bookings_fk_promo` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`),
  ADD CONSTRAINT `bookings_fk_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`),
  ADD CONSTRAINT `bookings_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`departure_province_id`) REFERENCES `provinces` (`id`),
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`destination_province_id`) REFERENCES `provinces` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
