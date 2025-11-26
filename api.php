<?php
/**
 * api.php
 * Xử lý toàn bộ logic Backend: Auth, Users, Provinces.
 * Trả về dữ liệu dạng JSON.
 */

require_once 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// --- SR-1.1: ĐĂNG KÝ ---
if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($fullname) || empty($email) || empty($phone) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin.']); exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ.']); exit;
    }
    if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Số điện thoại không hợp lệ.']); exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.']); exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']); exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email hoặc Tên đăng nhập đã tồn tại.']); exit;
    }

    try {
        $pdo->beginTransaction();
        $plain_pass = $password; // Demo: Plain text
        $sqlUser = "INSERT INTO users (username, password, email, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')";
        $stmt = $pdo->prepare($sqlUser);
        $stmt->execute([$username, $plain_pass, $email, $fullname, $phone]);
        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO customers (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        $pdo->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Đăng ký thành công! Vui lòng đăng nhập.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }
    exit;
}

// --- SR-1.2: ĐĂNG NHẬP (ĐÃ SỬA LOGIC SESSION) ---
if ($action === 'login') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập thông tin đăng nhập.']); exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && $user['password'] === $password) {
        
        // 1. Xóa session cũ để sạch sẽ
        session_regenerate_id(true);

        // 2. Lưu các biến cơ bản
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['full_name']    = $user['full_name'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['role']         = $user['role'];

        // 3. [QUAN TRỌNG] Cấp quyền cụ thể cho từng vai trò để Dashboard nhận diện
        $redirect = 'index.php'; 
        
        if ($user['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true; // Dashboard Admin cần biến này
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['is_admin'] = true;
            $redirect = 'admin/dashboard.php'; 
        }
        elseif ($user['role'] === 'staff') {
            $_SESSION['staff_logged_in'] = true; // Dashboard Staff cần biến này
            $_SESSION['staff_name'] = $user['full_name'];
            $redirect = 'staff/dashboard.php';
        }

        echo json_encode(['status' => 'ok', 'message' => 'Đăng nhập thành công.', 'data' => $redirect]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu.']);
    }
    exit;
}

// --- CÁC API KHÁC GIỮ NGUYÊN ---
if ($action === 'request_reset') {
    // (Giữ nguyên code cũ của bạn...)
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ.']); exit;
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(16));
        $expire = date('Y-m-d H:i:s', time() + 86400);
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expire = ? WHERE id = ?");
        $stmt->execute([$token, $expire, $user['id']]);
        $link = "reset_password.php?token=" . $token;
        echo json_encode(['status' => 'ok', 'message' => 'Link đặt lại mật khẩu đã tạo.', 'data' => ['reset_link' => $link]]);
    } else {
        echo json_encode(['status' => 'ok', 'message' => 'Nếu email tồn tại, link đã được gửi.']);
    }
    exit;
}

if ($action === 'reset_password') {
    // (Giữ nguyên code cũ của bạn...)
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu quá ngắn.']); exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu không khớp.']); exit;
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expire > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $new_pass = $password; 
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expire = NULL WHERE id = ?");
        $stmt->execute([$new_pass, $user['id']]);
        echo json_encode(['status' => 'ok', 'message' => 'Đổi mật khẩu thành công.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Liên kết lỗi.']);
    }
    exit;
}

if ($action === 'get_provinces') {
    // Sửa lại tên cột cho khớp với DB mới (province_name thay vì name)
    // Nhưng cẩn thận: script.js đang gọi p.name. 
    // Để an toàn, ta alias về name
    $stmt = $pdo->query("SELECT id, province_name as name FROM provinces WHERE status = 'active' ORDER BY province_name ASC");
    $data = $stmt->fetchAll();
    echo json_encode(['status' => 'ok', 'data' => $data]);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'ok', 'message' => 'Đã đăng xuất.']);
    exit;
}
?>