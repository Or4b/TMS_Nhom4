<?php
include '../config.php';

$pageTitle = "Báo cáo & Thống kê";

// 1. XỬ LÝ DATE RANGE
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Tính khoảng cách ngày
$date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
// Nếu khoảng cách > 31 ngày thì xem theo Tháng, ngược lại xem theo Ngày (để chi tiết hơn)
$chart_mode = ($date_diff > 31) ? 'month' : 'day'; 

// 2. XUẤT EXCEL
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="bao_cao_doanh_thu_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // SQL Thống kê
    $stmt = $pdo->prepare("SELECT 
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(*) as total_bookings,
        COALESCE(AVG(total_amount), 0) as avg_booking_value,
        COUNT(DISTINCT customer_id) as unique_customers
        FROM bookings 
        WHERE booking_date BETWEEN ? AND ? AND payment_status = 'paid'");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $exportStats = $stmt->fetch();
    
    // SQL Top chuyến đi
    $stmt = $pdo->prepare("SELECT 
        t.id,
        p1.province_name as departure_location,
        p2.province_name as destination,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as total_revenue
        FROM trips t
        LEFT JOIN provinces p1 ON t.departure_province_id = p1.id
        LEFT JOIN provinces p2 ON t.destination_province_id = p2.id
        LEFT JOIN bookings b ON t.id = b.trip_id AND b.payment_status = 'paid' 
            AND b.booking_date BETWEEN ? AND ?
        GROUP BY t.id
        HAVING booking_count > 0
        ORDER BY booking_count DESC
        LIMIT 10");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $exportTrips = $stmt->fetchAll();
    
    echo '<meta charset="UTF-8">';
    echo "<table border='1'>";
    echo "<tr><th colspan='5' style='background:#f0f0f0;font-size:16px;'>BÁO CÁO DOANH THU (" . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . ")</th></tr>";
    echo "<tr><th>Chỉ số</th><th>Giá trị</th></tr>";
    echo "<tr><td>Tổng doanh thu</td><td>" . number_format($exportStats['total_revenue']) . "</td></tr>";
    echo "<tr><td>Tổng đơn hàng</td><td>" . $exportStats['total_bookings'] . "</td></tr>";
    echo "<tr><td colspan='5'></td></tr>";
    echo "<tr><th colspan='5' style='background:#b3d9ff;'>TOP CHUYẾN ĐI</th></tr>";
    echo "<tr><th>STT</th><th>Tuyến đường</th><th>Số lượt đặt</th><th>Doanh thu</th></tr>";
    foreach($exportTrips as $index => $trip) {
        echo "<tr>";
        echo "<td>" . ($index + 1) . "</td>";
        echo "<td>" . htmlspecialchars($trip['departure_location']) . " - " . htmlspecialchars($trip['destination']) . "</td>";
        echo "<td>" . $trip['booking_count'] . "</td>";
        echo "<td>" . number_format($trip['total_revenue']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// 3. LẤY DỮ LIỆU DASHBOARD

// 3.1 Thống kê tổng quan
$stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COUNT(*) as total_bookings,
    COALESCE(AVG(total_amount), 0) as avg_booking_value,
    COUNT(DISTINCT customer_id) as unique_customers
    FROM bookings 
    WHERE booking_date BETWEEN ? AND ? AND payment_status = 'paid'");
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$revenueStats = $stmt->fetch();

// 3.2 Dữ liệu Biểu đồ (Logic mới cho Bar Chart)
if ($chart_mode == 'month') {
    // Group theo Tháng (YYYY-MM)
    $sql_chart = "SELECT DATE_FORMAT(booking_date, '%Y-%m') as time_label, SUM(total_amount) as revenue
                  FROM bookings 
                  WHERE booking_date BETWEEN ? AND ? AND payment_status = 'paid'
                  GROUP BY DATE_FORMAT(booking_date, '%Y-%m') ORDER BY time_label ASC";
} else {
    // Group theo Ngày (YYYY-MM-DD)
    $sql_chart = "SELECT DATE_FORMAT(booking_date, '%Y-%m-%d') as time_label, SUM(total_amount) as revenue
                  FROM bookings 
                  WHERE booking_date BETWEEN ? AND ? AND payment_status = 'paid'
                  GROUP BY DATE_FORMAT(booking_date, '%Y-%m-%d') ORDER BY time_label ASC";
}
$stmt = $pdo->prepare($sql_chart);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$rawChartData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý lấp đầy ngày trống (Để biểu đồ cột hiện số 0 cho ngày không có khách)
$chartData = [];
$period = new DatePeriod(
    new DateTime($start_date),
    new DateInterval('P1D'), // 1 ngày
    (new DateTime($end_date))->modify('+1 day')
);

// Chuyển data DB sang dạng map để dễ tra cứu
$dbDataMap = [];
foreach ($rawChartData as $row) {
    $dbDataMap[$row['time_label']] = $row['revenue'];
}

if ($chart_mode == 'day') {
    foreach ($period as $date) {
        $key = $date->format('Y-m-d');
        $chartData[] = [
            'time_label' => $key,
            'revenue' => $dbDataMap[$key] ?? 0 // Nếu không có thì bằng 0
        ];
    }
} else {
    // Nếu xem theo tháng thì giữ nguyên data từ DB (hoặc xử lý tương tự nếu cần lấp tháng)
    $chartData = $rawChartData;
}

// 3.3 Top Chuyến đi
$stmt = $pdo->prepare("SELECT 
    t.id,
    p1.province_name as departure_location,
    p2.province_name as destination,
    COUNT(b.id) as booking_count,
    COALESCE(SUM(b.total_amount), 0) as total_revenue
    FROM trips t
    LEFT JOIN provinces p1 ON t.departure_province_id = p1.id
    LEFT JOIN provinces p2 ON t.destination_province_id = p2.id
    LEFT JOIN bookings b ON t.id = b.trip_id AND b.payment_status = 'paid' 
        AND b.booking_date BETWEEN ? AND ?
    GROUP BY t.id
    HAVING booking_count > 0
    ORDER BY booking_count DESC
    LIMIT 5");
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$popularTrips = $stmt->fetchAll();

// 3.4 Thống kê Khách hàng
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS CŨ GIỮ NGUYÊN */
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
        
        /* Filter Styles */
        .date-filter { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-weight: 600; font-size: 0.9rem; color: #555; }
        .filter-group input { padding: 0.6rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        
        .btn { padding: 0.6rem 1.2rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
        .quick-filter-group { flex-grow: 1; display: flex; gap: 0.5rem; align-items: center; }
        .quick-btn { padding: 0.4rem 0.8rem; border: 1px solid #ddd; background: #f8f9fa; border-radius: 20px; color: #555; cursor: pointer; font-size: 0.85rem; transition: 0.2s; }
        .quick-btn:hover { background: #e2e6ea; }

        /* Stats & Charts */
        .stats-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .stat-icon { font-size: 2.5rem; margin-bottom: 1rem; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #2c3e50; margin: 0.5rem 0; }
        .stat-label { color: #666; font-size: 0.9rem; }
        
        .charts-container { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .chart-card, .customer-stats { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .chart-card h3, .customer-stats h3, .popular-trips h3 { margin-bottom: 1rem; font-size: 1.2rem; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem; }

        .popular-trips { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        
        .stats-list .stat-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #eee; }
        .stat-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; background: #3498db; color: white; }
        .no-data { text-align: center; padding: 2rem; color: #666; font-style: italic; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>📈 Báo Cáo & Thống Kê</h1>
            <div class="user-menu">
                <span>Xin chào, <?php echo $_SESSION['full_name'] ?? 'Quản trị viên'; ?></span>
                <a href="../logout.php" style="color: #e74c3c; text-decoration: none; margin-left: 10px;">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </div>
        </div>

        <div class="date-filter">
            <div class="filter-group">
                <label>Từ ngày</label>
                <input type="date" id="startDate" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label>Đến ngày</label>
                <input type="date" id="endDate" value="<?php echo $end_date; ?>">
            </div>
            
            <div class="filter-group quick-filter-group">
                <label style="opacity: 0;">.</label>
                <div style="display: flex; gap: 5px;">
                    <button class="quick-btn" onclick="quickSelect('today')">Hôm nay</button>
                    <button class="quick-btn" onclick="quickSelect('week')">7 ngày qua</button>
                    <button class="quick-btn" onclick="quickSelect('month')">Tháng này</button>
                </div>
            </div>

            <div class="filter-group" style="flex-direction: row; gap: 10px;">
                <label style="opacity: 0;">.</label>
                <button class="btn btn-primary" onclick="applyFilter()">
                    <i class="fas fa-filter"></i> Lọc
                </button>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Xuất Excel
                </button>
            </div>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon" style="color: #27ae60;">💰</div>
                <div class="stat-value"><?php echo number_format($revenueStats['total_revenue'] ?? 0); ?>₫</div>
                <div class="stat-label">Doanh thu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #3498db;">🎫</div>
                <div class="stat-value"><?php echo number_format($revenueStats['total_bookings'] ?? 0); ?></div>
                <div class="stat-label">Đơn hàng thành công</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #9b59b6;">📊</div>
                <div class="stat-value"><?php echo number_format($revenueStats['avg_booking_value'] ?? 0); ?>₫</div>
                <div class="stat-label">TB Giá trị đơn</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #f39c12;">👥</div>
                <div class="stat-value"><?php echo number_format($revenueStats['unique_customers'] ?? 0); ?></div>
                <div class="stat-label">Khách hàng mua vé</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <h3>
                    <i class="fas fa-chart-bar"></i> 
                    Biểu đồ Doanh thu (<?php echo ($chart_mode == 'month') ? 'Theo Tháng' : 'Theo Ngày'; ?>)
                </h3>
                <div style="position: relative; height: 350px; width: 100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <div class="customer-stats">
                <h3><i class="fas fa-users"></i> Thống Kê Khách Hàng</h3>
                <div class="stats-list">
                    <div class="stat-item">
                        <span>Tổng khách hàng</span>
                        <span class="stat-badge"><?php echo $customerStats['total_customers']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Đăng ký hôm nay</span>
                        <span class="stat-badge" style="background: #27ae60;">+<?php echo $customerStats['new_today']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span>Mới 7 ngày qua</span>
                        <span class="stat-badge" style="background: #e67e22;">+<?php echo $customerStats['new_this_week']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="popular-trips">
            <h3><i class="fas fa-route"></i> Top Chuyến Đi Phổ Biến (<?php echo date('d/m', strtotime($start_date)) . ' - ' . date('d/m', strtotime($end_date)); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Tuyến đường</th>
                        <th>Số lượt đặt</th>
                        <th>Doanh thu đóng góp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($popularTrips)): ?>
                        <tr>
                            <td colspan="4" class="no-data">Không có dữ liệu trong khoảng thời gian này.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($popularTrips as $index => $trip): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td style="font-weight: 500; color: #2c3e50;">
                                <?php echo htmlspecialchars($trip['departure_location']); ?> 
                                <i class="fas fa-arrow-right" style="font-size: 0.8rem; color: #999; margin: 0 5px;"></i> 
                                <?php echo htmlspecialchars($trip['destination']); ?>
                            </td>
                            <td><?php echo $trip['booking_count']; ?></td>
                            <td style="color: #27ae60; font-weight: bold;"><?php echo number_format($trip['total_revenue']); ?>₫</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // CẤU HÌNH BIỂU ĐỒ BAR CHART (CỘT)
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar', // Đổi sang biểu đồ Cột
            data: {
                labels: [<?php 
                    $labels = array_map(function($item) use ($chart_mode) {
                        $date = new DateTime($item['time_label']);
                        return ($chart_mode == 'month') ? "'" . $date->format('m/Y') . "'" : "'" . $date->format('d/m') . "'";
                    }, $chartData);
                    echo implode(',', $labels);
                ?>],
                datasets: [{
                    label: 'Doanh thu',
                    data: [<?php 
                        $values = array_column($chartData, 'revenue');
                        echo implode(',', $values);
                    ?>],
                    backgroundColor: 'rgba(52, 152, 219, 0.7)', // Màu cột xanh
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1,
                    barPercentage: 0.6 // Độ rộng cột
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Quan trọng: Cho phép biểu đồ co giãn theo div bao ngoài
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                // Logic hiển thị trục số thông minh
                                if (value >= 1000000) return (value/1000000) + ' tr';
                                if (value >= 1000) return (value/1000) + ' k';
                                return value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false } // Ẩn lưới dọc cho đỡ rối
                    }
                }
            }
        });

        function applyFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) {
                alert('Vui lòng chọn ngày bắt đầu và kết thúc!');
                return;
            }
            if (startDate > endDate) {
                alert('Lỗi: Ngày bắt đầu không được lớn hơn ngày kết thúc!');
                return;
            }
            window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
        }

        function exportToExcel() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            window.location.href = `reports.php?export=excel&start_date=${startDate}&end_date=${endDate}`;
        }

        function quickSelect(type) {
            const today = new Date();
            let start = new Date();
            let end = new Date();

            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            if (type === 'today') {
                // start & end = today
            } else if (type === 'week') {
                start.setDate(today.getDate() - 6);
            } else if (type === 'month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            }

            document.getElementById('startDate').value = formatDate(start);
            document.getElementById('endDate').value = formatDate(end);
            applyFilter();
        }
    </script>
</body>
</html>