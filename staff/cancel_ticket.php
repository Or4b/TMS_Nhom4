<?php
// FILE: staff/cancel_ticket.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['staff_logged_in'])) { header("Location: ../login.php"); exit(); }
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

$message = '';
$ticket_info = null; 

// --- 1. LẤY DANH SÁCH CÁC VÉ ĐANG YÊU CẦU HỦY ---
$req_sql = "SELECT b.booking_id, b.seat_numbers, b.total_price, 
                   u.full_name, u.phone, 
                   p1.province_name AS origin, p2.province_name AS destination,
                   t.departure_time
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN trips t ON b.trip_id = t.id
            JOIN provinces p1 ON t.departure_province_id = p1.id
            JOIN provinces p2 ON t.destination_province_id = p2.id
            WHERE b.cancel_request = 1 AND b.status != 'cancelled'
            ORDER BY b.booking_date ASC";
$request_list = $pdo->query($req_sql)->fetchAll(PDO::FETCH_ASSOC);


// --- 2. XỬ LÝ TÌM KIẾM CỤ THỂ ---
// Nhận ID tự động từ Dashboard hoặc từ danh sách bên dưới
$auto_id = $_GET['auto_id'] ?? '';

// Nếu có auto_id, tự động kích hoạt tìm kiếm
if($auto_id && $_SERVER["REQUEST_METHOD"] != "POST") {
    $_POST['search_ticket'] = 1;
    $_POST['booking_id'] = $auto_id;
}

// LOGIC TÌM KIẾM
if (isset($_POST['search_ticket']) || ($auto_id && $_SERVER["REQUEST_METHOD"] != "POST")) {
    $search_id = trim($_POST['booking_id'] ?? $auto_id);
    if (!empty($search_id)) {
        $sql = "SELECT b.booking_id AS id, b.seat_numbers, b.total_price AS total_amount, b.quantity AS number_of_seats, 
                       b.status, b.cancel_request, b.payment_status,
                       t.departure_time, p1.province_name AS origin, p2.province_name AS destination,
                       u.full_name AS passenger_name, u.phone
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN trips t ON b.trip_id = t.id
                JOIN provinces p1 ON t.departure_province_id = p1.id
                JOIN provinces p2 ON t.destination_province_id = p2.id
                WHERE b.booking_id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$search_id]);
        $ticket_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket_info) $message = '<div class="alert alert-warning">Không tìm thấy vé.</div>';
    }
}

// --- 3. LOGIC HỦY VÉ ---
if (isset($_POST['cancel_action'])) {
    $booking_id = $_POST['booking_id'];
    
    try {
        $pdo->beginTransaction();
        
        // [ĐÃ SỬA LỖI] Cập nhật status và reset yêu cầu hủy
        $sql_update = "UPDATE bookings SET status = 'cancelled', cancel_request = 0, payment_status = 'failed' WHERE booking_id = ?";
        $stmt_up = $pdo->prepare($sql_update); // Sửa biến $sql_up thành $sql_update
        $stmt_up->execute([$booking_id]);
        
        // Trả ghế
        $stmt_info = $pdo->prepare("SELECT quantity, trip_id FROM bookings WHERE booking_id = ?");
        $stmt_info->execute([$booking_id]);
        $tk = $stmt_info->fetch();
        if($tk) {
            $pdo->prepare("UPDATE trips SET available_seats = available_seats + ? WHERE id = ?")->execute([$tk['quantity'], $tk['trip_id']]);
        }
        
        $pdo->commit();
        $message = '<div class="alert alert-success">Đã hủy vé #' . $booking_id . ' thành công!</div>';
        $ticket_info = null; // Reset form tìm kiếm
        
        // Refresh lại danh sách yêu cầu
        $request_list = $pdo->query($req_sql)->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Lỗi: ' . $e->getMessage() . '</div>';
    }
}

include 'includes/header_staff.php';
?>

