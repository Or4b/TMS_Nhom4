<?php
// FILE: staff/confirm_tickets.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
if (!isset($_SESSION['staff_logged_in'])) { header("Location: ../login.php"); exit(); }
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

// Lấy thêm payment_method
$sql_list = "SELECT b.booking_id AS id, b.total_price AS total_amount, b.quantity AS number_of_seats, b.seat_numbers, 
                b.booking_date, b.payment_method, b.payment_status,
                u.full_name, u.phone, p1.province_name AS origin, p2.province_name AS destination, t.departure_time
             FROM bookings b
             JOIN users u ON b.user_id = u.id JOIN trips t ON b.trip_id = t.id
             JOIN provinces p1 ON t.departure_province_id = p1.id JOIN provinces p2 ON t.destination_province_id = p2.id
             WHERE b.status = 'pending' ORDER BY b.booking_date ASC";
$pending_tickets = $pdo->query($sql_list)->fetchAll();

include 'includes/header_staff.php'; 
?>
<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-check-circle me-2"></i> Duyệt Vé Online</h5>
<div class="card shadow-sm"><div class="card-body"><div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th><th>Khách hàng</th><th>Loại TT</th><th>Tuyến</th><th>Tổng tiền</th><th class="text-end">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pending_tickets as $ticket): 
                $is_counter = ($ticket['payment_method'] == 'counter');
            ?>
            <tr>
                <td><strong>#<?= $ticket['id'] ?></strong></td>
                <td>
                    <?= $ticket['full_name'] ?><br>
                    <small class="text-muted"><?= $ticket['phone'] ?></small>
                </td>
                <td>
                    <?php if($is_counter): ?>
                        <span class="badge bg-info text-dark"><i class="fas fa-store"></i> Tại quầy</span>
                        <div class="small text-danger">Chưa TT</div>
                    <?php else: ?>
                        <span class="badge bg-success"><i class="fas fa-globe"></i> Online</span>
                        <div class="small text-success">Đã TT</div>
                    <?php endif; ?>
                </td>
                <td><?= $ticket['origin'] ?> -> <?= $ticket['destination'] ?></td>
                <td class="fw-bold text-danger"><?= number_format($ticket['total_amount']) ?> đ</td>
                <td class="text-end">
                    <?php if($is_counter): ?>
                        <a href="tel:<?= $ticket['phone'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-phone"></i> Gọi</a>
                    <?php endif; ?>
                    <button class="btn btn-success btn-sm" onclick="processTicket(<?= $ticket['id'] ?>, 'confirm')">Duyệt</button>
                    <button class="btn btn-outline-danger btn-sm" onclick="processTicket(<?= $ticket['id'] ?>, 'cancel')">Hủy</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<script>
function processTicket(id, action) {
    if(!confirm(action==='confirm'?'Duyệt vé #'+id+'?':'Hủy vé #'+id+'?')) return;
    fetch("process_ticket.php", {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'booking_id': id, 'action': action })
    }).then(r=>r.json()).then(d=>{ alert(d.message); if(d.success) location.reload(); });
}
</script>
<?php include 'includes/footer_staff.php'; ?>