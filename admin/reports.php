<?php
include 'config.php';

$pageTitle = "Báo cáo & Thống kê";

// Get date range from request or use default
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Export to Excel functionality
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="bao_cao_doanh_thu_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Get data for export
    $stmt = $pdo->prepare("SELECT 
        SUM(total_amount) as total_revenue,
        COUNT(*) as total_bookings,
        AVG(total_amount) as avg_booking_value,
        COUNT(DISTINCT customer_id) as unique_customers
        FROM bookings 
        WHERE booking_date BETWEEN ? AND ? AND payment_status = 'paid'");
    $stmt->execute([$start_date, $end_date]);
    $exportStats = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT 
        t.id,
        p1.name as departure_location,
        p2.name as destination,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as total_revenue
        FROM trips t
        LEFT JOIN provinces p1 ON t.departure_province_id = p1.id
        LEFT JOIN provinces p2 ON t.destination_province_id = p2.id
        LEFT JOIN bookings b ON t.id = b.trip_id AND b.payment_status = 'paid' 
            AND b.booking_date BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY booking_count DESC
        LIMIT 5");
    $stmt->execute([$start_date, $end_date]);
    $exportTrips = $stmt->fetchAll();
    
    // Create Excel content
    $output = "<table border='1'>";
    $output .= "<tr><th colspan='5'>BÁO CÁO DOANH THU " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</th></tr>";
    $output .= "<tr><th>Tổng doanh thu</th><th>Tổng đơn hàng</th><th>Giá trị đơn TB</th><th>Khách hàng duy nhất</th></tr>";
    $output .= "<tr>";
    $output .= "<td>" . number_format($exportStats['total_revenue'] ?? 0, 0, ',', '.') . "₫</td>";
    $output .= "<td>" . ($exportStats['total_bookings'] ?? 0) . "</td>";
    $output .= "<td>" . number_format($exportStats['avg_booking_value'] ?? 0, 0, ',', '.') . "₫</td>";
    $output .= "<td>" . ($exportStats['unique_customers'] ?? 0) . "</td>";
    $output .= "</tr>";
    
    // Popular trips
    $output .= "<tr><td colspan='5'>&nbsp;</td></tr>";
    $output .= "<tr><th colspan='5'>CHUYẾN ĐI PHỔ BIẾN</th></tr>";
    $output .= "<tr><th>#</th><th>Tuyến đường</th><th>Số lượt đặt</th><th>Doanh thu</th></tr>";
    
    foreach($exportTrips as $index => $trip) {
        $output .= "<tr>";
        $output .= "<td>" . ($index + 1) . "</td>";
        $output .= "<td>" . htmlspecialchars($trip['departure_location']) . " → " . htmlspecialchars($trip['destination']) . "</td>";
        $output .= "<td>" . $trip['booking_count'] . "</td>";
        $output .= "<td>" . number_format($trip['total_revenue'] ?? 0, 0, ',', '.') . "₫</td>";
        $output .= "</tr>";
    }
    
    $output .= "</table>";
    echo $output;
    exit;
}

