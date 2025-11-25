<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>🏢 TMS Admin</h2>
        <p>Quản trị hệ thống</p>
    </div>
    <ul class="sidebar-menu">
        <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">📊 Dashboard</a>
        </li>
        <li class="<?php echo $current_page == 'manage_trip.php' ? 'active' : ''; ?>">
            <a href="manage_trip.php">🚌 Quản lý chuyến đi</a>
        </li>
        <li class="<?php echo $current_page == 'manage_customers.php' ? 'active' : ''; ?>">
            <a href="manage_customers.php">👥 Quản lý khách hàng</a>
        </li>
        <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <a href="reports.php">📈 Báo cáo doanh thu</a>
        </li>
        <li class="<?php echo $current_page == 'manage_promotions.php' ? 'active' : ''; ?>">
            <a href="manage_promotions.php">🎁 Khuyến mãi</a>
        </li>
        <li class="<?php echo $current_page == 'manage_staff.php' ? 'active' : ''; ?>">
            <a href="manage_staff.php">👨‍💼 Quản lý nhân viên</a>
        </li>
    </ul>
</div>