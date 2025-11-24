<?php
// user_home.php
session_start();
if (!isset($_SESSION['userId'])) {
    header('Location: login.php');
    exit;
}
$fullname = $_SESSION['userFullname'] ?? 'Người dùng';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Trang chủ - TMS VéXe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body class="page-home">
  <header class="topbar">
    <div class="brand">TMS<span class="accent">VéXe</span></div>
    <nav class="nav">
      <a href="#">Trang chủ</a>
      <a href="#">Vé của tôi</a>
      <a href="#">Khuyến mãi</a>
      <a href="#">Hỗ trợ</a>
    </nav>
    <div class="user-menu">
      <span class="username"><?= htmlspecialchars($fullname) ?></span>
      <div class="dropdown">
        <a href="user_profile.php">Hồ sơ cá nhân</a>
        <a href="#">Đổi mật khẩu</a>
        <a href="#" id="logoutBtn">Đăng xuất</a>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="search-card">
      <h2>Tìm Chuyến Xe</h2>
      <div class="trip-type">
        <label><input type="radio" name="tripType" value="oneway" checked> Một chiều</label>
        <label><input type="radio" name="tripType" value="roundtrip"> Khứ hồi</label>
      </div>
      <form id="searchForm">
        <div class="row">
          <label>Nơi đi<input id="from" type="text" placeholder="Nhập điểm đi"></label>
          <label>Nơi đến<input id="to" type="text" placeholder="Nhập điểm đến"></label>
        </div>
        <div class="row">
          <label>Ngày đi<input id="date" type="date"></label>
          <label id="returnDateLabel" style="display:none;">Ngày về<input id="returnDate" type="date"></label>
        </div>
        <button class="btn primary" type="submit">Tìm chuyến xe</button>
      </form>
    </section>

    <section id="notifications" class="notify">
      <h3>Thông báo</h3>
      <ul>
        <li>Chuyến A123 đã bị hủy</li>
        <li>Hoàn tiền vé #TMS20250001</li>
      </ul>
    </section>
  </main>

  <script src="script.js"></script>
  <script>
    document.getElementById('searchForm').addEventListener('submit', function(e){
      e.preventDefault();
      // TODO: call search API (SCR-1.7). For now just alert.
      alert('Tìm chuyến (demo). TODO liên kết SCR-1.7.');
    });
    // Logout button
    document.getElementById('logoutBtn').addEventListener('click', function(e){
      e.preventDefault();
      fetch('api_user.php?action=logout').then(r=>r.json()).then(j=>{
        if (j.status === 'ok') window.location = 'login.php';
      });
    });

    // show/hide return date
    document.querySelectorAll('input[name="tripType"]').forEach(function(el){
      el.addEventListener('change', function(){
        document.getElementById('returnDateLabel').style.display = this.value === 'roundtrip' ? 'block' : 'none';
      });
    });
  </script>
</body>
</html>
