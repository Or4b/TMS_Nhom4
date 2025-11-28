<?php
session_start();
require_once '../includes/config.php';

// --- BẢO VỆ TRUY CẬP ---
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}
// FIX: Thêm kiểm tra tồn tại biến session để tránh lỗi Warning
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';
$bookings = [];
$search_term = $_GET['search'] ?? '';

// LOGIC TÌM KIẾM VÀ HIỂN THỊ TẤT CẢ VÉ
try {
    // FIX: Sửa p1.name -> p1.province_name và p2.name -> p2.province_name
    $sql = "SELECT b.id, b.number_of_seats, b.total_amount, b.status, b.payment_status, 
                   b.booking_date, u.full_name, u.phone, 
                   p1.province_name AS origin, p2.province_name AS destination
            FROM bookings b
            JOIN users u ON b.customer_id = u.id
            JOIN trips t ON b.trip_id = t.id
            JOIN provinces p1 ON t.departure_province_id = p1.id
            JOIN provinces p2 ON t.destination_province_id = p2.id
            WHERE u.full_name LIKE ? OR b.id LIKE ? OR u.phone LIKE ?
            ORDER BY b.booking_date DESC
            LIMIT 100";
            
    $stmt = $pdo->prepare($sql);
    // Chuẩn bị tham số tìm kiếm
    $search_param = '%' . $search_term . '%';
    $stmt->execute([$search_param, $search_param, $search_param]);
    $bookings = $stmt->fetchAll();

} catch (PDOException $e) {
    // Xử lý lỗi
    $message = '<div class="alert alert-danger">Lỗi tải dữ liệu: ' . $e->getMessage() . '</div>';
    $bookings = [];
}

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-list-alt me-2"></i> Lịch sử & Tra cứu Vé (View Bookings)</h5>

<?php echo $message ?? ''; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-success text-white">
        Tìm kiếm Vé
    </div>
    <div class="card-body">
        <form method="GET" action="view_bookings.php">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Nhập ID Vé, Tên khách hàng hoặc SĐT" name="search" 
                       value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i> Tìm Kiếm</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        Kết quả (<?php echo count($bookings); ?> vé)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID Vé</th>
                        <th>Khách hàng</th>
                        <th>SĐT</th>
                        <th>Tuyến</th>
                        <th>Ghế</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>TT Thanh toán</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="8" class="text-center text-muted">Không tìm thấy lịch sử đặt vé nào.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($bookings as $booking): 
                        // Xác định class hiển thị trạng thái
                        $status_class = match ($booking['status']) {
                            'confirmed' => 'badge bg-success',
                            'pending' => 'badge bg-warning text-dark',
                            'cancelled' => 'badge bg-danger',
                            default => 'badge bg-secondary',
                        };
                        $payment_class = ($booking['payment_status'] == 'paid') ? 'badge bg-primary' : 'badge bg-secondary';
                    ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($booking['id']); ?></td>
                        <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                        <td><?php echo htmlspecialchars($booking['origin']); ?> -> <?php echo htmlspecialchars($booking['destination']); ?></td>
                        <td><?php echo htmlspecialchars($booking['number_of_seats']); ?></td>
                        <td><?php echo number_format($booking['total_amount']); ?> VNĐ</td>
                        <td><span class="<?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                        <td><span class="<?php echo $payment_class; ?>"><?php echo ucfirst($booking['payment_status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
include 'includes/footer_staff.php'; 
?>