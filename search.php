<?php
session_start();
$conn = new mysqli("localhost", "root", "", "webdatxe");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

// Lấy tham số tìm kiếm
$origin = isset($_GET['origin']) ? intval($_GET['origin']) : 0;
$destination = isset($_GET['destination']) ? intval($_GET['destination']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$trip_type = isset($_GET['trip_type']) ? $_GET['trip_type'] : 'one_way';

// Lấy tên địa điểm
$origin_name = '';
$destination_name = '';
if ($origin > 0) {
    $result = $conn->query("SELECT province_name FROM provinces WHERE province_id = $origin");
    if ($row = $result->fetch_assoc()) {
        $origin_name = $row['province_name'];
    }
}
if ($destination > 0) {
    $result = $conn->query("SELECT province_name FROM provinces WHERE province_id = $destination");
    if ($row = $result->fetch_assoc()) {
        $destination_name = $row['province_name'];
    }
}

// Format ngày hiển thị
$date_display = '';
if ($date) {
    $timestamp = strtotime($date);
    $days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    $day_name = $days[date('w', $timestamp)];
    $date_display = $day_name . ', ' . date('d/m/Y', $timestamp);
}

// Truy vấn danh sách chuyến xe
$trips = [];
if ($origin > 0 && $destination > 0) {
    $query = "SELECT r.route_id, r.base_price, r.is_round_trip,
                     o.province_name AS origin, 
                     d.province_name AS destination,
                     s.schedule_id, s.departure_time, s.total_seats, s.available_seats
              FROM routes r
              JOIN provinces o ON r.origin_id = o.province_id
              JOIN provinces d ON r.destination_id = d.province_id
              JOIN schedules s ON r.route_id = s.route_id
              WHERE r.origin_id = $origin 
              AND r.destination_id = $destination
              AND DATE(s.departure_time) = '$date'
              ORDER BY s.departure_time ASC";
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Giả sử mỗi chuyến đi mất khoảng 7-8 giờ (có thể tùy chỉnh theo tuyến)
            $departure_timestamp = strtotime($row['departure_time']);
            
            // Tính thời gian di chuyển dựa vào khoảng cách (có thể cải tiến sau)
            $duration_hours = rand(7, 9); // Tạm thời random 7-9 giờ
            $duration_minutes = rand(0, 45); // Random thêm phút
            
            $arrival_timestamp = $departure_timestamp + ($duration_hours * 3600) + ($duration_minutes * 60);
            $row['arrival_time'] = date('H:i:s', $arrival_timestamp);
            $row['duration'] = sprintf("%02d:%02d:00", $duration_hours, $duration_minutes);
            
            $trips[] = $row;
        }
    }
}

