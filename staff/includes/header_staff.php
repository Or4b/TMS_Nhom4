<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nhân Viên - TMS Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            width: 250px; background-color: #2c3e50; color: white; position: fixed; height: 100vh; padding-top: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar .logo { font-size: 1.6rem; font-weight: bold; text-align: center; margin-bottom: 30px; color: #ecf0f1; }
        .sidebar a { color: #bdc3c7; padding: 15px 20px; text-decoration: none; display: flex; align-items: center; border-left: 5px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: #ffffff; border-left: 5px solid #3498db; }
        .content { margin-left: 250px; padding: 20px; }
        .stat-card { text-align: center; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
        .stat-icon { font-size: 2.5rem; margin-bottom: 10px; }
        /* Style cho Sơ đồ ghế */
        .seat-map { display: flex; flex-wrap: wrap; justify-content: center; max-width: 300px; margin: 0 auto; padding: 10px; border-radius: 5px;}
        .seat-row { display: flex; margin-bottom: 5px; }
        .seat-button { width: 40px; height: 40px; margin: 3px; border-radius: 5px; border: 1px solid #ccc; cursor: pointer; font-size: 10px; }
        .seat-available { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .seat-booked { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; cursor: not-allowed; }
        .seat-selected { background-color: #007bff; border-color: #0069d9; color: white; }
        .seat-spacer { width: 20px; }
        .seat-button:disabled { opacity: 0.6; }
    </style>
</head>
<body>

<div class="sidebar d-flex flex-column">
    <h4 class="logo text-info">TMS Staff</h4>
    <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
    <a href="confirm_tickets.php"><i class="fas fa-check-circle me-2"></i> Xác nhận vé online</a>
    <a href="sell_ticket.php"><i class="fas fa-ticket-alt me-2"></i> Bán vé tại quầy</a>
    <a href="cancel_ticket.php"><i class="fas fa-times-circle me-2"></i> Hỗ trợ hủy vé</a>
    <a href="profile.php"><i class="fas fa-user-circle me-2"></i> Hồ sơ nhân viên</a>
    <a href="view_bookings.php"><i class="fas fa-search me-2"></i> Tra cứu vé</a>
</div>

<div class="content">
    <nav class="navbar navbar-light bg-white rounded-3 mb-4 shadow-sm p-3">
        <div class="container-fluid p-0">
            <h5 class="mb-0 text-dark fw-bold">Dashboard Nhân Viên</h5>
            <div class="d-flex align-items-center">
                <span class="me-3 text-secondary">Xin chào, **<?php echo $staff_name; ?>**</span>
                <a href="../../login.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-1"></i> Đăng xuất
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-0"></div>