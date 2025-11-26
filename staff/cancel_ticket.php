<?php
session_start();
require_once '../config.php';

// Kiểm tra session
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

// Tránh lỗi undefined key
$user_id = $_SESSION['user_id'] ?? null;
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

$message = '';
$ticket_info = null; 

// LOGIC XỬ LÝ TÌM KIẾM VÉ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_ticket'])) {
    $search_id = trim($_POST['booking_id']);

    if (!empty($search_id)) {
        try {
            // FIX: Sửa p1.name -> province_name
            // FIX: Thêm JOIN users/customers để lấy tên khách
            $sql = "SELECT b.*, t.departure_time, 
                           p1.province_name AS origin, p2.province_name AS destination,
                           u.full_name AS passenger_name, u.phone
                    FROM bookings b
                    JOIN trips t ON b.trip_id = t.id
                    JOIN provinces p1 ON t.departure_province_id = p1.id
                    JOIN provinces p2 ON t.destination_province_id = p2.id
                    LEFT JOIN customers c ON b.customer_id = c.id
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE b.id = ? AND b.status != 'cancelled'
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_id]);
            $ticket_info = $stmt->fetch();

            if (!$ticket_info) {
                $message = '<div class="alert alert-warning">Không tìm thấy vé hoặc vé đã bị hủy.</div>';
            }

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Lỗi tìm kiếm: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Vui lòng nhập Mã vé.</div>';
    }
}