// Revenue statistics
$stmt = $pdo->prepare("SELECT 
    SUM(total_amount) as total_revenue,
    COUNT(*) as total_bookings,
    AVG(total_amount) as avg_booking_value,
    COUNT(DISTINCT customer_id) as unique_customers
    FROM bookings 
    WHERE booking_date BETWEEN ? AND ? AND payment_status = 'paid'");
$stmt->execute([$start_date, $end_date]);
$revenueStats = $stmt->fetch();

// Monthly revenue data
$stmt = $pdo->query("SELECT 
    DATE_FORMAT(booking_date, '%Y-%m') as month,
    SUM(total_amount) as revenue
    FROM bookings 
    WHERE payment_status = 'paid'
    GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6");
$monthlyRevenue = $stmt->fetchAll();

// Popular trips - FIXED QUERY (removed trip_name)
$stmt = $pdo->prepare("SELECT 
    t.id,
    p1.name as departure_location,
    p2.name as destination,
    COUNT(b.id) as booking_count,
    COALESCE(SUM(b.total_amount), 0) as total_revenue
    FROM trips t
    LEFT JOIN provinces p1 ON t.departure_province_id = p1.id
    LEFT JOIN provinces p2 ON t.destination_province_id = p2.id
    LEFT JOIN bookings b ON t.id = b.trip_id AND b.payment_status = 'paid' 
        AND b.booking_date BETWEEN ? AND ?
    GROUP BY t.id
    ORDER BY booking_count DESC
    LIMIT 5");
$stmt->execute([$start_date, $end_date]);
$popularTrips = $stmt->fetchAll();

// Customer statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_customers,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
    COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
    FROM customers");
$customerStats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #34495e;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            padding: 0.75rem 1.5rem;
        }

        .sidebar-menu li.active {
            background: #34495e;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .date-filter {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group input {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .popular-trips {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .customer-stats {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stats-list {
            margin-top: 1rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .stat-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            background: #3498db;
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-style: italic;
        }

        .trip-route {
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>📈 Báo Cáo & Thống Kê</h1>
            <div class="user-menu">
                <span>Xin chào, <?php echo $_SESSION['full_name'] ?? 'Quản trị viên'; ?></span>
                <a href="../logout.php" style="color: #e74c3c;">🚪 Đăng xuất</a>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="date-filter">
            <div class="filter-group">
                <label>Từ ngày</label>
                <input type="date" id="startDate" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label>Đến ngày</label>
                <input type="date" id="endDate" value="<?php echo $end_date; ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary" onclick="applyFilter()">Lọc</button>
                    <button class="btn btn-secondary" onclick="resetFilter()">Mặc định</button>
                    <button class="btn btn-success" onclick="exportToExcel()">📊 Xuất Excel</button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value"><?php echo number_format($revenueStats['total_revenue'] ?? 0, 0, ',', '.'); ?>₫</div>
                <div class="stat-label">Tổng doanh thu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-value"><?php echo $revenueStats['total_bookings'] ?? 0; ?></div>
                <div class="stat-label">Tổng đơn hàng</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo number_format($revenueStats['avg_booking_value'] ?? 0, 0, ',', '.'); ?>₫</div>
                <div class="stat-label">Giá trị đơn trung bình</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $revenueStats['unique_customers'] ?? 0; ?></div>
                <div class="stat-label">Khách hàng duy nhất</div>
            </div>
        </div>

        <!-- Charts and Customer Stats -->
        <div class="charts-container">
            <div class="chart-card">
                <h3>Doanh Thu Theo Tháng</h3>
                <canvas id="revenueChart" height="300"></canvas>
            </div>
            
            <div class="customer-stats">
                <h3>Thống Kê Khách Hàng</h3>
                <div class="stats-list">
                    <div class="stat-item">
                        <span>Tổng khách hàng</span>
                        <span class="stat-badge"><?php echo $customerStats['total_customers']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Mới hôm nay</span>
                        <span class="stat-badge" style="background: #27ae60;"><?php echo $customerStats['new_today']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Mới tuần này</span>
                        <span class="stat-badge" style="background: #3498db;"><?php echo $customerStats['new_this_week']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Trips -->
        <div class="popular-trips">
            <h3>Chuyến Đi Phổ Biến</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tuyến đường</th>
                        <th>Số lượt đặt</th>
                        <th>Doanh thu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($popularTrips)): ?>
                        <tr>
                            <td colspan="4" class="no-data">Không có dữ liệu chuyến đi trong khoảng thời gian này</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($popularTrips as $index => $trip): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="trip-route"><?php echo htmlspecialchars($trip['departure_location']); ?> → <?php echo htmlspecialchars($trip['destination']); ?></td>
                            <td><?php echo $trip['booking_count']; ?></td>
                            <td><?php echo number_format($trip['total_revenue'] ?? 0, 0, ',', '.'); ?>₫</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $labels = [];
                    foreach(array_reverse($monthlyRevenue) as $item) {
                        $labels[] = "'" . date('m/Y', strtotime($item['month'] . '-01')) . "'";
                    }
                    echo implode(',', $labels);
                ?>],
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: [<?php 
                        $data = [];
                        foreach(array_reverse($monthlyRevenue) as $item) {
                            $data[] = $item['revenue'];
                        }
                        echo implode(',', $data);
                    ?>],
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('vi-VN') + '₫';
                            }
                        }
                    }
                }
            }
        });

        function applyFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate) {
                window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
            } else {
                alert('Vui lòng chọn cả ngày bắt đầu và ngày kết thúc');
            }
        }

        function resetFilter() {
            window.location.href = 'reports.php';
        }

        function exportToExcel() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            let url = 'reports.php?export=excel';
            if (startDate && endDate) {
                url += `&start_date=${startDate}&end_date=${endDate}`;
            }
            
            window.location.href = url;
        }

        // Set date inputs to current values
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('startDate').value = '<?php echo $start_date; ?>';
            document.getElementById('endDate').value = '<?php echo $end_date; ?>';
        });
    </script>
</body>
</html>