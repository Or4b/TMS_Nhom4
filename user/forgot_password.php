<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Khôi phục mật khẩu</h2>
        <form>
            <div class="form-group">
                <label>Nhập Email đã đăng ký</label>
                <input type="email" name="email" required>
            </div>
            <button type="button" onclick="alert('Hệ thống đã gửi link reset về email (Demo)')">Gửi link đặt lại mật khẩu</button>
            <a href="login.php" class="link">Quay lại đăng nhập</a>
        </form>
    </div>
</body>
</html>