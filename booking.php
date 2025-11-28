<?php
session_start();
// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "tms_nhom4");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. NHẬN TRIP_ID TỪ URL (Thay vì schedule_id)
$trip_id = isset($_GET['trip_id']) ? intval($_GET['trip_id']) : 0;

if ($trip_id <= 0) {
    die("Mã chuyến đi không hợp lệ.");
}

// 2. LẤY THÔNG TIN TỪ BẢNG TRIPS (Thay vì routes/schedules)
$query = "SELECT t.*, 
                 o.province_name AS origin, 
                 d.province_name AS destination
          FROM trips t
          JOIN provinces o ON t.departure_province_id = o.id
          JOIN provinces d ON t.destination_province_id = d.id
          WHERE t.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();

if (!$trip) {
    die("Không tìm thấy thông tin chuyến đi.");
}

// 3. XỬ LÝ ĐẶT VÉ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $ticket_type = isset($_POST['ticket_type']) ? $_POST['ticket_type'] : 'one_way';
    
    // Kiểm tra số lượng
    if ($quantity < 1) die("Số lượng phải lớn hơn 0");
    if ($quantity > $trip['available_seats']) die("Không đủ ghế trống!");

    // Tính giá tiền
    $unit_price = $trip['price'];
    // Nếu là vé khứ hồi, nhân hệ số (ví dụ 1.8 hoặc 2 tùy chính sách, ở đây mình để x2 cho đơn giản hoặc theo logic cũ x1.8)
    if ($ticket_type == 'round_trip' || $ticket_type == 'khứ hồi') {
        $unit_price = $trip['price'] * 1.8; 
    }
    $total_price = $unit_price * $quantity;

    // Phân bổ ghế (Logic đơn giản cho Trips: lấy số ghế tiếp theo)
    // Lấy danh sách ghế đã đặt
    $booked_seats_query = "SELECT seat_numbers FROM bookings WHERE trip_id = ?";
    $stmt = $conn->prepare($booked_seats_query);
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $booked_res = $stmt->get_result();
    
    $taken_seats = [];
    while ($row = $booked_res->fetch_assoc()) {
        $seats = explode(',', $row['seat_numbers']);
        foreach($seats as $s) $taken_seats[] = intval($s);
    }

    // Tìm ghế trống
    $new_seats = [];
    for ($i = 1; $i <= $trip['total_seats']; $i++) {
        if (!in_array($i, $taken_seats)) {
            $new_seats[] = $i;
            if (count($new_seats) == $quantity) break;
        }
    }
    
    $seat_numbers_str = implode(',', $new_seats);

    // Transaction để đảm bảo toàn vẹn dữ liệu
    $conn->begin_transaction();
    try {
        // Cập nhật số ghế trống trong bảng trips
        $new_available = $trip['available_seats'] - $quantity;
        $update_trip = $conn->prepare("UPDATE trips SET available_seats = ? WHERE id = ?");
        $update_trip->bind_param("ii", $new_available, $trip_id);
        $update_trip->execute();

        // Lưu vào bảng bookings (Dùng trip_id thay vì schedule_id)
        $insert_booking = $conn->prepare("INSERT INTO bookings (user_id, trip_id, ticket_type, quantity, total_price, seat_numbers, status, payment_status, booking_date) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'chưa thanh toán', NOW())");
        
        $insert_booking->bind_param("iisids", $_SESSION['user_id'], $trip_id, $ticket_type, $quantity, $total_price, $seat_numbers_str);
        
        if ($insert_booking->execute()) {
            $conn->commit();
            header("Location: my-tickets.php?success=1");
            exit();
        } else {
            throw new Exception("Lỗi lưu booking: " . $insert_booking->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        die("Lỗi hệ thống: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt vé - BusTicket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <header class="bg-[#3d4d5c] text-white sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <a href="index.php" class="text-2xl font-bold tracking-wide">VéXe</a>
                <nav class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="hover:text-blue-300 transition">Trang chủ</a>
                    <a href="my-tickets.php" class="hover:text-blue-300 transition">Vé của tôi</a>
                </nav>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-10 max-w-4xl">
        <div class="bg-white p-8 rounded-xl shadow-lg">
            <h2 class="text-2xl font-bold text-blue-700 mb-6 border-b pb-4">Xác nhận đặt vé</h2>
            
            <div class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Thông tin chuyến đi</h3>
                    <div class="space-y-2 text-gray-600">
                        <p><span class="font-medium text-gray-800">Tuyến:</span> <?= htmlspecialchars($trip['origin']) ?> → <?= htmlspecialchars($trip['destination']) ?></p>
                        <p><span class="font-medium text-gray-800">Ngày đi:</span> <?= date('d/m/Y H:i', strtotime($trip['departure_time'])) ?></p>
                        <p><span class="font-medium text-gray-800">Ghế trống:</span> <span class="text-green-600 font-bold"><?= htmlspecialchars($trip['available_seats']) ?></span></p>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <form method="POST" id="booking-form">
                        <div class="mb-4">
                            <label class="block font-medium mb-2">Loại vé:</label>
                            <div class="flex flex-col space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="ticket_type" value="one_way" 
                                           <?= ($trip['ticket_type'] == 'one_way') ? 'checked' : '' ?> 
                                           class="form-radio text-blue-600" onchange="updateTotalPrice()">
                                    <span class="ml-2">Một chiều (<?= number_format($trip['price'], 0, ',', '.') ?>đ)</span>
                                </label>
                                
                                <?php if ($trip['ticket_type'] == 'round_trip' || !empty($trip['return_time'])): ?>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="ticket_type" value="round_trip" 
                                           <?= ($trip['ticket_type'] == 'round_trip') ? 'checked' : '' ?>
                                           class="form-radio text-blue-600" onchange="updateTotalPrice()">
                                    <span class="ml-2">Khứ hồi (<?= number_format($trip['price'] * 1.8, 0, ',', '.') ?>đ)</span>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block font-medium mb-2">Số lượng vé:</label>
                            <div class="flex items-center">
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= htmlspecialchars($trip['available_seats']) ?>" 
                                       class="form-input w-24 border rounded px-3 py-2 text-center" oninput="updateTotalPrice()">
                                <span class="ml-3 text-gray-500 text-sm">/ <?= htmlspecialchars($trip['available_seats']) ?> ghế</span>
                            </div>
                        </div>

                        <div class="border-t pt-4 mb-6">
                            <div class="flex justify-between items-center text-lg">
                                <span class="font-bold text-gray-700">Tổng cộng:</span>
                                <span id="total-price" class="font-bold text-red-600 text-xl"><?= number_format($trip['price'], 0, ',', '.') ?>đ</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 font-bold shadow-lg transition transform hover:-translate-y-0.5">
                            Xác nhận đặt vé
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="mt-6 text-center">
            <a href="search.php" class="text-blue-600 hover:underline font-medium">← Quay lại tìm kiếm</a>
        </div>
    </main>

    <script>
        const basePrice = <?= $trip['price'] ?>;
        // Giả sử khứ hồi giá gấp 1.8 lần (hoặc 2 lần tùy bạn chỉnh ở PHP trên)
        const roundTripMultiplier = 1.8; 

        function updateTotalPrice() {
            const ticketType = document.querySelector('input[name="ticket_type"]:checked').value;
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            
            let pricePerTicket = basePrice;
            if (ticketType === 'round_trip' || ticketType === 'khứ hồi') {
                pricePerTicket = basePrice * roundTripMultiplier;
            }
            
            const totalPrice = pricePerTicket * quantity;
            document.getElementById('total-price').textContent = totalPrice.toLocaleString('vi-VN') + 'đ';
        }

        updateTotalPrice();
    </script>
</body>
</html>
<?php $conn->close(); ?>