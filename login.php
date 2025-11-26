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
      <label>Tên đăng nhập hoặc email
        <input name="login" id="login" type="text" placeholder="Nhập tên đăng nhập hoặc email" required>
      </label>

      <label>Mật khẩu
        <div class="password-field">
          <input name="password" id="loginPassword" type="password" placeholder="Nhập mật khẩu" required>
          <button type="button" id="togglePwd" class="eye">👁</button>
        </div>
      </label>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
        <label class="inline" style="margin:0; font-weight:400; cursor:pointer;">
            <input type="checkbox" id="remember"> Ghi nhớ đăng nhập
        </label>
        <a href="forgot_password.php" style="font-size:13px; color:#6b5bff; text-decoration:none;">Quên mật khẩu?</a>
      </div>

      <button id="btnLogin" type="submit" class="btn primary">Đăng nhập</button>

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