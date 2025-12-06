<?php
// logout.php
session_start();
session_unset();    // Xóa hết biến session
session_destroy();  // Hủy session trên server
header("Location: /TMS_V1/login.php"); // Quay về trang đăng nhập
exit();
?>