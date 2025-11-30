<?php
// FILE: staff/confirm_tickets.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); exit();
}

// --- [SỬA LỖI 1] THÊM DÒNG NÀY ĐỂ ĐỊNH NGHĨA BIẾN $staff_name ---
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

// LOGIC LẤY DANH SÁCH VÉ
$sql_list = "SELECT 
                b.booking_id AS id, 
                b.total_price AS total_amount, 
                b.quantity AS number_of_seats, 
                b.seat_numbers, 
                b.booking_date, 
                u.full_name, 
                u.phone, 
                p1.province_name AS origin, 
                p2.province_name AS destination, 
                t.departure_time
             FROM bookings b
             JOIN users u ON b.user_id = u.id 
             JOIN trips t ON b.trip_id = t.id
             JOIN provinces p1 ON t.departure_province_id = p1.id
             JOIN provinces p2 ON t.destination_province_id = p2.id
             WHERE b.status = 'pending' 
             ORDER BY b.booking_date ASC";
             
$pending_tickets = $pdo->query($sql_list)->fetchAll();

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-check-circle me-2"></i> Duyệt Vé Online</h5>

<div class="card shadow-sm border-info">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Tuyến / Giờ đi</th>
                        <th>Vị trí ghế</th> <th>Tổng tiền</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_tickets)): ?>
                        <tr><td colspan="6" class="text-center p-4">Không có vé nào cần xử lý.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($pending_tickets as $ticket): ?>
                    <tr>
                        <td><strong>#<?php echo $ticket['id']; ?></strong></td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($ticket['full_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($ticket['phone']); ?></small>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($ticket['origin']); ?> <i class="fas fa-arrow-right small"></i> <?php echo htmlspecialchars($ticket['destination']); ?></div>
                            <small class="text-success"><i class="far fa-clock"></i> <?php echo date('H:i d/m', strtotime($ticket['departure_time'])); ?></small>
                        </td>
                        
                        <td>
                            <span class="badge bg-info text-dark" style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars($ticket['seat_numbers'] ?? 'N/A'); ?>
                            </span>
                            <div class="small text-muted mt-1">(SL: <?php echo $ticket['number_of_seats']; ?>)</div>
                        </td>

                        <td class="fw-bold text-danger"><?php echo number_format($ticket['total_amount']); ?> đ</td>
                        
                        <td class="text-end">
                            <button class="btn btn-success btn-sm me-1" onclick="processTicket(<?php echo $ticket['id']; ?>, 'confirm')">
                                <i class="fas fa-check"></i> Duyệt
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="processTicket(<?php echo $ticket['id']; ?>, 'cancel')">
                                <i class="fas fa-times"></i> Hủy
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function processTicket(id, action) {
    if (!confirm(action === 'confirm' ? 'Duyệt vé #' + id + '?' : 'Hủy vé #' + id + '?')) return;

    fetch("process_ticket.php", {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'booking_id': id, 'action': action })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Có lỗi xảy ra khi kết nối server.');
    });
}
</script>

<?php include 'includes/footer_staff.php'; ?>