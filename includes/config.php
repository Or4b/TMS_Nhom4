<?php
  // Start session only if not already started
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
      error_log("Session started in config.php. Session ID: " . session_id());
  }

  // Database connection
  $conn = new mysqli("localhost", "root", "", "webdatxe");
  $conn->set_charset("utf8mb4");

  if ($conn->connect_error) {
      die("Kết nối database thất bại: " . $conn->connect_error);
  }

  // Define constants
  if (!defined('BASE_URL')) {
      define('BASE_URL', 'http://localhost/webdatxe/');
  }
  if (!defined('ADMIN_URL')) {
      define('ADMIN_URL', BASE_URL . 'admin/');
  }
  ?>