<div class="container-fluid">
    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-user-times me-2"></i> Xử Lý Hủy Vé</h5>
    
    <?= $message ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">Tìm kiếm thủ công</div>
        <div class="card-body">
            <form method="POST" action="cancel_ticket.php" class="row g-2 align-items-center">
                <div class="col-auto"><label class="fw-bold">Nhập ID vé:</label></div>
                <div class="col-auto">
                    <input type="text" class="form-control" name="booking_id" value="<?= htmlspecialchars($_POST['booking_id'] ?? $auto_id) ?>" placeholder="VD: 12">
                </div>
                <div class="col-auto">
                    <button type="submit" name="search_ticket" class="btn btn-primary"><i class="fas fa-search"></i> Tìm</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($ticket_info): ?>
    <div class="card shadow-sm border-danger mb-5">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Thông tin vé #<?= $ticket_info['id'] ?></h6>
            <?php if($ticket_info['cancel_request'] == 1): ?>
                <span class="badge bg-warning text-dark"><i class="fas fa-bell animate-pulse"></i> Khách đang yêu cầu hủy</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Khách hàng:</strong> <?= $ticket_info['passenger_name'] ?> <br> <span class="text-muted small"><?= $ticket_info['phone'] ?></span></p>
                    <p><strong>Hành trình:</strong> <?= $ticket_info['origin'] ?> <i class="fas fa-arrow-right mx-1"></i> <?= $ticket_info['destination'] ?></p>
                    <p><strong>Khởi hành:</strong> <?= date('H:i d/m/Y', strtotime($ticket_info['departure_time'])) ?></p>
                </div>
                <div class="col-md-6 border-start">
                    <p><strong>Ghế:</strong> <?= $ticket_info['seat_numbers'] ?> (SL: <?= $ticket_info['number_of_seats'] ?>)</p>
                    <p><strong>Tổng tiền:</strong> <span class="text-danger fw-bold"><?= number_format($ticket_info['total_amount']) ?> đ</span></p>
                    <p><strong>Trạng thái TT:</strong> <?= $ticket_info['payment_status'] == 'paid' ? '<span class="text-success fw-bold">Đã thanh toán</span>' : '<span class="text-danger">Chưa/Tại quầy</span>' ?></p>
                </div>
            </div>
            <div class="text-end mt-3 border-top pt-3">
                
                <form method="POST" class="d-inline" onsubmit="return confirm('XÁC NHẬN: Bạn có chắc chắn muốn hủy vé này không? Hành động này sẽ hoàn lại ghế trống.');">
                    <input type="hidden" name="cancel_action" value="1">
                    <input type="hidden" name="booking_id" value="<?= $ticket_info['id'] ?>">
                    <button class="btn btn-danger fw-bold"><i class="fas fa-trash-alt me-2"></i> XÁC NHẬN HỦY VÉ</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">
            <i class="fas fa-list-ul me-2"></i> Danh Sách Chờ Hủy (<?= count($request_list) ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Hành trình</th>
                            <th>Ngày đi</th>
                            <th>Ghế</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($request_list)): ?>
                            <tr><td colspan="6" class="text-center p-4 text-muted">Hiện không có yêu cầu hủy nào.</td></tr>
                        <?php else: ?>
                            <?php foreach ($request_list as $req): ?>
                            <tr>
                                <td><strong>#<?= $req['booking_id'] ?></strong></td>
                                <td>
                                    <?= $req['full_name'] ?><br>
                                    <small class="text-muted"><?= $req['phone'] ?></small>
                                </td>
                                <td><?= $req['origin'] ?> <i class="fas fa-arrow-right small"></i> <?= $req['destination'] ?></td>
                                <td><?= date('d/m H:i', strtotime($req['departure_time'])) ?></td>
                                <td><?= $req['seat_numbers'] ?></td>
                                <td class="text-end">
                                    <a href="cancel_ticket.php?auto_id=<?= $req['booking_id'] ?>" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-search me-1"></i> Xem & Xử lý
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
.animate-pulse { animation: pulse 1s infinite; }
</style>

<?php include 'includes/footer_staff.php'; ?>