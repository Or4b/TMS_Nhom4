<!-- forgot_password.php -->
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Khôi phục mật khẩu - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body class="page-auth">
  <div class="card forgot-card">
    <h1 class="brand">TMS<span class="accent">VéXe</span></h1>
    <p class="subtitle">Khôi phục mật khẩu của bạn</p>

    <div class="steps">
      <div class="step active">1<br><small>Nhập email</small></div>
      <div class="step">2<br><small>Kiểm tra email</small></div>
      <div class="step">3<br><small>Mật khẩu mới</small></div>
    </div>

    <form id="forgotForm">
      <label>Địa chỉ email
        <input name="email" id="resetEmail" type="email" placeholder="Nhập email của bạn">
      </label>
      <button id="btnSendReset" class="btn primary">Gửi link đặt lại mật khẩu</button>
    </form>

    <div id="msg" class="message"></div>
    <p class="muted"><a href="login.php">Quay lại đăng nhập</a></p>
  </div>

  <script src="script.js"></script>
  <script>
    document.getElementById('forgotForm').addEventListener('submit', function(e){
      e.preventDefault();
      requestPasswordReset();
    });
  </script>
</body>
</html>
