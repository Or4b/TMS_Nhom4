<?php
session_start();
// 1. KẾT NỐI DATABASE
$conn = new mysqli("localhost", "root", "", "tms_nhom4");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Lấy tham số
$origin = isset($_GET['origin']) ? intval($_GET['origin']) : 0;
$destination = isset($_GET['destination']) ? intval($_GET['destination']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// Lấy loại vé
$search_trip_type = isset($_GET['trip_type']) ? $_GET['trip_type'] : 'one_way';

// Lấy tên địa điểm
$origin_name = '';
$destination_name = '';
if ($origin > 0) {
    $result = $conn->query("SELECT province_name FROM provinces WHERE id = $origin");
    if ($row = $result->fetch_assoc()) $origin_name = $row['province_name'];
}
if ($destination > 0) {
    $result = $conn->query("SELECT province_name FROM provinces WHERE id = $destination");
    if ($row = $result->fetch_assoc()) $destination_name = $row['province_name'];
}

// Format ngày hiển thị
$date_display = '';
if ($date) {
    $timestamp = strtotime($date);
    $days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    $date_display = $days[date('w', $timestamp)] . ', ' . date('d/m/Y', $timestamp);
}

$trips = [];
if ($origin > 0 && $destination > 0) {
    // --- 1. LẤY CHUYẾN THẬT ---
    $query = "SELECT t.id AS schedule_id, t.price AS base_price,
                     o.province_name AS origin, d.province_name AS destination,
                     t.departure_time, t.total_seats, t.available_seats,
                     t.ticket_type, t.return_time
              FROM trips t
              JOIN provinces o ON t.departure_province_id = o.id
              JOIN provinces d ON t.destination_province_id = d.id
              WHERE t.departure_province_id = $origin 
              AND t.destination_province_id = $destination
              AND DATE(t.departure_time) = '$date'
              AND t.status = 'scheduled'
              ORDER BY t.departure_time ASC";
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['is_virtual'] = false;
            $trips[] = $row;
        }
    }

    // --- 2. TẠO CHUYẾN ẢO (RANDOM HÓA TOÀN DIỆN) ---
    if (strtotime($date) >= strtotime(date('Y-m-d'))) {
        $virtual_times = ['06:30', '09:15', '12:45', '15:30', '19:15', '22:00'];
        
        foreach ($virtual_times as $time) {
            $v_datetime = $date . ' ' . $time . ':00';
            if (strtotime($v_datetime) < time()) continue;

            $is_duplicate = false;
            foreach ($trips as $real) {
                if (abs(strtotime($real['departure_time']) - strtotime($v_datetime)) < 1800) {
                    $is_duplicate = true; break;
                }
            }

            if (!$is_duplicate) {
                // Random giá vé (250k - 450k)
                $random_price = rand(25, 45) * 10000;
                // Random ghế (15 - 42)
                $random_seats = rand(15, 42);
                
                // XỬ LÝ LOGIC KHỨ HỒI (RANDOM NGÀY VỀ)
                $v_return_time = null;
                if ($search_trip_type == 'round_trip') {
                    // [RANDOM] Số ngày ở lại: từ 1 đến 4 ngày
                    $stay_days = rand(1, 4); 
                    
                    // [RANDOM] Giờ về: từ 06:00 đến 21:00
                    $return_hour = rand(6, 21); 
                    $return_minute = rand(0, 59);
                    
                    // Tính toán timestamp ngày về
                    $dep_ts = strtotime($v_datetime);
                    $return_ts = $dep_ts + ($stay_days * 24 * 3600); // Cộng thêm số ngày
                    
                    // Ghép thành chuỗi thời gian hoàn chỉnh
                    $v_return_time = date('Y-m-d', $return_ts) . " " . sprintf("%02d:%02d:00", $return_hour, $return_minute);
                }

                $trips[] = [
                    'schedule_id' => 0,
                    'base_price' => $random_price,
                    'origin' => $origin_name,
                    'destination' => $destination_name,
                    'departure_time' => $v_datetime,
                    'total_seats' => 45,
                    'available_seats' => $random_seats,
                    'is_virtual' => true,
                    'origin_id' => $origin,
                    'dest_id' => $destination,
                    'ticket_type' => $search_trip_type, 
                    'return_time' => $v_return_time
                ];
            }
        }
        
        usort($trips, function($a, $b) {
            return strtotime($a['departure_time']) - strtotime($b['departure_time']);
        });
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
        body { font-family: 'Poppins', sans-serif; background: #f5f7fa; }
        .user-dropdown { position: relative; }
        .dropdown-menu { display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; background: white; border-radius: 0.75rem; box-shadow: 0 10px 40px rgba(0,0,0,0.15); min-width: 220px; padding: 0.5rem; z-index: 1000; }
        .user-dropdown:hover .dropdown-menu { display: block; }
        .dropdown-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #334155; text-decoration: none; border-radius: 0.5rem; transition: background 0.2s; font-size: 0.9375rem; }
        .dropdown-item:hover { background: #f1f5f9; }
        .dropdown-item.logout { color: #dc2626; }
        .dropdown-item.logout:hover { background: #fee2e2; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #4f46e5, #6366f1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; }
    </style>
</head>
<body>
    <header class="bg-[#3d4d5c] text-white sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="text-2xl font-bold tracking-wide">VéXe</a>
                </div>
                <nav class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="hover:text-blue-300 transition">Trang chủ</a>
                    <a href="my-tickets.php" class="hover:text-blue-300 transition">Vé của tôi</a>
                </nav>
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
                                    <a href="admin/dashboard.php" class="dropdown-item"><i class="fas fa-tachometer-alt"></i><span>Quản trị</span></a>
                                <?php endif; ?>
                                <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i><span>Hồ sơ cá nhân</span></a>
                                <a href="logout.php" class="dropdown-item logout"><i class="fas fa-sign-out-alt"></i><span>Đăng xuất</span></a>
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

    <div class="container mx-auto px-6 py-8 max-w-6xl">
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
                <?php if ($search_trip_type == 'round_trip'): ?>
                    <div class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-sm font-medium">
                        <i class="fas fa-exchange-alt mr-1"></i> Khứ hồi
                    </div>
                <?php endif; ?>
                <a href="index.php" class="ml-auto bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Tìm lại
                </a>
            </div>
        </div>

        <div class="flex items-center gap-2 mb-6">
            <i class="fas fa-clipboard-list text-xl text-gray-600"></i>
            <p class="text-lg font-semibold text-gray-800"><?php echo count($trips); ?> chuyến xe phù hợp</p>
        </div>

        <?php if (count($trips) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($trips as $trip): ?>
                    <?php
                    $price = number_format($trip['base_price'], 0, ',', '.');
                    $seats = $trip['available_seats'];
                    $seats_color = $seats > 10 ? 'text-green-600' : 'text-red-600';
                    $departure = date('H:i', strtotime($trip['departure_time']));
                    
                    $is_virtual = $trip['is_virtual'] ?? false;
                    $is_round_trip = ($trip['ticket_type'] === 'round_trip' || $trip['ticket_type'] === 'round-trip');
                    
                    // Xử lý hiển thị ngày về
                    $return_str = "";
                    if ($is_round_trip && !empty($trip['return_time'])) {
                        $return_date = date('d/m/Y', strtotime($trip['return_time']));
                        $return_time = date('H:i', strtotime($trip['return_time']));
                        $return_str = "Về: " . $return_time . " (" . $return_date . ")";
                    }
                    ?>
                    
                    <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow border border-gray-100 p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                            
                            <div class="lg:col-span-4">
                                <h3 class="text-lg font-bold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($trip['origin']) . ' → ' . htmlspecialchars($trip['destination']); ?>
                                </h3>
                                <?php if ($is_round_trip): ?>
                                    <span class="inline-block px-3 py-1 bg-orange-100 text-orange-700 text-xs rounded-full font-bold">
                                        Khứ hồi
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="lg:col-span-4 text-center">
                                <div class="text-4xl font-bold text-gray-800 mb-1"><?php echo $departure; ?></div>
                                
                                <?php if ($is_round_trip && $return_str): ?>
                                    <div class="text-sm text-orange-600 font-medium mt-2 bg-orange-50 inline-block px-3 py-1 rounded-lg">
                                        <i class="fas fa-undo-alt text-xs mr-1"></i> <?php echo $return_str; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="lg:col-span-4 flex flex-col lg:flex-row items-center gap-4 lg:justify-end">
                                <div class="text-center lg:text-right">
                                    <div class="<?php echo $seats_color; ?> font-semibold mb-1 text-sm">
                                        <?php echo $seats; ?> ghế trống
                                    </div>
                                    <div class="text-2xl font-bold text-red-500">
                                        <?php echo $price; ?>đ 
                                    </div>
                                </div>
                                
                                <?php if ($is_virtual): ?>
                                    <form action="create_and_book.php" method="POST">
                                        <input type="hidden" name="origin_id" value="<?php echo $trip['origin_id']; ?>">
                                        <input type="hidden" name="dest_id" value="<?php echo $trip['dest_id']; ?>">
                                        <input type="hidden" name="dep_time" value="<?php echo $trip['departure_time']; ?>">
                                        <input type="hidden" name="price" value="<?php echo $trip['base_price']; ?>">
                                        <?php if($is_round_trip): ?>
                                            <input type="hidden" name="ticket_type" value="round_trip">
                                            <input type="hidden" name="return_time" value="<?php echo $trip['return_time']; ?>">
                                        <?php endif; ?>
                                        
                                        <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition font-semibold whitespace-nowrap w-full lg:w-auto">
                                            Chọn chuyến
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="booking.php?trip_id=<?php echo $trip['schedule_id']; ?>" 
                                       class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold whitespace-nowrap w-full lg:w-auto text-center">
                                        Chọn chuyến
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-bus-alt text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Không tìm thấy chuyến xe phù hợp</h3>
                <a href="index.php" class="inline-block bg-blue-500 text-white px-8 py-3 rounded-lg hover:bg-blue-600 transition mt-4">
                    <i class="fas fa-search mr-2"></i>Tìm kiếm lại
                </a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-gray-800 text-white py-12 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2025 VéXe. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>
</body>
</html>
<?php $conn->close(); ?>