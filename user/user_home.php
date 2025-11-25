<?php
// user_home.php - SCR-1.4
require_once 'config.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Lấy thông tin User từ Session
$full_name = $_SESSION['full_name'];
// Tạo Avatar chữ cái đầu (Ví dụ: Nguyễn Văn A -> A)
$parts = explode(' ', $full_name);
$avatar_letter = substr(end($parts), 0, 1);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - TMS VéXe</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="home-header">
        <h2 style="margin:0; color:#1877f2;">TMS VéXe</h2>
        <nav>
            <a href="#" class="link" style="display:inline; margin:0 10px;">Trang chủ</a>
            <a href="#" class="link" style="display:inline; margin:0 10px;">Vé của tôi</a>
            <a href="#" class="link" style="display:inline; margin:0 10px;">Khuyến mãi</a>
        </nav>
        
        <div class="user-menu">
            <span>Xin chào, <strong><?= htmlspecialchars($full_name) ?></strong></span>
            <div class="avatar"><?= $avatar_letter ?></div>
            <div class="dropdown-content">
                <a href="#">Hồ sơ cá nhân</a>
                <a href="#">Đổi mật khẩu</a>
                <a href="#" onclick="logout()">Đăng xuất</a>
            </div>
        </div>
    </header>

    <div class="search-box">
        <h3 style="text-align: center; margin-top: 0;">Tìm Chuyến Xe</h3>
        
        <form id="searchForm" action="search_results.php" method="GET">
            <div class="trip-type">
                <label style="display:inline-flex; align-items:center;">
                    <input type="radio" name="trip_type" value="one-way" checked onchange="toggleReturnDate(false)"> Một chiều
                </label>
                <label style="display:inline-flex; align-items:center;">
                    <input type="radio" name="trip_type" value="round-trip" onchange="toggleReturnDate(true)"> Khứ hồi
                </label>
            </div>

            <div class="search-row">
                <div class="search-col">
                    <label>Nơi đi</label>
                    <select name="departure_id" id="departureSelect" required>
                        <option value="">Chọn điểm đi</option>
                        </select>
                </div>
                <div class="search-col">
                    <label>Nơi đến</label>
                    <select name="destination_id" id="destinationSelect" required>
                        <option value="">Chọn điểm đến</option>
                        </select>
                </div>
            </div>

            <div class="search-row" style="margin-top: 15px;">
                <div class="search-col">
                    <label>Ngày đi</label>
                    <input type="date" name="date_go" required>
                </div>
                <div class="search-col" id="returnDateDiv" style="display: none;">
                    <label>Ngày về</label>
                    <input type="date" name="date_return">
                </div>
            </div>

            <button type="submit" style="margin-top: 25px; background: #e74c3c; font-weight: bold;">TÌM CHUYẾN XE</button>
        </form>
    </div>

    <div style="max-width: 800px; margin: 0 auto;">
        <h3>Thông báo</h3>
        <div style="background: white; padding: 15px; border-radius: 8px; border-left: 5px solid #e74c3c;">
            <strong>Chuyến xe đã bị hủy</strong><br>
            <small>Chuyến xe Hà Nội - Đà Nẵng ngày 15/12/2025 đã bị hủy. Vui lòng kiểm tra email.</small>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Khi trang load xong, gọi API lấy tỉnh thành
        document.addEventListener('DOMContentLoaded', function() {
            loadProvinces();
        });
    </script>
</body>
</html>