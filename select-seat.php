<?php
session_start();
require_once 'includes/config.php';

// Kiểm tra đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Kiểm tra đã chọn chuyến xe chưa
if (!isset($_GET['trip_id']) || empty($_GET['trip_id'])) {
    header('Location: search.php');
    exit();
}

$trip_id = $_GET['trip_id'];
$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Lấy thông tin chuyến xe
$stmt = $pdo->prepare("SELECT t.*, 
             po.province_name as origin_name, 
             pd.province_name as destination_name
             FROM trips t
             JOIN provinces po ON t.departure_province_id = po.id
             JOIN provinces pd ON t.destination_province_id = pd.id
             WHERE t.id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header('Location: search.php');
    exit();
}

// Lấy danh sách ghế đã đặt
$stmt = $pdo->prepare("SELECT seat_numbers FROM bookings 
               WHERE trip_id = ? AND status != 'cancelled'");
$stmt->execute([$trip_id]);
$booked_seats = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['seat_numbers'])) {
        $seats = explode(',', $row['seat_numbers']);
        foreach ($seats as $seat) {
            $parts = explode('-', trim($seat));
            if (count($parts) == 2) {
                $col = chr(64 + intval($parts[0]));
                $row_num = $parts[1];
                $booked_seats[] = $col . $row_num;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn Ghế - TMS VéXe</title>
    <style>
        /* GIỮ NGUYÊN CSS CŨ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .header { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1rem 2rem; }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: bold; color: #333; }
        .breadcrumb { color: #666; font-size: 0.9rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .steps { display: flex; justify-content: center; gap: 2rem; margin-bottom: 2rem; }
        .step { display: flex; align-items: center; gap: 0.5rem; }
        .step-number { width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; }
        .step.completed .step-number { background: #22c55e; }
        .step.active .step-number { background: #3b82f6; }
        .step.inactive .step-number { background: #d1d5db; color: #6b7280; }
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        .card { background: white; border-radius: 0.5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card-title { font-size: 1.25rem; font-weight: bold; margin-bottom: 0.5rem; }
        .trip-info { color: #666; font-size: 0.9rem; margin-bottom: 1rem; }
        .driver-position { text-align: center; margin: 1rem 0; color: #666; font-size: 0.9rem; }
        .driver-icon { display: inline-block; width: 3rem; height: 2rem; background: #fecdd3; border-radius: 0.25rem; margin-right: 0.5rem; }
        .seats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; max-width: 450px; margin: 0 auto 2rem; }
        .seat { aspect-ratio: 1; border: 2px solid #22c55e; border-radius: 0.5rem; background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.85rem; transition: all 0.2s; }
        .seat:hover:not(.booked) { background: #f0fdf4; transform: scale(1.05); }
        .seat.selected { background: #3b82f6; border-color: #3b82f6; color: white; }
        .seat.booked { background: #ea580c; border-color: #ea580c; color: white; cursor: not-allowed; }
        .legend { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; font-size: 0.85rem; }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; }
        .legend-box { width: 1.5rem; height: 1.5rem; border-radius: 0.25rem; }
        .legend-box.available { border: 2px solid #22c55e; background: white; }
        .legend-box.selected { background: #3b82f6; }
        .legend-box.booked { background: #ea580c; }
        .summary-section { margin-bottom: 1.5rem; }
        .summary-title { font-weight: 600; margin-bottom: 0.5rem; }
        .summary-text { color: #666; font-size: 0.9rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9rem; }
        .form-input:focus { outline: none; border-color: #3b82f6; }
        .promo-section { margin-bottom: 1.5rem; }
        .promo-input-group { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
        .promo-input { flex: 1; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .price-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .total-price { font-size: 1.25rem; font-weight: bold; color: #ea580c; }
        .btn-continue { width: 100%; padding: 0.75rem; background: #d1d5db; color: #6b7280; cursor: not-allowed; }
        .btn-continue.active { background: #22c55e; color: white; cursor: pointer; }
        .btn-continue.active:hover { background: #16a34a; }
        
        /* Thêm style cho thông báo lỗi/thành công */
        .promo-message { font-size: 0.85rem; margin-top: 0.25rem; }
        .text-success { color: #22c55e; }
        .text-error { color: #ea580c; }
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
                <span>Chọn ghế & Thông tin</span>
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
                    <p class="summary-text">Xe buýt <?php echo $trip['total_seats']; ?> chỗ<?php echo $trip['ticket_type'] == 'round_trip' ? ' • Khứ hồi' : ' • Một chiều'; ?></p>
                </div>

                <div class="summary-section">
                    <h3 class="summary-title">Ghế đã chọn:</h3>
                    <p class="summary-text" id="selectedSeatsDisplay">Chưa chọn ghế nào</p>
                </div>

                <div class="summary-section" style="border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-top: 1rem;">
                    <h3 class="summary-title">Thông tin hành khách</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Họ và tên *</label>
                        <input type="text" class="form-input" id="fullname" 
                               value="<?php echo htmlspecialchars($user_info['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-input" id="email" 
                               value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Số điện thoại *</label>
                        <input type="tel" class="form-input" id="phone" 
                               value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="promo-section">
                    <h3 class="summary-title">🎁 Mã khuyến mãi</h3>
                    <div class="promo-input-group">
                        <input type="text" class="promo-input" id="promoCode" placeholder="Nhập mã khuyến mãi">
                        <button class="btn btn-primary" onclick="applyPromo()">Áp dụng</button>
                    </div>
                    <div id="promoMessage" class="promo-message"></div>
                </div>

                <div class="summary-section" style="border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                    <div class="price-row">
                        <span>Giá vé gốc:</span>
                        <span id="originalPrice">0 VNĐ</span>
                    </div>
                    <div class="price-row" id="discountRow" style="display:none;">
                        <span>Giảm giá:</span>
                        <span id="discountPrice" style="color: #22c55e;">-0 VNĐ</span>
                    </div>
                    <div class="price-row">
                        <span class="total-price">Tổng cộng:</span>
                        <span class="total-price" id="totalPrice">0 VNĐ</span>
                    </div>
                </div>

                <button class="btn btn-continue" id="continueBtn" onclick="proceedToPayment()">Thanh toán</button>
            </div>
        </div>
    </div>

    <script>
        const selectedSeats = [];
        const pricePerSeat = <?php echo $trip['price']; ?>;
        const tripId = <?php echo $trip_id; ?>;
        
        // Biến lưu thông tin giảm giá
        let currentDiscount = 0;
        let appliedPromoId = null;

        document.querySelectorAll('.seat:not(.booked)').forEach(seat => {
            seat.addEventListener('click', function() {
                const seatNumber = this.getAttribute('data-seat');
                
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    const index = selectedSeats.indexOf(seatNumber);
                    if (index > -1) {
                        selectedSeats.splice(index, 1);
                    }
                } else {
                    this.classList.add('selected');
                    selectedSeats.push(seatNumber);
                }
                
                // Khi thay đổi ghế, reset mã giảm giá để đảm bảo tính đúng đắn (ví dụ: điều kiện đơn tối thiểu)
                resetPromo();
                updateSummary();
            });
        });

        function resetPromo() {
            currentDiscount = 0;
            appliedPromoId = null;
            document.getElementById('promoCode').value = '';
            document.getElementById('promoMessage').innerHTML = '';
            document.getElementById('discountRow').style.display = 'none';
        }

        function updateSummary() {
            const display = document.getElementById('selectedSeatsDisplay');
            display.textContent = selectedSeats.length > 0 ? selectedSeats.join(', ') : 'Chưa chọn ghế nào';
            
            const originalTotal = selectedSeats.length * pricePerSeat;
            const finalTotal = originalTotal - currentDiscount;

            document.getElementById('originalPrice').textContent = originalTotal.toLocaleString('vi-VN') + ' VNĐ';
            
            if (currentDiscount > 0) {
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountPrice').textContent = '-' + currentDiscount.toLocaleString('vi-VN') + ' VNĐ';
            } else {
                document.getElementById('discountRow').style.display = 'none';
            }

            document.getElementById('totalPrice').textContent = (finalTotal > 0 ? finalTotal : 0).toLocaleString('vi-VN') + ' VNĐ';
            
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
            const messageDiv = document.getElementById('promoMessage');
            
            if (!promoCode) {
                messageDiv.innerHTML = '<span class="text-error">Vui lòng nhập mã khuyến mãi</span>';
                return;
            }

            if (selectedSeats.length === 0) {
                messageDiv.innerHTML = '<span class="text-error">Vui lòng chọn ghế trước khi áp dụng mã</span>';
                return;
            }

            const currentTotal = selectedSeats.length * pricePerSeat;

            // Gọi AJAX kiểm tra mã
            fetch('check_promo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    code: promoCode,
                    total_amount: currentTotal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentDiscount = data.discount_amount;
                    appliedPromoId = data.promotion_id;
                    messageDiv.innerHTML = `<span class="text-success">${data.message}</span>`;
                    updateSummary();
                } else {
                    currentDiscount = 0;
                    appliedPromoId = null;
                    messageDiv.innerHTML = `<span class="text-error">${data.message}</span>`;
                    updateSummary();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.innerHTML = '<span class="text-error">Có lỗi xảy ra, vui lòng thử lại</span>';
            });
        }

        function proceedToPayment() {
            if (selectedSeats.length === 0) {
                alert('Vui lòng chọn ít nhất một ghế');
                return;
            }

            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();

            if (!fullname || !email || !phone) {
                alert('Vui lòng điền đầy đủ thông tin hành khách');
                return;
            }
            
            const seatsFormatted = selectedSeats.map(seat => {
                const col = seat.charCodeAt(0) - 64;
                const row = seat.substring(1);
                return col + '-' + row;
            }).join(',');
            
            const params = new URLSearchParams({
                trip_id: tripId,
                seats: seatsFormatted,
                seats_display: selectedSeats.join(','),
                fullname: fullname,
                email: email,
                phone: phone
            });

            // Nếu có áp dụng mã khuyến mãi, truyền thêm ID
            if (appliedPromoId) {
                params.append('promotion_id', appliedPromoId);
                params.append('discount_amount', currentDiscount);
            }
            
            window.location.href = 'payment.php?' + params.toString();
        }
    </script>
</body>
</html>