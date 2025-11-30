<?php
session_start();
require_once 'includes/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Lấy thông tin từ URL
$trip_id = $_GET['trip_id'] ?? 0;
$seats = $_GET['seats'] ?? '';
$seats_display = $_GET['seats_display'] ?? '';
$fullname = $_GET['fullname'] ?? '';
$email = $_GET['email'] ?? '';
$phone = $_GET['phone'] ?? '';

if (!$trip_id || !$seats) {
    header('Location: search.php');
    exit();
}

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

// Tính toán giá
$seat_count = count(explode(',', $seats_display));
$price_per_seat = $trip['price'];
$subtotal = $seat_count * $price_per_seat;
$service_fee = 10000;
$discount = 0;
$total = $subtotal + $service_fee - $discount;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - TMS VéXe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: #4a3f4f;
            color: white;
            padding: 1.2rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-steps {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            font-size: 0.95rem;
        }

        .nav-steps span {
            opacity: 0.8;
        }

        .progress-container {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .progress-steps {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 3rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .step.completed .step-circle {
            background: #27ae60;
            color: white;
        }

        .step.active .step-circle {
            background: #3498db;
            color: white;
        }

        .step-label {
            font-size: 0.95rem;
            color: #333;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem 3rem;
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 2rem;
        }

        .payment-method-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }

        .timer-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .timer {
            color: #dc3545;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .payment-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            background: white;
        }

        .payment-option:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .payment-option.selected {
            border-color: #3498db;
            background: #e3f2fd;
        }

        .radio-circle {
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-radius: 50%;
            margin-right: 1rem;
            position: relative;
        }

        .payment-option.selected .radio-circle {
            border-color: #3498db;
        }

        .payment-option.selected .radio-circle::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: #3498db;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .option-text {
            font-size: 1rem;
            color: #2c3e50;
        }

        .complete-btn {
            width: 100%;
            padding: 1rem;
            background: #d1d5db;
            color: #6b7280;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: not-allowed;
            transition: all 0.3s;
        }

        .complete-btn.active {
            background: #27ae60;
            color: white;
            cursor: pointer;
        }

        .complete-btn.active:hover {
            background: #229954;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .order-summary {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .summary-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }

        .trip-details {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .route {
            font-weight: 600;
            font-size: 1.05rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .trip-info {
            color: #666;
            font-size: 0.95rem;
            margin: 0.4rem 0;
        }

        .seats-info {
            margin-top: 0.5rem;
        }

        .seats-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .price-details {
            margin-bottom: 1.5rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.95rem;
        }

        .price-label {
            color: #666;
        }

        .price-value {
            font-weight: 500;
            color: #2c3e50;
        }

        .discount-row .price-value {
            color: #27ae60;
        }

        .total-row {
            border-top: 2px solid #2c3e50;
            border-bottom: none;
            padding-top: 1rem;
            margin-top: 0.5rem;
        }

        .total-row .price-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .total-row .price-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #e74c3c;
        }

        .passenger-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
        }

        .info-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .passenger-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .contact-info {
            color: #666;
            font-size: 0.9rem;
            margin: 0.3rem 0;
        }

        @media (max-width: 968px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                order: -1;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">TMS VéXe</div>
            <div class="nav-steps">
                Đặt vé → Chọn ghế → Thông tin → Thanh toán
            </div>
        </div>
    </div>

    <div class="progress-container">
        <div class="progress-steps">
            <div class="step completed">
                <div class="step-circle">✓</div>
                <div class="step-label">Chọn chuyến</div>
            </div>
            <div class="step completed">
                <div class="step-circle">✓</div>
                <div class="step-label">Chọn ghế</div>
            </div>
            <div class="step active">
                <div class="step-circle">3</div>
                <div class="step-label">Thanh toán</div>
            </div>
        </div>
    </div>

    <div class="main-container">
        <div class="payment-method-section">
            <h2 class="section-title">Chọn Phương Thức Thanh Toán</h2>
            
            <div class="timer-warning">
                Thời gian còn lại: <span class="timer" id="countdown">14:58</span>
            </div>

            <form id="paymentForm" method="POST" action="process_payment.php">
                <input type="hidden" name="trip_id" value="<?php echo $trip_id; ?>">
                <input type="hidden" name="seats" value="<?php echo htmlspecialchars($seats); ?>">
                <input type="hidden" name="seats_display" value="<?php echo htmlspecialchars($seats_display); ?>">
                <input type="hidden" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                <input type="hidden" name="total" value="<?php echo $total; ?>">
                <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="">

                <div class="payment-options">
                    <div class="payment-option" onclick="selectPayment(this, 'bank_transfer')">
                        <div class="radio-circle"></div>
                        <div class="option-text">💳 Chuyển khoản ngân hàng</div>
                    </div>

                    <div class="payment-option" onclick="selectPayment(this, 'counter')">
                        <div class="radio-circle"></div>
                        <div class="option-text">🏢 Thanh toán tại quầy</div>
                    </div>
                </div>

                <button type="button" class="complete-btn" id="completeBtn" onclick="completePayment()">
                    Xác nhận thanh toán
                </button>
            </form>
        </div>

        <div class="order-summary">
            <h3 class="summary-title">Thông Tin Đơn Hàng</h3>
            
            <div class="trip-details">
                <div class="route">
                    <?php echo htmlspecialchars($trip['origin_name']); ?> → 
                    <?php echo htmlspecialchars($trip['destination_name']); ?>
                </div>
                <div class="trip-info"><?php echo date('d/m/Y - H:i', strtotime($trip['departure_time'])); ?></div>
                <div class="trip-info">Xe buýt <?php echo $trip['total_seats']; ?> chỗ<?php echo $trip['ticket_type'] == 'round_trip' ? ' • Khứ hồi' : ' • Một chiều'; ?></div>
                <div class="seats-info">
                    <span class="seats-label">Ghế:</span> <?php echo htmlspecialchars($seats_display); ?>
                </div>
            </div>

            <div class="price-details">
                <div class="price-row">
                    <span class="price-label">Giá vé (<?php echo $seat_count; ?> ghế):</span>
                    <span class="price-value"><?php echo number_format($subtotal, 0, ',', '.'); ?> VNĐ</span>
                </div>
                <div class="price-row">
                    <span class="price-label">Phí dịch vụ:</span>
                    <span class="price-value"><?php echo number_format($service_fee, 0, ',', '.'); ?> VNĐ</span>
                </div>
                <?php if ($discount > 0): ?>
                <div class="price-row discount-row">
                    <span class="price-label">Khuyến mãi:</span>
                    <span class="price-value">-<?php echo number_format($discount, 0, ',', '.'); ?> VNĐ</span>
                </div>
                <?php endif; ?>
                <div class="price-row total-row">
                    <span class="price-label">Tổng cộng:</span>
                    <span class="price-value"><?php echo number_format($total, 0, ',', '.'); ?> VNĐ</span>
                </div>
            </div>

            <div class="passenger-info">
                <div class="info-title">Thông tin hành khách:</div>
                <div class="passenger-name"><?php echo htmlspecialchars($fullname); ?></div>
                <div class="contact-info">Email: <?php echo htmlspecialchars($email); ?></div>
                <div class="contact-info">Điện thoại: <?php echo htmlspecialchars($phone); ?></div>
            </div>
        </div>
    </div>

    <script>
        let selectedMethod = '';

        function selectPayment(element, method) {
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            element.classList.add('selected');
            selectedMethod = method;
            document.getElementById('selectedPaymentMethod').value = method;
            
            const btn = document.getElementById('completeBtn');
            btn.classList.add('active');
            btn.disabled = false;
        }

        function completePayment() {
            if (!selectedMethod) {
                alert('Vui lòng chọn phương thức thanh toán');
                return;
            }

            const btn = document.getElementById('completeBtn');
            btn.textContent = 'Đang xử lý...';
            btn.disabled = true;

            setTimeout(() => {
                document.getElementById('paymentForm').submit();
            }, 1000);
        }

        let timeLeft = 898;
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft > 0) {
                timeLeft--;
            } else {
                clearInterval(timerInterval);
                alert('Hết thời gian đặt vé!');
                window.location.href = 'search.php';
            }
        }

        const timerInterval = setInterval(updateTimer, 1000);
    </script>
</body>
</html>