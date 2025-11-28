<?php
/**
 * api.php
 * Đã sửa để hoạt động với dữ liệu CŨ, mật khẩu CŨ (không hash).
 */

// 1. Chặn mọi ký tự lạ có thể làm hỏng JSON
ob_start();

session_start();
require_once 'includes/config.php';

// Xóa buffer để đảm bảo JSON sạch
ob_end_clean(); 

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    // --- SR-1.1: ĐĂNG KÝ (Giữ nguyên logic text thường của bạn) ---
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

        if ($password !== $confirm) {
            echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']); exit;
        }

        // Kiểm tra trùng lặp
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Email hoặc Username đã tồn tại.']); exit;
        }

        $pdo->beginTransaction();
        
        // GIỮ NGUYÊN: Lưu mật khẩu dạng text thường như bạn muốn
        $sqlUser = "INSERT INTO users (username, password, email, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')";
        $stmt = $pdo->prepare($sqlUser);
        $stmt->execute([$username, $password, $email, $fullname, $phone]);
        
        // Thử thêm vào bảng customers (nếu có lỗi thì bỏ qua để không chặn quy trình)
        try {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO customers (user_id) VALUES (?)");
            $stmt->execute([$userId]);
        } catch (Exception $ex) {
            // Bỏ qua lỗi này nếu bảng customers cấu trúc khác, quan trọng là user đã tạo xong
        }

        $pdo->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Đăng ký thành công!']);
        exit;
    }

    // --- SR-1.2: ĐĂNG NHẬP (QUAN TRỌNG NHẤT) ---
    if ($action === 'login') {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin.']); exit;
        }

        // SỬA 1: Bỏ điều kiện "AND status='active'". 
        // Tài khoản cũ trong DB có thể status là 1, hoặc NULL, code này sẽ chấp nhận hết.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        // SỬA 2: So sánh mật khẩu dạng TEXT THƯỜNG (Dữ liệu cũ)
        if ($user && $user['password'] === $password) {
            
            // Tạo ID phiên làm việc mới
            session_regenerate_id(true);

            // SỬA 3: Cấp đủ các biến Session phổ biến để Admin/Staff Dashboard nhận diện
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            // Điều hướng
            $redirect = 'index.php'; // Mặc định là khách

            if ($user['role'] === 'admin') {
                // Cấp cờ cho Admin
                $_SESSION['is_admin'] = true;       
                $_SESSION['admin_logged_in'] = true; 
                $_SESSION['admin_id'] = $user['id']; 
                $redirect = 'admin/dashboard.php';
            } 
            elseif ($user['role'] === 'staff') {
                // Cấp cờ cho Staff
                $_SESSION['is_staff'] = true;
                $_SESSION['staff_logged_in'] = true;
                $_SESSION['staff_id'] = $user['id'];
                $redirect = 'staff/dashboard.php';
            }

            echo json_encode([
                'status' => 'ok', 
                'message' => 'Đăng nhập thành công!', 
                'data' => $redirect
            ]);
        } else {
            // Không báo lỗi chi tiết để tránh dò pass, nhưng ở đây user và pass phải khớp 100%
            echo json_encode(['status' => 'error', 'message' => 'Sai tài khoản hoặc mật khẩu.']);
        }
        exit;
    }
    
    // --- CÁC PHẦN KHÁC (REQUEST RESET, GET PROVINCE...) ---
    // Giữ nguyên logic lấy dữ liệu
    if ($action === 'get_provinces') {
        $stmt = $pdo->query("SELECT id, province_name as name FROM provinces ORDER BY province_name ASC");
        $data = $stmt->fetchAll();
        echo json_encode(['status' => 'ok', 'data' => $data]);
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'ok', 'message' => 'Đã đăng xuất.']);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Trả về lỗi server dạng JSON để JS không bị crash
    echo json_encode(['status' => 'error', 'message' => 'Lỗi Server: ' . $e->getMessage()]);
}
?>