<?php
session_start();
require_once 'includes/config.php';

// Kiểm tra đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Kiểm tra đã chọn chuyến xe chưa
if (!isset($_GET['schedule_id']) || empty($_GET['schedule_id'])) {
    header('Location: search.php');
    exit();
}

$schedule_id = $_GET['schedule_id'];
$user_id = $_SESSION['user_id'];

// Lấy thông tin chuyến xe
$sql_trip = "SELECT s.*, 
             r.base_price, r.is_round_trip,
             po.province_name as origin_name, 
             pd.province_name as destination_name
             FROM schedules s
             JOIN routes r ON s.route_id = r.route_id
             JOIN provinces po ON r.origin_id = po.province_id
             JOIN provinces pd ON r.destination_id = pd.province_id
             WHERE s.schedule_id = ?";
$stmt = $conn->prepare($sql_trip);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();

if (!$trip) {
    header('Location: search.php');
    exit();
}

// Lấy danh sách ghế đã đặt từ bảng bookings
$sql_booked = "SELECT seat_numbers FROM bookings 
               WHERE schedule_id = ? AND status != 'đã hủy'";
$stmt = $conn->prepare($sql_booked);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$booked_seats = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['seat_numbers'])) {
        // Tách chuỗi ghế "1-12,1-15,1-16" thành mảng ["1-12", "1-15", "1-16"]
        $seats = explode(',', $row['seat_numbers']);
        foreach ($seats as $seat) {
            // Chuyển "1-12" thành "A12" (cột 1 = A, cột 2 = B, ...)
            $parts = explode('-', trim($seat));
            if (count($parts) == 2) {
                $col = chr(64 + intval($parts[0])); // 1=A, 2=B, 3=C, 4=D
                $row_num = $parts[1];
                $booked_seats[] = $col . $row_num;
            }
        }
    }
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn Ghế - TMS VéXe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }

        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .breadcrumb {
            color: #666;
            font-size: 0.9rem;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .steps {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .step-number {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        .step.completed .step-number {
            background: #22c55e;
        }

        .step.active .step-number {
            background: #3b82f6;
        }

        .step.inactive .step-number {
            background: #d1d5db;
            color: #6b7280;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .trip-info {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .driver-position {
            text-align: center;
            margin: 1rem 0;
            color: #666;
            font-size: 0.9rem;
        }

        .driver-icon {
            display: inline-block;
            width: 3rem;
            height: 2rem;
            background: #fecdd3;
            border-radius: 0.25rem;
            margin-right: 0.5rem;
        }

        .seats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.75rem;
            max-width: 450px;
            margin: 0 auto 2rem;
        }

        .seat {
            aspect-ratio: 1;
            border: 2px solid #22c55e;
            border-radius: 0.5rem;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .seat:hover:not(.booked) {
            background: #f0fdf4;
            transform: scale(1.05);
        }

        .seat.selected {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .seat.booked {
            background: #ea580c;
            border-color: #ea580c;
            color: white;
            cursor: not-allowed;
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            font-size: 0.85rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .legend-box {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 0.25rem;
        }

        .legend-box.available {
            border: 2px solid #22c55e;
            background: white;
        }

        .legend-box.selected {
            background: #3b82f6;
        }

        .legend-box.booked {
            background: #ea580c;
        }

        .summary-section {
            margin-bottom: 1.5rem;
        }

        .summary-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .summary-text {
            color: #666;
            font-size: 0.9rem;
        }

        .promo-section {
            margin-bottom: 1.5rem;
        }

        .promo-input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .promo-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: transparent;
            color: #ea580c;
            font-size: 0.85rem;
            padding: 0.25rem 0;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .total-price {
            font-size: 1.25rem;
            font-weight: bold;
            color: #ea580c;
        }

        .btn-continue {
            width: 100%;
            padding: 0.75rem;
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }

        .btn-continue.active {
            background: #22c55e;
            color: white;
            cursor: pointer;
        }

        .btn-continue.active:hover {
            background: #16a34a;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .seats-grid {
                gap: 0.5rem;
                grid-template-columns: repeat(5, 1fr);
            }

            .legend {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">TMS VéXe</div>
            <div class="breadcrumb">Đặt vé → Chọn ghế → Thanh toán</div>
        </div>
    </div>

    <div class="container">
        <div class="steps">
            <div class="step completed">
                <div class="step-number">✓</div>
                <span>Chọn chuyến</span>
            </div>
            <div class="step active">
                <div class="step-number">2</div>
                <span>Chọn ghế</span>
            </div>
            <div class="step inactive">
                <div class="step-number">3</div>
                <span>Thanh toán</span>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2 class="card-title">Chọn Ghế Ngồi</h2>
                <p class="trip-info">
                    Chuyến: <?php echo htmlspecialchars($trip['origin_name']); ?> → 
                    <?php echo htmlspecialchars($trip['destination_name']); ?> - 
                    <?php echo date('d/m/Y H:i', strtotime($trip['departure_time'])); ?>
                </p>

                <div class="driver-position">
                    <span class="driver-icon"></span>
                    Vị trí tài xế
                </div>

                <div class="seats-grid" id="seatsGrid">
                    <?php
                    // Xe buýt 25 chỗ: 5 hàng x 5 cột
                    $rows = ['A', 'B', 'C', 'D', 'E'];
                    $cols = range(1, 5);
                    
                    foreach ($rows as $row) {
                        foreach ($cols as $col) {
                            $seat = $row . $col;
                            $is_booked = in_array($seat, $booked_seats);
                            $class = $is_booked ? 'seat booked' : 'seat';
                            $disabled = $is_booked ? 'disabled' : '';
                            
                            echo "<button class='$class' data-seat='$seat' $disabled>$seat</button>";
                        }
                    }
                    ?>
                </div>

                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-box available"></div>
                        <span>Còn trống</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box selected"></div>
                        <span>Đã chọn</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box booked"></div>
                        <span>Đã đặt</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Thông Tin Đặt Vé</h2>
                
                <div class="summary-section">
                    <h3 class="summary-title">
                        <?php echo htmlspecialchars($trip['origin_name']); ?> → 
                        <?php echo htmlspecialchars($trip['destination_name']); ?>
                    </h3>
                    <p class="summary-text"><?php echo date('d/m/Y - H:i', strtotime($trip['departure_time'])); ?></p>
                    <p class="summary-text">Xe buýt 25 chỗ<?php echo $trip['is_round_trip'] ? ' • Khứ hồi' : ' • Một chiều'; ?></p>
                </div>

                <div class="summary-section">
                    <h3 class="summary-title">Ghế đã chọn:</h3>
                    <p class="summary-text" id="selectedSeatsDisplay">Chưa chọn ghế nào</p>
                </div>

                <div class="promo-section">
                    <h3 class="summary-title">🎁 Mã khuyến mãi</h3>
                    <div class="promo-input-group">
                        <input type="text" class="promo-input" id="promoCode" placeholder="Nhập mã khuyến mãi">
                        <button class="btn btn-primary" onclick="applyPromo()">Áp dụng</button>
                    </div>
                    <button class="btn btn-secondary">📋 Xem mã khuyến mãi có sẵn</button>
                </div>

                <div class="summary-section" style="border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                    <div class="price-row">
                        <span>Giá vé gốc:</span>
                        <span id="originalPrice">0 VNĐ</span>
                    </div>
                    <div class="price-row">
                        <span class="total-price">Tổng cộng:</span>
                        <span class="total-price" id="totalPrice">0 VNĐ</span>
                    </div>
                </div>

                <button class="btn btn-continue" id="continueBtn" onclick="proceedToPayment()">Tiếp tục</button>
            </div>
        </div>
    </div>

    <script>
        const selectedSeats = [];
        const pricePerSeat = <?php echo $trip['base_price']; ?>;
        const scheduleId = <?php echo $schedule_id; ?>;

        // Xử lý chọn ghế
        document.querySelectorAll('.seat:not(.booked)').forEach(seat => {
            seat.addEventListener('click', function() {
                const seatNumber = this.getAttribute('data-seat');
                
                if (this.classList.contains('selected')) {
                    // Bỏ chọn ghế
                    this.classList.remove('selected');
                    const index = selectedSeats.indexOf(seatNumber);
                    if (index > -1) {
                        selectedSeats.splice(index, 1);
                    }
                } else {
                    // Chọn ghế
                    this.classList.add('selected');
                    selectedSeats.push(seatNumber);
                }
                
                updateSummary();
            });
        });

        function updateSummary() {
            // Cập nhật danh sách ghế đã chọn
            const display = document.getElementById('selectedSeatsDisplay');
            display.textContent = selectedSeats.length > 0 ? selectedSeats.join(', ') : 'Chưa chọn ghế nào';
            
            // Cập nhật giá
            const totalPrice = selectedSeats.length * pricePerSeat;
            document.getElementById('originalPrice').textContent = totalPrice.toLocaleString('vi-VN') + ' VNĐ';
            document.getElementById('totalPrice').textContent = totalPrice.toLocaleString('vi-VN') + ' VNĐ';
            
            // Kích hoạt nút tiếp tục
            const continueBtn = document.getElementById('continueBtn');
            if (selectedSeats.length > 0) {
                continueBtn.classList.add('active');
                continueBtn.disabled = false;
            } else {
                continueBtn.classList.remove('active');
                continueBtn.disabled = true;
            }
        }

        function applyPromo() {
            const promoCode = document.getElementById('promoCode').value.trim();
            if (promoCode) {
                // TODO: Gọi API kiểm tra mã khuyến mãi
                alert('Chức năng áp dụng mã khuyến mãi đang được phát triển');
            }
        }

        function proceedToPayment() {
            if (selectedSeats.length === 0) {
                alert('Vui lòng chọn ít nhất một ghế');
                return;
            }
            
            // Chuyển ghế từ "A1,B2" thành format "1-1,2-2" để lưu vào DB
            const seatsFormatted = selectedSeats.map(seat => {
                const col = seat.charCodeAt(0) - 64; // A=1, B=2, C=3...
                const row = seat.substring(1);
                return col + '-' + row;
            }).join(',');
            
            // Chuyển đến trang thanh toán
            const params = new URLSearchParams({
                schedule_id: scheduleId,
                seats: seatsFormatted,
                seats_display: selectedSeats.join(',')
            });
            window.location.href = 'payment.php?' + params.toString();
        }
    </script>
</body>
</html>

<?php include 'includes/footer.php'; ?>