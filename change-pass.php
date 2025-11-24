<?php
session_start();
require 'includes/config.php'; // requre config

$error = '';
$success = '';
$valid_token = false;
$token = '';

if (isset($_GET['token'])) {
    $token = $_GET['token']; // Lấy token từ URL

    // Kiểm tra token hợp lệ và chưa sử dụng
    $query = "SELECT pr.user_id, pr.expiry, u.username 
              FROM password_resets pr 
              JOIN users u ON pr.user_id = u.user_id 
              WHERE pr.token = ? AND pr.used = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    //end truy vâns    

    if ($result->num_rows == 1) { // Nếu tìm thấy một bản ghi với token hợp lệ
        $valid_token = true;
        $reset_data = $result->fetch_assoc();
        $user_id = $reset_data['user_id']; // Lấy user_id từ kết quả truy vấn
    } else {
        $error = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token'])) { // Xử lý khi người dùng gửi form đặt lại mật khẩu
    $token = $_POST['token']; // Lấy token từ form
    $password = trim($_POST['password']); // Lấy mật khẩu mới từ form và loại bỏ khoảng trắng thừa
    $confirm_password = trim($_POST['confirm_password']); // Lấy mật khẩu xác nhận từ form và loại bỏ khoảng trắng thừa
    
    if (empty($password) || empty($confirm_password)) { // Kiểm tra xem người dùng đã nhập đầy đủ thông tin chưa
        $error = 'Vui lòng nhập đầy đủ thông tin'; 
    } elseif ($password !== $confirm_password) { // Kiểm tra xem mật khẩu và mật khẩu xác nhận có khớp không
        $error = 'Mật khẩu xác nhận không khớp';
    } else {
        // Mã hóa mật khẩu mới trước khi lưu vào database
        $query = "SELECT user_id FROM password_resets WHERE token = ? AND used = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
            //end truy vấn

             
        if ($result->num_rows == 1) { // Nếu tìm thấy một bản ghi với token hợp lệ
            $reset_data = $result->fetch_assoc();
            $user_id = $reset_data['user_id']; // Lấy user_id từ kết quả truy vấn
            
            // lưu mật khẩu mới vào database
            $query = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $password, $user_id);
               //end truy vấn

            if ($stmt->execute()) { // Nếu cập nhật mật khẩu thành công

            // Đánh dấu token là đã sử dụng
                $query = "UPDATE password_resets SET used = 1 WHERE token = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $token);
                $stmt->execute();
                //end truy vâns

                $success = 'Mật khẩu đã được cập nhật thành công!';
                header("refresh:3;url=login.php"); // Chuyển hướng đến trang đăng nhập sau 3 giây
            } else {
                $error = 'Không thể cập nhật mật khẩu. Vui lòng thử lại sau.';
            }
        } else {
            $error = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu - Đặt vé xe & tàu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-md w-full max-w-md overflow-hidden">
            <!-- Header form -->
            <div class="bg-blue-600 py-4 px-6">
                <h2 class="text-xl font-bold text-white">Đặt lại mật khẩu</h2>
            </div>
            
            <!-- Nội dung form -->
            <div class="p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <p><?php echo htmlspecialchars($success); ?></p>
                        <p class="mt-2">Bạn sẽ được chuyển hướng đến trang đăng nhập trong vài giây...</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($valid_token && !$success): ?>
                <p class="mb-4 text-gray-600">Vui lòng nhập mật khẩu mới của bạn.</p>
                
                <form action="change-pass.php" method="POST" class="space-y-4">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-4">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Mật khẩu mới</label>
                        <input type="password" id="password" name="password" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required autofocus>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Xác nhận mật khẩu mới</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="flex items-center justify-between mb-4">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                            Đặt lại mật khẩu
                        </button>
                    </div>
                </form>
                <?php elseif (!$valid_token && !$success): ?>
                <div class="text-center">
                    <p class="text-gray-600 mb-4">Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.</p>
                    <a href="forgot_password.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        Yêu cầu liên kết mới
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="text-blue-600 hover:text-blue-800 text-sm inline-block">
                        ← Quay lại đăng nhập
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
