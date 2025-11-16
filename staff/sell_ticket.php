<?php
session_start();
// Khối PHP giả lập dữ liệu cần thiết cho giao diện
$staff_name = 'Nguyễn Văn A';
$message = '';
$selected_trip_id = 1; // Giả lập đã chọn chuyến đi
$current_trip_price = 350000;
$booked_seats = ['A3', 'A6', 'A12', 'A15'];
$total_seats = 16; 

// Dữ liệu chuyến đi giả lập (để hiển thị chi tiết)
$selected_trip = [
    'origin_name' => 'Thành phố Hồ Chí Minh',
    'destination_name' => 'Đà Lạt',
    'departure_time' => '2025-10-15 07:30:00',
    'return_time' => '2025-10-15 13:45:00',
    'ticket_type' => 'Limousine',
    'available_seats' => 12,
    'total_seats' => $total_seats,
    'price' => $current_trip_price
];

// Dữ liệu tỉnh/thành giả lập (chỉ để hiển thị dropdown)
$provinces = [
    ['id' => 1, 'name' => 'Thành phố Hồ Chí Minh'],
    ['id' => 2, 'name' => 'Lâm Đồng'],
    ['id' => 3, 'name' => 'Hà Nội'],
    // ... thêm các tỉnh khác nếu cần
];

// Giả lập logic tìm kiếm để hiển thị danh sách chuyến đi
$trips = [
    ['id' => 1, 'departure_time' => '2025-10-15 07:30:00', 'ticket_type' => 'Thường', 'available_seats' => 12, 'total_seats' => 20, 'price' => 350000],
    ['id' => 2, 'departure_time' => '2025-10-15 10:00:00', 'ticket_type' => 'Limousine', 'available_seats' => 5, 'total_seats' => 20, 'price' => 450000],
];


// Bắt đầu HTML (Giả định includes/header_staff.php chứa Bootstrap/CSS)
include 'includes/header_staff.php'; 
?>

