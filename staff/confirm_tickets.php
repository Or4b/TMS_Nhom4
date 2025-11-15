<?php
session_start();
require_once '../db_connect.php'; 

// --- BẢO VỆ TRUY CẬP ---
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}
$staff_name = $_SESSION['staff_name'];
$message = '';

// LOGIC LẤY DANH SÁCH VÉ CHỜ XÁC NHẬN
try {
    $sql_list = "SELECT b.id, b.total_amount, b.number_of_seats, b.booking_date, u.full_name, u.phone, 
                   p1.name AS origin, p2.name AS destination, t.departure_time
                 FROM bookings b
                 JOIN users u ON b.customer_id = u.id
                 JOIN trips t ON b.trip_id = t.id
                 JOIN provinces p1 ON t.departure_province_id = p1.id
                 JOIN provinces p2 ON t.destination_province_id = p2.id
                 WHERE b.status = 'pending' 
                 ORDER BY b.booking_date ASC";
    $stmt_list = $pdo->query($sql_list);
    $pending_tickets = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Lỗi tải danh sách vé chờ: ' . $e->getMessage() . '</div>';
    $pending_tickets = [];
}

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-check-circle me-2"></i> Xác nhận Vé đặt Online</h5>

<?php echo $message; ?>

<div class="card shadow-sm border-info">
    <div class="card-header bg-info text-white">
        Danh sách Vé cần xác minh và xử lý thanh toán tại quầy
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID Vé</th>
                        <th>Khách hàng / SĐT</th>
                        <th>Tuyến</th>
                        <th>Khởi hành</th>
                        <th>SL Ghế</th>
                        <th>Giá trị</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_tickets)): ?>
                        <tr><td colspan="7" class="text-center text-muted">Không có vé nào cần xử lý.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($pending_tickets as $ticket): ?>
                    <tr>
                        <td>#<?php echo htmlspecialchars($ticket['id']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($ticket['full_name']); ?></strong><br>
                            <?php echo htmlspecialchars($ticket['phone']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($ticket['origin']); ?> -> <?php echo htmlspecialchars($ticket['destination']); ?></td>
                        <td><?php echo date('H:i d/m', strtotime($ticket['departure_time'])); ?></td>
                        <td><?php echo htmlspecialchars($ticket['number_of_seats']); ?></td>
                        <td><?php echo number_format($ticket['total_amount']); ?> VNĐ</td>
                        <td>
                            <button class="btn btn-success btn-sm me-1 btn-confirm" data-id="<?php echo $ticket['id']; ?>" data-action="confirm">Xác nhận</button>
                            <button class="btn btn-danger btn-sm btn-cancel" data-id="<?php echo $ticket['id']; ?>" data-action="cancel">Từ chối</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-confirm, .btn-cancel').forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            const action = this.getAttribute('data-action');
            const message = (action === 'confirm') ? 
                'Xác nhận vé #' + bookingId + '?' : 
                'Hủy vé #' + bookingId + '?';

            if (confirm(message)) {
                fetch('process_ticket.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 'booking_id': bookingId, 'action': action })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) { window.location.reload(); } // Tải lại trang sau khi xử lý
                })
                .catch(error => alert('Lỗi mạng khi xử lý vé.'));
            }
        });
    });
});
</script>

<?php 
include 'includes/footer_staff.php'; 
?>