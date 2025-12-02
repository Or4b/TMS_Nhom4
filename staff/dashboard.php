<?php
// FILE: staff/dashboard.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';
$today_start = date('Y-m-d 00:00:00');
$today_end   = date('Y-m-d 23:59:59');

// 1. Số liệu
$pending_count = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$confirmed_count = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND booking_date BETWEEN '$today_start' AND '$today_end'")->fetchColumn();
$cancelled_count = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled' AND booking_date BETWEEN '$today_start' AND '$today_end'")->fetchColumn();

// 2. Đếm yêu cầu hủy (cancel_request = 1 và chưa hủy)
$cancel_req_count = $pdo->query("SELECT COUNT(*) FROM bookings WHERE cancel_request = 1 AND status != 'cancelled'")->fetchColumn();

// 3. LẤY DANH SÁCH YÊU CẦU HỦY (Mới)
$sql_req = "SELECT b.booking_id, u.full_name, u.phone, b.seat_numbers 
            FROM bookings b JOIN users u ON b.user_id = u.id 
            WHERE b.cancel_request = 1 AND b.status != 'cancelled'";
$cancel_requests = $pdo->query($sql_req)->fetchAll(PDO::FETCH_ASSOC);
$cancel_req_count = count($cancel_requests);

// [MỚI] Lấy danh sách VÉ CHỜ (Thêm payment_method)
$sql_list = "SELECT b.booking_id AS id, b.total_price AS total_amount, b.seat_numbers, b.payment_method,
                    u.full_name, u.phone, p1.province_name AS origin, p2.province_name AS destination
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

<div class="row mb-4">
    <div class="col-lg-3"><div class="stat-card bg-white shadow-sm border-warning border"><h3 class="text-warning"><?= $pending_count ?></h3><small>Chờ xác nhận</small></div></div>
    <div class="col-lg-3"><div class="stat-card bg-white shadow-sm border-success border"><h3 class="text-success"><?= $confirmed_count ?></h3><small>Đã xác nhận (Hôm nay)</small></div></div>
    <div class="col-lg-3">
        <div class="stat-card bg-white shadow-sm border-danger border position-relative">
            <h3 class="text-danger"><?= $cancel_req_count ?></h3>
            <small>Yêu cầu hủy vé</small>
            <?php if($cancel_req_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">NEW</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-3"><div class="stat-card bg-secondary text-white shadow-sm"><h3 class="text-white"><?= $cancelled_count ?></h3><small>Đã hủy (Hôm nay)</small></div></div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-danger mb-4">
            <div class="card-header bg-danger text-white fw-bold"><i class="fas fa-bell me-2"></i> Khách Yêu Cầu Hủy</div>
            <div class="card-body p-0">
                <?php if(empty($cancel_requests)): ?>
                    <p class="p-3 text-muted text-center">Không có yêu cầu nào.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                    <?php foreach($cancel_requests as $req): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>#<?= $req['booking_id'] ?></strong> - <?= $req['full_name'] ?><br>
                                    <small class="text-muted"><i class="fas fa-phone"></i> <?= $req['phone'] ?></small>
                                </div>
                                <a href="cancel_ticket.php?auto_id=<?= $req['booking_id'] ?>" class="btn btn-sm btn-outline-danger">
                                    Xử lý
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark fw-bold"><i class="fas fa-clock me-2"></i> Vé Chờ Xác Nhận</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Vé</th>
                                <th>Khách & TT</th>
                                <th>Hành trình</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_tickets as $t): 
                            $is_counter = ($t['payment_method'] == 'counter');
                            $badge_pay = $is_counter 
                                ? '<span class="badge bg-info text-dark">Tại quầy</span>' 
                                : '<span class="badge bg-success">Online (Đã CK)</span>';
                        ?>
                            <tr>
                                <td><strong>#<?= $t['id'] ?></strong></td>
                                <td>
                                    <?= $t['full_name'] ?><br>
                                    <?= $badge_pay ?>
                                </td>
                                <td>
                                    <?= $t['origin'] ?> -> <?= $t['destination'] ?><br>
                                    <small>Ghế: <b><?= $t['seat_numbers'] ?></b></small>
                                </td>
                                <td class="text-end">
                                    <?php if($is_counter): ?>
                                        <a href="tel:<?= $t['phone'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Gọi khách">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-success" onclick="processTicket(<?= $t['id'] ?>, 'confirm')"><i class="fas fa-check"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="processTicket(<?= $t['id'] ?>, 'cancel')"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function processTicket(id, action) {
    let msg = (action === 'confirm') ? 'Xác nhận vé #' + id + '?' : 'Hủy và Từ chối vé #' + id + '?';
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
    });
}
</script>
<?php include 'includes/footer_staff.php'; ?>