<?php
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin khách hàng']);
    exit();
}

$customerId = $_GET['customer_id'];

try {
    // Get customer basic info
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name, u.email, u.phone, u.created_at 
        FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng']);
        exit();
    }

    // Format customer created_at date
    $customer['created_at'] = date('d/m/Y', strtotime($customer['created_at']));

    // Get booking statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(total_amount) as total_spent
        FROM bookings 
        WHERE customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $stats = $stmt->fetch();

    // Get detailed booking history
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            CONCAT(dp.name, ' → ', dsp.name) as route,
            t.departure_time
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN provinces dp ON t.departure_province_id = dp.id
        JOIN provinces dsp ON t.destination_province_id = dsp.id
        WHERE b.customer_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$customerId]);
    $bookings = $stmt->fetchAll();

    // Format booking data
    foreach ($bookings as &$booking) {
        $booking['booking_date'] = date('d/m/Y H:i', strtotime($booking['booking_date']));
        $booking['departure_time'] = date('d/m/Y H:i', strtotime($booking['departure_time']));
    }

    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'stats' => $stats,
        'bookings' => $bookings
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}
?>