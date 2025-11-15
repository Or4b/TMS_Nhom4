<?php
session_start();
require_once 'db_connect.php'; 

// Khối code này chạy MỘT LẦN để BĂM MẬT KHẨU an toàn nếu mật khẩu là 'password'
$test_user = $pdo->query("SELECT id, password FROM users WHERE role IN ('staff', 'admin') AND password = 'password' LIMIT 1")->fetch();
if ($test_user) {
    $hashed_pass = password_hash('password', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE role IN ('staff', 'admin')")->execute([$hashed_pass]);
}

// Đây là khối bảo vệ phiên, nó sử dụng đường dẫn an toàn mới.
if (isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true) {
    $redirect_path = 'http://localhost/THLVN1/login.php';
    header("Location: " . $redirect_path);
    exit();
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Tìm user theo username và vai trò 'staff'
    $sql = "SELECT id, password, full_name, role FROM users WHERE username = ? AND role = 'staff' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        
        if ($password === $user['password']) {

            
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['user_id'] = $user['id']; 
            $_SESSION['staff_name'] = $user['full_name'];
            
            // Chuyển hướng thành công (Đã dùng đường dẫn tuyệt đối mới)
            $redirect_path = 'http://localhost/THLVN1/staff/dashboard.php';
            header("Location: " . $redirect_path);
            exit();

        } else {
            $error_message = "Tên đăng nhập hoặc mật khẩu không đúng.";
        }
    } else {
        $error_message = "Tên đăng nhập hoặc mật khẩu không đúng.";
    }
}
?> 
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - TMS Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #2c3e50; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { max-width: 400px; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
        .logo-title { color: #2c3e50; font-weight: bold; margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h3 class="text-center logo-title">TMS Staff Login</h3>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label" for="remember_me">Ghi nhớ đăng nhập</label>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="background-color: #2c3e50; border-color: #2c3e50;">Đăng Nhập</button>
        </form>
    </div>
</body>
</html>