<style>
/* CSS sơ đồ ghế */
.seat-grid { display: flex; gap: 2.5rem; justify-content: center; align-items:flex-start; }
.seat-column { display:flex; flex-direction:column; gap:0.8rem; }
.seat-button {
    width:56px; height:36px; border-radius:8px; border:2px solid #2ecc71;
    background:#fff; display:inline-flex; align-items:center; justify-content:center;
    font-weight:600; cursor:pointer; font-size: 14px;
}
.seat-available { border-color:#2ecc71; background:#fff; color:#2ecc71; }
.seat-selected { background:#f39c12; border-color:#f39c12; color:#fff; }
.seat-booked { background:#e74c3c; border-color:#e74c3c; color:#fff; cursor:not-allowed; opacity:0.9; }
.seat-legend { display:flex; gap:1rem; align-items:center; margin-top:1rem; justify-content: center; }
.legend-item { display:flex; gap:0.5rem; align-items:center; }
.legend-box { width:12px; height:12px; border-radius:50%; }
</style>

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
                            <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == 1) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="destination_id" class="form-label">Điểm Đến</label>
                    <select class="form-select" id="destination_id" name="destination_id" required>
                        <option value="">Chọn Tỉnh/Thành</option>
                           <?php foreach ($provinces as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == 2) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="departure_date" class="form-label">Ngày Đi</label>
                    <input type="date" class="form-control" id="departure_date" name="departure_date" 
                            value="2025-10-15" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Tìm</button>
                </div>
            </div>
        </form>
    </div>
</div>

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
                                        <?php echo ($selected_trip_id == $trip['id']) ? 'disabled' : ''; ?>>
                                    <?php echo ($selected_trip_id == $trip['id']) ? 'Đã chọn' : 'Chọn chuyến'; ?>
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

<?php if ($selected_trip): ?>
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-route me-2"></i> Chi Tiết Chuyến Đi Đã Chọn
            </div>
            <div class="card-body">
                <p class="fw-bold fs-5 text-primary"><?php echo htmlspecialchars($selected_trip['origin_name'] . ' - ' . $selected_trip['destination_name']); ?></p>
                <p><strong>Ngày đi:</strong> <?php echo date('d/m/Y', strtotime($selected_trip['departure_time'])); ?></p>
                <p><strong>Giờ khởi hành:</strong> <?php echo date('H:i', strtotime($selected_trip['departure_time'])); ?> - <span class="text-muted">~ <?php echo date('H:i', strtotime($selected_trip['return_time'])); ?></span></p>
                <p><strong>Loại xe:</strong> <?php echo htmlspecialchars($selected_trip['ticket_type']); ?></p>
                <p><strong>Giá vé:</strong> <strong class="text-danger"><?php echo number_format($current_trip_price, 0, ',', '.'); ?> VNĐ</strong></p>
                <p><strong>Ghế trống:</strong> <strong class="text-success"><?php echo $selected_trip['available_seats']; ?></strong> / <?php echo $selected_trip['total_seats']; ?></p>

                <hr>

                <h6 class="mb-3 fw-bold"><i class="fas fa-chair me-2"></i> Sơ Đồ Ghế Xe</h6>
                <div id="seat_map_container" class="p-3">
                    <div class="seat-grid">
                        <?php 
                        $seat_list = [];
                        for ($i = 1; $i <= $total_seats; $i++) { $seat_list[] = 'A' . $i; }
                        $seats_per_col = 4;
                        $col_counter = 0;
                        
                        foreach (array_chunk($seat_list, $seats_per_col) as $col_index => $column_seats) {
                            echo '<div class="seat-column">';
                            foreach ($column_seats as $seat_name) {
                                $is_booked = in_array($seat_name, $booked_seats);
                                $class = $is_booked ? 'seat-booked' : 'seat-available';
                                $disabled = $is_booked ? 'disabled' : '';

                                // Sử dụng ID để JS dễ dàng thao tác, MOCK theo ảnh A3, A6, A12, A15 là Đã đặt
                                if ($seat_name == 'A3' || $seat_name == 'A6' || $seat_name == 'A12' || $seat_name == 'A15') {
                                     $class = 'seat-booked'; $disabled = 'disabled';
                                } elseif ($seat_name == 'A1') {
                                    // Giả lập ghế đang chọn
                                    $class = 'seat-selected';
                                } else {
                                     $class = 'seat-available';
                                }

                                echo '<button type="button" class="seat-button ' . $class . '" 
                                            data-seat="' . $seat_name . '" ' . $disabled . '>' . $seat_name . '</button>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                <div class="seat-legend">
                    <div class="legend-item"><span class="legend-box" style="background:#2ecc71"></span> Còn trống</div>
                    <div class="legend-item"><span class="legend-box" style="background:#f39c12"></span> Đang chọn</div>
                    <div class="legend-item"><span class="legend-box" style="background:#e74c3c"></span> Đã đặt</div>
                </div>
            </div>
            
            <div class="col-md-6">
                <h6 class="mb-3 fw-bold">2. Thông tin đặt vé & Thanh toán</h6>
                <form id="booking_form" method="POST" action="sell_ticket_process.php">
                    <input type="hidden" name="trip_id" value="<?php echo $selected_trip_id; ?>">
                    <input type="hidden" name="unit_price" id="unit_price_value" value="<?php echo $current_trip_price; ?>">
                    <input type="hidden" name="selected_seats" id="final_selected_seats" value="A1">
                    <input type="hidden" name="final_total_amount" id="final_total_amount" value="315000">
                    
                    <h6 class="fw-bold">Thông tin khách hàng:</h6>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="passenger_name" name="passenger_name" required placeholder="Họ tên Khách hàng">
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="passenger_phone" name="passenger_phone" required placeholder="Số điện thoại">
                    </div>
                    <div class="mb-3">
                        <input type="email" class="form-control" id="passenger_email" name="passenger_email" placeholder="Email (Tùy chọn)">
                    </div>
                    
                    <hr>
                    
                    <h6 class="fw-bold">Mã khuyến mãi:</h6>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Nhập mã khuyến mãi" name="promo_code" id="promo_code">
                        <button class="btn btn-primary" type="button" id="apply_promo">Áp Dụng</button>
                    </div>
                    
                    <h6 class="fw-bold">Tổng thanh toán:</h6>
                    <p>Giá vé (1 khách): <strong id="unit_price_display"><?php echo number_format($current_trip_price, 0, ',', '.'); ?> VNĐ</strong></p>
                    <p>Số lượng: <strong id="seats_count_display">1 ghế</strong></p>
                    <p>Tổng cộng: <strong id="subtotal_display">350.000 VNĐ</strong></p>
                    <p>Giảm giá: <strong id="discount_display" class="text-danger">35.000 VNĐ</strong></p>
                    
                    <h5 class="fw-bold text-success">Cần Thu: <span id="final_total_display">315.000 VNĐ</span></h5>
                    
                    <button type="submit" class="btn btn-lg btn-success w-100 mt-3" id="complete_sale_btn">
                        <i class="fas fa-print me-2"></i> In Vé & Hoàn Tất
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // JS MOCK: Chức năng click ghế và tính tiền
    const seatMapContainer = document.getElementById('seat_map_container');
    const finalSelectedSeatsInput = document.getElementById('final_selected_seats');
    const seatsCountDisplay = document.getElementById('seats_count_display');
    const subtotalDisplay = document.getElementById('subtotal_display');
    const discountDisplay = document.getElementById('discount_display');
    const finalTotalDisplay = document.getElementById('final_total_display');
    const unitPrice = parseFloat(document.getElementById('unit_price_value').value);
    
    // Khởi tạo trạng thái
    let selectedSeats = ['A1'];
    let currentDiscount = 35000;

    function formatCurrency(amount) {
        return amount.toLocaleString('vi-VN') + ' VNĐ';
    }

    function updateTotalPrice() {
        const subtotal = selectedSeats.length * unitPrice;
        const finalTotal = subtotal - currentDiscount;

        seatsCountDisplay.textContent = selectedSeats.length + ' ghế';
        finalSelectedSeatsInput.value = selectedSeats.join(',');
        
        subtotalDisplay.textContent = formatCurrency(subtotal);
        discountDisplay.textContent = formatCurrency(currentDiscount);
        finalTotalDisplay.textContent = formatCurrency(finalTotal);
    }

    seatMapContainer.addEventListener('click', function(e) {
        const seatButton = e.target.closest('.seat-button');
        if (!seatButton || seatButton.classList.contains('seat-booked')) return;

        const seatNumber = seatButton.getAttribute('data-seat');
        const idx = selectedSeats.indexOf(seatNumber);
        
        if (idx > -1) {
            selectedSeats.splice(idx, 1);
            seatButton.classList.remove('seat-selected');
            seatButton.classList.add('seat-available');
        } else {
            selectedSeats.push(seatNumber);
            seatButton.classList.remove('seat-available');
            seatButton.classList.add('seat-selected');
        }
        
        // Cập nhật lại tổng tiền (giả lập giảm giá ban đầu)
        currentDiscount = selectedSeats.length > 0 ? 35000 : 0;
        updateTotalPrice();
    });
    
    // Khởi tạo trạng thái ban đầu
    updateTotalPrice();

    // Chức năng khuyến mãi MOCK
    document.getElementById('apply_promo').addEventListener('click', function() {
        alert("vui lòng thử lại.");
    });
</script>
<?php endif; ?>

<?php 
// Giả định footer_staff.php đóng thẻ </body> và </html>
include 'includes/footer_staff.php'; 
?>