<?php
// api.php
require_once 'config.php';
session_start();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// 1. Đăng ký (SCR-1.1)
if ($action == 'register') {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Kiểm tra tồn tại
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email đã tồn tại!']); exit;
    }

    try {
        $pdo->beginTransaction();
        // Insert User
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, email, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $hashed_pass, $email, $fullname, $phone]); // Lấy email làm username
        $user_id = $pdo->lastInsertId();

        // Insert Customer (Theo database.sql có bảng customers)
        $stmt = $pdo->prepare("INSERT INTO customers (user_id) VALUES (?)");
        $stmt->execute([$user_id]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
}

// 2. Đăng nhập (SCR-1.2)
if ($action == 'login') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $role_input = $_POST['role'] ?? 'customer';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Kiểm tra đúng Role không (trừ Admin được quyền vào tất cả)
        if ($user['role'] != 'admin' && $user['role'] != $role_input) {
            echo json_encode(['status' => 'error', 'message' => 'Tài khoản không thuộc quyền này!']); exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // Redirect URL
        $redirect = 'user_home.php'; 
        if ($user['role'] == 'staff') $redirect = 'staff_dashboard.php'; // (Placeholder)
        if ($user['role'] == 'admin') $redirect = 'admin_dashboard.php'; // (Placeholder)

        echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sai thông tin đăng nhập!']);
    }
}

// 3. Lấy danh sách Tỉnh/Thành (Cho SCR-1.4)
if ($action == 'get_provinces') {
    // Lấy dữ liệu thực từ bảng provinces trong database.sql
    $stmt = $pdo->query("SELECT id, name FROM provinces WHERE status = 'active' ORDER BY name ASC");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

// 4. Đăng xuất
if ($action == 'logout') {
    session_destroy();
    echo json_encode(['status' => 'success']);
}
?>