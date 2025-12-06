<?php
// create_and_book.php
session_start();
// Kết nối database (nên dùng PDO hoặc include config chung để đồng bộ, nhưng tôi giữ nguyên style mysqli của bạn ở file này)
$conn = new mysqli("localhost", "root", "", "tms_nhom4");
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Nhận dữ liệu từ form (Search)
    $origin_id = intval($_POST['origin_id']);
    $dest_id   = intval($_POST['dest_id']);
    $dep_time  = $_POST['dep_time']; 
    $price     = floatval($_POST['price']);
    
    // [SỬA LỖI] Nhận thêm loại vé và ngày về từ POST
    $ticket_type = isset($_POST['ticket_type']) ? $_POST['ticket_type'] : 'one_way';
    $return_time = !empty($_POST['return_time']) ? $_POST['return_time'] : NULL;

    // Cấu hình mặc định
    $total_seats = 45;
    $available_seats = 45;
    $status = 'scheduled';

    // 2. KIỂM TRA: Đã có chuyến này trong DB chưa? 
    // (Cần check cả ticket_type và return_time để không bị trùng lặp sai lệch)
    $sql_check = "SELECT id FROM trips 
                  WHERE departure_province_id = $origin_id 
                  AND destination_province_id = $dest_id 
                  AND departure_time = '$dep_time' 
                  AND ticket_type = '$ticket_type' 
                  LIMIT 1";
                  
    $check = $conn->query($sql_check);

    if ($check && $check->num_rows > 0) {
        // Đã có rồi thì dùng luôn ID đó
        $row = $check->fetch_assoc();
        $new_trip_id = $row['id'];
    } else {
        // 3. INSERT VÀO DB (Biến ảo thành thật)
        // [CẬP NHẬT] Thêm return_time vào câu lệnh INSERT
        $stmt = $conn->prepare("INSERT INTO trips (departure_province_id, destination_province_id, departure_time, price, available_seats, total_seats, status, ticket_type, return_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        // "iisdiisss" tương ứng: int, int, string, double, int, int, string, string, string
        $stmt->bind_param("iisdiisss", $origin_id, $dest_id, $dep_time, $price, $available_seats, $total_seats, $status, $ticket_type, $return_time);
        
        if ($stmt->execute()) {
            $new_trip_id = $stmt->insert_id;
        } else {
            die("Lỗi tạo chuyến: " . $stmt->error);
        }
    }

    // 4. CHUYỂN HƯỚNG SANG CHỌN GHẾ
    header("Location: select-seat.php?trip_id=" . $new_trip_id);
    exit();
}
?>