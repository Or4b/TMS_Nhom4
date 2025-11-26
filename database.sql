-- TMS_Nhom4 Database Structure for phpMyAdmin
-- Updated by Gemini: Fix column names & Enum matching
-- Date: 2025-11-26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Create database
CREATE DATABASE IF NOT EXISTS `tms_nhom4` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tms_nhom4`;

-- --------------------------------------------------------

-- Table structure for table `provinces`
-- FIX: Đổi 'name' thành 'province_name' để khớp với code PHP gợi ý
CREATE TABLE `provinces` (
  `id` int(11) NOT NULL,
  `province_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','staff','customer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `customers`
CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `staff`
CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `hire_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `trips`
-- FIX: ticket_type đổi thành underscore (_) để khớp với HTML value
CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `departure_province_id` int(11) NOT NULL,
  `destination_province_id` int(11) NOT NULL,
  `departure_time` datetime NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `available_seats` int(11) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled','paused','full') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `ticket_type` enum('one_way','round_trip') COLLATE utf8mb4_unicode_ci DEFAULT 'one_way',
  `return_time` datetime NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `promotions`
CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `promotion_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `promotion_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `bookings`
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `number_of_seats` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Indexes for dumped tables

ALTER TABLE `provinces` ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `departure_province_id` (`departure_province_id`),
  ADD KEY `destination_province_id` (`destination_province_id`);

ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promotion_code` (`promotion_code`);

ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `promotion_id` (`promotion_id`);

-- --------------------------------------------------------

-- AUTO_INCREMENT for dumped tables

ALTER TABLE `provinces` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `customers` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `staff` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `trips` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `promotions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `bookings` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

-- --------------------------------------------------------

-- Constraints for dumped tables

ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`departure_province_id`) REFERENCES `provinces` (`id`),
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`destination_province_id`) REFERENCES `provinces` (`id`);

ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`);

 -- Thêm cột lưu token reset password và thời gian hết hạn
ALTER TABLE `users`
ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `status`,
ADD COLUMN `reset_expire` DATETIME NULL DEFAULT NULL AFTER `reset_token`; 

-- --------------------------------------------------------

-- Insert sample data

INSERT INTO `provinces` (`id`, `province_name`, `status`, `created_at`) VALUES
(1, 'Hà Nội', 'active', NOW()),
(2, 'Huế', 'active', NOW()),
(3, 'Quảng Ninh', 'active', NOW()),
(4, 'Cao Bằng', 'active', NOW()),
(5, 'Lạng Sơn', 'active', NOW()),
(6, 'Lai Châu', 'active', NOW()),
(7, 'Điện Biên', 'active', NOW()),
(8, 'Sơn La', 'active', NOW()),
(9, 'Thanh Hóa', 'active', NOW()),
(10, 'Nghệ An', 'active', NOW()),
(11, 'Hà Tĩnh', 'active', NOW()),
(12, 'Tuyên Quang', 'active', NOW()),
(13, 'Lào Cai', 'active', NOW()),
(14, 'Thái Nguyên', 'active', NOW()),
(15, 'Phú Thọ', 'active', NOW()),
(16, 'Bắc Ninh', 'active', NOW()),
(17, 'Hưng Yên', 'active', NOW()),
(18, 'Hải Phòng', 'active', NOW()),
(19, 'Ninh Bình', 'active', NOW()),
(20, 'Quảng Trị', 'active', NOW()),
(21, 'Đà Nẵng', 'active', NOW()),
(22, 'Quảng Ngãi', 'active', NOW()),
(23, 'Gia Lai', 'active', NOW()),
(24, 'Khánh Hòa', 'active', NOW()),
(25, 'Lâm Đồng', 'active', NOW()),
(26, 'Đắk Lắk', 'active', NOW()),
(27, 'Thành phố Hồ Chí Minh', 'active', NOW()),
(28, 'Đồng Nai', 'active', NOW()),
(29, 'Tây Ninh', 'active', NOW()),
(30, 'Cần Thơ', 'active', NOW()),
(31, 'Vĩnh Long', 'active', NOW()),
(32, 'Đồng Tháp', 'active', NOW()),
(33, 'Cà Mau', 'active', NOW()),
(34, 'An Giang', 'active', NOW());

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `phone`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'password', 'admin@tms.com', 'System Administrator', '0123456789', 'admin', 'active', NOW(), NOW()),
(2, 'staff1', 'password', 'staff1@tms.com', 'Nguyễn Văn A', '0987654321', 'staff', 'active', NOW(), NOW()),
(3, 'staff2', 'password', 'staff2@tms.com', 'Trần Thị B', '0912345678', 'staff', 'active', NOW(), NOW()),
(4, 'customer1', 'password', 'customer1@gmail.com', 'Lê Văn C', '0909123456', 'customer', 'active', NOW(), NOW()),
(5, 'customer2', 'password', 'customer2@gmail.com', 'Phạm Thị D', '0918123456', 'customer', 'active', NOW(), NOW()),
(6, 'customer3', 'password', 'customer3@gmail.com', 'Hoàng Văn E', '0927123456', 'customer', 'active', NOW(), NOW());

