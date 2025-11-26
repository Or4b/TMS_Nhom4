<?php
include '../config.php';

$pageTitle = "Quản lý Chuyến đi";

// --- XỬ LÝ 1: THÊM CHUYẾN ĐI MỚI ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_trip'])) {
    $departure_province_id = $_POST['departure_province_id'];
    $destination_province_id = $_POST['destination_province_id'];
    $departure_time = $_POST['departure_time'];
    $price = $_POST['price'];
    $total_seats = $_POST['total_seats'];
    $ticket_type = $_POST['ticket_type'];
    $return_time = ($ticket_type == 'round-trip' && !empty($_POST['return_time'])) ? $_POST['return_time'] : null;

    $available_seats = $total_seats;

    try {
        // Mặc định thêm mới là 'scheduled' (Đang mở vé)
        // FIX: ticket_type trong DB là 'one_way'/'round_trip' (underscore) nhưng form gửi lên là dash (-).
        // Tuy nhiên ở đây ta cứ lưu theo form gửi lên, nhưng tốt nhất nên chuẩn hóa.
        // Để an toàn với DB mới sửa, ta map lại giá trị:
        $db_ticket_type = ($ticket_type == 'round-trip') ? 'round_trip' : 'one_way';

        $sql = "INSERT INTO trips (departure_province_id, destination_province_id, departure_time, price, available_seats, total_seats, ticket_type, return_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$departure_province_id, $destination_province_id, $departure_time, $price, $available_seats, $total_seats, $db_ticket_type, $return_time]);

        $_SESSION['message'] = "Thêm chuyến đi thành công!";
        header("Location: manage_trip.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Lỗi khi thêm chuyến đi: " . $e->getMessage();
    }
}

// --- XỬ LÝ 2: CẬP NHẬT THÔNG TIN CHUYẾN ĐI ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_trip'])) {
    $trip_id = $_POST['trip_id'];
    $departure_province_id = $_POST['departure_province_id'];
    $destination_province_id = $_POST['destination_province_id'];
    $departure_time = $_POST['departure_time'];
    $price = $_POST['price'];
    $total_seats = $_POST['total_seats'];
    $ticket_type = $_POST['ticket_type'];

    $return_time = ($ticket_type == 'round-trip' && !empty($_POST['return_time'])) ? $_POST['return_time'] : null;
    
    // Map lại ticket type cho chuẩn DB
    $db_ticket_type = ($ticket_type == 'round-trip') ? 'round_trip' : 'one_way';

    try {
        $stmt = $pdo->prepare("SELECT available_seats, total_seats FROM trips WHERE id = ?");
        $stmt->execute([$trip_id]);
        $current_trip = $stmt->fetch();

        $current_booked_seats = $current_trip['total_seats'] - $current_trip['available_seats'];
        $new_available_seats = $total_seats - $current_booked_seats;
        if ($new_available_seats < 0) $new_available_seats = 0;

        $stmt = $pdo->prepare("UPDATE trips SET departure_province_id = ?, destination_province_id = ?, departure_time = ?, price = ?, total_seats = ?, available_seats = ?, ticket_type = ?, return_time = ? WHERE id = ?");
        $stmt->execute([$departure_province_id, $destination_province_id, $departure_time, $price, $total_seats, $new_available_seats, $db_ticket_type, $return_time, $trip_id]);

        $_SESSION['message'] = "Cập nhật chuyến đi thành công!";
        header("Location: manage_trip.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Lỗi khi cập nhật chuyến đi: " . $e->getMessage();
    }
}

// --- XỬ LÝ 3: CÁC ACTION (XÓA, ĐỔI TRẠNG THÁI) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $tripId = $_GET['id'];

    // --- ĐOẠN CODE NÀY THAY THẾ CHO PHẦN IF DELETE CŨ ---
    if ($_GET['action'] == 'delete') {
        try {
            $pdo->beginTransaction();

            // 1. Chuyển tất cả vé của chuyến này sang trạng thái 'cancelled'
            // (Giữ lại dữ liệu vé để nhân viên biết mà hoàn tiền/thông báo khách)
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE trip_id = ?");
            $stmt->execute([$tripId]);

            // 2. Chuyển trạng thái chuyến đi sang 'cancelled' (Thay vì DELETE)
            // Việc này giúp giữ lại thông tin chuyến đi để tham chiếu trong vé của khách
            $stmt = $pdo->prepare("UPDATE trips SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$tripId]);

            $pdo->commit();
            
            $_SESSION['message'] = "Đã hủy chuyến đi! Tất cả vé đã đặt (nếu có) đã được chuyển sang trạng thái 'Đã hủy'.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Lỗi khi xử lý hủy chuyến: " . $e->getMessage();
        }
        header("Location: manage_trip.php");
        exit();
    }
}

