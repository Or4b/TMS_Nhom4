<?php
// reset_password.php
$token = $_GET['token'] ?? '';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đặt lại mật khẩu - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body class="page-auth">
  <div class="card reset-card">
    <h1 class="brand">TMS<span class="accent">VéXe</span></h1>
    <p class="subtitle">Đặt mật khẩu mới</p>

    <form id="resetForm">
      <input type="hidden" id="token" value="<?= htmlspecialchars($token) ?>">
      <label>Mật khẩu mới
        <input id="newPassword" type="password" placeholder="Mật khẩu mới">
      </label>
      <label>Xác nhận mật khẩu
        <input id="confirmPassword" type="password" placeholder="Nhập lại mật khẩu">
      </label>

      <button class="btn primary" type="submit">Cập nhật mật khẩu</button>
    </form>

    <div id="msg" class="message"></div>
    <p class="muted"><a href="login.php">Quay lại đăng nhập</a></p>
  </div>

  <script src="script.js"></script>
  <script>
    document.getElementById('resetForm').addEventListener('submit', function(e){
      e.preventDefault();
      resetPassword();
    });
  </script>
</body>
</html>