// LOGIC XỬ LÝ HỦY VÉ (Khi nhân viên nhấn nút 'Hủy Vé')
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_action']) && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    
    // FIX: Sửa t.trip_id thành b.trip_id (vì trips table không có cột trip_id, id là PK)
    $sql_check = "SELECT b.total_amount, b.number_of_seats, b.trip_id, t.departure_time 
                  FROM bookings b
                  JOIN trips t ON b.trip_id = t.id
                  WHERE b.id = ?";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([$booking_id]);
    $current_ticket = $stmt->fetch();

    if ($current_ticket) {
        $departure_time = strtotime($current_ticket['departure_time']);
        $current_time = time();
        $total_amount = $current_ticket['total_amount'];
        
        $min_cancellation_time = 2 * 3600; // 2 giờ
        $time_diff = $departure_time - $current_time;
        
        if ($time_diff < $min_cancellation_time) {
            $message = '<div class="alert alert-danger">Không thể hủy vé! Yêu cầu hủy phải trước giờ khởi hành ít nhất 2 giờ.</div>';
        } else {
            // 2. Tính toán hoàn tiền theo chính sách
            $refund_rate = 0;
            if ($time_diff >= (24 * 3600)) { // Hủy trước 24 giờ
                $refund_rate = 0.80; 
            } elseif ($time_diff >= $min_cancellation_time) { // Hủy từ 2 đến 24 giờ
                $refund_rate = 0.50;
            }
            
            $refund_amount = $total_amount * $refund_rate;
            
            try {
                $pdo->beginTransaction();
                
                // Cập nhật trạng thái vé (Lưu ý: Logic total_amount = refund_amount để ghi nhận số thực thu/chi)
                $sql_update_booking = "UPDATE bookings SET status = 'cancelled', payment_status = 'refunded', total_amount = ? WHERE id = ?";
                $pdo->prepare($sql_update_booking)->execute([$refund_amount, $booking_id]);
                
                // Cập nhật số ghế trống (tăng available_seats)
                $pdo->prepare("UPDATE trips SET available_seats = available_seats + ? WHERE id = ?")->execute([$current_ticket['number_of_seats'], $current_ticket['trip_id']]);
                
                $pdo->commit();
                $message = '<div class="alert alert-success">Hủy vé thành công! Số tiền hoàn lại cho khách hàng: ' . number_format($refund_amount) . ' VNĐ (' . ($refund_rate * 100) . '% giá vé).</div>';
                
                // Reset ticket info để không hiện lại form
                $ticket_info = null; 
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $message = '<div class="alert alert-danger">Lỗi giao dịch hủy vé: ' . $e->getMessage() . '</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Dữ liệu vé không tồn tại để xử lý.</div>';
    }
}

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-times-circle me-2"></i> Hỗ trợ Hủy Vé Khách Hàng</h5>

<?php echo $message; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-warning text-dark"><i class="fas fa-search me-2"></i> Tìm Kiếm Vé</div>
    <div class="card-body">
        <form method="POST" action="cancel_ticket.php">
            <input type="hidden" name="search_ticket" value="1">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Nhập ID vé cần hủy" name="booking_id" required 
                       value="<?php echo $_POST['booking_id'] ?? ''; ?>">
                <button type="submit" class="btn btn-warning text-dark"><i class="fas fa-search me-2"></i> Tìm Kiếm</button>
            </div>
        </form>
    </div>
</div>

<?php if ($ticket_info): ?>
    <?php
    // Chuyển departure_time sang timestamp nếu tồn tại
    $departure_time = isset($ticket_info['departure_time']) ? strtotime($ticket_info['departure_time']) : null;
    
    // Thời gian hiện tại
    $current_time = time();
    
    // Tính số giờ còn lại trước khởi hành
    $time_diff_hours = ($departure_time) ? floor(($departure_time - $current_time) / 3600) : 0;
    
    // FIX: Tính toán tỷ lệ hoàn tiền để hiển thị
    $refund_rate = 0;
    if ($time_diff_hours >= 24) {
        $refund_rate = 0.8;
    } elseif ($time_diff_hours >= 2) {
        $refund_rate = 0.5;
    }
    $refund_amount = $ticket_info['total_amount'] * $refund_rate;
    ?>

<div class="card shadow-sm mb-4 border-danger">
    <div class="card-header bg-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> Thông Tin Vé Cần Hủy: ID #<?php echo $ticket_info['id']; ?></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Chi Tiết Vé Đặt</h6>
                <p><strong>Tên Khách Hàng:</strong> <?php echo htmlspecialchars($ticket_info['passenger_name'] ?? 'Khách vãng lai'); ?></p>
                <p><strong>SĐT:</strong> <?php echo htmlspecialchars($ticket_info['phone'] ?? 'N/A'); ?></p>
                <p><strong>Tuyến:</strong> <?php echo htmlspecialchars($ticket_info['origin'] . ' -> ' . $ticket_info['destination']); ?></p>
                <p><strong>Ghế Đặt:</strong> <?php echo $ticket_info['number_of_seats']; ?> ghế</p>
                <p><strong>Giá trị Vé:</strong> <?php echo number_format($ticket_info['total_amount']); ?> VNĐ</p>
            </div>
            <div class="col-md-6 border-start">
                <h6>Quy Định Hủy & Hoàn Tiền</h6>
                
                <?php if ($departure_time): ?>
                    <p><strong>Giờ Khởi Hành:</strong> <?php echo date('H:i:s d/m/Y', $departure_time); ?></p>
                    <p><strong>Thời Gian Còn Lại:</strong> <span class="<?php echo ($time_diff_hours < 2) ? 'text-danger fw-bold' : 'text-success'; ?>">
                        <?php echo max(0, $time_diff_hours); ?> giờ
                    </span></p>
                <?php else: ?>
                    <p><strong>Giờ Khởi Hành:</strong> N/A</p>
                <?php endif; ?>

                <hr>
                <?php if ($time_diff_hours >= 2): ?>
                    <div class="alert alert-info">
                        <strong>Dự kiến hoàn tiền:</strong> <?php echo $refund_rate * 100; ?>% giá trị vé.<br>
                        <strong>Số tiền:</strong> <?php echo number_format($refund_amount); ?> VNĐ
                    </div>
                    <form method="POST" action="cancel_ticket.php" onsubmit="return confirm('Xác nhận hủy vé này và hoàn <?php echo number_format($refund_amount); ?>đ cho khách?');">
                        <input type="hidden" name="cancel_action" value="1">
                        <input type="hidden" name="booking_id" value="<?php echo $ticket_info['id']; ?>">
                        <button type="submit" class="btn btn-danger w-100 mt-3">
                            <i class="fas fa-trash-alt me-2"></i> TIẾN HÀNH HỦY VÉ
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger mt-3 text-center">
                        <i class="fas fa-ban mb-2 d-block text-2xl"></i>
                        Vé **không đủ điều kiện hủy**<br>(Phải hủy trước giờ khởi hành ít nhất 2 giờ).
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
include 'includes/footer_staff.php'; 
?>