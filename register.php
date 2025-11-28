<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng ký - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-auth">
  <div class="card register-card">
    <h1 class="brand">TMS<span class="accent">VéXe</span></h1>
    <p class="subtitle">Đăng ký tài khoản mới</p>

    <form id="registerForm">
      <label>Tên đăng nhập
        <input name="username" id="username" type="text" placeholder="Nhập tên đăng nhập (VD: user123)" required>
      </label>

      <label>Họ và tên
        <input name="fullname" id="fullname" type="text" placeholder="Họ và tên" required>
      </label>

      <label>Địa chỉ email
        <input name="email" id="email" type="email" placeholder="example@mail.com" required>
      </label>

      <label>Số điện thoại
        <input name="phone" id="phone" type="text" placeholder="0123456789" required>
      </label>

      <label>Mật khẩu
        <input name="password" id="password" type="password" placeholder="Mật khẩu (Tối thiểu 6 ký tự)" required>
      </label>

      <label>Xác nhận mật khẩu
        <input name="confirm_password" id="confirm_password" type="password" placeholder="Nhập lại mật khẩu" required>
      </label>

      <button id="btnRegister" type="submit" class="btn primary">Đăng ký</button>
      <p class="muted">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </form>

    <div id="msg" class="message" style="color: red; text-align: center; margin-top: 10px;"></div>
  </div>

  <script src="js/script.js"></script> 
  
  <script>
    document.getElementById('registerForm').addEventListener('submit', function(e){
      e.preventDefault();
      // Hàm registerUser nằm trong js/script.js
      if(typeof registerUser === 'function') {
          registerUser();
      } else {
          alert('Lỗi: Không tìm thấy file js/script.js');
      }
    });
  </script>
</body>
</html>