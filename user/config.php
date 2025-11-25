<?php
// config.php
$host = 'localhost';
$dbname = 'tms_nhom4'; // Tên DB trong file database.sql bạn gửi
$username = 'root';    // Mặc định của XAMPP
$password = '1234';        // Mặc định là rỗng

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}
?>