// Hàm format thời gian
function formatDuration($duration) {
    $parts = explode(':', $duration);
    $hours = intval($parts[0]);
    $minutes = intval($parts[1]);
    
    if ($hours > 0 && $minutes > 0) {
        return $hours . ' giờ ' . $minutes . ' phút';
    } elseif ($hours > 0) {
        return $hours . ' giờ';
    } else {
        return $minutes . ' phút';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tìm kiếm chuyến xe - BusTicket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
        }
        
        /* User Dropdown Styles */
        .user-dropdown {
            position: relative;
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 220px;
            padding: 0.5rem;
            z-index: 1000;
        }
        .user-dropdown:hover .dropdown-menu {
            display: block;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #334155;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: background 0.2s;
            font-size: 0.9375rem;
        }
        .dropdown-item:hover {
            background: #f1f5f9;
        }
        .dropdown-item.logout {
            color: #dc2626;
        }
        .dropdown-item.logout:hover {
            background: #fee2e2;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="bg-[#3d4d5c] text-white sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="text-2xl font-bold tracking-wide">VéXe</a>
                </div>

                <!-- Navigation -->
                <nav class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="hover:text-blue-300 transition">Trang chủ</a>
                    <a href="my-tickets.php" class="hover:text-blue-300 transition">Vé của tôi</a>
                    <a href="#" class="hover:text-blue-300 transition">Khuyến mãi</a>
                    <a href="#" class="hover:text-blue-300 transition">Hỗ trợ</a>
                </nav>

                <!-- User Actions -->
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
                        <div class="user-dropdown flex items-center space-x-3 cursor-pointer">
                            <div class="user-avatar">
                                <?php 
                                $username = $_SESSION['username'] ?? $_SESSION['admin_username'];
                                echo strtoupper(substr($username, 0, 2)); 
                                ?>
                            </div>
                            <span class="font-medium"><?php echo htmlspecialchars($username); ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                            
                            <div class="dropdown-menu">
                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                    <a href="admin/dashboard.php" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i>
                                        <span>Quản trị</span>
                                    </a>
                                <?php endif; ?>
                                <a href="profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i>
                                    <span>Hồ sơ cá nhân</span>
                                </a>
                                <a href="change-pass.php" class="dropdown-item">
                                    <i class="fas fa-lock"></i>
                                    <span>Đổi mật khẩu</span>
                                </a>
                                <a href="logout.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Đăng xuất</span>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <a href="login.php" class="hover:text-blue-300 transition">Đăng nhập</a>
                            <a href="register.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">Đăng ký</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-8 max-w-6xl">
        
        <!-- Search Summary Card -->
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Kết quả tìm kiếm chuyến xe</h2>
            
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2 text-gray-700">
                    <i class="fas fa-map-marker-alt text-pink-500"></i>
                    <span class="font-semibold"><?php echo htmlspecialchars($origin_name) . ' → ' . htmlspecialchars($destination_name); ?></span>
                </div>
                
                <div class="flex items-center gap-2 text-gray-700">
                    <i class="far fa-calendar text-blue-500"></i>
                    <span><?php echo htmlspecialchars($date_display); ?></span>
                </div>
                
                <a href="index.php" class="ml-auto bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i>
                    Tìm lại
                </a>
            </div>
        </div>

        <!-- Results Count -->
        <div class="flex items-center gap-2 mb-6">
            <i class="fas fa-clipboard-list text-xl text-gray-600"></i>
            <p class="text-lg font-semibold text-gray-800"><?php echo count($trips); ?> chuyến xe phù hợp</p>
        </div>

        <!-- Trip Cards -->
        <?php if (count($trips) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($trips as $trip): ?>
                    <?php
                    $price = number_format($trip['base_price'], 0, ',', '.');
                    $seats = $trip['available_seats'];
                    $seats_color = $seats > 10 ? 'text-green-600' : 'text-red-600';
                    $departure = date('H:i', strtotime($trip['departure_time']));
                    $arrival = date('H:i', strtotime($trip['arrival_time']));
                    $duration = formatDuration($trip['duration']);
                    $duration_short = explode(' ', $duration)[0] . substr($duration, strpos($duration, ' '));
                    ?>
                    
                    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow border-l-4 border-blue-500">
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                                
                                <!-- Left Section -->
                                <div class="lg:col-span-3">
                                    <h3 class="text-lg font-bold text-gray-800 mb-2">
                                        <?php echo htmlspecialchars($trip['origin']) . ' → ' . htmlspecialchars($trip['destination']); ?>
                                    </h3>
                                    <div class="flex items-center gap-2 text-gray-600 text-sm">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo htmlspecialchars($duration); ?></span>
                                    </div>
                                </div>

                                <!-- Center Section -->
                                <div class="lg:col-span-3 text-center">
                                    <div class="text-3xl font-bold text-gray-800 mb-1"><?php echo $departure; ?></div>
                                    <div class="text-sm text-gray-600"><?php echo $duration_short; ?> • <?php echo $arrival; ?> đến</div>
                                </div>

                                <!-- Right Section -->
                                <div class="lg:col-span-6 flex flex-col lg:flex-row items-center gap-4 lg:justify-end">
                                    <div class="text-center lg:text-right">
                                        <div class="<?php echo $seats_color; ?> font-semibold mb-1">
                                            <?php echo $seats; ?> ghế còn trống
                                        </div>
                                        <div class="text-2xl font-bold text-red-500">
                                            <?php echo $price; ?>đ 
                                            <span class="text-sm text-gray-600">/khách</span>
                                        </div>
                                    </div>
                                    <a href="booking.php?schedule_id=<?php echo $trip['schedule_id']; ?>" 
                                       class="bg-green-500 text-white px-8 py-3 rounded-lg hover:bg-green-600 transition font-semibold whitespace-nowrap">
                                        Chọn chuyến
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-bus-alt text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Không tìm thấy chuyến xe phù hợp</h3>
                <p class="text-gray-600 mb-6">Vui lòng thử lại với điểm đi, điểm đến hoặc ngày khác</p>
                <a href="index.php" class="inline-block bg-blue-500 text-white px-8 py-3 rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-search mr-2"></i>Tìm kiếm lại
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12 mt-16">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <h3 class="font-bold text-lg mb-4">VéXe</h3>
                    <p class="text-gray-400 mb-4">Nền tảng đặt vé xe buýt hàng đầu Việt Nam</p>
                    <div class="flex space-x-3">
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-facebook text-lg"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-twitter text-lg"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-instagram text-lg"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="font-semibold mb-4">Liên kết nhanh</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white transition">Trang chủ</a></li>
                        <li><a href="my-tickets.php" class="text-gray-400 hover:text-white transition">Vé của tôi</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Khuyến mãi</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Về chúng tôi</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="font-semibold mb-4">Hỗ trợ</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Câu hỏi thường gặp</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Điều khoản sử dụng</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Chính sách bảo mật</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Liên hệ</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="font-semibold mb-4">Liên hệ</h4>
                    <div class="space-y-2 text-gray-400">
                        <p><i class="fas fa-phone mr-2"></i> 1900 1234</p>
                        <p><i class="fas fa-envelope mr-2"></i> support@vexe.vn</p>
                        <p><i class="fas fa-map-marker-alt mr-2"></i> Hà Nội, Việt Nam</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 VéXe. Tất cả quyền được bảo lưu.</p>
            </div>
        </div>
    </footer>
</body>
</html>
<?php
$conn->close();
?>