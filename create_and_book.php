<?php
// create_and_book.php
session_start();
$conn = new mysqli("localhost", "root", "", "tms_nhom4");
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Nhận dữ liệu từ form chuyến ảo
    $origin_id = intval($_POST['origin_id']);
    $dest_id = intval($_POST['dest_id']);
    $dep_time = $_POST['dep_time']; 
    $price = floatval($_POST['price']);
    
    // Cấu hình mặc định
    $total_seats = 45;
    $available_seats = 45;
    $status = 'scheduled';
    $ticket_type = 'one_way';

    // 2. KIỂM TRA: Đã có chuyến này trong DB chưa? (Tránh F5 tạo trùng)
    $check = $conn->query("SELECT id FROM trips WHERE departure_province_id = $origin_id AND destination_province_id = $dest_id AND departure_time = '$dep_time' LIMIT 1");

    if ($check && $check->num_rows > 0) {
        // Đã có rồi thì dùng luôn ID đó
        $row = $check->fetch_assoc();
        $new_trip_id = $row['id'];
    } else {
        // 3. INSERT VÀO DB (Biến ảo thành thật)
        $stmt = $conn->prepare("INSERT INTO trips (departure_province_id, destination_province_id, departure_time, price, available_seats, total_seats, status, ticket_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisdiiss", $origin_id, $dest_id, $dep_time, $price, $available_seats, $total_seats, $status, $ticket_type);
        
        if ($stmt->execute()) {
            $new_trip_id = $stmt->insert_id;
        } else {
            die("Lỗi: " . $stmt->error);
        }
    }

    // 4. CHUYỂN HƯỚNG SANG BOOKING
    header("Location: booking.php?trip_id=" . $new_trip_id);
    exit();
}
?>