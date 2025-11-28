<?php
session_start();
require 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$temp_id = isset($_GET['temp_id']) ? $_GET['temp_id'] : '';

// HỖ TRỢ trip_id từ search-results.php
if ($schedule_id <= 0 && empty($temp_id) && isset($_GET['trip_id'])) {
    $schedule_id = intval($_GET['trip_id']);
}

// Validate input
if ($schedule_id <= 0 && empty($temp_id)) {
    die("Mã lịch trình hoặc thông tin tạm thời không hợp lệ. <br>
         <a href='index.php' class='text-blue-600 hover:underline'>← Quay lại trang tìm kiếm</a>");
}

// Lấy thông tin chuyến đi
$trip = null;
$total_available_seats = 0;
$available_carriages = 0;
$carriage_list = [];

if ($schedule_id > 0) {
    // THỬ QUERY TỪ BẢNG TRIPS TRƯỚC (từ search-results.php)
    $query = "SELECT t.*, 
                     o.province_name AS origin, 
                     d.province_name AS destination,
                     'xe buýt' as transport_type
              FROM trips t
              JOIN provinces o ON t.departure_province_id = o.id
              JOIN provinces d ON t.destination_province_id = d.id
              WHERE t.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trip = $result->fetch_assoc();

    if ($trip) {
        // Dữ liệu từ bảng trips
        $total_available_seats = $trip['available_seats'] ?? 0;
        $trip['base_price'] = $trip['price'];
        $trip['departure_time'] = $trip['departure_time'];
        $trip['is_round_trip'] = ($trip['ticket_type'] == 'round_trip') ? 1 : 0;
        $trip['total_seats'] = $trip['total_seats'] ?? 25;
    } else {
        // NẾU KHÔNG CÓ TRONG TRIPS, THỬ QUERY TỪ ROUTES + SCHEDULES (code cũ)
        $query = "SELECT r.*, s.*, o.province_name AS origin, d.province_name AS destination, 
                         s.total_carriages, s.available_seats
                  FROM routes r
                  JOIN schedules s ON r.route_id = s.route_id
                  JOIN provinces o ON r.origin_id = o.province_id
                  JOIN provinces d ON r.destination_id = d.province_id
                  WHERE s.schedule_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $trip = $result->fetch_assoc();

        if ($trip) {
            // Calculate total available seats and available carriages
            if ($trip['transport_type'] == 'xe buýt') {
                $total_available_seats = $trip['available_seats'] ?? 0;
            } else { // tàu lửa
                $query = "SELECT SUM(available_seats) AS total_available FROM train_carriages WHERE schedule_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $total_available_seats = $row['total_available'] ?? 0;

                // Count available carriages and get list of carriages with available seats
                $carriages_query = "SELECT carriage_id, carriage_number, available_seats, total_seats 
                                   FROM train_carriages 
                                   WHERE schedule_id = ? AND available_seats > 0 
                                   ORDER BY carriage_number";
                $stmt = $conn->prepare($carriages_query);
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $carriage_list = $result->fetch_all(MYSQLI_ASSOC);
                $available_carriages = count($carriage_list);
            }
        }
    }
} elseif (!empty($temp_id)) {
    if (!isset($_SESSION['random_routes'][$temp_id])) {
        die("Phiên làm việc đã hết hạn. Vui lòng tìm kiếm lại. <br>
             <a href='index.php' class='text-blue-600 hover:underline'>← Quay lại trang tìm kiếm</a>");
    }
    
    $random_route = $_SESSION['random_routes'][$temp_id];
    $trip = [
        'origin' => $conn->query("SELECT province_name FROM provinces WHERE province_id = " . $random_route['origin_id'])->fetch_assoc()['province_name'],
        'destination' => $conn->query("SELECT province_name FROM provinces WHERE province_id = " . $random_route['destination_id'])->fetch_assoc()['province_name'],
        'transport_type' => $random_route['transport_type'],
        'base_price' => $random_route['base_price'],
        'departure_time' => $random_route['departure_time'],
        'is_round_trip' => $random_route['is_round_trip']
    ];

    // Use stored values from session instead of generating new random values
    $total_available_seats = $random_route['total_available_seats'];
    $available_carriages = $random_route['available_carriages'];
    $carriage_list = $random_route['carriage_details'] ?? [];
}

if (!$trip) {
    error_log("No trip found for schedule_id: $schedule_id or temp_id: $temp_id");
    die("Không tìm thấy thông tin chuyến đi. Vui lòng kiểm tra lại hoặc liên hệ admin. <br>
         <a href='index.php' class='text-blue-600 hover:underline'>← Quay lại trang tìm kiếm</a>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ticket_type = $_POST['ticket_type'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $selected_carriage = isset($_POST['carriage']) ? intval($_POST['carriage']) : 0; // For train
    
    // Validate quantity
    if ($quantity < 1) {
        die("Số lượng vé phải lớn hơn 0.");
    }
    
    if ($quantity > $total_available_seats) {
        die("Không đủ ghế trống. Chỉ còn " . $total_available_seats . " ghế.");
    }

    // Validate carriage selection for train
    if ($trip['transport_type'] == 'tàu lửa' && $selected_carriage <= 0) {
        die("Vui lòng chọn toa hợp lệ.");
    }

    // Calculate price based on ticket type and quantity
    $base_price_per_ticket = ($ticket_type == 'khứ hồi' && $trip['is_round_trip']) ? $trip['base_price'] * 1.8 : $trip['base_price'];
    $price = $base_price_per_ticket * $quantity;

    // Assign seat numbers
    $seat_numbers = [];
    if ($schedule_id > 0) {
        $conn->begin_transaction();
        try {
            if ($trip['transport_type'] == 'xe buýt') {
                // Get booked seats for this schedule
                $booked_seats_query = "SELECT seat_numbers FROM bookings WHERE schedule_id = ? AND seat_numbers IS NOT NULL";
                $stmt = $conn->prepare($booked_seats_query);
                $stmt->bind_param("i", $schedule_id);
                $stmt->execute();
                $booked_result = $stmt->get_result();
                $booked_seats = [];
                while ($booked_row = $booked_result->fetch_assoc()) {
                    $seats = explode(',', $booked_row['seat_numbers']);
                    foreach ($seats as $seat) {
                        if (preg_match('/^(\d+)/', $seat, $matches)) {
                            $booked_seats[] = (int)$matches[1];
                        }
                    }
                }

                // Assign available seats for bus
                $total_seats = $trip['total_seats'] ?? 25;
                $assigned = 0;
                for ($i = 1; $i <= $total_seats && $assigned < $quantity; $i++) {
                    if (!in_array($i, $booked_seats)) {
                        $seat_numbers[] = (string)$i;
                        $assigned++;
                    }
                }

                if ($assigned < $quantity) {
                    throw new Exception("Không thể phân bổ đủ ghế cho xe buýt. Vui lòng thử lại.");
                }

                // Update available seats - CHECK WHICH TABLE TO UPDATE
                $new_available_seats = $total_available_seats - $assigned;
                
                // Kiểm tra xem có trong bảng trips hay schedules
                $check_trips = $conn->query("SELECT id FROM trips WHERE id = $schedule_id");
                if ($check_trips && $check_trips->num_rows > 0) {
                    // Update bảng trips
                    $update_query = "UPDATE trips SET available_seats = ? WHERE id = ?";
                } else {
                    // Update bảng schedules
                    $update_query = "UPDATE schedules SET available_seats = ? WHERE schedule_id = ?";
                }
                
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $new_available_seats, $schedule_id);
                $stmt->execute();
            } else { // tàu lửa
                // Find the selected carriage details
                $selected_carriage_data = null;
                foreach ($carriage_list as $carriage) {
                    if ($carriage['carriage_number'] == $selected_carriage) {
                        $selected_carriage_data = $carriage;
                        break;
                    }
                }

                if (!$selected_carriage_data) {
                    throw new Exception("Toa đã chọn không hợp lệ hoặc không còn ghế trống.");
                }

                if ($quantity > $selected_carriage_data['available_seats']) {
                    throw new Exception("Toa đã chọn không đủ ghế trống. Chỉ còn " . $selected_carriage_data['available_seats'] . " ghế.");
                }

                // Get booked seats for this carriage
                $booked_seats_query = "SELECT seat_numbers FROM bookings WHERE schedule_id = ? AND seat_numbers LIKE ?";
                $like_pattern = $selected_carriage_data['carriage_number'] . '-%';
                $stmt = $conn->prepare($booked_seats_query);
                $stmt->bind_param("is", $schedule_id, $like_pattern);
                $stmt->execute();
                $booked_result = $stmt->get_result();
                $booked_seats = [];
                while ($booked_row = $booked_result->fetch_assoc()) {
                    $seats = explode(',', $booked_row['seat_numbers']);
                    foreach ($seats as $seat) {
                        if (preg_match('/^' . $selected_carriage_data['carriage_number'] . '-(\d+)/', $seat, $matches)) {
                            $booked_seats[] = (int)$matches[1];
                        }
                    }
                }

                // Assign random available seats in the selected carriage
                $available_seat_numbers = [];
                for ($i = 1; $i <= $selected_carriage_data['total_seats']; $i++) {
                    if (!in_array($i, $booked_seats)) {
                        $available_seat_numbers[] = $i;
                    }
                }

                // Shuffle available seats to assign randomly
                shuffle($available_seat_numbers);
                for ($i = 0; $i < $quantity && $i < count($available_seat_numbers); $i++) {
                    $seat_numbers[] = $selected_carriage_data['carriage_number'] . '-' . $available_seat_numbers[$i];
                }

                if (count($seat_numbers) < $quantity) {
                    throw new Exception("Không thể phân bổ đủ ghế trong toa đã chọn. Vui lòng thử lại.");
                }

                // Update available seats for the selected carriage
                $new_available = $selected_carriage_data['available_seats'] - $quantity;
                $update_query = "UPDATE train_carriages SET available_seats = ? WHERE carriage_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $new_available, $selected_carriage_data['carriage_id']);
                $stmt->execute();
            }

            $seat_numbers_str = implode(',', $seat_numbers);

            // Handle database insertion
            $insert_query = "INSERT INTO bookings (user_id, schedule_id, ticket_type, quantity, total_price, seat_numbers, status, payment_status) 
                             VALUES (?, ?, ?, ?, ?, ?, 'đang chờ', 'chưa thanh toán')";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iisids", $_SESSION['user_id'], $schedule_id, $ticket_type, $quantity, $price, $seat_numbers_str);
            $stmt->execute();
            $conn->commit();

            header("Location: my-tickets.php?success=1&seats=" . urlencode($seat_numbers_str));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            die($e->getMessage());
        }
    } else {
        // For random routes, assign seats in the selected carriage
        $selected_carriage_data = null;
        foreach ($carriage_list as $carriage) {
            if ($carriage['carriage_number'] == $selected_carriage) {
                $selected_carriage_data = $carriage;
                break;
            }
        }

        if ($trip['transport_type'] == 'tàu lửa' && !$selected_carriage_data) {
            die("Toa đã chọn không hợp lệ.");
        }

        if ($trip['transport_type'] == 'xe buýt') {
            for ($i = 1; $i <= $quantity; $i++) {
                $seat_numbers[] = (string)$i;
            }
        } else { // tàu lửa
            $available_seat_numbers = [];
            for ($i = 1; $i <= $selected_carriage_data['total_seats']; $i++) {
                $available_seat_numbers[] = $i;
            }
            shuffle($available_seat_numbers);
            for ($i = 0; $i < $quantity && $i < count($available_seat_numbers); $i++) {
                $seat_numbers[] = $selected_carriage_data['carriage_number'] . '-' . $available_seat_numbers[$i];
            }
        }
        $seat_numbers_str = implode(',', $seat_numbers);

        // lưu thông tin chuyến đi ngẫu nhiên
        $insert_route = "INSERT INTO routes (origin_id, destination_id, transport_type, base_price, is_round_trip) 
                         VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_route);
        $stmt->bind_param("iisid", $random_route['origin_id'], $random_route['destination_id'], $random_route['transport_type'], $random_route['base_price'], $random_route['is_round_trip']);
        $stmt->execute();
        $route_id = $conn->insert_id;

        $insert_schedule = "INSERT INTO schedules (route_id, departure_time, total_carriages, available_seats) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_schedule);
        if ($trip['transport_type'] == 'xe buýt') {
            $total_seats = 25;
            $available_seats = $total_available_seats - $quantity;
            $total_carriages = 0;
            $stmt->bind_param("isii", $route_id, $random_route['departure_time'], $total_carriages, $available_seats); 
        } else {
            $total_carriages = $random_route['is_round_trip'] ? count($carriage_list) : 1;
            $available_seats = 0;
            $stmt->bind_param("isii", $route_id, $random_route['departure_time'], $total_carriages, $available_seats);
        }
        $stmt->execute();
        $schedule_id = $conn->insert_id;

        if ($trip['transport_type'] == 'tàu lửa') {
            foreach ($carriage_list as $carriage) {
                $insert_carriage = "INSERT INTO train_carriages (schedule_id, carriage_number, total_seats, available_seats) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_carriage);
                $total_seats = 25;
                $available_seats = $carriage['carriage_number'] == $selected_carriage ? $carriage['available_seats'] - $quantity : $carriage['available_seats'];
                $stmt->bind_param("iiii", $schedule_id, $carriage['carriage_number'], $total_seats, $available_seats);
                $stmt->execute();
            }
        }

        $insert_query = "INSERT INTO bookings (user_id, schedule_id, ticket_type, quantity, total_price, seat_numbers, status, payment_status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'đang chờ', 'chưa thanh toán')";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iisids", $_SESSION['user_id'], $schedule_id, $ticket_type, $quantity, $price, $seat_numbers_str);
        $stmt->execute();
        unset($_SESSION['random_routes'][$temp_id]);
        header("Location: my-tickets.php?success=1&seats=" . urlencode($seat_numbers_str));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt vé - Đặt vé xe & tàu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    
    <main class="container mx-auto px-4 py-10 max-w-4xl">
        <div class="bg-white p-6 rounded shadow">
            <h2 class="text-2xl font-bold text-blue-700 mb-6">Đặt vé</h2>
            
            <?php if ($trip): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-2">Thông tin chuyến đi:</h3>
                    <p><span class="font-medium">Tuyến:</span> <?= htmlspecialchars($trip['origin']) ?> → <?= htmlspecialchars($trip['destination']) ?></p>
                    <p><span class="font-medium">Ngày đi:</span> <?= date('d/m/Y H:i', strtotime($trip['departure_time'])) ?></p>
                    <p><span class="font-medium">Loại xe:</span> <?= htmlspecialchars($trip['transport_type']) ?></p>
                    <p class="text-gray-700">
                        Ghế trống: <?= htmlspecialchars($total_available_seats) ?>
                        <?php if ($trip['transport_type'] == 'tàu lửa' && $available_carriages > 0): ?>
                            (Số toa còn: <?= htmlspecialchars($available_carriages) ?>)
                        <?php endif; ?>
                    </p>
                </div>
                
                <form method="POST" id="booking-form">
                    <?php if ($trip['transport_type'] == 'tàu lửa' && $available_carriages > 0): ?>
                        <div class="mb-4">
                            <label class="block font-medium mb-2">Chọn toa:</label>
                            <select name="carriage" id="carriage" class="form-select w-32" required>
                                <option value="">Chọn toa</option>
                                <?php foreach ($carriage_list as $carriage): ?>
                                    <option value="<?= htmlspecialchars($carriage['carriage_number']) ?>">
                                        Toa <?= htmlspecialchars($carriage['carriage_number']) ?> (Còn <?= htmlspecialchars($carriage['available_seats']) ?> ghế)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label class="block font-medium mb-2">Loại vé:</label>
                        <div class="flex items-center space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="ticket_type" value="một chiều" checked class="form-radio" onchange="updateTotalPrice()">
                                <span class="ml-2">Một chiều (<?= number_format($trip['base_price'], 0, ',', '.') ?>đ/vé)</span>
                            </label>
                            <?php if ($trip['is_round_trip']): ?>
                            <label class="inline-flex items-center">
                                <input type="radio" name="ticket_type" value="khứ hồi" class="form-radio" onchange="updateTotalPrice()">
                                <span class="ml-2">Khứ hồi (<?= number_format($trip['base_price'] * 1.8, 0, ',', '.') ?>đ/vé)</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium mb-2">Số lượng vé:</label>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= htmlspecialchars($total_available_seats) ?>" class="form-input w-24" oninput="updateTotalPrice()">
                        <span class="ml-2 text-gray-600">(Tối đa: <?= htmlspecialchars($total_available_seats) ?> vé)</span>
                    </div>

                    <div class="mb-4">
                        <p><span class="font-medium">Tổng giá:</span> <span id="total-price" class="font-bold text-blue-600"><?= number_format($trip['base_price'], 0, ',', '.') ?></span>đ</p>
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700">
                        Xác nhận đặt vé
                    </button>
                </form>

                <script>
                    const basePrice = <?= $trip['base_price'] ?>;
                    const roundTripMultiplier = 1.8;

                    function updateTotalPrice() {
                        const ticketType = document.querySelector('input[name="ticket_type"]:checked').value;
                        const quantity = parseInt(document.getElementById('quantity').value) || 1;
                        const pricePerTicket = (ticketType === 'khứ hồi' && <?= $trip['is_round_trip'] ? 'true' : 'false' ?>) ? basePrice * roundTripMultiplier : basePrice;
                        const totalPrice = pricePerTicket * quantity;
                        document.getElementById('total-price').textContent = totalPrice.toLocaleString('vi-VN');
                    }

                    updateTotalPrice();
                </script>
            <?php else: ?>
                <p class="text-red-500">Không tìm thấy thông tin chuyến đi. Vui lòng kiểm tra lại hoặc liên hệ admin.</p>
            <?php endif; ?>
        </div>
        <div class="mt-6">
            <a href="index.php" class="text-blue-600 hover:underline">← Quay lại trang tìm kiếm</a>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>