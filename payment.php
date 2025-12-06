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

// Lấy thông tin khuyến mãi từ URL
$promotion_id = $_GET['promotion_id'] ?? null;
$discount_amount = isset($_GET['discount_amount']) ? floatval($_GET['discount_amount']) : 0;

if (!$trip_id || !$seats) {
    header('Location: search.php');
    exit();
}

// Lấy thông tin chuyến xe (bao gồm loại vé và ngày về)
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

// Lấy tên mã khuyến mãi nếu có
$promotion_code_name = '';
if ($promotion_id) {
    $stmt_promo = $pdo->prepare("SELECT promotion_code FROM promotions WHERE id = ?");
    $stmt_promo->execute([$promotion_id]);
    $promo_info = $stmt_promo->fetch(PDO::FETCH_ASSOC);
    if ($promo_info) {
        $promotion_code_name = $promo_info['promotion_code'];
    }
}

// Tính toán giá
$seat_count = count(explode(',', $seats_display));
$price_per_seat = $trip['price'];
$subtotal = $seat_count * $price_per_seat;

// Tổng tiền
$total = $subtotal - $discount_amount;
if ($total < 0) $total = 0;

// Tạo mã booking hiển thị
$booking_code = 'TMS' . date('Ymd') . ' ' . $fullname;
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .progress-steps {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            background: #10b981;
            color: white;
        }

        .step.active .step-circle {
            background: #3b82f6;
        }

        .step-label {
            font-size: 0.95rem;
            font-weight: 500;
            color: #1a1a1a;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto 3rem;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #1a1a1a;
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .payment-method {
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
        }

        .payment-method.selected {
            border-color: #3b82f6;
        }

        .method-header {
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            background: white;
        }

        .payment-method.selected .method-header {
            background: #eff6ff;
        }

        .radio-circle {
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            margin-right: 1rem;
            position: relative;
            flex-shrink: 0;
        }

        .payment-method.selected .radio-circle {
            border-color: #3b82f6;
        }

        .payment-method.selected .radio-circle::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: #3b82f6;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .method-text {
            font-size: 0.95rem;
            color: #1a1a1a;
            font-weight: 500;
        }

        .method-details {
            display: none;
            padding: 1.5rem 1.25rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .payment-method.selected .method-details {
            display: block;
        }

        .bank-info {
            background: white;
            padding: 1.25rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }

        .info-label {
            color: #6b7280;
            font-weight: 500;
        }

        .info-value {
            color: #1a1a1a;
            font-weight: 600;
            text-align: right;
        }

        .amount-highlight {
            color: #dc2626;
            font-size: 1.05rem;
        }

        .timer-warning {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .timer {
            color: #dc2626;
            font-weight: 700;
            font-size: 1rem;
        }

        .complete-btn {
            width: 100%;
            padding: 1rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .complete-btn:hover {
            background: #059669;
        }

        .complete-btn:disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }

        .trip-info-box {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .route-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1a1a1a;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.75rem;
        }

        .trip-detail {
            color: #1a1a1a;
            font-size: 0.95rem;
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
        }

        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
        }

        .seats-info {
            margin-top: 0.75rem;
            font-weight: 600;
            color: #1a1a1a;
            padding-top: 0.75rem;
            border-top: 1px dashed #d1d5db;
        }

        .price-breakdown {
            margin-bottom: 1.5rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            font-size: 0.9rem;
        }

        .price-label {
            color: #4b5563;
        }

        .price-value {
            font-weight: 600;
            color: #1a1a1a;
        }

        .discount-row .price-value {
            color: #10b981;
        }

        .total-row {
            border-top: 2px solid #e5e7eb;
            padding-top: 1rem;
            margin-top: 0.5rem;
            font-size: 1rem;
        }

        .total-row .price-label {
            font-weight: 700;
            color: #1a1a1a;
        }

        .total-row .price-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #dc2626;
        }

        .passenger-box {
            background: #f9fafb;
            padding: 1.25rem;
            border-radius: 6px;
        }

        .section-subtitle {
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .passenger-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .contact-detail {
            color: #4b5563;
            font-size: 0.85rem;
            margin: 0.25rem 0;
        }

        @media (max-width: 968px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .order-summary {
                order: -1;
            }
        }

        /* Thêm mới */
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .back-btn {
            padding: 1rem;
            background: white;
            color: #4b5563;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            min-width: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .back-btn:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }

        /* Chỉnh lại nút complete-btn để nó nằm trong group đẹp hơn */
        .complete-btn {
            margin-top: 0 !important;
            /* Ghi đè margin cũ */
            flex: 1;
            /* Nút hoàn tất chiếm phần còn lại */
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">TMS VéXe</div>
        </div>
    </div>

    <div class="progress-steps">
        <div class="step">
            <div class="step-circle">✓</div>
            <div class="step-label">Chọn chuyến</div>
        </div>
        <div class="step">
            <div class="step-circle">✓</div>
            <div class="step-label">Chọn ghế</div>
        </div>
        <div class="step">
            <div class="step-circle">✓</div>
            <div class="step-label">Thông tin</div>
        </div>
        <div class="step active">
            <div class="step-circle">4</div>
            <div class="step-label">Thanh toán</div>
        </div>
    </div>

    <div class="main-container">
        <div class="card payment-section">
            <h2 class="card-title">Chọn Phương Thức Thanh Toán</h2>

            <div class="timer-warning">
                Thời gian còn lại: <span class="timer" id="countdown">15:00</span>
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

                <input type="hidden" name="promotion_id" value="<?php echo htmlspecialchars($promotion_id ?? ''); ?>">
                <input type="hidden" name="discount_amount" value="<?php echo $discount_amount; ?>">

                <div class="payment-methods">
                    <div class="payment-method selected" onclick="selectPayment(this, 'bank_transfer')">
                        <div class="method-header">
                            <div class="radio-circle"></div>
                            <div class="method-text">Chuyển khoản ngân hàng</div>
                        </div>
                        <div class="method-details">
                            <div class="bank-info">
                                <div class="info-row">
                                    <span class="info-label">Ngân hàng:</span>
                                    <span class="info-value">Vietcombank</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Số tài khoản:</span>
                                    <span class="info-value">0123456789</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Chủ tài khoản:</span>
                                    <span class="info-value">CONG TY TMS VEXE</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Số tiền:</span>
                                    <span class="info-value amount-highlight"><?php echo number_format($total, 0, ',', '.'); ?> VNĐ</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Nội dung:</span>
                                    <span class="info-value"><?php echo $booking_code; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="payment-method" onclick="selectPayment(this, 'qr_code')">
                        <div class="method-header">
                            <div class="radio-circle"></div>
                            <div class="method-text">Quét QR Code</div>
                        </div>
                        <div class="method-details">
                            <p style="color: #6b7280; font-size: 0.9rem; text-align: center;">
                                Vui lòng quét mã QR để thanh toán
                            </p>
                        </div>
                    </div>

                    <div class="payment-method" onclick="selectPayment(this, 'counter')">
                        <div class="method-header">
                            <div class="radio-circle"></div>
                            <div class="method-text">Thanh toán tại quầy</div>
                        </div>
                        <div class="method-details">
                            <p style="color: #6b7280; font-size: 0.9rem; text-align: center;">
                                Thanh toán trực tiếp tại quầy trước khi lên xe
                            </p>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="select-seat.php?trip_id=<?php echo $trip_id; ?>" class="back-btn">
                        ← Chọn lại ghế
                    </a>

                    <button type="button" class="complete-btn" id="completeBtn" onclick="completePayment()">
                        Hoàn tất thanh toán
                    </button>
                </div>
            </form>
        </div>

        <div class="card order-summary">
            <h3 class="card-title">Thông tin vé xe</h3>

            <div class="trip-info-box">
                <div class="route-title">
                    <?php echo htmlspecialchars($trip['origin_name']); ?> →
                    <?php echo htmlspecialchars($trip['destination_name']); ?>
                </div>

                <div class="trip-detail">
                    <span class="detail-label">Khởi hành:</span>
                    <span class="detail-value"><?php echo date('H:i - d/m/Y', strtotime($trip['departure_time'])); ?></span>
                </div>

                <div class="trip-detail">
                    <span class="detail-label">Loại vé:</span>
                    <span class="detail-value text-primary">
                        <?php echo ($trip['ticket_type'] == 'round_trip') ? 'Vé khứ hồi' : 'Vé một chiều'; ?>
                    </span>
                </div>

                <?php if ($trip['ticket_type'] == 'round_trip' && !empty($trip['return_time'])): ?>
                    <div class="trip-detail">
                        <span class="detail-label">Ngày về:</span>
                        <span class="detail-value text-warning" style="color: #ea580c;">
                            <?php echo date('H:i - d/m/Y', strtotime($trip['return_time'])); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="seats-info">
                    Ghế đã chọn: <span style="color: #3b82f6;"><?php echo htmlspecialchars($seats_display); ?></span>
                </div>
            </div>

            <div class="price-breakdown">
                <div class="price-row">
                    <span class="price-label">Giá vé (<?php echo $seat_count; ?> ghế):</span>
                    <span class="price-value"><?php echo number_format($subtotal, 0, ',', '.'); ?> VNĐ</span>
                </div>

                <?php if ($discount_amount > 0): ?>
                    <div class="price-row discount-row">
                        <span class="price-label">Khuyến mãi (<?php echo htmlspecialchars($promotion_code_name); ?>):</span>
                        <span class="price-value">-<?php echo number_format($discount_amount, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                <?php endif; ?>

                <div class="price-row total-row">
                    <span class="price-label">Tổng cộng:</span>
                    <span class="price-value"><?php echo number_format($total, 0, ',', '.'); ?> VNĐ</span>
                </div>
            </div>

            <div class="passenger-box">
                <div class="section-subtitle">Thông tin hành khách:</div>
                <div class="passenger-name"><?php echo htmlspecialchars($fullname); ?></div>
                <div class="contact-detail">Email: <?php echo htmlspecialchars($email); ?></div>
                <div class="contact-detail">Điện thoại: <?php echo htmlspecialchars($phone); ?></div>
            </div>
        </div>
    </div>

    <script>
        let selectedMethod = 'bank_transfer';
        document.getElementById('selectedPaymentMethod').value = 'bank_transfer';

        function selectPayment(element, method) {
            document.querySelectorAll('.payment-method').forEach(option => {
                option.classList.remove('selected');
            });
            element.classList.add('selected');
            selectedMethod = method;
            document.getElementById('selectedPaymentMethod').value = method;
        }

        function completePayment() {
            if (!selectedMethod) {
                alert('Vui lòng chọn phương thức thanh toán');
                return;
            }

            const btn = document.getElementById('completeBtn');
            btn.textContent = 'Đang xử lý...';
            btn.disabled = true;

            // Submit form
            document.getElementById('paymentForm').submit();
        }

        // Timer countdown
        let timeLeft = 900; // 15 phút

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