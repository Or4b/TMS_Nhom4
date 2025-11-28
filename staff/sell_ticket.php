<?php
session_start();
require_once '../includes/config.php';

// --- BẢO VỆ TRUY CẬP ---
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}
$user_id = $_SESSION['user_id'] ?? null;        // Nếu không tồn tại, mặc định null
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên'; // Nếu không tồn tại, mặc định 'Nhân viên'

$message = '';
$trips = []; // Danh sách chuyến đi khả dụng
$selected_trip_id = null; 

// Lấy danh sách Tỉnh/Thành cho Dropdown
try {
    $sql_provinces = "SELECT id, name FROM provinces WHERE status = 'active' ORDER BY name ASC";
    $provinces = $pdo->query($sql_provinces)->fetchAll();
} catch (PDOException $e) {
    $provinces = []; 
}

// LOGIC TÌM KIẾM CHUYẾN ĐI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_trip'])) {
    $origin_id = $_POST['origin_id'] ?? null;
    $destination_id = $_POST['destination_id'] ?? null;
    $departure_date = $_POST['departure_date'] ?? null;

    if ($origin_id && $destination_id && $departure_date) {
        try {
            // Bước 1: Tìm các chuyến đi (trips) cho Ngày khởi hành và Tỉnh/Thành
            $search_date_start = $departure_date . ' 00:00:00';
            $search_date_end = $departure_date . ' 23:59:59';

            $sql_trips = "SELECT 
                                id, departure_time, total_seats, available_seats, price, ticket_type, return_time
                              FROM trips 
                              WHERE departure_province_id = ? AND destination_province_id = ?
                              AND status = 'scheduled' 
                              AND available_seats > 0
                              AND departure_time BETWEEN ? AND ? 
                              ORDER BY departure_time ASC";
            
            $stmt_trips = $pdo->prepare($sql_trips);
            $stmt_trips->execute([$origin_id, $destination_id, $search_date_start, $search_date_end]);
            $trips = $stmt_trips->fetchAll();
            
            if (empty($trips)) {
                $message = '<div class="alert alert-info">Không tìm thấy chuyến xe nào khả dụng.</div>';
            }

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Lỗi tìm kiếm chuyến đi: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Vui lòng chọn đầy đủ Điểm đi, Điểm đến và Ngày đi.</div>';
    }
}

// LOGIC CHỌN CHUYẾN ĐI (Tải lại trang sau khi chọn)
if (isset($_POST['select_trip_id'])) {
    $selected_trip_id = $_POST['select_trip_id'];
}

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-ticket-alt me-2"></i> Bán vé tại Quầy</h5>

