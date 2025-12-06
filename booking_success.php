<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_id = $_GET['booking_id'] ?? '';

if (!$booking_id) {
    header('Location: index.php');
    exit();
}

// Lấy thông tin booking
$stmt = $pdo->prepare("SELECT b.*, t.departure_time, t.ticket_type,
        po.province_name as origin_name,
        pd.province_name as destination_name
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN provinces po ON t.departure_province_id = po.id
        JOIN provinces pd ON t.destination_province_id = pd.id
        WHERE b.booking_id = ? AND b.user_id = ?");

$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: index.php');
    exit();
}

// Chuyển đổi seat_numbers (giả sử đã lưu dạng "A1,A2,A3")
$seats_display = $booking['seat_numbers'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt vé thành công - TMS VéXe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .success-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-header {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            animation: scaleIn 0.5s ease-out 0.2s both;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .success-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }

        .success-body {
            padding: 2rem;
        }

        .booking-code-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .booking-code-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .booking-code {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 2px;
        }

        .info-section {
            margin-bottom: 1.5rem;
        }

        .info-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .info-label {
            color: #666;
            font-size: 0.95rem;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
            text-align: right;
        }

        .info-value.highlight {
            color: #e74c3c;
            font-weight: bold;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #856404;
        }

        @media (max-width: 640px) {
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">✓</div>
            <h1 class="success-title">Đặt Vé Thành Công!</h1>
            <p class="success-subtitle">Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi</p>
        </div>

        <div class="success-body">
            <div class="booking-code-section">
                <div class="booking-code-label">Mã đặt vé của bạn</div>
                <div class="booking-code">BK<?php echo str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT); ?></div>
            </div>

            <div class="info-section">
                <h3 class="info-title">Thông tin chuyến đi</h3>
                <div class="info-row">
                    <span class="info-label">Tuyến đường:</span>
                    <span class="info-value">
                        <?php echo htmlspecialchars($booking['origin_name']); ?> → 
                        <?php echo htmlspecialchars($booking['destination_name']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ngày giờ khởi hành:</span>
                    <span class="info-value">
                        <?php echo date('d/m/Y - H:i', strtotime($booking['departure_time'])); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số ghế:</span>
                    <span class="info-value"><?php echo $seats_display; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Loại vé:</span>
                    <span class="info-value">
                        <?php echo $booking['ticket_type'] == 'round_trip' ? 'Khứ hồi' : 'Một chiều'; ?>
                    </span>
                </div>
            </div>

            <div class="info-section">
                <h3 class="info-title">Thanh toán</h3>
                <div class="info-row">
                    <span class="info-label">Tổng tiền:</span>
                    <span class="info-value highlight">
                        <?php echo number_format($booking['total_price'], 0, ',', '.'); ?> VNĐ
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trạng thái:</span>
                    <span class="info-value" style="color: #27ae60;">✓ Đã thanh toán</span>
                </div>
            </div>

            <div class="note">
                <strong>Lưu ý:</strong> Vui lòng lưu lại mã đặt vé và có mặt tại bến xe trước giờ khởi hành ít nhất 15 phút. 
                Thông tin chi tiết đã được gửi đến email của bạn.
            </div>

            <div class="action-buttons">
                <a href="my-tickets.php" class="btn btn-primary">Xem vé của tôi</a>
                <a href="index.php" class="btn btn-secondary">Về trang chủ</a>
            </div>
        </div>
    </div>
</body>
</html>