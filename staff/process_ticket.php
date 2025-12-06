<?php
// FILE: staff/process_ticket.php
session_start();
require_once '../includes/config.php';
header('Content-Type: application/json');

// 1. Kiểm tra quyền
if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập.']);
    exit;
}

// 2. Lấy dữ liệu
$bookingId = $_POST['booking_id'] ?? null;
$action    = $_POST['action'] ?? null;

if (empty($bookingId) || !in_array($action, ['confirm', 'cancel'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // --- SỬA LỖI: Chỉ cập nhật các cột CÓ THẬT trong database ---
    if ($action === 'confirm') {
        // Duyệt: Cập nhật status thành 'confirmed' và thanh toán thành 'paid'
        $sql_update = "UPDATE bookings 
                       SET status = 'confirmed', payment_status = 'paid' 
                       WHERE booking_id = :id AND status = 'pending'";
    } else {
        // Hủy: Cập nhật status thành 'cancelled'
        $sql_update = "UPDATE bookings 
                       SET status = 'cancelled' 
                       WHERE booking_id = :id AND status = 'pending'";
    }
                   
    $stmt = $pdo->prepare($sql_update);
    $stmt->execute([':id' => $bookingId]);

    // Kiểm tra xem có dòng nào được cập nhật không
    if ($stmt->rowCount() > 0) {
        
        // Nếu là thao tác HỦY (cancel), cần hoàn lại số ghế vào bảng trips
        if ($action === 'cancel') {
            // Lấy thông tin số lượng ghế và mã chuyến xe từ vé cần hủy
            $stmt_info = $pdo->prepare("SELECT quantity, trip_id FROM bookings WHERE booking_id = ?");
            $stmt_info->execute([$bookingId]);
            $ticket = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            if ($ticket) {
                // Cộng lại ghế trống cho chuyến xe
                $sql_return_seat = "UPDATE trips SET available_seats = available_seats + ? WHERE id = ?";
                $stmt_seat = $pdo->prepare($sql_return_seat);
                $stmt_seat->execute([$ticket['quantity'], $ticket['trip_id']]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => ($action === 'confirm' ? 'Đã duyệt vé thành công!' : 'Đã hủy vé thành công!')]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Vé không tìm thấy hoặc đã được xử lý rồi.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Ghi log lỗi để debug nếu cần
    error_log("SQL Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>