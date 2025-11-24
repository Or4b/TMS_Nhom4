<!-- register.php -->
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng ký - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body class="page-auth">
  <div class="card register-card">
    <h1 class="brand">TMS<span class="accent">VéXe</span></h1>
    <p class="subtitle">Đăng ký tài khoản mới</p>

    <form id="registerForm">
      <label>Họ và tên
        <input name="fullname" id="fullname" type="text" placeholder="Họ và tên">
      </label>

      <label>Địa chỉ email
        <input name="email" id="email" type="email" placeholder="example@mail.com">
      </label>

      <label>Số điện thoại
        <input name="phone" id="phone" type="text" placeholder="0123456789">
      </label>

      <label>Mật khẩu
        <input name="password" id="password" type="password" placeholder="Mật khẩu">
      </label>

      <label>Xác nhận mật khẩu
        <input name="confirm_password" id="confirm_password" type="password" placeholder="Nhập lại mật khẩu">
      </label>

      <button id="btnRegister" type="submit" class="btn primary">Đăng ký</button>
      <p class="muted">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </form>

    <div id="msg" class="message"></div>
  </div>

  <script src="script.js"></script>
  <script>
    // Hook form submit handled in script.js
    document.getElementById('registerForm').addEventListener('submit', function(e){
      e.preventDefault();
      registerUser();
    });
  </script>
</body>
</html>
