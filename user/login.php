<!-- login.php -->
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng nhập - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body class="page-auth">
  <div class="card login-card">
    <h1 class="brand">TMS<span class="accent">VéXe</span></h1>
    <p class="subtitle">Hệ thống quản lý vé xe thông minh</p>

    <form id="loginForm">
      <div class="role-select">
        <label class="role active"><input type="radio" name="role" value="customer" checked> Khách hàng</label>
        <label class="role"><input type="radio" name="role" value="staff"> Nhân viên</label>
        <label class="role"><input type="radio" name="role" value="admin"> Quản trị</label>
      </div>

      <label>Tên đăng nhập hoặc email
        <input name="login" id="login" type="text" placeholder="Nhập tên đăng nhập hoặc email">
      </label>

      <label>Mật khẩu
        <div class="password-field">
          <input name="password" id="loginPassword" type="password" placeholder="Nhập mật khẩu">
          <button type="button" id="togglePwd" class="eye">👁</button>
        </div>
      </label>

      <label class="inline"><input type="checkbox" id="remember"> Ghi nhớ đăng nhập</label>

      <div class="actions">
        <a href="forgot_password.php" class="muted">Quên mật khẩu?</a>
        <button id="btnLogin" type="submit" class="btn primary">Đăng nhập</button>
      </div>

      <p class="muted">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
    </form>

    <div id="msg" class="message"></div>
  </div>

  <script src="script.js"></script>
  <script>
    document.getElementById('loginForm').addEventListener('submit', function(e){
      e.preventDefault();
      loginUser();
    });
    document.getElementById('togglePwd').addEventListener('click', function(){
      togglePassword('loginPassword');
    });
  </script>
</body>
</html>
