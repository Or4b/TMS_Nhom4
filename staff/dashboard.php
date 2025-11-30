<?php
// FILE: staff/dashboard.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';

// --- THỐNG KÊ SỐ LIỆU ---
$today_start = date('Y-m-d 00:00:00');
$today_end   = date('Y-m-d 23:59:59');

// 1. Vé chờ xác nhận (Lấy TOÀN BỘ, không lọc ngày vì đây là việc tồn đọng cần làm)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$stmt->execute();
$pending_count = $stmt->fetchColumn();

// 2. Vé đã xác nhận (Chỉ tính trong hôm nay để xem KPI ngày)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND booking_date BETWEEN ? AND ?");
$stmt->execute([$today_start, $today_end]);
$confirmed_count = $stmt->fetchColumn();

// 3. Vé đã hủy (Chỉ tính trong hôm nay)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled' AND booking_date BETWEEN ? AND ?");
$stmt->execute([$today_start, $today_end]);
$cancelled_count = $stmt->fetchColumn();

// 4. Vé yêu cầu hủy (Giả sử logic là status = 'pending_cancel' hoặc dựa vào ghi chú, ở đây tôi ví dụ đếm các vé đã hủy để hiển thị)
// Nếu bạn chưa có trạng thái 'pending_cancel', ta sẽ hiển thị số vé hủy làm ví dụ
$cancel_request_count = 0; // Tạm để 0 hoặc bạn query trạng thái 'pending_cancel' nếu có

// --- LẤY DANH SÁCH VÉ PENDING ---
$sql_list = "SELECT 
                b.booking_id AS id, 
                b.total_price AS total_amount, 
                b.quantity AS number_of_seats, 
                b.seat_numbers,  /* THÊM CỘT NÀY */
                b.booking_date, 
                u.full_name, 
                u.phone, 
                p1.province_name AS origin, 
                p2.province_name AS destination
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             JOIN trips t ON b.trip_id = t.id
             JOIN provinces p1 ON t.departure_province_id = p1.id
             JOIN provinces p2 ON t.destination_province_id = p2.id
             WHERE b.status = 'pending'
             ORDER BY b.booking_date ASC LIMIT 10";
$pending_tickets = $pdo->query($sql_list)->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header_staff.php';
?>

<div class="row mb-5">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card bg-white shadow-sm border border-warning">
            <i class="fas fa-hourglass-half stat-icon text-warning"></i>
            <h4 class="fw-bold mt-2"><?php echo $pending_count; ?></h4>
            <p class="text-secondary mb-0">Vé chờ xác nhận (Toàn bộ)</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card bg-white shadow-sm border border-success">
            <i class="fas fa-check-circle stat-icon text-success"></i>
            <h4 class="fw-bold mt-2"><?php echo $confirmed_count; ?></h4>
            <p class="text-secondary mb-0">Vé đã xác nhận (Hôm nay)</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card bg-white shadow-sm border border-danger">
            <i class="fas fa-exclamation-circle stat-icon text-danger"></i>
            <h4 class="fw-bold mt-2"><?php echo $cancel_request_count; ?></h4>
            <p class="text-secondary mb-0">Yêu cầu hủy</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card bg-secondary text-white shadow-sm"> <i class="fas fa-times-circle stat-icon text-white"></i>
            <h4 class="fw-bold mt-2"><?php echo $cancelled_count; ?></h4>
            <p class="mb-0">Đã hủy (Hôm nay)</p>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3 text-dark">Danh sách cần xử lý ngay</h5>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if (count($pending_tickets) > 0): ?>
            <?php foreach ($pending_tickets as $ticket): ?>
                <div class="row align-items-center mb-3 pb-3 border-bottom">
                    <div class="col-md-8">
                        <p class="fw-bold mb-1 text-primary">#<?php echo $ticket['id'] . ' - ' . htmlspecialchars($ticket['full_name']); ?></p>
                        <p class="mb-0 text-muted small">
                            <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($ticket['phone']); ?> 
                            <span class="mx-2">|</span> 
                            <i class="fas fa-route"></i> <?php echo htmlspecialchars($ticket['origin'] . ' -> ' . $ticket['destination']); ?> 
                            <span class="mx-2">|</span> 
                            <i class="fas fa-chair"></i> <b><?php echo htmlspecialchars($ticket['seat_numbers'] ?? 'N/A'); ?></b>
                            <span class="mx-2">|</span> 
                            <?php echo number_format($ticket['total_amount']); ?> VNĐ
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-success btn-sm me-2" onclick="processTicket(<?php echo $ticket['id']; ?>, 'confirm')">Xác nhận</button>
                        <button class="btn btn-danger btn-sm" onclick="processTicket(<?php echo $ticket['id']; ?>, 'cancel')">Từ chối</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-success text-center">Tuyệt vời! Không có vé nào tồn đọng.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function processTicket(id, action) {
    let msg = (action === 'confirm') ? 'Xác nhận vé #' + id + '?' : 'Hủy vé #' + id + '?';
    if (!confirm(msg)) return;

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
    .catch(err => alert('Lỗi kết nối'));
}
</script>

<?php include 'includes/footer_staff.php'; ?>