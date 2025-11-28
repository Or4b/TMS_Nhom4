<?php
session_start();
require_once '../includes/config.php'; 

// --- Check Admin Login ---
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

$pageTitle = "Admin Dashboard";

// 1. Thống kê cơ bản
// Lưu ý: Nếu bảng customers không còn dùng nữa thì có thể bỏ query này hoặc thay bằng count users role='customer'
$stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM users WHERE role = 'customer'");
$totalCustomers = $stmt->fetch()['total_customers'];

$stmt = $pdo->query("SELECT COUNT(*) as total_staff FROM users WHERE role = 'staff'"); // Sửa logic đếm staff từ bảng users (hoặc bảng staff tùy cấu trúc)
$totalStaff = $stmt->fetch()['total_staff'];

$stmt = $pdo->query("SELECT COUNT(*) as total_trips FROM trips");
$totalTrips = $stmt->fetch()['total_trips'];

$stmt = $pdo->query("SELECT COUNT(*) as total_bookings FROM bookings");
$totalBookings = $stmt->fetch()['total_bookings'];

// SỬA LỖI 1: total_amount -> total_price
$stmt = $pdo->query("SELECT SUM(total_price) as revenue FROM bookings WHERE payment_status = 'paid'");
$revenue = $stmt->fetch()['revenue'] ?? 0;

// 2. Recent bookings (ĐÃ SỬA LỖI SQL HOÀN TOÀN)
// - Bỏ JOIN customers (vì bookings giờ nối thẳng users)
// - customer_id -> user_id
$stmt = $pdo->query("SELECT b.*, u.full_name, 
                     p1.province_name as departure_province, 
                     p2.province_name as destination_province,
                     t.departure_time, t.price
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     JOIN trips t ON b.trip_id = t.id 
                     JOIN provinces p1 ON t.departure_province_id = p1.id 
                     JOIN provinces p2 ON t.destination_province_id = p2.id 
                     ORDER BY b.booking_date DESC LIMIT 5");
$recentBookings = $stmt->fetchAll();

// 3. Recent activities (ĐÃ SỬA LỖI SQL)
// - number_of_seats -> quantity
// - Bỏ JOIN customers
$stmt = $pdo->query("SELECT 'booking' as type, b.booking_date as activity_date, 
                     CONCAT(u.full_name, ' đã đặt ', b.quantity, ' vé') as description,
                     u.full_name
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     UNION ALL
                     SELECT 'trip' as type, t.created_at as activity_date,
                     CONCAT('Chuyến đi mới: ', p1.province_name, ' → ', p2.province_name) as description,
                     'Hệ thống' as full_name
                     FROM trips t 
                     JOIN provinces p1 ON t.departure_province_id = p1.id 
                     JOIN provinces p2 ON t.destination_province_id = p2.id 
                     ORDER BY activity_date DESC LIMIT 5");
$recentActivities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        /* CSS GIỮ NGUYÊN NHƯ CŨ */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8f9fa; display: flex; }
        .sidebar { width: 250px; background: #2c3e50; color: white; height: 100vh; position: fixed; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #34495e; text-align: center; }
        .sidebar-menu { list-style: none; padding: 1rem 0; }
        .sidebar-menu li { padding: 0.75rem 1.5rem; }
        .sidebar-menu li.active { background: #34495e; border-left: 4px solid #3498db; }
        .sidebar-menu a { color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .main-content { margin-left: 250px; padding: 2rem; width: calc(100% - 250px); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .user-menu { display: flex; align-items: center; gap: 1rem; }
        .stats-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 2.5rem; margin-bottom: 1rem; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #2c3e50; margin: 0.5rem 0; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .recent-activities { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 2rem; }
        .activity-list { margin-top: 1rem; }
        .activity-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; border-bottom: 1px solid #eee; }
        .activity-icon { width: 40px; height: 40px; border-radius: 50%; background: #3498db; display: flex; align-items: center; justify-content: center; color: white; }
        .activity-content { flex: 1; }
        .activity-time { color: #666; font-size: 0.8rem; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .payment-status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
        .payment-paid { background: #d4edda; color: #155724; }
        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-failed { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Dashboard Quản Trị</h1>
            <div class="user-menu">
                <span>Xin chào, <?php echo $_SESSION['full_name'] ?? 'Quản trị viên'; ?></span>
                <a href="../logout.php" style="color: #e74c3c;">🚪 Đăng xuất</a>
            </div>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo number_format($revenue, 0, ',', '.'); ?>₫</div>
                <div class="stat-label">Doanh thu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-value"><?php echo $totalBookings; ?></div>
                <div class="stat-label">Vé đã bán</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $totalCustomers; ?></div>
                <div class="stat-label">Khách hàng</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚌</div>
                <div class="stat-value"><?php echo $totalTrips; ?></div>
                <div class="stat-label">Chuyến đi</div>
            </div>
        </div>

        <div class="recent-activities">
            <h3>Đặt Vé Gần Đây</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mã đặt</th>
                            <th>Khách hàng</th>
                            <th>Tuyến đường</th>
                            <th>Thời gian</th>
                            <th>Số ghế</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thanh toán</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentBookings as $booking): ?>
                        <tr>
                            <td>#<?php echo $booking['booking_id'] ?? $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['departure_province'] . ' → ' . $booking['destination_province']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($booking['departure_time'])); ?></td>
                            
                            <td><?php echo $booking['quantity']; ?></td>
                            
                            <td><?php echo number_format($booking['total_price'], 0, ',', '.'); ?>₫</td>
                            
                            <td>
                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                    <?php 
                                    switch($booking['status']) {
                                        case 'confirmed': echo 'Đã xác nhận'; break;
                                        case 'pending': echo 'Đang chờ'; break;
                                        case 'cancelled': echo 'Đã hủy'; break;
                                        case 'completed': echo 'Hoàn thành'; break;
                                        default: echo $booking['status'];
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="payment-status payment-<?php echo $booking['payment_status']; ?>">
                                    <?php 
                                    switch($booking['payment_status']) {
                                        case 'paid': echo 'Đã thanh toán'; break;
                                        case 'pending': echo 'Chờ thanh toán'; break;
                                        case 'failed': echo 'Thất bại'; break;
                                        default: echo $booking['payment_status'];
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="recent-activities">
            <h3>Hoạt Động Gần Đây</h3>
            <div class="activity-list">
                <?php foreach($recentActivities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <?php echo $activity['type'] == 'booking' ? '🎫' : '🚌'; ?>
                    </div>
                    <div class="activity-content">
                        <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                        <div class="activity-time">
                            <?php echo date('d/m/Y H:i', strtotime($activity['activity_date'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        console.log('Admin Dashboard loaded');
        // Auto refresh stats every 30 seconds
        setInterval(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>