<?php echo $message; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-search me-2"></i> 1. Chọn Chuyến Đi
    </div>
    <div class="card-body">
        <form method="POST" action="sell_ticket.php">
            <input type="hidden" name="search_trip" value="1">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="origin_id" class="form-label">Điểm Đi</label>
                    <select class="form-select" id="origin_id" name="origin_id" required>
                        <option value="">Chọn Tỉnh/Thành</option>
                        <?php foreach ($provinces as $p): ?>
                            <option value="<?php echo $p['id']; ?>" 
                                <?php echo (isset($_POST['origin_id']) && $_POST['origin_id'] == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo $p['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="destination_id" class="form-label">Điểm Đến</label>
                    <select class="form-select" id="destination_id" name="destination_id" required>
                        <option value="">Chọn Tỉnh/Thành</option>
                         <?php foreach ($provinces as $p): ?>
                            <option value="<?php echo $p['id']; ?>" 
                                <?php echo (isset($_POST['destination_id']) && $_POST['destination_id'] == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo $p['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="departure_date" class="form-label">Ngày Đi</label>
                    <input type="date" class="form-control" id="departure_date" name="departure_date" 
                           value="<?php echo $_POST['departure_date'] ?? date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Tìm</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($trips)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <i class="fas fa-list-alt me-2"></i> Danh Sách Chuyến Xe Khả Dụng
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Khởi hành</th>
                        <th>Loại vé</th>
                        <th>Ghế trống</th>
                        <th>Giá vé</th>
                        <th>Chọn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trips as $trip): ?>
                    <tr>
                        <td><?php echo date('H:i d/m', strtotime($trip['departure_time'])); ?></td>
                        <td><?php echo htmlspecialchars($trip['ticket_type']); ?></td>
                        <td class="<?php echo ($trip['available_seats'] > 5) ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $trip['available_seats'] . '/' . $trip['total_seats']; ?>
                        </td>
                        <td><?php echo number_format($trip['price'], 0, ',', '.'); ?> VNĐ</td>
                        <td>
                            <form method="POST" action="sell_ticket.php">
                                <input type="hidden" name="select_trip_id" value="<?php echo $trip['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-primary" 
                                        <?php echo ($trip['available_seats'] == 0) ? 'disabled' : ''; ?>>
                                    Chọn chuyến
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif (isset($_POST['search_trip'])): ?>
    <div class="alert alert-info text-center shadow-sm">Không tìm thấy chuyến xe nào phù hợp với yêu cầu.</div>
<?php endif; ?>

<?php if ($selected_trip_id): 
    // Lấy giá vé của chuyến đã chọn để dùng trong JS
    $selected_trip = $pdo->prepare("SELECT price FROM trips WHERE id = ?")->execute([$selected_trip_id])->fetch();
    $current_trip_price = $selected_trip['price'] ?? 0;
?>
<div class="card shadow-sm mb-4 border-success">
    <div class="card-header bg-success text-white">
        <i class="fas fa-chair me-2"></i> 2. Chọn Ghế & Thanh Toán
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 border-end">
                <h6 class="mb-3 fw-bold">Sơ Đồ Ghế Xe</h6>
                <div id="seat_map_container" class="p-3 border rounded">
                    <p class="text-muted text-center">Đang tải sơ đồ ghế...</p>
                </div>
            </div>
            
            <div class="col-md-6">
                <h6 class="mb-3 fw-bold">Thông tin Khách Hàng & Thanh Toán</h6>
                <form id="booking_form" method="POST" action="sell_ticket_process.php">
                    <input type="hidden" name="trip_id" value="<?php echo $selected_trip_id; ?>">
                    <input type="hidden" name="unit_price" value="<?php echo $current_trip_price; ?>">
                    <input type="hidden" name="selected_seats" id="final_selected_seats">
                    <input type="hidden" name="final_total_amount" id="final_total_amount">
                    
                    <div class="mb-3">
                        <label for="passenger_name" class="form-label">Họ tên Khách hàng</label>
                        <input type="text" class="form-control" id="passenger_name" name="passenger_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="passenger_phone" class="form-label">Số điện thoại</label>
                        <input type="text" class="form-control" id="passenger_phone" name="passenger_phone" required>
                    </div>
                    
                    <hr>
                    
                    <h6 class="fw-bold">Tóm tắt đơn hàng</h6>
                    <p>Ghế đã chọn: <strong id="seats_count_display" class="text-primary">0 ghế</strong></p>
                    <p>Tổng tiền vé: <strong id="subtotal_display">0 VNĐ</strong></p>
                    
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Mã Khuyến Mãi" name="promo_code" id="promo_code">
                        <button class="btn btn-outline-secondary" type="button" id="apply_promo">Áp Dụng</button>
                    </div>
                    
                    <p>Giảm giá: <strong id="discount_display" class="text-danger">0 VNĐ</strong></p>
                    <h5 class="fw-bold text-success">Cần Thu: <span id="final_total_display">0 VNĐ</span></h5>
                    
                    <button type="submit" class="btn btn-lg btn-success w-100 mt-3" id="complete_sale_btn" disabled>
                        <i class="fas fa-print me-2"></i> In Vé & Hoàn Tất
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const seatMapContainer = document.getElementById('seat_map_container');
    const finalSelectedSeatsInput = document.getElementById('final_selected_seats');
    const seatsCountDisplay = document.getElementById('seats_count_display');
    const subtotalDisplay = document.getElementById('subtotal_display');
    const discountDisplay = document.getElementById('discount_display');
    const finalTotalDisplay = document.getElementById('final_total_display');
    const finalTotalAmountInput = document.getElementById('final_total_amount');
    const completeSaleBtn = document.getElementById('complete_sale_btn');
    
    let selectedSeats = [];
    const currentTripPrice = <?php echo $current_trip_price ?? 0; ?>; // Giá vé cơ bản

    // Hàm định dạng tiền tệ
    function formatCurrency(amount) {
        return amount.toLocaleString('vi-VN') + ' VNĐ';
    }

    // HÀM TÍNH TOÁN TỔNG TIỀN
    function updateTotalPrice() {
        const subtotal = selectedSeats.length * currentTripPrice;
        // Logic khuyến mãi (Tạm thời bỏ qua)
        const discount = 0; 
        const finalTotal = subtotal - discount;

        seatsCountDisplay.textContent = selectedSeats.length + ' ghế';
        finalSelectedSeatsInput.value = selectedSeats.join(',');
        finalTotalAmountInput.value = finalTotal;

        subtotalDisplay.textContent = formatCurrency(subtotal);
        discountDisplay.textContent = formatCurrency(discount);
        finalTotalDisplay.textContent = formatCurrency(finalTotal);

        // Bật/Tắt nút Hoàn tất
        completeSaleBtn.disabled = selectedSeats.length === 0;
    }
    
    // XỬ LÝ LỰA CHỌN GHẾ
    seatMapContainer.addEventListener('click', function(e) {
        const seatButton = e.target.closest('.seat-button');
        if (!seatButton || seatButton.disabled) return;

        const seatNumber = seatButton.getAttribute('data-seat');
        const index = selectedSeats.indexOf(seatNumber);

        if (index > -1) {
            // Hủy chọn
            selectedSeats.splice(index, 1);
            seatButton.classList.remove('seat-selected');
            seatButton.classList.add('seat-available');
        } else {
            // Chọn
            selectedSeats.push(seatNumber);
            seatButton.classList.remove('seat-available');
            seatButton.classList.add('seat-selected');
        }
        updateTotalPrice();
    });

    // TẢI SƠ ĐỒ GHẾ BẰNG AJAX
    const selectedTripId = document.querySelector('input[name="trip_id"]').value;
    if (selectedTripId) {
        fetch('get_seats.php?trip_id=' + selectedTripId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                seatMapContainer.innerHTML = data.html;
            } else {
                seatMapContainer.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            seatMapContainer.innerHTML = '<div class="alert alert-danger">Lỗi tải sơ đồ ghế</div>';
        });
    }

    // XỬ LÝ KHUYẾN MÃI (Tạm thời là placeholder)
    document.getElementById('apply_promo').addEventListener('click', function() {
        alert("Chức năng Áp dụng khuyến mãi chưa được triển khai.");
    });
</script>
<?php endif; ?>

<?php 
include 'includes/footer_staff.php'; 
?>