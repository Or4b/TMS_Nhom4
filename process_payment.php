<?php
// FILE: process_payment.php
session_start();
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Nhận dữ liệu
    $user_id = $_SESSION['user_id'];
    $trip_id = $_POST['trip_id'];
    $seats_str = $_POST['seats']; // Dạng: "A-1,B-2" (lưu vào DB cần xử lý lại nếu muốn)
    $seats_display = $_POST['seats_display']; // Dạng "A1, B2" (Lưu cái này vào DB cho đẹp)
    $total_price = $_POST['total'];
    $payment_method = $_POST['payment_method']; // 'bank_transfer', 'qr_code', 'counter'
    
    // Nhận thêm thông tin khuyến mãi
    $promotion_id = !empty($_POST['promotion_id']) ? $_POST['promotion_id'] : NULL;

    // 2. XỬ LÝ TRẠNG THÁI (Logic bạn yêu cầu)
    $status = 'pending'; // Luôn là CHỜ XÁC NHẬN (Nhân viên phải duyệt)
    
    if ($payment_method === 'counter') {
        $payment_status = 'pending'; // Tại quầy -> Chưa thanh toán
    } else {
        $payment_status = 'paid';    // Online (QR/CK) -> Coi như Đã thanh toán
    }

    // Tính số lượng vé
    $quantity = count(explode(',', $seats_display));

    try {
        $pdo->beginTransaction();

        // 3. Insert vào bảng bookings
        $sql = "INSERT INTO bookings (user_id, trip_id, promotion_id, booking_date, quantity, total_price, seat_numbers, status, payment_status, payment_method) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, 
            $trip_id, 
            $promotion_id, 
            $quantity, 
            $total_price, 
            $seats_display, 
            $status, 
            $payment_status,
            $payment_method
        ]);
        
        $booking_id = $pdo->lastInsertId();

        // 4. Cập nhật số ghế trống
        $stmt_update = $pdo->prepare("UPDATE trips SET available_seats = available_seats - ? WHERE id = ?");
        $stmt_update->execute([$quantity, $trip_id]);

        $pdo->commit();

        // 5. Chuyển hướng về trang vé của tôi
        header("Location: my-tickets.php?new_booking=1");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Lỗi xử lý: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
}
?>