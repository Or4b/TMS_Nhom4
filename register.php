<?php
session_start();
require 'includes/config.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);

    // Validate required fields
    if (empty($username) || empty($password) || empty($email)) {
        $error = "Vui lòng nhập đầy đủ tên đăng nhập, mật khẩu và email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ.";
    } else {
        // Insert into database
        $query = "INSERT INTO users (username, password, email, full_name, phone) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $username, $password, $email, $full_name, $phone);
        if ($stmt->execute()) {
            $success = 'Đăng ký thành công!';
            header("Location: login.php?registered=1");
            exit();
        } else {
            $error = "Đăng ký thất bại. Tên đăng nhập có thể đã tồn tại ";
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Đặt vé xe & tàu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-md w-full max-w-md overflow-hidden">
            <!-- Header form -->
            <div class="bg-blue-600 py-4 px-6">
                <h2 class="text-xl font-bold text-white">Đăng ký tài khoản mới</h2>
            </div>
            
            <!-- Nội dung form -->
            <div class="p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <form action="register.php" method="POST">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Tên đăng nhập *</label>
                        <input type="text" id="username" name="username" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Mật khẩu *</label>
                        <input type="password" id="password" name="password" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email *</label>
                        <input type="email" id="email" name="email" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="full_name" class="block text-gray-700 text-sm font-bold mb-2">Họ và tên *</label>
                        <input type="text" id="full_name" name="full_name" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-6">
                        <label for="phone" class="block text-gray-700 text-sm font-bold mb-2">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="flex items-center justify-between mb-4">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                            Đăng ký
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <p class="text-gray-600 text-sm">Đã có tài khoản? 
                        <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium">Đăng nhập ngay</a>
                    </p>
                    <a href="index.php" class="text-gray-600 hover:text-gray-800 text-sm mt-2 inline-block">
                        ← Quay lại trang chủ
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>