// Lấy tất cả chuyến đi lần 1 (để xử lý tự động)
// FIX: Sửa dp.name -> dp.province_name và dsp.name -> dsp.province_name
$stmt = $pdo->query("
    SELECT t.*, 
           dp.province_name as departure_province_name, 
           dsp.province_name as destination_province_name 
    FROM trips t
    JOIN provinces dp ON t.departure_province_id = dp.id
    JOIN provinces dsp ON t.destination_province_id = dsp.id
    ORDER BY t.departure_time DESC
");
$trips = $stmt->fetchAll();

// --- XỬ LÝ 4: TỰ ĐỘNG CẬP NHẬT TRẠNG THÁI ---
foreach ($trips as $trip) {
    $current_time = date('Y-m-d H:i:s');
    $departure_time = $trip['departure_time'];

    // --- [QUAN TRỌNG] CHỐT CHẶN BẢO VỆ ---
    if ($trip['status'] == 'paused' || $trip['status'] == 'cancelled') {
        continue;
    }

    // 1. Quá giờ -> Hủy (Hoặc Completed tùy logic, ở đây giữ logic cũ là Cancelled hoặc nên đổi thành Completed)
    // Thường quá giờ khởi hành thì là Ongoing hoặc Completed chứ không phải Cancelled. 
    // Nhưng tôi sẽ giữ nguyên logic cũ của bạn để tránh lỗi logic khác.
    if ($current_time > $departure_time) {
        // Tùy chọn: Đổi thành 'completed' thay vì 'cancelled' nếu muốn đúng thực tế
        $stmt = $pdo->prepare("UPDATE trips SET status = 'completed' WHERE id = ?"); 
        $stmt->execute([$trip['id']]);
    }
    // 2. Hết ghế -> Full
    elseif ($trip['available_seats'] == 0 && $trip['status'] != 'full') {
        $stmt = $pdo->prepare("UPDATE trips SET status = 'full' WHERE id = ?");
        $stmt->execute([$trip['id']]);
    }
    // 3. Còn ghế -> Mở bán (Scheduled)
    elseif ($trip['available_seats'] > 0 && $trip['status'] == 'full') {
        $stmt = $pdo->prepare("UPDATE trips SET status = 'scheduled' WHERE id = ?");
        $stmt->execute([$trip['id']]);
    }
}

// Lấy lại dữ liệu lần 2 (để hiển thị ra bảng sau khi đã update)
// FIX: Sửa dp.name -> dp.province_name
$stmt = $pdo->query("
    SELECT t.*, 
           dp.province_name as departure_province_name, 
           dsp.province_name as destination_province_name 
    FROM trips t
    JOIN provinces dp ON t.departure_province_id = dp.id
    JOIN provinces dsp ON t.destination_province_id = dsp.id
    ORDER BY t.departure_time DESC
");
$trips = $stmt->fetchAll();

// Lấy danh sách tỉnh cho dropdown form
// FIX: Sửa ORDER BY name -> ORDER BY province_name
$stmt = $pdo->query("SELECT * FROM provinces WHERE status = 'active' ORDER BY province_name");
$provinces = $stmt->fetchAll();

// Lấy thông tin chỉnh sửa (nếu có action edit)
$edit_trip = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    // FIX: Sửa dp.name -> dp.province_name
    $stmt = $pdo->prepare("
        SELECT t.*, dp.province_name as departure_province_name, dsp.province_name as destination_province_name 
        FROM trips t
        JOIN provinces dp ON t.departure_province_id = dp.id
        JOIN provinces dsp ON t.destination_province_id = dsp.id
        WHERE t.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $edit_trip = $stmt->fetch();
    
    // Chuẩn hóa lại ticket_type để hiển thị đúng trên form (đổi _ thành -)
    if($edit_trip) {
        $edit_trip['ticket_type'] = str_replace('_', '-', $edit_trip['ticket_type']);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* GIỮ NGUYÊN CSS CŨ */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8f9fa; display: flex; }
        .sidebar { width: 250px; background: #2c3e50; color: white; height: 100vh; position: fixed; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #34495e; text-align: center; }
        .sidebar-menu { list-style: none; padding: 1rem 0; }
        .sidebar-menu li { padding: 0.75rem 1.5rem; }
        .sidebar-menu li.active { background: #34495e; border-left: 4px solid #3498db; }
        .sidebar-menu a { color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .main-content { margin-left: 250px; padding: 2rem; width: calc(100% - 250px); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .user-menu { display: flex; align-items: center; gap: 1rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; font-size: 0.9rem; transition: all 0.3s; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .trips-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
        .status-scheduled { background: #d4edda; color: #155724; }
        .status-full { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        .status-paused { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .ticket-type-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
        .ticket-type-one-way, .ticket-type-one_way { background: #e8f4fd; color: #0c5460; }
        .ticket-type-round-trip, .ticket-type-round_trip { background: #e8f5e9; color: #1b5e20; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }
        .trip-type-selection { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .trip-type-btn { flex: 1; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; transition: all 0.3s; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .trip-type-btn:hover { border-color: #3498db; }
        .trip-type-btn.active { border-color: #3498db; background: #e8f4fd; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; transition: border 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: #3498db; outline: none; }
        .form-row { display: flex; gap: 1rem; }
        .form-row .form-group { flex: 1; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content { display: none; position: absolute; background-color: white; min-width: 160px; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); z-index: 1; border-radius: 6px; overflow: hidden; }
        .dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; border-bottom: 1px solid #eee; }
        .dropdown-content a:hover { background-color: #f8f9fa; }
        .dropdown:hover .dropdown-content { display: block; }
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #ddd; }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-bus"></i> Quản Lý Chuyến Đi</h1>
            <div class="user-menu">
                <span>Xin chào, <?php echo $_SESSION['full_name'] ?? 'Quản trị viên'; ?></span>
                <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <button class="btn btn-success" onclick="openAddTripModal()" style="margin-bottom: 1rem;">
            <i class="fas fa-plus"></i> Thêm chuyến đi
        </button>

        <div class="trips-table">
            <table>
                <thead>
                    <tr>
                        <th>Mã chuyến</th>
                        <th>Tuyến đường</th>
                        <th>Loại vé</th>
                        <th>Ngày giờ khởi hành</th>
                        <th>Ngày giờ về</th>
                        <th>Ghế trống</th>
                        <th>Giá vé</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trips)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-bus-slash"></i>
                                    <h3>Chưa có chuyến đi nào</h3>
                                    <p>Hãy thêm chuyến đi đầu tiên để bắt đầu</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><strong>#TRIP<?php echo str_pad($trip['id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo htmlspecialchars($trip['departure_province_name']); ?> → <?php echo htmlspecialchars($trip['destination_province_name']); ?></td>
                                <td>
                                    <span class="ticket-type-badge ticket-type-<?php echo $trip['ticket_type']; ?>">
                                        <?php 
                                            if($trip['ticket_type'] == 'round-trip' || $trip['ticket_type'] == 'round_trip') echo 'Khứ hồi';
                                            else echo 'Một chiều'; 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('H:i d/m/Y', strtotime($trip['departure_time'])); ?>
                                </td>
                                <td>
                                    <?php 
                                        // Kiểm tra cả 2 trường hợp format
                                        $is_round_trip = ($trip['ticket_type'] == 'round-trip' || $trip['ticket_type'] == 'round_trip');
                                        if ($is_round_trip && $trip['return_time']): 
                                    ?>
                                        <?php echo date('H:i d/m/Y', strtotime($trip['return_time'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $trip['available_seats']; ?>/<?php echo $trip['total_seats']; ?></td>
                                <td><?php echo number_format($trip['price'], 0, ',', '.'); ?>₫</td>

                                <td>
                                    <?php
                                    $status_label = $trip['status'];
                                    $css_class = '';

                                    if ($trip['status'] == 'scheduled') {
                                        $status_label = 'Đang mở vé';
                                        $css_class = 'status-scheduled';
                                    } elseif ($trip['status'] == 'paused' || $trip['status'] == '') {
                                        $status_label = 'Tạm dừng';
                                        $css_class = 'status-paused';
                                    } elseif ($trip['status'] == 'full') {
                                        $status_label = 'Hết chỗ';
                                        $css_class = 'status-full';
                                    } elseif ($trip['status'] == 'cancelled') {
                                        $status_label = 'Đã hủy';
                                        $css_class = 'status-cancelled';
                                    } elseif ($trip['status'] == 'completed') {
                                        $status_label = 'Hoàn thành';
                                        $css_class = 'status-scheduled'; // Hoặc tạo class mới cho completed
                                    }
                                    ?>

                                    <span class="status-badge <?php echo $css_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="manage_trip.php?action=edit&id=<?php echo $trip['id']; ?>" class="btn btn-info">
                                        <i class="fas fa-edit"></i> Sửa
                                    </a>

                                    <div class="dropdown">
                                        <button class="btn btn-primary">
                                            <i class="fas fa-sync-alt"></i> Trạng thái
                                        </button>
                                        <div class="dropdown-content">
                                            <a href="manage_trip.php?action=update_status&id=<?php echo $trip['id']; ?>&status=scheduled">
                                                Mở vé bán
                                            </a>

                                            <a href="manage_trip.php?action=update_status&id=<?php echo $trip['id']; ?>&status=paused">
                                                Tạm dừng vé
                                            </a>
                                        </div>
                                    </div>

                                    <a href="manage_trip.php?action=delete&id=<?php echo $trip['id']; ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm('Bạn có chắc chắn muốn xóa chuyến đi này?')">
                                        <i class="fas fa-trash"></i> Xóa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="addTripModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-bus"></i> Thêm Chuyến Đi Mới</h2>
                <button class="close-btn" onclick="closeModal('addTripModal')">&times;</button>
            </div>

            <div class="trip-type-selection">
                <div class="trip-type-btn active" onclick="selectTripType('one-way')">
                    <i class="fas fa-arrow-right"></i>
                    <h3>Một chiều</h3>
                    <p>Chuyến đi từ điểm A đến điểm B</p>
                </div>
                <div class="trip-type-btn" onclick="selectTripType('round-trip')">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>Khứ hồi</h3>
                    <p>Chuyến đi từ điểm A đến B và quay trở lại</p>
                </div>
            </div>

            <form method="POST" id="addTripForm">
                <input type="hidden" name="add_trip" value="1">
                <input type="hidden" name="ticket_type" id="tripType" value="one-way">

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Điểm đi *</label>
                        <select name="departure_province_id" required>
                            <option value="">-- Chọn tỉnh/thành phố --</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo $province['id']; ?>"><?php echo htmlspecialchars($province['province_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-flag-checkered"></i> Điểm đến *</label>
                        <select name="destination_province_id" required>
                            <option value="">-- Chọn tỉnh/thành phố --</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo $province['id']; ?>"><?php echo htmlspecialchars($province['province_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="dateTimeRow">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Ngày giờ khởi hành *</label>
                        <input type="datetime-local" name="departure_time" required>
                    </div>
                    <div class="form-group" id="returnTimeGroup" style="display: none;">
                        <label><i class="fas fa-calendar-check"></i> Ngày giờ về *</label>
                        <input type="datetime-local" name="return_time">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-money-bill-wave"></i> Giá vé *</label>
                        <input type="number" name="price" required min="0" placeholder="VD: 350000">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-chair"></i> Số ghế *</label>
                        <input type="number" name="total_seats" required min="1" max="60" placeholder="VD: 45">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal('addTripModal')">
                        <i class="fas fa-times"></i> Hủy
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Thêm chuyến đi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($edit_trip): ?>
        <div class="modal" id="editTripModal" style="display: flex;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-edit"></i> Chỉnh Sửa Chuyến Đi</h2>
                    <button class="close-btn" onclick="closeEditModal()">&times;</button>
                </div>

                <div class="trip-type-selection">
                    <div class="trip-type-btn <?php echo $edit_trip['ticket_type'] == 'one-way' ? 'active' : ''; ?>" onclick="selectEditTripType('one-way')">
                        <i class="fas fa-arrow-right"></i>
                        <h3>Một chiều</h3>
                        <p>Chuyến đi từ điểm A đến điểm B</p>
                    </div>
                    <div class="trip-type-btn <?php echo $edit_trip['ticket_type'] == 'round-trip' ? 'active' : ''; ?>" onclick="selectEditTripType('round-trip')">
                        <i class="fas fa-exchange-alt"></i>
                        <h3>Khứ hồi</h3>
                        <p>Chuyến đi từ điểm A đến B và quay trở lại</p>
                    </div>
                </div>

                <form method="POST" id="editTripForm">
                    <input type="hidden" name="update_trip" value="1">
                    <input type="hidden" name="trip_id" value="<?php echo $edit_trip['id']; ?>">
                    <input type="hidden" name="ticket_type" id="editTripType" value="<?php echo $edit_trip['ticket_type']; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Điểm đi *</label>
                            <select name="departure_province_id" required>
                                <option value="">-- Chọn tỉnh/thành phố --</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?php echo $province['id']; ?>" <?php echo $edit_trip['departure_province_id'] == $province['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($province['province_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-flag-checkered"></i> Điểm đến *</label>
                            <select name="destination_province_id" required>
                                <option value="">-- Chọn tỉnh/thành phố --</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?php echo $province['id']; ?>" <?php echo $edit_trip['destination_province_id'] == $province['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($province['province_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" id="editDateTimeRow">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Ngày giờ khởi hành *</label>
                            <input type="datetime-local" name="departure_time" value="<?php echo date('Y-m-d\TH:i', strtotime($edit_trip['departure_time'])); ?>" required>
                        </div>
                        <div class="form-group" id="editReturnTimeGroup" style="display: <?php echo $edit_trip['ticket_type'] == 'round-trip' ? 'block' : 'none'; ?>;">
                            <label><i class="fas fa-calendar-check"></i> Ngày giờ về *</label>
                            <input type="datetime-local" name="return_time" value="<?php echo $edit_trip['return_time'] ? date('Y-m-d\TH:i', strtotime($edit_trip['return_time'])) : ''; ?>" <?php echo $edit_trip['ticket_type'] == 'round-trip' ? 'required' : ''; ?>>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> Giá vé *</label>
                            <input type="number" name="price" value="<?php echo $edit_trip['price']; ?>" required min="0" placeholder="VD: 350000">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-chair"></i> Số ghế *</label>
                            <input type="number" name="total_seats" value="<?php echo $edit_trip['total_seats']; ?>" required min="1" max="60" placeholder="VD: 45">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="manage_trip.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Hủy
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Cập nhật chuyến đi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function openAddTripModal() {
            document.getElementById('addTripModal').style.display = 'flex';
            const today = new Date().toISOString().slice(0, 16);
            const datetimeInputs = document.querySelectorAll('#addTripModal input[type="datetime-local"]');
            datetimeInputs.forEach(input => {
                input.min = today;
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function closeEditModal() {
            window.location.href = 'manage_trip.php';
        }

        function selectTripType(type) {
            const buttons = document.querySelectorAll('#addTripModal .trip-type-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            const returnTimeGroup = document.getElementById('returnTimeGroup');
            const returnTimeInput = document.querySelector('#returnTimeGroup input[name="return_time"]');

            if (type === 'one-way') {
                document.querySelector('#addTripModal .trip-type-btn:nth-child(1)').classList.add('active');
                document.getElementById('tripType').value = 'one-way';
                returnTimeGroup.style.display = 'none';
                returnTimeInput.removeAttribute('required');
                returnTimeInput.value = '';
            } else {
                document.querySelector('#addTripModal .trip-type-btn:nth-child(2)').classList.add('active');
                document.getElementById('tripType').value = 'round-trip';
                returnTimeGroup.style.display = 'block';
                returnTimeInput.setAttribute('required', 'required');
            }
        }

        function selectEditTripType(type) {
            const buttons = document.querySelectorAll('#editTripModal .trip-type-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            const returnTimeGroup = document.getElementById('editReturnTimeGroup');
            const returnTimeInput = document.querySelector('#editReturnTimeGroup input[name="return_time"]');

            if (type === 'one-way') {
                document.querySelector('#editTripModal .trip-type-btn:nth-child(1)').classList.add('active');
                document.getElementById('editTripType').value = 'one-way';
                returnTimeGroup.style.display = 'none';
                returnTimeInput.removeAttribute('required');
                returnTimeInput.value = '';
            } else {
                document.querySelector('#editTripModal .trip-type-btn:nth-child(2)').classList.add('active');
                document.getElementById('editTripType').value = 'round-trip';
                returnTimeGroup.style.display = 'block';
                returnTimeInput.setAttribute('required', 'required');
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('addTripModal');
            if (event.target === modal) {
                closeModal('addTripModal');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().slice(0, 16);
            const datetimeInputs = document.querySelectorAll('input[type="datetime-local"]');
            datetimeInputs.forEach(input => {
                if (!input.value) {
                    input.min = today;
                }
            });
        });
    </script>
</body>

</html>