INSERT INTO `staff` (`id`, `user_id`, `salary`, `hire_date`, `created_at`) VALUES
(1, 2, 15000000.00, '2023-01-15', NOW()),
(2, 3, 8000000.00, '2023-06-20', NOW());

INSERT INTO `customers` (`id`, `user_id`, `address`, `date_of_birth`, `created_at`) VALUES
(1, 4, '123 Đường ABC, Quận 1, TP.HCM', '1990-05-15', NOW()),
(2, 5, '456 Đường XYZ, Quận 2, TP.HCM', '1985-12-20', NOW()),
(3, 6, '789 Đường DEF, Quận 3, TP.HCM', '1992-08-10', NOW());

-- FIX: Cập nhật ticket_type dùng underscore (one_way, round_trip)
INSERT INTO `trips` (`id`, `departure_province_id`, `destination_province_id`, `departure_time`, `price`, `available_seats`, `total_seats`, `status`, `ticket_type`, `return_time`, `created_at`) VALUES
(1, 27, 25, '2025-02-01 07:00:00', 350000.00, 15, 20, 'scheduled', 'one_way', NULL, NOW()), 
(2, 27, 24, '2025-02-01 21:00:00', 280000.00, 8, 12, 'scheduled', 'round_trip', '2025-02-03 21:00:00', NOW()),
(3, 27, 28, '2025-02-02 08:30:00', 120000.00, 25, 30, 'scheduled', 'one_way', NULL, NOW()),
(4, 27, 30, '2025-02-02 06:00:00', 180000.00, 18, 25, 'scheduled', 'round_trip', '2025-02-04 06:00:00', NOW()),
(5, 27, 29, '2025-02-03 09:00:00', 200000.00, 10, 16, 'scheduled', 'one_way', NULL, NOW());

-- FIX: Cập nhật ngày hết hạn lên 2025/2026 để test không bị lỗi hết hạn
INSERT INTO `promotions` (`id`, `promotion_code`, `promotion_name`, `description`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `start_date`, `end_date`, `usage_limit`, `used_count`, `status`, `created_at`) VALUES
(1, 'WELCOME2024', 'Khuyến mãi chào năm mới', 'Giảm giá cho khách hàng đặt vé', 'percentage', 10.00, 200000.00, 50000.00, '2024-01-01', '2025-12-31', 100, 15, 'active', NOW()),
(2, 'SUMMER50K', 'Giảm 50K mùa hè', 'Giảm trực tiếp 50K cho đơn hàng từ 300K', 'fixed', 50000.00, 300000.00, 50000.00, '2024-06-01', '2025-08-31', 200, 45, 'active', NOW()),
(3, 'FIRSTTRIP', 'Giảm giá chuyến đầu tiên', 'Dành cho khách hàng mới đặt chuyến đầu tiên', 'percentage', 15.00, 100000.00, 75000.00, '2024-01-01', '2025-12-31', NULL, 23, 'active', NOW()),
(4, 'VIP20', 'Giảm 20% cho khách VIP', 'Chương trình dành cho khách hàng thân thiết', 'percentage', 20.00, 500000.00, 100000.00, '2024-01-01', '2025-12-31', 50, 12, 'active', NOW());

INSERT INTO `bookings` (`id`, `customer_id`, `trip_id`, `promotion_id`, `booking_date`, `number_of_seats`, `total_amount`, `status`, `payment_status`) VALUES
(1, 1, 1, 1, '2024-01-15 10:30:00', 2, 630000.00, 'confirmed', 'paid'),
(2, 2, 2, 2, '2024-01-16 14:20:00', 1, 230000.00, 'confirmed', 'paid'),
(3, 3, 3, NULL, '2024-01-17 09:15:00', 3, 360000.00, 'pending', 'pending'),
(4, 1, 4, 3, '2024-01-18 16:45:00', 2, 306000.00, 'confirmed', 'paid'),
(5, 2, 5, NULL, '2024-01-19 11:20:00', 1, 200000.00, 'cancelled', 'failed');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;