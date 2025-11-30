<?php
// FILE: staff/view_bookings.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); exit();
}

// --- [SỬA LỖI] THÊM DÒNG NÀY ĐỂ ĐỊNH NGHĨA BIẾN $staff_name ---
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

$search_term = trim($_GET['search'] ?? ''); 
$search_term = ltrim($search_term, '#'); 
$where_conditions = ["1 = 1"];
$execute_params = [];

// Logic tìm kiếm
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
    $sql = "SELECT 
                b.booking_id AS id, 
                b.quantity AS number_of_seats, 
                b.seat_numbers, 
                b.total_price AS total_amount, 
                b.status, 
                b.payment_status, 
                b.booking_date, 
                u.full_name, 
                u.phone, 
                p1.province_name AS origin, 
                p2.province_name AS destination,
                t.ticket_type  
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
    $message = '<div class="alert alert-danger">Lỗi tải dữ liệu: ' . $e->getMessage() . '</div>';
    $bookings = [];
}

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-list-alt me-2"></i> Lịch sử & Tra cứu Vé</h5>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-success text-white">Tìm kiếm Vé</div>
    <div class="card-body">
        <form method="GET" action="view_bookings.php">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Nhập ID Vé, Tên khách hoặc SĐT" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit" class="btn btn-primary">Tìm Kiếm</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Hành trình</th>
                        <th>Loại vé</th> <th>Ghế</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thanh toán</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="8" class="text-center p-4">Không tìm thấy dữ liệu.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($bookings as $booking): 
                        $status_class = match ($booking['status']) {
                            'confirmed' => 'bg-success',
                            'pending' => 'bg-warning text-dark',
                            'cancelled' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        
                        $type_display = ($booking['ticket_type'] == 'round_trip') 
                            ? '<span class="badge bg-info text-dark">Khứ hồi</span>' 
                            : '<span class="badge bg-light text-dark border">Một chiều</span>';
                    ?>
                    <tr>
                        <td>#<?php echo $booking['id']; ?></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($booking['full_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($booking['phone']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($booking['origin']); ?> <i class="fas fa-arrow-right small"></i> <?php echo htmlspecialchars($booking['destination']); ?></td>
                        
                        <td><?php echo $type_display; ?></td>
                        
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($booking['seat_numbers'] ?? '-'); ?></div>
                            <small class="text-muted">(SL: <?php echo $booking['number_of_seats']; ?>)</small>
                        </td>
                        
                        <td class="fw-bold text-danger"><?php echo number_format($booking['total_amount']); ?> đ</td>
                        
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                        <td>
                            <?php if($booking['payment_status'] == 'paid'): ?>
                                <span class="badge bg-primary">Đã TT</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Chưa TT</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer_staff.php'; ?>