<?php
  require_once 'includes/config.php';

  $error = '';
  $success = '';

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
      $username = trim($_POST['username']);
      $password = trim($_POST['password']);

      if (empty($username) || empty($password)) {
          $error = 'Vui lòng nhập đầy đủ thông tin';
      } else {
          // Check users table - SỬA: dùng cột 'id' thay vì 'user_id'
          $query = "SELECT id, username, password, role FROM users WHERE username = ? AND password = ? AND status = 'active'";
          $stmt = $conn->prepare($query);
          $stmt->bind_param("ss", $username, $password);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows == 1) {
              $user = $result->fetch_assoc();
              $_SESSION['user_id'] = $user['id']; // Lưu id vào session
              $_SESSION['username'] = $user['username'];
              $_SESSION['role'] = $user['role'];
              $_SESSION['is_admin'] = ($user['role'] == 'admin'); // Kiểm tra role từ bảng users
              
              error_log("User login successful. Username: $username, Role: " . $user['role'] . ", Session: " . print_r($_SESSION, true));
              
              // Chuyển hướng theo role
              if ($user['role'] == 'admin') {
                  header("Location: admin/dashboard.php");
              } else {
                  header("Location: index.php");
              }
              exit();
          }

          $error = 'Tên đăng nhập hoặc mật khẩu không đúng hoặc tài khoản chưa được kích hoạt';
          error_log("Login failed for username: $username");
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
    <title>Đăng nhập - Đặt vé xe & tàu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-md w-full max-w-md overflow-hidden">
            <!-- Header form -->
            <div class="bg-blue-600 py-4 px-6">
                <h2 class="text-xl font-bold text-white">Đăng nhập tài khoản</h2>
            </div>
            
            <!-- Nội dung form -->
            <div class="p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['registered'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <p>Đăng ký thành công! Vui lòng đăng nhập.</p>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" class="space-y-4">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Tên đăng nhập</label>
                        <input type="text" id="username" name="username" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required autofocus>
                    </div>
                    
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Mật khẩu</label>
                        <input type="password" id="password" name="password" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="flex items-center justify-between mb-4">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                            Đăng nhập
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <p class="text-gray-600 text-sm">Chưa có tài khoản? 
                        <a href="register.php" class="text-blue-600 hover:text-blue-800 font-medium">Đăng ký ngay</a>
                    </p>
                    <!-- quên mật khẩu -->
                    <a href="forgot-password.php" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">
                        Quên mật khẩu?
                    </a>
                    <!-- quay lại trang chủ -->
                    <a href="index.php" class="text-gray-600 hover:text-gray-800 text-sm mt-2 inline-block">
                        ← Quay lại trang chủ
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>