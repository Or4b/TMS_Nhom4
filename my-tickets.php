<?php
// FILE: my-tickets.php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// XỬ LÝ YÊU CẦU HỦY VÉ
if (isset($_GET['request_cancel_id'])) {
    $req_id = intval($_GET['request_cancel_id']);
    $stmt = $pdo->prepare("UPDATE bookings SET cancel_request = 1 
                           WHERE booking_id = ? AND user_id = ? AND status NOT IN ('cancelled', 'completed')");
    $stmt->execute([$req_id, $_SESSION['user_id']]);
    echo "<script>alert('Đã gửi yêu cầu hủy! Nhân viên sẽ liên hệ với bạn.'); window.location.href='my-tickets.php';</script>";
    exit();
}

// [QUAN TRỌNG] Sửa SQL: Lấy thêm t.ticket_type (đặt tên là real_ticket_type) để ưu tiên hiển thị
$stmt = $pdo->prepare("SELECT b.*, 
                 t.departure_time, t.return_time, t.price,
                 t.ticket_type AS real_ticket_type, /* <--- Lấy loại vé chuẩn từ bảng trips */
                 po.province_name AS origin, 
                 pd.province_name AS destination
          FROM bookings b
          JOIN trips t ON b.trip_id = t.id
          JOIN provinces po ON t.departure_province_id = po.id
          JOIN provinces pd ON t.destination_province_id = pd.id
          WHERE b.user_id = ?
          ORDER BY b.booking_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <a href="index.php" class="text-2xl font-bold tracking-wide">
                        <span class="text-gray-300">TMS</span><span class="text-blue-400">VéXe</span>
                    </a>
                </div>
                <nav class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="hover:text-blue-300 transition">Trang chủ</a>
                    <a href="my-tickets.php" class="text-blue-300 font-semibold">Vé của tôi</a>
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
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Vé Của Tôi</h1>
            <p class="text-gray-600">Quản lý và theo dõi các vé đã đặt</p>
        </div>

        <?php if (isset($_GET['new_booking'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <p class="font-semibold">✓ Đặt vé thành công!</p>
                <p>Vé của bạn đang chờ xác nhận.</p>
            </div>
        <?php endif; ?>

        <?php if (count($bookings) > 0): ?>
            <div class="space-y-6">
                <?php foreach ($bookings as $booking): 
                    $ticket_code = '#TMS' . str_pad($booking['booking_id'], 5, '0', STR_PAD_LEFT);
                    $departure_date = date('H:i - d/m/Y', strtotime($booking['departure_time']));
                    $seat_numbers = $booking['seat_numbers'] ?? 'Chưa phân bổ';
                    
                    // --- SỬA LOGIC XÁC ĐỊNH LOẠI VÉ ---
                    // Ưu tiên lấy từ bảng trips (real_ticket_type)
                    $ticket_type = $booking['real_ticket_type'] ?? $booking['ticket_type'];
                    $is_round_trip = ($ticket_type == 'round_trip');

                    // Badge Loại vé
                    if ($is_round_trip) {
                        $type_badge = '<span class="bg-orange-100 text-orange-700 border border-orange-200 px-2 py-1 rounded text-xs font-bold uppercase ml-2"><i class="fas fa-exchange-alt mr-1"></i> Vé Khứ Hồi</span>';
                    } else {
                        $type_badge = '<span class="bg-blue-50 text-blue-600 border border-blue-100 px-2 py-1 rounded text-xs font-bold uppercase ml-2"><i class="fas fa-arrow-right mr-1"></i> Vé Một Chiều</span>';
                    }

                    // --- TRẠNG THÁI VÉ ---
                    if ($booking['status'] == 'cancelled') {
                        $status_text = 'Đã hủy'; $status_badge_color = 'bg-gray-200 text-gray-600'; $border_color = 'border-gray-400';
                    } elseif ($booking['status'] == 'confirmed') {
                        $status_text = 'Đã xác nhận'; $status_badge_color = 'bg-green-100 text-green-800'; $border_color = 'border-green-500';
                    } elseif ($booking['cancel_request'] == 1) {
                        $status_text = 'Đang yêu cầu hủy'; $status_badge_color = 'bg-red-100 text-red-800'; $border_color = 'border-red-500';
                    } else {
                        $status_text = 'Chờ xác nhận'; $status_badge_color = 'bg-yellow-100 text-yellow-800'; $border_color = 'border-yellow-500';
                    }

                    // --- TRẠNG THÁI THANH TOÁN ---
                    if ($booking['payment_status'] == 'paid') {
                        $pay_text = 'Đã thanh toán (Online)'; $pay_color = 'bg-blue-50 border-blue-200 text-blue-700'; $pay_icon = 'fa-credit-card';
                    } elseif ($booking['payment_method'] == 'counter') {
                        $pay_text = 'Thanh toán tại quầy'; $pay_color = 'bg-indigo-50 border-indigo-200 text-indigo-700'; $pay_icon = 'fa-store';
                    } else {
                        $pay_text = 'Chưa thanh toán'; $pay_color = 'bg-yellow-50 border-yellow-200 text-yellow-700'; $pay_icon = 'fa-wallet';
                    }
                ?>
                    
                    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 <?php echo $border_color; ?> p-6">
                        <div class="flex flex-wrap justify-between items-start mb-4 gap-2">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="text-gray-600 text-sm">Mã vé: <span class="font-semibold text-gray-800"><?php echo $ticket_code; ?></span></p>
                                    <?php echo $type_badge; ?>
                                </div>
                                <h3 class="text-xl font-bold text-gray-800">
                                    <?php echo htmlspecialchars($booking['origin']) . ' → ' . htmlspecialchars($booking['destination']); ?>
                                </h3>
                            </div>
                            <span class="<?php echo $status_badge_color; ?> px-4 py-1 rounded-full text-sm font-semibold">
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <div class="<?php echo $pay_color; ?> border rounded-lg p-3 mb-4 inline-flex items-center gap-2 w-full sm:w-auto">
                            <i class="fas <?php echo $pay_icon; ?>"></i>
                            <span class="text-sm font-medium"><?php echo $pay_text; ?></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-4">
                            <div>
                                <p class="text-gray-500 text-xs uppercase font-bold mb-1">Ngày đi</p>
                                <p class="font-semibold text-gray-800 text-lg"><?php echo $departure_date; ?></p>
                            </div>

                            <?php if($is_round_trip && !empty($booking['return_time'])): ?>
                            <div>
                                <p class="text-gray-500 text-xs uppercase font-bold mb-1 text-orange-600">Ngày về</p>
                                <p class="font-semibold text-blue-700 text-lg">
                                    <?php echo date('H:i - d/m/Y', strtotime($booking['return_time'])); ?>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="hidden md:block"></div> 
                            <?php endif; ?>

                            <div>
                                <p class="text-gray-500 text-xs uppercase font-bold mb-1">Ghế số</p>
                                <p class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($seat_numbers); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs uppercase font-bold mb-1">Tổng tiền</p>
                                <p class="font-bold text-orange-600 text-lg"><?php echo number_format($booking['total_price'], 0, ',', '.'); ?> đ</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <?php if ($booking['status'] != 'cancelled'): ?>
                                <?php if ($booking['cancel_request'] == 0): ?>
                                    <a href="my-tickets.php?request_cancel_id=<?php echo $booking['booking_id']; ?>" 
                                       onclick="return confirm('Bạn muốn gửi yêu cầu hủy vé này? Nhân viên sẽ liên hệ xử lý.')"
                                       class="bg-red-500 text-white px-5 py-2 rounded-lg hover:bg-red-600 transition font-medium text-sm flex items-center gap-2">
                                        <i class="fas fa-headset"></i> Yêu cầu hủy
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-500 px-5 py-2 rounded-lg border border-gray-200 font-medium text-sm flex items-center gap-2 cursor-not-allowed">
                                        <i class="fas fa-clock"></i> Đang chờ xử lý hủy
                                    </span>
                                <?php endif; ?>   
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <p class="text-gray-600 mb-6">Bạn chưa đặt vé nào.</p>
                <a href="index.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-search mr-2"></i>Tìm kiếm chuyến xe
                </a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-gray-800 text-white py-12 mt-16">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="font-bold text-lg mb-4">TMSVéXe</h3>
                    <p class="text-gray-400 mb-4">Nền tảng đặt vé xe buýt hàng đầu Việt Nam</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Liên kết nhanh</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-white transition">Trang chủ</a></li>
                        <li><a href="my-tickets.php" class="text-gray-400 hover:text-white transition">Vé của tôi</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Hỗ trợ</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Câu hỏi thường gặp</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Liên hệ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Liên hệ</h4>
                    <div class="space-y-2 text-gray-400">
                        <p><i class="fas fa-phone mr-2"></i> 1900 1234</p>
                        <p><i class="fas fa-envelope mr-2"></i> support@vexe.vn</p>
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