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
    // Lấy thêm username
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // 1. Kiểm tra điền đầy đủ
    if (empty($username) || empty($fullname) || empty($email) || empty($phone) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin.']); exit;
    }

    // 2. Validate dữ liệu (* Quan trọng theo yêu cầu SR-1.1)
    
    // Kiểm tra định dạng email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ.']); exit;
    }
    // Kiểm tra số điện thoại (9–11 số)
    if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Số điện thoại không hợp lệ (9-11 số).']); exit;
    }
    // Kiểm tra độ dài mật khẩu
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.']); exit;
    }
    // Kiểm tra mật khẩu nhập lại
    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']); exit;
    }

    // 3. Kiểm tra trùng lặp (Email hoặc Username)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email hoặc Tên đăng nhập đã tồn tại.']); exit;
    }

    try {
        $pdo->beginTransaction();

        // [QUAN TRỌNG] Yêu cầu Demo: KHÔNG HASH MẬT KHẨU
        // Lưu password dạng văn bản thuần (plain text)
        $plain_pass = $password; 

        $sqlUser = "INSERT INTO users (username, password, email, full_name, phone, role, status) 
                    VALUES (?, ?, ?, ?, ?, 'customer', 'active')";
        $stmt = $pdo->prepare($sqlUser);
        
        // Truyền username và password chưa mã hóa
        $stmt->execute([$username, $plain_pass, $email, $fullname, $phone]);

        $userId = $pdo->lastInsertId();

        // Tạo bảng phụ customers
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


// --- SR-1.2: ĐĂNG NHẬP ---
if ($action === 'login') {
    $login    = trim($_POST['login'] ?? ''); // Username hoặc Email
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tên đăng nhập/email và mật khẩu.']); exit;
    }

    // Tìm user theo email hoặc username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    // [QUAN TRỌNG] Kiểm tra mật khẩu dạng Plain Text (Theo yêu cầu SR-1.2 Demo)
    // Thay vì password_verify(), ta so sánh chuỗi trực tiếp
    if ($user && $user['password'] === $password) {
        
        // Lưu session
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['full_name']    = $user['full_name'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['role']         = $user['role'];

        // Điều hướng dựa trên Role (Logic SR-1.2)
        $redirect = 'index.php'; // Mặc định cho User (SR-1.4)
        
        if ($user['role'] === 'admin') {
            // NOTE: Bạn cần tạo folder admin và file dashboard.php tương ứng
            $redirect = 'admin/dashboard.php'; 
        }
        if ($user['role'] === 'staff') {
            // NOTE: Bạn cần tạo folder staff và file dashboard.php tương ứng
            $redirect = 'staff/dashboard.php';
        }

        echo json_encode(['status' => 'ok', 'message' => 'Đăng nhập thành công.', 'data' => $redirect]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu.']);
    }
    exit;
}

// --- SR-1.3: QUÊN MẬT KHẨU (Yêu cầu) ---
if ($action === 'request_reset') {
    $email = trim($_POST['email'] ?? '');
    
    // Kiểm tra định dạng
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ.']); exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Tạo token & thời gian hết hạn (24h)
        $token = bin2hex(random_bytes(16));
        $expire = date('Y-m-d H:i:s', time() + 86400);

        // Cập nhật vào DB
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expire = ? WHERE id = ?");
        $stmt->execute([$token, $expire, $user['id']]);

        // Trả về link reset
        // [DEMO]: Hiển thị link ngay trên thông báo để test, thay vì gửi email thật
        $link = "reset_password.php?token=" . $token;
        echo json_encode([
            'status' => 'ok', 
            'message' => 'Link đặt lại mật khẩu đã được tạo (Demo mode).', 
            'data' => ['reset_link' => $link]
        ]);
    } else {
        // Bảo mật: Vẫn báo thành công ảo để tránh dò email
        echo json_encode(['status' => 'ok', 'message' => 'Nếu email tồn tại, link đã được gửi.']);
    }
    exit;
}

// --- SR-1.3: ĐẶT LẠI MẬT KHẨU (Xác nhận) ---
if ($action === 'reset_password') {
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu phải từ 6 ký tự.']); exit;
    }

    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']); exit;
    }

    // Kiểm tra token hợp lệ và còn hạn
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expire > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // [QUAN TRỌNG] Không Hash mật khẩu (theo yêu cầu demo)
        $new_pass = $password; 

        // Cập nhật pass, xóa token
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expire = NULL WHERE id = ?");
        $stmt->execute([$new_pass, $user['id']]);
        
        echo json_encode(['status' => 'ok', 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Liên kết không hợp lệ hoặc đã hết hạn.']);
    }
    exit;
}

// --- SR-1.4: LẤY DANH SÁCH TỈNH ---
if ($action === 'get_provinces') {
    $stmt = $pdo->query("SELECT id, name FROM provinces WHERE status = 'active' ORDER BY name ASC");
    $data = $stmt->fetchAll();
    echo json_encode(['status' => 'ok', 'data' => $data]);
    exit;
}

// --- ĐĂNG XUẤT ---
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'ok', 'message' => 'Đã đăng xuất.']);
    exit;
}
?>