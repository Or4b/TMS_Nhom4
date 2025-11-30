<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

// Kiểm tra session
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

$staff_id = $_SESSION['staff_id'] ?? null;
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

$message = '';
$ticket_info = null; 

// --- 1. LOGIC TÌM KIẾM VÉ ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_ticket'])) {
    $search_id = trim($_POST['booking_id']);

    if (!empty($search_id)) {
        try {
            // SỬA: Dùng booking_id, province_name, total_price, quantity, join users để lấy tên
            $sql = "SELECT b.booking_id AS id, b.seat_numbers, b.total_price AS total_amount, b.quantity AS number_of_seats, b.status,
                           t.departure_time, 
                           p1.province_name AS origin, p2.province_name AS destination,
                           u.full_name AS passenger_name, u.phone
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    JOIN trips t ON b.trip_id = t.id
                    JOIN provinces p1 ON t.departure_province_id = p1.id
                    JOIN provinces p2 ON t.destination_province_id = p2.id
                    WHERE b.booking_id = ? AND b.status != 'cancelled'
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_id]);
            $ticket_info = $stmt->fetch(PDO::FETCH_ASSOC);

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

// --- 2. LOGIC HỦY VÉ ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_action']) && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];

    // SỬA: Lấy đúng cột total_price, quantity, booking_id
    $sql_check = "SELECT b.total_price AS total_amount, b.quantity AS number_of_seats, b.payment_status,
                          t.id AS trip_id, t.departure_time
                    FROM bookings b
                    JOIN trips t ON b.trip_id = t.id
                    WHERE b.booking_id = ?";

    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([$booking_id]);
    $current_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Nếu vé tồn tại và chưa hoàn tiền (hoặc logic tùy chỉnh của bạn)
    if ($current_ticket) { 
        $departure_time = strtotime($current_ticket['departure_time']);
        $current_time = time();
        $total_amount = $current_ticket['total_amount'];
        
        $min_cancellation_time = 2 * 3600; // 2 giờ
        $time_diff = $departure_time - $current_time;
        $time_diff_hours = floor($time_diff / 3600);

        if ($time_diff < $min_cancellation_time) {
            $message = '<div class="alert alert-danger">Không thể hủy vé! Yêu cầu hủy phải trước giờ khởi hành ít nhất 2 giờ.</div>';
        } else {
            // Tính toán hoàn tiền
            $refund_rate = 0;
            if ($time_diff_hours >= 24) {
                $refund_rate = 0.80; 
            } elseif ($time_diff_hours >= 2) {
                $refund_rate = 0.50; 
            }
            
            $refund_amount = $total_amount * $refund_rate;
            
            try {
                $pdo->beginTransaction();
                
                // SỬA: Cập nhật status. Bỏ 'refund_amount', 'updated_by' vì bảng bookings trong DB không có cột này.
                // Nếu muốn lưu refund_amount, bạn cần vào PHPMyAdmin thêm cột này vào bảng bookings.
                $sql_update_booking = "UPDATE bookings SET status = 'cancelled', payment_status = 'failed' WHERE booking_id = ?";
                $pdo->prepare($sql_update_booking)->execute([$booking_id]);
                
                // Hoàn lại ghế
                $pdo->prepare("UPDATE trips SET available_seats = available_seats + ? WHERE id = ?")
                    ->execute([$current_ticket['number_of_seats'], $current_ticket['trip_id']]);
                
                $pdo->commit();
                $message = '<div class="alert alert-success">Hủy vé thành công! Số tiền cần hoàn lại khách: ' . number_format($refund_amount) . ' VNĐ (' . ($refund_rate * 100) . '%).</div>';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $message = '<div class="alert alert-danger">Lỗi giao dịch hủy vé: ' . $e->getMessage() . '</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Lỗi: Không tìm thấy vé.</div>';
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
                        value="<?php echo htmlspecialchars($_POST['booking_id'] ?? ''); ?>">
                <button type="submit" class="btn btn-warning text-dark"><i class="fas fa-search me-2"></i> Tìm Kiếm</button>
            </div>
        </form>
    </div>
</div>

<?php if ($ticket_info): ?>
    <?php
    $departure_time_ts = isset($ticket_info['departure_time']) ? strtotime($ticket_info['departure_time']) : null;
    $current_time_ts = time();
    $time_diff_hours_estimate = ($departure_time_ts) ? floor(($departure_time_ts - $current_time_ts) / 3600) : null;
    
    $refund_rate_estimate = 0; 
    if ($time_diff_hours_estimate >= 24) {
        $refund_rate_estimate = 0.80; 
    } elseif ($time_diff_hours_estimate >= 2) {
        $refund_rate_estimate = 0.50;
    }
    ?>

<div class="card shadow-sm mb-4 border-danger">
    <div class="card-header bg-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i> Thông Tin Vé Cần Hủy: ID #<?php echo $ticket_info['id']; ?></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Chi Tiết Vé Đặt</h6>
                <p><strong>Tên Khách Hàng:</strong> <?php echo htmlspecialchars($ticket_info['passenger_name'] ?? 'N/A'); ?></p>
                <p><strong>SĐT:</strong> <?php echo htmlspecialchars($ticket_info['phone'] ?? 'N/A'); ?></p>
                <p><strong>Tuyến:</strong> <?php echo htmlspecialchars($ticket_info['origin'] . ' -> ' . $ticket_info['destination']); ?></p>
                <p><strong>Ghế Đặt:</strong> <?php echo htmlspecialchars($ticket_info['seat_numbers'] ?? 'N/A'); ?> (<?php echo $ticket_info['number_of_seats']; ?> ghế)</p>
                <p><strong>Giá trị Vé:</strong> <?php echo number_format($ticket_info['total_amount']); ?> VNĐ</p>
            </div>
            <div class="col-md-6 border-start">
                <h6>Quy Định Hủy & Hoàn Tiền</h6>
                <?php if ($departure_time_ts): ?>
                    <p><strong>Giờ Khởi Hành:</strong> <?php echo date('H:i:s d/m/Y', $departure_time_ts); ?></p>
                    <p><strong>Thời Gian Còn Lại:</strong> <span class="<?php echo ($time_diff_hours_estimate < 2) ? 'text-danger fw-bold' : 'text-success'; ?>">
                        <?php echo max(0, $time_diff_hours_estimate); ?> giờ
                    </span></p>
                <?php else: ?>
                    <p><strong>Giờ Khởi Hành:</strong> N/A</p>
                <?php endif; ?>

                <hr>
                <?php if ($time_diff_hours_estimate >= 2): ?>
                    <div class="alert alert-info">Ước tính hoàn: **<?php echo $refund_rate_estimate * 100; ?>%** (Khoảng **<?php echo number_format($ticket_info['total_amount'] * $refund_rate_estimate); ?> VNĐ**)</div>
                    <form method="POST" action="cancel_ticket.php" onsubmit="return confirm('Xác nhận hủy vé ID #<?php echo $ticket_info['id']; ?>?');">
                        <input type="hidden" name="cancel_action" value="1">
                        <input type="hidden" name="booking_id" value="<?php echo $ticket_info['id']; ?>">
                        <button type="submit" class="btn btn-danger w-100 mt-3">
                            <i class="fas fa-trash-alt me-2"></i> TIẾN HÀNH HỦY VÉ
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger mt-3 text-center">Vé **không đủ điều kiện hủy** (Chưa đủ 2 giờ).</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
// --- 3. DANH SÁCH VÉ ĐÃ HỦY ---
// SỬA: Sửa tên cột p1.name -> p1.province_name, b.id -> b.booking_id, v.v...
$sql_cancelled = "SELECT b.booking_id AS id, b.seat_numbers, b.total_price AS total_amount, 
                         t.departure_time, 
                         p1.province_name AS origin, p2.province_name AS destination,
                         u.full_name AS passenger_name
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    JOIN trips t ON b.trip_id = t.id
                    JOIN provinces p1 ON t.departure_province_id = p1.id
                    JOIN provinces p2 ON t.destination_province_id = p2.id
                    WHERE b.status = 'cancelled'
                    ORDER BY b.booking_id DESC
                    LIMIT 20"; // Giới hạn 20 vé gần nhất để tránh lag

try {
    $stmt_cancelled = $pdo->prepare($sql_cancelled);
    $stmt_cancelled->execute();
    $cancelled_list = $stmt_cancelled->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Lỗi tải danh sách: ' . $e->getMessage() . '</div>';
    $cancelled_list = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white"><i class="fas fa-check-circle me-2"></i> Vé Đã Hủy Gần Đây</div>
            <div class="card-body p-0">
                <?php if (empty($cancelled_list)): ?>
                    <p class="p-3 text-center text-muted">Chưa có vé nào bị hủy.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Khách</th>
                                    <th>Tuyến</th>
                                    <th>Giờ Khởi Hành</th>
                                    <th>Ghế</th>
                                    <th>Trạng Thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cancelled_list as $row): ?>
                                <tr>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['passenger_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['origin']) ?> → <?= htmlspecialchars($row['destination']) ?></td>
                                    <td><?= date('H:i d/m/Y', strtotime($row['departure_time'])) ?></td>
                                    <td><?= htmlspecialchars($row['seat_numbers'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-danger">Đã hủy</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer_staff.php'; ?>