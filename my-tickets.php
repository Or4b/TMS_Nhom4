<?php
session_start();
$conn = new mysqli("localhost", "root", "", "webdatxe");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Xử lý hủy vé nếu có yêu cầu
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    $update_query = "UPDATE bookings SET status = 'đã hủy' WHERE booking_id = ? AND user_id = ? AND status != 'đã hủy'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $cancel_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: my-tickets.php");
    exit();
}

// Xử lý thanh toán
if (isset($_GET['pay_id'])) {
    $pay_id = intval($_GET['pay_id']);
    $update_query = "UPDATE bookings SET payment_status = 'đã thanh toán', status = 'đã xác nhận' WHERE booking_id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt) {
        $stmt->bind_param("ii", $pay_id, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        header("Location: my-tickets.php?success=1");
    }
    exit();
}

// Lấy danh sách vé
$query = "SELECT b.*, 
                 s.departure_time,
                 r.base_price,
                 o.province_name AS origin, 
                 d.province_name AS destination
          FROM bookings b
          JOIN schedules s ON b.schedule_id = s.schedule_id
          JOIN routes r ON s.route_id = r.route_id
          JOIN provinces o ON r.origin_id = o.province_id
          JOIN provinces d ON r.destination_id = d.province_id
          WHERE b.user_id = ?
          ORDER BY b.booking_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Vé của tôi - VéXe</title>
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
                    <a href="index.php" class="text-2xl font-bold tracking-wide">
                        <span class="text-gray-300">TMS</span><span class="text-blue-400">VéXe</span>
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="hover:text-blue-300 transition">Trang chủ</a>
                    <a href="my-tickets.php" class="text-blue-300 font-semibold">Vé của tôi</a>
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
        
        <!-- Page Title -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Vé Của Tôi</h1>
            <p class="text-gray-600">Quản lý và theo dõi các vé đã đặt</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <p class="font-semibold">✓ Thanh toán thành công!</p>
                <p>Vé của bạn đã được xác nhận.</p>
            </div>
        <?php endif; ?>

        <!-- Tickets List -->
        <?php if ($result->num_rows > 0): ?>
            <div class="space-y-6">
                <?php 
                $counter = 1;
                while ($row = $result->fetch_assoc()): 
                    // Tạo mã vé
                    $ticket_code = '#TMS' . date('Y', strtotime($row['booking_date'])) . str_pad($row['booking_id'], 5, '0', STR_PAD_LEFT);
                    
                    // Định dạng ngày giờ
                    $departure_date = date('d/m/Y - H:i', strtotime($row['departure_time']));
                    
                    // Xác định màu border và badge
                    $border_color = 'border-green-500';
                    $badge_color = 'bg-blue-100 text-blue-700';
                    $badge_text = 'Đã thanh toán';
                    
                    if ($row['payment_status'] == 'chưa thanh toán') {
                        $border_color = 'border-yellow-500';
                        $badge_color = 'bg-yellow-100 text-yellow-700';
                        $badge_text = 'Chờ xác nhận';
                    } elseif ($row['status'] == 'đã hủy') {
                        $border_color = 'border-gray-400';
                        $badge_color = 'bg-gray-100 text-gray-700';
                        $badge_text = 'Đã hủy';
                    }
                    
                    // Xử lý số ghế
                    $seat_numbers = $row['seat_numbers'] ?? 'Chưa phân bổ';
                ?>
                    
                    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 <?php echo $border_color; ?>">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm mb-1">Mã vé: <span class="font-semibold text-gray-800"><?php echo $ticket_code; ?></span></p>
                                    <h3 class="text-xl font-bold text-gray-800">
                                        <?php echo htmlspecialchars($row['origin']) . ' → ' . htmlspecialchars($row['destination']); ?>
                                    </h3>
                                </div>
                                <span class="<?php echo $badge_color; ?> px-4 py-1 rounded-full text-sm font-semibold">
                                    <?php echo $badge_text; ?>
                                </span>
                            </div>

                            <!-- Payment Method Badge -->
                            <?php if ($row['payment_status'] == 'đã thanh toán'): ?>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 inline-flex items-center gap-2">
                                    <i class="fas fa-credit-card text-blue-600"></i>
                                    <span class="text-blue-700 text-sm font-medium">Thanh toán online</span>
                                </div>
                            <?php elseif ($row['payment_status'] == 'chưa thanh toán' && $row['status'] != 'đã hủy'): ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4 inline-flex items-center gap-2">
                                    <i class="fas fa-wallet text-yellow-600"></i>
                                    <span class="text-yellow-700 text-sm font-medium">Đã thanh toán tại quầy</span>
                                </div>
                            <?php endif; ?>

                            <!-- Warning Message for Pending Tickets -->
                            <?php if ($row['payment_status'] == 'chưa thanh toán' && $row['status'] != 'đã hủy'): ?>
                                <div class="bg-red-50 border-l-4 border-red-500 p-3 mb-4">
                                    <div class="flex items-start gap-2">
                                        <i class="fas fa-phone text-red-600 mt-1"></i>
                                        <p class="text-red-700 text-sm">
                                            <span class="font-semibold">Nhân viên sẽ liên hệ xác nhận trong 24h</span>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Ticket Details -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                                <div>
                                    <p class="text-gray-600 text-sm mb-1">Ngày đi</p>
                                    <p class="font-semibold text-gray-800"><?php echo $departure_date; ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm mb-1">Ghế số</p>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($seat_numbers); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm mb-1">Tổng tiền</p>
                                    <p class="font-bold text-orange-600 text-lg">
                                        <?php echo number_format($row['total_price'], 0, ',', '.'); ?> VNĐ
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                <?php if ($row['payment_status'] == 'chưa thanh toán' && $row['status'] != 'đã hủy'): ?>
                                    <a href="my-tickets.php?pay_id=<?php echo $row['booking_id']; ?>" 
                                       class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition font-semibold">
                                        Thanh toán ngay
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($row['status'] != 'đã hủy' && $row['payment_status'] == 'đã thanh toán'): ?>
                                    <button class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition font-semibold">
                                        Liên hệ hỗ trợ
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($row['status'] != 'đã hủy' && $row['payment_status'] == 'chưa thanh toán'): ?>
                                    <a href="my-tickets.php?cancel_id=<?php echo $row['booking_id']; ?>" 
                                       onclick="return confirm('Bạn có chắc muốn hủy vé này?')"
                                       class="bg-red-500 text-white px-6 py-2 rounded-lg hover:bg-red-600 transition font-semibold">
                                        Hủy vé
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php 
                $counter++;
                endwhile; 
                ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Chưa có vé nào</h3>
                <p class="text-gray-600 mb-6">Bạn chưa đặt vé nào. Hãy bắt đầu đặt vé ngay hôm nay!</p>
                <a href="index.php" class="inline-block bg-blue-500 text-white px-8 py-3 rounded-lg hover:bg-blue-600 transition font-semibold">
                    <i class="fas fa-search mr-2"></i>Tìm kiếm chuyến xe
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
                    <h3 class="font-bold text-lg mb-4">TMSVéXe</h3>
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
                <p>&copy; 2025 TMSVéXe. Tất cả quyền được bảo lưu.</p>
            </div>
        </div>
    </footer>
</body>
</html>
<?php
$conn->close();
?>