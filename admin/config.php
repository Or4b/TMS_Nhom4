<?php
// config.php
session_start();

// Database configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tms_nhom4');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

// Check if user is logged in as admin
function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not logged in
if (!isAdminLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: ../login.php");
    exit();
}

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>