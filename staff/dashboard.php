<?php
session_start();
// --- SỬA LỖI FATAL: Dùng dirname(__DIR__) để tìm đường dẫn tuyệt đối vật lý ---
// Hàm này sẽ đi ra thư mục cha (THLVN1/) và tìm db_connect.php, khắc phục lỗi đường dẫn tương đối
require_once '../db_connect.php';


// --- END SỬA LỖI FATAL ---

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    // Nếu chưa đăng nhập, chuyển hướng về trang login
    header("Location: ../login.php"); 
    exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';
$user_id = $_SESSION['user_id'] ?? 0; 

// --- LOGIC LẤY DỮ LIỆU THỐNG KÊ (SCR-2.1) ---
// --- LOGIC LẤY DỮ LIỆU THỐNG KÊ (SCR-2.1) ---
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

// Hàm lấy COUNT gọn và chuẩn PDO
function getCount($pdo, $status, $start, $end) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = ? AND booking_date BETWEEN ? AND ?");
    $stmt->execute([$status, $start, $end]);
    return $stmt->fetchColumn();
}

// Lấy dữ liệu
$pending_count   = getCount($pdo, 'pending',   $today_start, $today_end);
$confirmed_count = getCount($pdo, 'confirmed', $today_start, $today_end);
$cancelled_count = getCount($pdo, 'cancelled', $today_start, $today_end);

// Nếu vé bán tại quầy = vé đã xác nhận (tạm thời)
$counter_count = $confirmed_count;

// Lấy danh sách vé pending chi tiết
$sql_list = "SELECT b.id, b.total_amount, b.number_of_seats, b.booking_date, 
                    u.full_name, u.phone, 
                    p1.name AS origin, p2.name AS destination
             FROM bookings b
             JOIN users u ON b.customer_id = u.id
             JOIN trips t ON b.trip_id = t.id
             JOIN provinces p1 ON t.departure_province_id = p1.id
             JOIN provinces p2 ON t.destination_province_id = p2.id
             WHERE b.status = 'pending'
             ORDER BY b.booking_date ASC 
             LIMIT 10";

$stmt = $pdo->query($sql_list);
$pending_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);


include 'includes/header_staff.php';
?>

<div class="row mb-5">
    <div class="col-lg-3 col-md-6 mb-4"><div class="stat-card bg-white shadow-sm border border-warning"><i class="fas fa-hourglass-half stat-icon text-warning"></i><h4 class="fw-bold mt-2"><?php echo $pending_count; ?></h4><p class="text-secondary mb-0">Vé chờ xác nhận</p></div></div>
    <div class="col-lg-3 col-md-6 mb-4"><div class="stat-card bg-white shadow-sm border border-success"><i class="fas fa-check-circle stat-icon text-success"></i><h4 class="fw-bold mt-2"><?php echo $confirmed_count; ?></h4><p class="text-secondary mb-0">Vé đã xác nhận</p></div></div>
    <div class="col-lg-3 col-md-6 mb-4"><div class="stat-card bg-white shadow-sm border border-info"><i class="fas fa-cash-register stat-icon text-info"></i><h4 class="fw-bold mt-2"><?php echo $counter_count; ?></h4><p class="text-secondary mb-0">Vé bán tại quầy</p></div></div>
    <div class="col-lg-3 col-md-6 mb-4"><div class="stat-card bg-white shadow-sm border border-danger"><i class="fas fa-times-circle stat-icon text-danger"></i><h4 class="fw-bold mt-2"><?php echo $cancelled_count; ?></h4><p class="text-secondary mb-0">Vé đã hủy</p></div></div>
</div>

<h5 class="fw-bold mb-3 text-dark">Vé Chờ Xác Nhận</h5>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <?php if (count($pending_tickets) > 0): ?>
            <?php foreach ($pending_tickets as $ticket): ?>
                <div class="row align-items-center mb-3 pb-3 border-bottom">
                    <div class="col-md-8">
                        <p class="fw-bold mb-1 text-primary">#<?php echo $ticket['id'] . ' - ' . htmlspecialchars($ticket['full_name']); ?></p>
                        <p class="mb-0 text-muted small">
                            <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($ticket['phone']); ?> 
                            <span class="mx-2 text-primary">|</span> <i class="fas fa-route me-1"></i> <?php echo htmlspecialchars($ticket['origin'] . ' -> ' . $ticket['destination']); ?> 
                            <span class="mx-2 text-primary">|</span> <i class="fas fa-chair me-1"></i> Ghế: <?php echo $ticket['number_of_seats']; ?> 
                            <span class="mx-2 text-primary">|</span> <?php echo number_format($ticket['total_amount']); ?> VNĐ
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-success btn-sm me-2" data-id="<?php echo $ticket['id']; ?>" data-action="confirm">Xác nhận</button>
                        <button class="btn btn-danger btn-sm" data-id="<?php echo $ticket['id']; ?>" data-action="cancel">Từ chối</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info text-center">Không có vé nào chờ xác nhận hôm nay.</div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logic AJAX xử lý nút Xác nhận/Từ chối
    document.querySelectorAll('[data-action="confirm"], [data-action="cancel"]').forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            const action = this.getAttribute('data-action');
            const message = (action === 'confirm') ? 'Xác nhận vé #' + bookingId + '?' : 'Hủy vé #' + bookingId + '?';

            if (confirm(message)) {
                fetch('process_ticket.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 'booking_id': bookingId, 'action': action })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) { window.location.reload(); }
                })
                .catch(error => alert('Lỗi mạng.'));
            }
        });
    });
});
</script>

<?php 
include 'includes/footer_staff.php';

?>