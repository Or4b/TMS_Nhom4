<?php
session_start();
$conn = new mysqli("localhost", "root", "", "tms_nhom4");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

$current_time = date('Y-m-d H:i:s');
$update_sql = "UPDATE trips 
               SET status = 'completed' 
               WHERE departure_time < '$current_time' 
               AND status = 'scheduled'";
$conn->query($update_sql);

// Lấy tên người dùng hiển thị
$display_name = "Khách";
$is_logged_in = false;

if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    $is_logged_in = true;
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        // Kiểm tra tồn tại cho admin
        $display_name = $_SESSION['admin_username'] ?? 'Admin';
    } else {
        // Kiểm tra tồn tại cho user, nếu lỗi thì hiển thị là 'Thành viên'
        $display_name = $_SESSION['username'] ?? 'Thành viên';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMS - Hệ Thống Quản Lý Vé Xe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Reset & Base Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* --- Header Styles (SR14) --- */
        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .logo span {
            color: #3498db;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            margin-left: 2rem;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-weight: 500;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background-color: #34495e;
        }

        /* --- User & Notification --- */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            z-index: 100;
            border-radius: 6px;
            overflow: hidden;
        }

        .user-info:hover .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .dropdown-menu a:hover {
            background-color: #f8f9fa;
        }

        /* Notification Bell */
        .notification-container {
            position: relative;
        }

        .notification-bell {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            color: white;
            transition: background-color 0.3s;
        }

        .notification-bell:hover {
            background-color: #34495e;
        }

        .notification-count {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: -80px;
            width: 350px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 15px;
            z-index: 1001;
            display: none;
        }

        .notification-container:hover .notification-dropdown {
            display: block;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .notification-item {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
            background-color: #f8f9fa;
            cursor: pointer;
        }

        .notification-item.unread {
            background-color: #e8f4fd;
            border-left-color: #e74c3c;
        }

        /* --- Hero Section --- */
        .hero {
            background: linear-gradient(rgba(44, 62, 80, 0.8), rgba(44, 62, 80, 0.8)),
                url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?q=80&w=1469&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 4rem 0;
            text-align: center;
            margin-bottom: 2rem;
            /* Sửa lại margin dương để đẩy form xuống đúng vị trí */
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        /* --- SEARCH FORM STYLES (Chuẩn SR14) --- */
        .main-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .search-form {
            background-color: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: -5rem auto 3rem;
            /* Đẩy lên đè hero */
            position: relative;
            z-index: 10;
        }

        .trip-type {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }

        .trip-type label {
            margin-right: 2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .trip-type input {
            margin-right: 0.5rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
            padding: 0 10px;
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        /* Style cho input và select */
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            outline: none;
            background-color: #fff;
            /* Đảm bảo nền trắng */
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3498db;
        }

        .search-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: block;
            margin: 0 auto;
            width: 100%;
            max-width: 300px;
        }

        .search-btn:hover {
            background-color: #c0392b;
        }

        /* Notification Animation */
        .notification-container .notification-dropdown {
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .notification-container:hover .notification-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* Footer */
        footer {
            background-color: #2c3e50;
            color: white;
            text-align: center;
            padding: 1.5rem 0;
            margin-top: 3rem;
        }
    </style>
</head>

<body>
    <header>
        <div class="header-content">
            <div style="display: flex; align-items: center;">
                <div class="logo">TMS<span>VéXe</span></div>
                <nav class="nav-menu">
                    <a href="index.php" class="active">Trang chủ</a>
                    <a href="my-tickets.php">Vé của tôi</a>
                </nav>
            </div>

            <div class="user-menu flex items-center gap-6">
                <?php if ($is_logged_in): ?>
                    <div class="notification-container relative z-50">
                        <button class="notification-bell relative p-2 text-gray-300 hover:text-white transition-colors duration-200">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="notification-count absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-5 w-5 flex items-center justify-center rounded-full border-2 border-[#2c3e50]">
                                2
                            </span>
                        </button>

                        <div class="notification-dropdown absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-100">
                            <div class="notification-header flex justify-between items-center px-4 py-3 border-b border-gray-100 bg-gray-50">
                                <h3 class="font-bold text-gray-700 text-sm">Thông báo</h3>
                                <button class="mark-all-read text-xs font-medium text-blue-500 hover:text-blue-700 transition-colors">
                                    Đánh dấu đã đọc
                                </button>
                            </div>

                            <div class="notification-list max-h-[300px] overflow-y-auto">
                                <div class="notification-item unread cursor-pointer p-4 border-b border-gray-50 hover:bg-gray-50 transition bg-blue-50/50 flex gap-3 items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                            <i class="fas fa-user-plus text-xs"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="notification-title font-semibold text-gray-800 text-sm mb-1">Chào thành viên mới</div>
                                        <div class="notification-message text-xs text-gray-500 leading-relaxed">
                                            Xin chào <b><?php echo htmlspecialchars($display_name); ?></b>! Chúc bạn có những chuyến đi tuyệt vời.
                                        </div>
                                        <span class="text-[10px] text-gray-400 mt-1 block">Vừa xong</span>
                                    </div>
                                    <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></span>
                                </div>

                                <div class="notification-item unread cursor-pointer p-4 hover:bg-gray-50 transition bg-blue-50/50 flex gap-3 items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-8 h-8 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center">
                                            <i class="fas fa-tag text-xs"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="notification-title font-semibold text-gray-800 text-sm mb-1">Khuyến mãi cực sốc</div>
                                        <div class="notification-message text-xs text-gray-500 leading-relaxed">
                                            Nhập mã <b>TMS2025</b> để được giảm 20% cho chuyến đi tiếp theo.
                                        </div>
                                        <span class="text-[10px] text-gray-400 mt-1 block">1 giờ trước</span>
                                    </div>
                                    <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></span>
                                </div>
                            </div>

                            <a href="#" class="block text-center py-2 text-xs text-gray-500 hover:bg-gray-50 border-t border-gray-100">
                                Xem tất cả thông báo
                            </a>
                        </div>
                    </div>

                    <div class="user-info flex items-center gap-3 cursor-pointer group relative z-40">
    
<div class="user-info relative z-40" id="userMenuContainer">
    
    <div onclick="toggleUserMenu()" class="flex items-center gap-3 cursor-pointer select-none">
        <div class="text-right hidden md:block">
            <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($display_name); ?></div>
            <div class="text-xs text-gray-400">Thành viên</div>
        </div>

        <div class="user-avatar w-10 h-10 rounded-full bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center text-white font-bold shadow-lg border-2 border-[#2c3e50] hover:border-cyan-400 transition-all">
            <?php echo strtoupper(substr($display_name, 0, 1)); ?>
        </div>
    </div>

    <div id="userDropdown" class="hidden absolute top-full right-0 mt-3 w-48 bg-white rounded-xl shadow-xl overflow-hidden transition-all transform origin-top-right z-50 animate-fade-in-down">
        <a href="profile.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-cyan-600 transition border-b border-gray-100">
            <i class="fas fa-user mr-2"></i> Hồ sơ cá nhân
        </a>
        <a href="logout.php" class="block px-4 py-3 text-sm text-red-500 hover:bg-red-50 transition">
            <i class="fas fa-sign-out-alt mr-2"></i> Đăng xuất
        </a>
    </div>
</div>
                <?php else: ?>
                    <div class="flex gap-4">
                        <a href="login.php" class="text-gray-300 hover:text-white transition font-medium text-sm py-2">Đăng nhập</a>
                        <a href="register.php" class="bg-cyan-500 hover:bg-cyan-600 text-white px-5 py-2 rounded-full font-medium text-sm transition shadow-lg shadow-cyan-500/30">Đăng ký</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="main-container">
            <h1>Chào mừng, <?php echo htmlspecialchars($display_name); ?>!</h1>
            <p>Chúc bạn một hành trình an toàn và thoải mái</p>
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

                            echo "<option value='{$row['id']}'>{$row['province_name']}</option>";
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
                            echo "<option value='{$row['id']}'>{$row['province_name']}</option>";
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

    <section class="main-container" style="margin-top: 2rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h2 style="font-size: 1.8rem; color: #2c3e50; margin-bottom: 0.5rem;">Tuyến đường phổ biến</h2>
            <p style="color: #666;">Khám phá những hành trình được yêu thích nhất</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php
            $query = "SELECT t.id, t.price, 
                 o.province_name AS origin, d.province_name AS destination,
                 t.departure_time
          FROM trips t
          JOIN provinces o ON t.departure_province_id = o.id
          JOIN provinces d ON t.destination_province_id = d.id
          WHERE t.status = 'scheduled' 
          AND t.departure_time > NOW()  /* Chỉ lấy chuyến chưa đi */
          ORDER BY t.departure_time ASC /* Chuyến nào đi trước hiện trước */
          LIMIT 6";

            $result = $conn->query($query);

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $price = number_format($row['price'], 0, ',', '.') . 'đ';
                    $date = date('d/m/Y H:i', strtotime($row['departure_time']));
            ?>
                    <div style="background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                            <div>
                                <h3 style="font-weight: bold; color: #2c3e50; margin: 0;">
                                    <?php echo htmlspecialchars($row['origin']); ?> → <?php echo htmlspecialchars($row['destination']); ?>
                                </h3>
                                <p style="font-size: 0.9rem; color: #7f8c8d; margin-top: 5px;">
                                    <i class="far fa-clock"></i> <?php echo $date; ?>
                                </p>
                            </div>
                            <div>
                                <span style="background: #e8f4fd; color: #3498db; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 0.9rem;">
                                    <?php echo $price; ?>
                                </span>
                            </div>
                        </div>
                        <a href="booking.php?trip_id=<?php echo $row['id']; ?>"
                            style="display: block; width: 100%; text-align: center; background: #2c3e50; color: white; text-decoration: none; padding: 10px; border-radius: 4px; transition: background 0.3s;">
                            Đặt vé ngay
                        </a>
                    </div>
            <?php
                }
            } else {
                echo '<p style="text-align: center; grid-column: 1/-1; color: #666;">Chưa có chuyến xe nào.</p>';
            }
            ?>
        </div>
    </section>

    <footer>
        <div class="container" style="width: 90%; max-width: 1200px; margin: 0 auto;">
            <p>&copy; 2025 Hệ Thống Quản Lý Vé Xe TMS. Đồ án môn học.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Xử lý hiển thị ngày về khi chọn Khứ hồi
            const radioButtons = document.querySelectorAll('input[name="trip_type"]');
            const returnDateGroup = document.querySelector('.return-date-group');
            const returnDateInput = document.getElementById('return_date');

            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'round_trip') {
                        returnDateGroup.style.display = 'block';
                        returnDateInput.setAttribute('required', 'required');
                    } else {
                        returnDateGroup.style.display = 'none';
                        returnDateInput.removeAttribute('required');
                        returnDateInput.value = ''; // Reset giá trị
                    }
                });
            });

            // 2. Xử lý thông báo (đếm số lượng và đánh dấu đã đọc)
            const notificationBell = document.querySelector('.notification-bell');
            const notificationCount = document.querySelector('.notification-count');
            const markAllReadBtn = document.querySelector('.mark-all-read');
            const notificationItems = document.querySelectorAll('.notification-item');

            function updateNotificationCount() {
                const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                if (notificationCount) {
                    notificationCount.textContent = unreadCount;
                    notificationCount.style.display = unreadCount === 0 ? 'none' : 'flex';
                }
            }

            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    notificationItems.forEach(item => item.classList.remove('unread'));
                    updateNotificationCount();
                });
            }

            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    this.classList.remove('unread');
                    updateNotificationCount();
                });
            });

            updateNotificationCount();
        });
    </script>
<script>
    function toggleUserMenu() {
        const menu = document.getElementById('userDropdown');
        menu.classList.toggle('hidden');
    }

    // Đóng menu khi click ra ngoài
    document.addEventListener('click', function(event) {
        const container = document.getElementById('userMenuContainer');
        const menu = document.getElementById('userDropdown');
        
        // Nếu click không nằm trong container thì ẩn menu đi
        if (!container.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });
</script>
</body>

</html>
<?php
$conn->close();
?>