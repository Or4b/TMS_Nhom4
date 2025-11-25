<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Đăng ký Tài khoản</h2>
        <form onsubmit="submitAuth(event, 'register')">
            <div class="form-group">
                <label>Họ và tên</label>
                <input type="text" name="fullname" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Số điện thoại</label>
                <input type="text" name="phone" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" required>
            </div>
            
            <div id="message"></div>
            
            <button type="submit">Đăng ký</button>
            <a href="login.php" class="link">Đã có tài khoản? Đăng nhập</a>
        </form>
    </div>
    <script src="script.js"></script>
</body>
</html>