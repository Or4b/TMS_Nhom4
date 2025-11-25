<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Đăng nhập TMS VéXe</h2>
        <form onsubmit="submitAuth(event, 'login')">
            <div class="form-group">
                <label>Vai trò:</label>
                <select name="role">
                    <option value="customer">Khách hàng</option>
                    <option value="staff">Nhân viên</option>
                    <option value="admin">Quản trị</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Email / Tên đăng nhập</label>
                <input type="text" name="login" required placeholder="Nhập email hoặc username">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" required placeholder="Nhập mật khẩu">
            </div>
            
            <div id="message"></div>
            
            <button type="submit">Đăng nhập</button>
            <div style="display:flex; justify-content:space-between; margin-top:10px;">
                <a href="register.php" class="link" style="margin:0;">Đăng ký ngay</a>
                <a href="forgot_password.php" class="link" style="margin:0;">Quên mật khẩu?</a>
            </div>
        </form>
    </div>
    <script src="script.js"></script>
</body>
</html>