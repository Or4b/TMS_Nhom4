<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng nhập - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <style>
      .message { margin-top: 15px; padding: 10px; border-radius: 5px; display: none; text-align: center; font-size: 14px;}
      .message.error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; display: block; }
      .message.success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; display: block; }
  </style>
</head>
<body class="page-auth">
  <div class="card login-card">
    <h1 class="brand">TMS<span class="accent">VéXe</span></h1>
    <p class="subtitle">Hệ thống quản lý vé xe thông minh</p>

    <form id="loginForm" novalidate>
      <label>Tên đăng nhập hoặc email
        <input name="login" id="login" type="text" placeholder="Nhập tên đăng nhập hoặc email">
      </label>

      <label>Mật khẩu
        <div class="password-field">
          <input name="password" id="loginPassword" type="password" placeholder="Nhập mật khẩu">
          <button type="button" id="togglePwd" class="eye">👁</button>
        </div>
      </label>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
        <label class="inline" style="margin:0; font-weight:400; cursor:pointer;">
            <input type="checkbox" id="remember" name="remember" value="1"> Ghi nhớ đăng nhập
        </label>
        <a href="forgot_password.php" style="font-size:13px; color:#6b5bff; text-decoration:none;">Quên mật khẩu?</a>
      </div>

      <button id="btnLogin" type="submit" class="btn primary">Đăng nhập</button>

      <p class="muted">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
    </form>

    <div id="msg" class="message"></div>
  </div>

<script>
    // 1. Chức năng ẩn/hiện mật khẩu
    document.getElementById('togglePwd').addEventListener('click', function(){
      const pwdInput = document.getElementById('loginPassword');
      const isPwd = pwdInput.type === 'password';
      pwdInput.type = isPwd ? 'text' : 'password';
      this.textContent = isPwd ? '🙈' : '👁';
    });

    // 2. Xử lý khi nhấn Đăng nhập
    document.getElementById('loginForm').addEventListener('submit', function(e){
      e.preventDefault(); 
      
      const btn = document.getElementById('btnLogin');
      const msgDiv = document.getElementById('msg');
      const formData = new FormData(this);

      // Reset trạng thái
      btn.disabled = true;
      btn.textContent = 'Đang kiểm tra...';
      msgDiv.style.display = 'none';
      msgDiv.className = 'message'; 

      fetch('api.php?action=login', {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          btn.disabled = false;
          btn.textContent = 'Đăng nhập';

          if (data.status === 'ok') {
              msgDiv.className = 'message success';
              msgDiv.style.display = 'block';
              msgDiv.textContent = data.message;
              setTimeout(() => { window.location.href = data.data; }, 1500);
          } else {
              // HIỂN THỊ LỖI CỤ THỂ TỪ PHP (Ví dụ: "Vui lòng nhập mật khẩu")
              msgDiv.className = 'message error';
              msgDiv.style.display = 'block';
              msgDiv.textContent = data.message;
          }
      })
      .catch(error => {
          console.error(error);
          btn.disabled = false;
          btn.textContent = 'Đăng nhập';
          msgDiv.className = 'message error';
          msgDiv.textContent = 'Lỗi kết nối máy chủ!';
      });
    });
  </script>
</body>
</html>