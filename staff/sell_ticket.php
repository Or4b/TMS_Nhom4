<?php
session_start();
require_once '../includes/config.php';

// --- 1. KIỂM TRA QUYỀN NHÂN VIÊN ---
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên';
$message    = '';

// --- 2. XỬ LÝ THANH TOÁN & XUẤT VÉ (Action: book_ticket) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'book_ticket') {
    // Nhận dữ liệu
    $trip_id         = $_POST['trip_id'];
    $seat_list       = $_POST['selected_seats']; 
    $seat_arr        = explode(',', $seat_list);
    $quantity        = count($seat_arr);
    $total_price     = $_POST['total_price'];
    $passenger_name  = trim($_POST['passenger_name']);
    $passenger_phone = trim($_POST['passenger_phone']);
    
    // Nhận thêm dữ liệu mới để khớp DB
    $ticket_type     = $_POST['ticket_type_hidden'] ?? 'one_way'; 
    $promotion_id    = !empty($_POST['promotion_id']) ? $_POST['promotion_id'] : NULL;

    if (empty($seat_list) || $quantity == 0) {
        $message = '<div class="alert alert-danger">Chưa chọn ghế nào!</div>';
    } elseif (empty($passenger_name) || empty($passenger_phone)) {
        $message = '<div class="alert alert-danger">Vui lòng nhập tên và SĐT khách!</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // A. XỬ LÝ KHÁCH HÀNG (Tự động tạo hoặc lấy ID cũ)
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            $stmt_check->execute([$passenger_phone]);
            $user = $stmt_check->fetch();

            if ($user) {
                $user_id = $user['id'];
                // Cập nhật tên mới nhất cho khách
                $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")->execute([$passenger_name, $user_id]);
            } else {
                // Tạo khách mới
                $dummy_email = $passenger_phone . '@guest.local';
                $dummy_pass  = password_hash('guest123', PASSWORD_DEFAULT);
                $sql_new_user = "INSERT INTO users (full_name, phone, email, password, role, created_at) VALUES (?, ?, ?, ?, 'customer', NOW())";
                $pdo->prepare($sql_new_user)->execute([$passenger_name, $passenger_phone, $dummy_email, $dummy_pass]);
                $user_id = $pdo->lastInsertId();
            }

            // B. TẠO VÉ (Khớp với tms_nhom4.sql)
            // Cột: user_id, trip_id, promotion_id, ticket_type, quantity, total_price, seat_numbers, status, payment_status
            $sql_book = "INSERT INTO bookings (
                            user_id, trip_id, promotion_id, ticket_type, 
                            booking_date, quantity, total_price, seat_numbers, 
                            status, payment_status
                        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 'confirmed', 'paid')";
            
            $stmt_book = $pdo->prepare($sql_book);
            $stmt_book->execute([
                $user_id, 
                $trip_id, 
                $promotion_id, // Lưu ID khuyến mãi (hoặc NULL)
                $ticket_type,  // Lưu loại vé (one_way/round_trip)
                $quantity, 
                $total_price, 
                $seat_list
            ]);
            
            // C. TRỪ GHẾ VÀ CẬP NHẬT LƯỢT DÙNG MÃ
            $sql_update_trip = "UPDATE trips SET available_seats = available_seats - ? WHERE id = ?";
            $pdo->prepare($sql_update_trip)->execute([$quantity, $trip_id]);

            if ($promotion_id) {
                $pdo->prepare("UPDATE promotions SET used_count = used_count + 1 WHERE id = ?")->execute([$promotion_id]);
            }

            $pdo->commit();
            
            echo "<script>
                    alert('✅ XUẤT VÉ THÀNH CÔNG!\\n\\nKhách: $passenger_name\\nGhế: $seat_list\\nTổng thu: " . number_format($total_price) . " VNĐ');
                    window.location.href='view_bookings.php';
                  </script>";
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Lỗi hệ thống: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- 3. HÀM HỖ TRỢ ---
function getProvinceName($pdo, $id) {
    $stmt = $pdo->prepare("SELECT province_name FROM provinces WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ?: "N/A";
}
// Lấy danh sách tỉnh
$provinces = [];
try {
    $stmt = $pdo->query("SELECT id, province_name FROM provinces ORDER BY province_name ASC");
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }

// --- 4. TÌM KIẾM CHUYẾN XE ---
$trips = [];
$selected_trip = null;

if (($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btn_search"])) || isset($_SESSION["staff_search_trips"])) {
    if (isset($_POST["btn_search"])) {
        $origin_id = intval($_POST["origin_id"]);
        $dest_id   = intval($_POST["destination_id"]);
        $date      = $_POST["departure_date"];
        $trip_type = $_POST["trip_type"] ?? 'one_way';

        if ($origin_id == $dest_id) {
            $message = '<div class="alert alert-warning">Điểm đi và đến không được trùng nhau!</div>';
            $_SESSION["staff_search_trips"] = [];
        } else {
            $fixed_times = ['06:30', '08:00', '10:30', '13:15', '15:45', '19:00', '22:30'];
            $origin_name = getProvinceName($pdo, $origin_id);
            $dest_name   = getProvinceName($pdo, $dest_id);
            $generated_trips = [];

            foreach ($fixed_times as $time) {
                $departure_datetime = $date . ' ' . $time . ':00';
                $random_price = rand(20, 50) * 10000; 
                $random_seats = rand(15, 42); 
                
                $return_time = null;
                if ($trip_type === 'round_trip') {
                    $stay_days = rand(2, 5);
                    $return_ts = strtotime($departure_datetime) + ($stay_days * 86400);
                    $return_time = date('Y-m-d H:i:00', $return_ts);
                }

                $generated_trips[] = [
                    "virtual_id" => uniqid(), "id" => 0,
                    "departure_time" => $departure_datetime, "trip_type" => $trip_type, "return_time" => $return_time,
                    "available_seats" => $random_seats, "total_seats" => 45,
                    "price" => $random_price,
                    "origin_id" => $origin_id, "destination_id" => $dest_id,
                    "origin_name" => $origin_name, "destination_name"=> $dest_name,
                ];
            }
            $_SESSION["staff_search_trips"] = $generated_trips;
        }
    }
    $trips = $_SESSION["staff_search_trips"] ?? [];
}

// --- 5. CHỌN CHUYẾN & XỬ LÝ KHUYẾN MÃI (SỬA LẠI SQL) ---
$promo_valid = false;
$promo_code = '';
$promo_data = null; // Chứa thông tin: id, discount_value, discount_type

if (isset($_POST["select_virtual_id"])) {
    $v_id = $_POST["select_virtual_id"];
    
    // [FIX] Kiểm tra mã khuyến mãi dựa trên cột DB thực tế
    if (isset($_POST["promo_input"])) {
        $promo_code = trim($_POST["promo_input"]); // Không uppercase vì code có thể phân biệt hoa thường
        if (!empty($promo_code)) {
            try {
                // Sửa query: dùng promotion_code, discount_value, discount_type
                $stmt_promo = $pdo->prepare("SELECT id, discount_value, discount_type, min_order_value 
                                             FROM promotions 
                                             WHERE promotion_code = ? 
                                             AND start_date <= CURDATE() 
                                             AND end_date >= CURDATE()
                                             AND status = 'active'
                                             LIMIT 1");
                $stmt_promo->execute([$promo_code]);
                $found_promo = $stmt_promo->fetch(PDO::FETCH_ASSOC);

                if ($found_promo) {
                    $promo_valid = true;
                    $promo_data = $found_promo;
                } else {
                    $message = '<div class="alert alert-warning">Mã khuyến mãi không hợp lệ hoặc đã hết hạn!</div>';
                }
            } catch (PDOException $ex) {
                $message = '<div class="alert alert-danger">Lỗi DB: '.$ex->getMessage().'</div>';
            }
        }
    }

    if (!empty($trips)) {
        foreach ($trips as $t) {
            if ($t["virtual_id"] == $v_id) {
                // Check chuyến ảo -> thật
                $stmt_check = $pdo->prepare("SELECT id, available_seats FROM trips 
                                             WHERE departure_province_id = ? AND destination_province_id = ? AND departure_time = ? LIMIT 1");
                $stmt_check->execute([$t['origin_id'], $t['destination_id'], $t['departure_time']]);
                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $t['id'] = $existing['id'];
                    $t['available_seats'] = $existing['available_seats'];
                } else {
                    $sql_insert = "INSERT INTO trips (departure_province_id, destination_province_id, departure_time, price, available_seats, total_seats, status, ticket_type, return_time, created_at) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?, NOW())";
                    $stmt_in = $pdo->prepare($sql_insert);
                    $stmt_in->execute([$t['origin_id'], $t['destination_id'], $t['departure_time'], $t['price'], $t['available_seats'], 45, $t['trip_type'], $t['return_time']]);
                    $t['id'] = $pdo->lastInsertId();
                }
                $selected_trip = $t;
                break;
            }
        }
    }
}

include 'includes/header_staff.php';
?>

<style>
    .seat-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; max-width: 300px; margin: 0 auto; }
    .seat { height: 40px; background: #e9ecef; border: 2px solid #ced4da; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; cursor: pointer; user-select: none; }
    .seat:hover { background: #dbeafe; border-color: #3b82f6; }
    .seat.selected { background: #198754; color: white; border-color: #146c43; }
    .seat.booked { background: #ced4da; color: #6c757d; cursor: not-allowed; pointer-events: none; opacity: 0.6; }
    .aisle { grid-column: span 5; text-align: center; font-size: 12px; color: #adb5bd; letter-spacing: 2px; margin: 5px 0; }
</style>

<div class="container-fluid mt-3">
    <h4 class="fw-bold mb-4 text-dark border-bottom pb-2">
        <i class="fas fa-ticket-alt me-2"></i> BÁN VÉ TẠI QUẦY
    </h4>
    
    <?= $message ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">1. Tìm kiếm Chuyến xe</div>
        <div class="card-body bg-light">
            <form method="POST">
                <input type="hidden" name="btn_search" value="1">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="fw-bold">Điểm Đi</label>
                        <select class="form-select" name="origin_id" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach ($provinces as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($_POST['origin_id']) && $_POST['origin_id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['province_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Điểm Đến</label>
                        <select class="form-select" name="destination_id" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach ($provinces as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($_POST['destination_id']) && $_POST['destination_id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['province_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Ngày đi</label>
                        <input type="date" class="form-control" name="departure_date" value="<?= $_POST['departure_date'] ?? date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Loại Vé</label>
                        <div class="bg-white p-2 rounded border">
                            <label class="me-3"><input type="radio" name="trip_type" value="one_way" checked> Một chiều</label>
                            <label><input type="radio" name="trip_type" value="round_trip" <?= (isset($_POST['trip_type']) && $_POST['trip_type'] == 'round_trip') ? 'checked' : '' ?>> Khứ hồi</label>
                        </div>
                    </div>
                    <div class="col-12 text-center"><button class="btn btn-primary px-5 fw-bold">TÌM CHUYẾN</button></div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($trips) && !$selected_trip): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">Kết quả tìm kiếm: <?= count($trips) ?> chuyến</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Giờ chạy</th><th>Hành trình</th><th>Loại vé</th><th>Chi tiết</th><th>Giá vé</th><th>Chọn</th></tr></thead>
                <tbody>
                    <?php foreach ($trips as $t): ?>
                    <tr>
                        <td class="h5 text-primary fw-bold"><?= date('H:i', strtotime($t['departure_time'])) ?></td>
                        <td><?= $t['origin_name'] ?> -> <?= $t['destination_name'] ?> <br> <small class="text-muted"><?= date('d/m/Y', strtotime($t['departure_time'])) ?></small></td>
                        <td><?= ($t['trip_type'] == 'round_trip') ? '<span class="badge bg-warning text-dark">Khứ hồi</span>' : '<span class="badge bg-info text-dark">Một chiều</span>' ?></td>
                        <td><?= $t['available_seats'] ?> ghế trống</td>
                        <td class="fw-bold text-danger fs-5"><?= number_format($t['price']) ?> đ</td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="select_virtual_id" value="<?= $t['virtual_id'] ?>">
                                <input type="hidden" name="origin_id" value="<?= $t['origin_id'] ?>">
                                <input type="hidden" name="destination_id" value="<?= $t['destination_id'] ?>">
                                <input type="hidden" name="departure_date" value="<?= $_POST['departure_date'] ?? '' ?>">
                                <input type="hidden" name="trip_type" value="<?= $_POST['trip_type'] ?? 'one_way' ?>">
                                <button class="btn btn-success btn-sm px-3">Chọn</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($selected_trip): ?>
    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm border-info h-100">
                <div class="card-header bg-info text-dark fw-bold">Thông tin Vé</div>
                <div class="card-body">
                    <p><strong>Tuyến:</strong> <?= $selected_trip['origin_name'] ?> -> <?= $selected_trip['destination_name'] ?></p>
                    <p><strong>Giờ chạy:</strong> <?= date('H:i d/m/Y', strtotime($selected_trip['departure_time'])) ?></p>
                    <p><strong>Giá gốc:</strong> <?= number_format($selected_trip['price']) ?> đ</p>
                    <hr>
                    
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="select_virtual_id" value="<?= $selected_trip['virtual_id'] ?>">
                        <input type="hidden" name="origin_id" value="<?= $selected_trip['origin_id'] ?>">
                        <input type="hidden" name="destination_id" value="<?= $selected_trip['destination_id'] ?>">
                        <input type="hidden" name="departure_date" value="<?= date('Y-m-d', strtotime($selected_trip['departure_time'])) ?>">
                        <input type="hidden" name="trip_type" value="<?= $selected_trip['trip_type'] ?>">

                        <label class="form-label small fw-bold">Mã Khuyến Mãi</label>
                        <div class="input-group">
                            <input type="text" name="promo_input" class="form-control" 
                                   placeholder="Nhập mã (VD: WELCOME2024)" value="<?= htmlspecialchars($promo_code) ?>" 
                                   <?= $promo_valid ? 'disabled' : '' ?>>
                            <?php if (!$promo_valid): ?>
                                <button class="btn btn-outline-primary">Áp dụng</button>
                            <?php else: ?>
                                <button class="btn btn-success" disabled><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </div>
                        <?php if($promo_valid): ?>
                            <small class="text-success fw-bold d-block mt-1">
                                <i class="fas fa-check-circle"></i> 
                                <?= ($promo_data['discount_type'] == 'percentage') ? "Giảm {$promo_data['discount_value']}%" : "Giảm ".number_format($promo_data['discount_value'])." VNĐ" ?>
                            </small>
                        <?php endif; ?>
                    </form>

                    <div class="alert alert-warning text-center">
                        <small>Tổng thanh toán</small>
                        <h3 class="fw-bold text-danger mb-0" id="total_display">0 VNĐ</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-success text-white fw-bold">Chọn Ghế & Thông tin Khách</div>
                <div class="card-body">
                    <form id="bookingForm" method="POST" action="sell_ticket.php">
                        <input type="hidden" name="action" value="book_ticket">
                        <input type="hidden" name="trip_id" value="<?= $selected_trip['id'] ?>">
                        <input type="hidden" name="ticket_type_hidden" value="<?= $selected_trip['trip_type'] ?>">
                        
                        <input type="hidden" name="promotion_id" value="<?= $promo_valid ? $promo_data['id'] : '' ?>">
                        
                        <input type="hidden" id="selected_seats" name="selected_seats" required>
                        <input type="hidden" id="total_price_input" name="total_price">

                        <div class="mb-4 text-center">
                            <label class="fw-bold mb-2">Sơ đồ ghế</label>
                            <div class="seat-grid">
                                <?php for($i=1; $i<=5; $i++): ?><div id="A<?=$i?>" class="seat" onclick="toggleSeat('A<?=$i?>')">A<?=$i?></div><?php endfor; ?>
                                <div class="aisle">LỐI ĐI</div>
                                <?php for($i=1; $i<=5; $i++): ?><div id="B<?=$i?>" class="seat" onclick="toggleSeat('B<?=$i?>')">B<?=$i?></div><?php endfor; ?>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-primary fw-bold">Khách hàng</h6>
                        <div class="row g-3">
                            <div class="col-md-6"><label>Họ tên *</label><input type="text" class="form-control" name="passenger_name" required></div>
                            <div class="col-md-6"><label>SĐT *</label><input type="text" class="form-control" name="passenger_phone" required></div>
                        </div>
                        <button class="btn btn-success w-100 mt-4 py-3 fw-bold">THU TIỀN & XUẤT VÉ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedSeats = [];
        const basePrice = <?= $selected_trip['price'] ?>;
        
        // Truyền dữ liệu KM xuống JS
        const hasPromo = <?= $promo_valid ? 'true' : 'false' ?>;
        const discountType = "<?= $promo_valid ? $promo_data['discount_type'] : '' ?>"; // 'percentage' hoặc 'fixed'
        const discountValue = <?= $promo_valid ? $promo_data['discount_value'] : 0 ?>;

        function toggleSeat(seatId) {
            const seatEl = document.getElementById(seatId);
            if (seatEl.classList.contains('booked')) return;

            if (selectedSeats.includes(seatId)) {
                selectedSeats = selectedSeats.filter(s => s !== seatId);
                seatEl.classList.remove('selected');
            } else {
                selectedSeats.push(seatId);
                seatEl.classList.add('selected');
            }
            updateTotal();
        }

        function updateTotal() {
            document.getElementById('selected_seats').value = selectedSeats.join(',');
            
            // Tính tổng gốc
            let total = selectedSeats.length * basePrice;

            // Áp dụng khuyến mãi
            if (hasPromo && total > 0) {
                if (discountType === 'percentage') {
                    // Giảm theo %
                    let discountAmount = total * (discountValue / 100);
                    total = total - discountAmount;
                } else if (discountType === 'fixed') {
                    // Giảm tiền mặt
                    total = total - discountValue;
                    if (total < 0) total = 0; // Không để âm tiền
                }
            }
            
            document.getElementById('total_price_input').value = total;
            document.getElementById('total_display').innerText = total.toLocaleString('vi-VN') + " VNĐ";
        }
    </script>
    <?php endif; ?>
</div>

<?php include 'includes/footer_staff.php'; ?>