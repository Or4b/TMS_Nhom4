<?php
// FILE: staff/view_bookings.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

// --- LOGIC TÌM KIẾM ---
$search_term = trim($_GET['search'] ?? ''); 
$search_term = ltrim($search_term, '#'); 
$where_conditions = ["1 = 1"];
$execute_params = [];

if (is_numeric($search_term) && $search_term > 0) {
    $where_conditions[] = "b.booking_id = ?"; 
    $execute_params[] = $search_term;
} else if (!empty($search_term)) {
    $search_param = '%' . $search_term . '%';
    $where_conditions[] = "(u.full_name LIKE ? OR u.phone LIKE ?)"; 
    $execute_params[] = $search_param;
    $execute_params[] = $search_param;
}

$sql_where_clause = "WHERE " . implode(" AND ", $where_conditions); 

try {
    // [CẬP NHẬT] Thêm t.return_time vào SQL
    $sql = "SELECT 
                b.booking_id AS id, 
                b.quantity AS number_of_seats, 
                b.seat_numbers, 
                b.total_price AS total_amount, 
                b.status, 
                b.payment_status, 
                b.payment_method, 
                b.cancel_request,
                b.booking_date, 
                b.ticket_type,
                t.departure_time, /* Lấy giờ khởi hành từ trip */
                t.return_time,    /* Lấy giờ về từ trip */
                u.full_name, 
                u.phone, 
                p1.province_name AS origin, 
                p2.province_name AS destination
            FROM bookings b
            JOIN users u ON b.user_id = u.id  
            JOIN trips t ON b.trip_id = t.id
            JOIN provinces p1 ON t.departure_province_id = p1.id
            JOIN provinces p2 ON t.destination_province_id = p2.id
            " . $sql_where_clause . " 
            ORDER BY b.booking_date DESC
            LIMIT 100";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($execute_params); 
    $bookings = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = 'Lỗi dữ liệu: ' . $e->getMessage();
    $bookings = [];
}

include 'includes/header_staff.php'; 
?>

<div class="container-fluid p-0">
    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-list-alt me-2"></i> Danh Sách Vé Đặt</h5>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="view_bookings.php" class="row g-2 align-items-center">
                <div class="col-auto"><label class="fw-bold m-0">Tìm kiếm:</label></div>
                <div class="col-auto flex-grow-1">
                    <input type="text" class="form-control" placeholder="Nhập Mã vé, Tên khách hoặc SĐT..." name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <a href="view_bookings.php" class="btn btn-light border"><i class="fas fa-sync-alt"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="py-3 ps-3">Mã Vé</th>
                            <th class="py-3">Khách Hàng</th>
                            <th class="py-3">Hành Trình & Thời Gian</th>
                            <th class="py-3">Chi Tiết Vé</th>
                            <th class="py-3">Thanh Toán</th>
                            <th class="py-3">Trạng Thái</th>
                            <th class="py-3 text-end pe-3">Tổng Tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr><td colspan="7" class="text-center p-5 text-muted">Không tìm thấy vé nào phù hợp.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach ($bookings as $b): 
                            // Badge Loại vé
                            $ticket_type_badge = ($b['ticket_type'] == 'round_trip') 
                                ? '<span class="badge bg-warning text-dark border border-warning me-1"><i class="fas fa-exchange-alt"></i> Khứ hồi</span>' 
                                : '<span class="badge bg-light text-secondary border me-1"><i class="fas fa-arrow-right"></i> Một chiều</span>';

                            // Badge Phương thức thanh toán
                            $method_badge = ($b['payment_method'] == 'counter') 
                                ? '<span class="badge bg-info text-dark"><i class="fas fa-store"></i> Tại quầy</span>' 
                                : '<span class="badge bg-primary"><i class="fas fa-globe"></i> Online</span>';

                            // Trạng thái thanh toán
                            $paid_badge = ($b['payment_status'] == 'paid')
                                ? '<i class="fas fa-check-circle text-success ms-1" title="Đã thanh toán"></i>'
                                : '<i class="fas fa-times-circle text-danger ms-1" title="Chưa thanh toán"></i>';

                            // Trạng thái Vé
                            $status_badge = '';
                            if ($b['status'] == 'confirmed') $status_badge = '<span class="badge bg-success">Đã xác nhận</span>';
                            elseif ($b['status'] == 'cancelled') $status_badge = '<span class="badge bg-secondary">Đã hủy</span>';
                            else $status_badge = '<span class="badge bg-warning text-dark">Chờ duyệt</span>';

                            // Cảnh báo Yêu cầu hủy
                            $cancel_alert = ($b['cancel_request'] == 1 && $b['status'] != 'cancelled') 
                                ? '<div class="mt-1"><span class="badge bg-danger animate-pulse"><i class="fas fa-bell"></i> Khách báo hủy</span></div>' : '';
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary">#<?= $b['id'] ?></td>
                            
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($b['full_name']) ?></div>
                                <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($b['phone']) ?></div>
                            </td>
                            
                            <td>
                                <div class="mb-1"><?= htmlspecialchars($b['origin']) ?> <i class="fas fa-long-arrow-alt-right text-muted mx-1"></i> <?= htmlspecialchars($b['destination']) ?></div>
                                <div class="small text-success"><i class="fas fa-plane-departure me-1"></i> Đi: <?= date('H:i d/m/Y', strtotime($b['departure_time'])) ?></div>
                                <?php if($b['ticket_type'] == 'round_trip' && !empty($b['return_time'])): ?>
                                    <div class="small text-primary"><i class="fas fa-plane-arrival me-1"></i> Về: <?= date('H:i d/m/Y', strtotime($b['return_time'])) ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div class="mb-1"><?= $ticket_type_badge ?></div>
                                <div class="small">
                                    Ghế: <b><?= htmlspecialchars($b['seat_numbers'] ?? 'N/A') ?></b> 
                                    <span class="text-muted">(SL: <?= $b['number_of_seats'] ?>)</span>
                                </div>
                            </td>
                            
                            <td>
                                <div class="mb-1"><?= $method_badge ?></div>
                                <div class="small text-muted">
                                    TT: <?= $paid_badge ?> <span class="fst-italic"><?= ($b['payment_status']=='paid') ? 'Đã xong' : 'Chưa xong' ?></span>
                                </div>
                            </td>
                            
                            <td>
                                <?= $status_badge ?>
                                <?= $cancel_alert ?>
                            </td>
                            
                            <td class="text-end pe-3">
                                <span class="fw-bold text-danger fs-6"><?= number_format($b['total_amount']) ?> đ</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3">
            <small class="text-muted">Hiển thị 100 giao dịch gần nhất.</small>
        </div>
    </div>
</div>

<style>
@keyframes pulse-red { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
.animate-pulse { animation: pulse-red 1.5s infinite; }
</style>

<?php include 'includes/footer_staff.php'; ?>