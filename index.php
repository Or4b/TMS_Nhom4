<?php
session_start();
$conn = new mysqli("localhost", "root", "", "webdatxe");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>BusTicket - Đặt vé xe buýt trực tuyến</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-bg {
            background: linear-gradient(135deg, #22d3ee 0%, #06b6d4 100%);
        }
        .search-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .trip-card {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .feature-icon {
            background: linear-gradient(135deg, #22d3ee, #06b6d4);
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
            background: linear-gradient(135deg, #22d3ee, #06b6d4);
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
<body class="font-['Poppins'] bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-br from-cyan-400 to-cyan-600 text-white p-2 rounded-lg">
                        <i class="fas fa-bus text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-cyan-600">BusTicket</h1>
                        <p class="text-xs text-gray-600">Đặt vé xe buýt trực tuyến</p>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="index.php" class="text-cyan-600 font-semibold border-b-2 border-cyan-600">Trang chủ</a>
                    <a href="my-tickets.php" class="text-gray-600 hover:text-cyan-600 transition">Vé của tôi</a>
                    <a href="#" class="text-gray-600 hover:text-cyan-600 transition">Khuyến mãi</a>
                    <a href="#" class="text-gray-600 hover:text-cyan-600 transition">Hỗ trợ</a>
                </nav>

                <!-- User Actions -->
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])): ?>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <!-- Admin User -->
                            <div class="user-dropdown flex items-center space-x-3 cursor-pointer">
                                <div class="user-avatar">
                                    <?php 
                                    $username = $_SESSION['admin_username'];
                                    echo strtoupper(substr($username, 0, 2)); 
                                    ?>
                                </div>
                                <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($username); ?></span>
                                <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                                
                                <div class="dropdown-menu">
                                    <a href="admin/dashboard.php" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i>
                                        <span>Quản trị</span>
                                    </a>
                                    <a href="profile.php" class="dropdown-item">
                                        <i class="fas fa-user"></i>
                                        <span>Hồ sơ cá nhân</span>
                                    </a>
                                    
                                    <a href="logout.php" class="dropdown-item logout">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Đăng xuất</span>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Regular User -->
                            <div class="user-dropdown flex items-center space-x-3 cursor-pointer">
                                <div class="user-avatar">
                                    <?php 
                                    $username = $_SESSION['username'];
                                    echo strtoupper(substr($username, 0, 2)); 
                                    ?>
                                </div>
                                <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($username); ?></span>
                                <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                                
                                <div class="dropdown-menu">
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
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <a href="login.php" class="text-gray-700 hover:text-cyan-600 transition">Đăng nhập</a>
                            <a href="register.php" class="bg-gradient-to-r from-cyan-400 to-cyan-600 text-white px-6 py-2 rounded-lg hover:from-cyan-500 hover:to-cyan-700 transition">Đăng ký</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-bg text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">
                Đặt vé xe buýt
                <span class="block text-yellow-300">Dễ dàng & Nhanh chóng</span>
            </h1>
            <p class="text-xl mb-8 opacity-90 max-w-2xl mx-auto">
                Hơn 100+ tuyến đường trên toàn quốc. Đặt vé trực tuyến chỉ với vài cú nhấp chuột!
            </p>
        </div>
    </section>

    <!-- Search Section -->
    <section class="container mx-auto px-4 -mt-12 relative z-10">
        <div class="search-card rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                <i class="fas fa-search mr-3 text-cyan-600"></i>
                Tìm chuyến xe của bạn
            </h2>
            
            <form action="search.php" method="GET" class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                <!-- Trip Type -->
                <div class="flex flex-col">
                    <label class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-route mr-2 text-cyan-500"></i>Loại vé
                    </label>
                    <select name="trip_type" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                        <option value="one_way">Một chiều</option>
                        <option value="round_trip">Khứ hồi</option>
                    </select>
                </div>

                <!-- Origin -->
                <div class="flex flex-col">
                    <label class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>Nơi đi
                    </label>
                    <select name="origin" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                        <option value="">Chọn điểm đi</option>
                        <?php
                        $result = $conn->query("SELECT * FROM provinces ORDER BY province_name");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['province_id']}'>{$row['province_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Destination -->
                <div class="flex flex-col">
                    <label class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-2 text-green-500"></i>Nơi đến
                    </label>
                    <select name="destination" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                        <option value="">Chọn điểm đến</option>
                        <?php
                        $result = $conn->query("SELECT * FROM provinces ORDER BY province_name");
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='{$row['province_id']}'>{$row['province_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Date & Passengers -->
                <div class="flex flex-col">
                    <label class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="far fa-calendar-alt mr-2 text-cyan-500"></i>Ngày đi
                    </label>
                    <input type="date" name="date" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-500" required>
                </div>

                <!-- Search Button -->
                <div class="flex flex-col justify-end">
                    <button type="submit" class="bg-gradient-to-r from-cyan-400 to-cyan-600 text-white rounded-lg px-8 py-3 hover:from-cyan-500 hover:to-cyan-700 transition font-semibold">
                        <i class="fas fa-search mr-2"></i>Tìm chuyến
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Tại sao chọn BusTicket?</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">Chúng tôi mang đến trải nghiệm đặt vé xe buýt tốt nhất với nhiều ưu đãi hấp dẫn</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="text-center p-6">
                <div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center text-white mx-auto mb-4">
                    <i class="fas fa-bolt text-2xl"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Đặt vé nhanh</h3>
                <p class="text-gray-600">Chỉ mất 2 phút để hoàn tất đặt vé trực tuyến</p>
            </div>

            <div class="text-center p-6">
                <div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center text-white mx-auto mb-4">
                    <i class="fas fa-shield-alt text-2xl"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Bảo mật an toàn</h3>
                <p class="text-gray-600">Thông tin được mã hóa và bảo vệ tuyệt đối</p>
            </div>

            <div class="text-center p-6">
                <div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center text-white mx-auto mb-4">
                    <i class="fas fa-percentage text-2xl"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Giá tốt nhất</h3>
                <p class="text-gray-600">Cam kết giá vé rẻ nhất thị trường</p>
            </div>

            <div class="text-center p-6">
                <div class="feature-icon w-16 h-16 rounded-full flex items-center justify-center text-white mx-auto mb-4">
                    <i class="fas fa-headset text-2xl"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Hỗ trợ 24/7</h3>
                <p class="text-gray-600">Đội ngũ hỗ trợ luôn sẵn sàng giúp đỡ</p>
            </div>
        </div>
    </section>

    <!-- Popular Routes -->
    <section class="bg-gray-100 py-16">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">Tuyến đường phổ biến</h2>
                <p class="text-gray-600">Khám phá những hành trình được yêu thích nhất</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $query = "SELECT r.route_id, r.base_price, 
                                 o.province_name AS origin, d.province_name AS destination,
                                 COUNT(b.booking_id) as booking_count
                          FROM routes r
                          JOIN provinces o ON r.origin_id = o.province_id
                          JOIN provinces d ON r.destination_id = d.province_id
                          LEFT JOIN bookings b ON r.route_id = b.route_id
                          WHERE r.transport_type = 'xe buýt'
                          GROUP BY r.route_id
                          ORDER BY booking_count DESC
                          LIMIT 6";
                $result = $conn->query($query);
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $price = number_format($row['base_price'], 0, ',', '.') . 'đ';
                        ?>
                        <div class="trip-card bg-white rounded-xl p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800">
                                        <?php echo htmlspecialchars($row['origin']); ?> → <?php echo htmlspecialchars($row['destination']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm mt-1">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo $row['booking_count']; ?> lượt đặt
                                    </p>
                                </div>
                                <div class="bg-cyan-100 text-cyan-600 px-3 py-1 rounded-full text-sm font-semibold">
                                    <?php echo $price; ?>
                                </div>
                            </div>
                            <a href="search.php?origin=<?php echo $row['origin']; ?>&destination=<?php echo $row['destination']; ?>" 
                               class="w-full bg-gradient-to-r from-cyan-400 to-cyan-600 text-white text-center py-3 rounded-lg hover:from-cyan-500 hover:to-cyan-700 transition block">
                                <i class="fas fa-ticket-alt mr-2"></i>Đặt vé ngay
                            </a>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p class="text-gray-600 text-center col-span-3 py-8">Đang cập nhật tuyến đường...</p>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="bg-cyan-600 p-2 rounded-lg">
                            <i class="fas fa-bus"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">BusTicket</h3>
                            <p class="text-gray-400 text-sm">Đặt vé xe buýt trực tuyến</p>
                        </div>
                    </div>
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
                        <p><i class="fas fa-envelope mr-2"></i> support@busticket.vn</p>
                        <p><i class="fas fa-map-marker-alt mr-2"></i> Hà Nội, Việt Nam</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 BusTicket. Tất cả quyền được bảo lưu.</p>
            </div>
        </div>
    </footer>
</body>
</html>
<?php
$conn->close();
?>