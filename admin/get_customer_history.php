<?php
// Tắt báo lỗi hiển thị ra màn hình để tránh làm hỏng định dạng JSON
error_reporting(0); 
ini_set('display_errors', 0);

require_once dirname(__DIR__) . '/includes/config.php';
// Đặt header là JSON
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['customer_id'])) {
        throw new Exception('Thiếu ID khách hàng');
    }

    $customerId = $_GET['customer_id'];

    // 1. Lấy thông tin khách hàng VÀ user_id để truy vấn booking
    // SỬA: Lấy thêm user_id
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.email, u.phone, u.created_at, c.user_id 
        FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Không tìm thấy khách hàng');
    }
    
    $userId = $customer['user_id'];

    // 2. Lấy thống kê tổng quan
    // SỬA: Dùng user_id và total_price
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END), 0) as total_spent,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings
        FROM bookings 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Lấy danh sách lịch sử đặt vé chi tiết
    // SỬA: Dùng user_id, booking_id, quantity, total_price
    // MẸO: Dùng 'AS' để giữ nguyên tên trường JSON cho JavaScript đỡ phải sửa
    $stmt = $pdo->prepare("
        SELECT 
            b.booking_id AS id, 
            b.booking_date,
            b.quantity AS number_of_seats,
            b.total_price AS total_amount,
            b.status,
            b.payment_status,
            CONCAT(p1.province_name, ' → ', p2.province_name) as route
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        LEFT JOIN provinces p1 ON t.departure_province_id = p1.id
        LEFT JOIN provinces p2 ON t.destination_province_id = p2.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Định dạng lại ngày tháng cho đẹp
    foreach ($bookings as &$booking) {
        $booking['booking_date'] = date('d/m/Y H:i', strtotime($booking['booking_date']));
    }
    
    // Format lại ngày tạo khách hàng
    $customer['created_at'] = date('d/m/Y', strtotime($customer['created_at']));

    // Trả về JSON thành công
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'stats' => $stats,
        'bookings' => $bookings
    ]);

} catch (Exception $e) {
    // Trả về JSON lỗi
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>