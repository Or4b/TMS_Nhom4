<?php
/**
 * api.php
 * Đã sửa: Bổ sung logic Reset Password từ api1.php
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
        
        // Lưu mật khẩu (Plain text theo demo)
        $sqlUser = "INSERT INTO users (username, password, email, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')";
        $stmt = $pdo->prepare($sqlUser);
        $stmt->execute([$username, $password, $email, $fullname, $phone]);
        
        try {
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO customers (user_id) VALUES (?)");
            $stmt->execute([$userId]);
        } catch (Exception $ex) {
            // Bỏ qua nếu lỗi bảng phụ
        }

        $pdo->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Đăng ký thành công!']);
        exit;
    }

// --- SR-1.2: ĐĂNG NHẬP (Đã nâng cấp thông báo chi tiết) ---
    if ($action === 'login') {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin.']); exit;
        }

        // 1. Tìm user trước (Chưa kiểm tra pass vội)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        // 2. Logic kiểm tra chi tiết
        if (!$user) {
            // Trường hợp A: Sai tài khoản
            echo json_encode(['status' => 'error', 'message' => '❌ Tài khoản hoặc email này không tồn tại.']);
            exit;
        }

        if ($user['password'] !== $password) { // Nếu đã mã hóa thì dùng: !password_verify($password, $user['password'])
            // Trường hợp B: Sai mật khẩu
            echo json_encode(['status' => 'error', 'message' => '❌ Mật khẩu không chính xác.']);
            exit;
        }

        // Trường hợp C: Đăng nhập thành công
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        $redirect = 'index.php';
        if ($user['role'] === 'admin') {
            $_SESSION['is_admin'] = true;       
            $_SESSION['admin_logged_in'] = true; 
            $_SESSION['admin_id'] = $user['id']; 
            $redirect = 'admin/dashboard.php';
        } elseif ($user['role'] === 'staff') {
            $_SESSION['is_staff'] = true;
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['staff_id'] = $user['id'];
            $redirect = 'staff/dashboard.php';
        }

        echo json_encode([
            'status' => 'ok', 
            'message' => '✅ Đăng nhập thành công! Đang chuyển hướng...', 
            'data' => $redirect
        ]);
        exit;
    }

    // --- SR-1.3: YÊU CẦU RESET PASSWORD  ---
    if ($action === 'request_reset') {
        $email = trim($_POST['email'] ?? '');
        
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
    
            // Trả về link reset (Demo mode)
            $link = "reset_password.php?token=" . $token;
            echo json_encode([
                'status' => 'ok', 
                'message' => 'Link đặt lại mật khẩu đã được tạo (Demo mode).', 
                'data' => ['reset_link' => $link]
            ]);
        } else {
            // Giả vờ thành công để bảo mật
            echo json_encode(['status' => 'ok', 'message' => 'Tài khoản hoặc email không tồn tại.']);
        }
        exit;
    }

    // --- SR-1.3: XÁC NHẬN ĐỔI MẬT KHẨU (BỔ SUNG TỪ API1.PHP) ---
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
            // Cập nhật pass (Plain text), xóa token
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expire = NULL WHERE id = ?");
            $stmt->execute([$password, $user['id']]);
            
            echo json_encode(['status' => 'ok', 'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Liên kết không hợp lệ hoặc đã hết hạn.']);
        }
        exit;
    }
    
    // --- SR-1.4: LẤY PROVINCES ---
    if ($action === 'get_provinces') {
        $stmt = $pdo->query("SELECT id, province_name as name FROM provinces ORDER BY province_name ASC");
        $data = $stmt->fetchAll();
        echo json_encode(['status' => 'ok', 'data' => $data]);
        exit;
    }

    // --- LOGOUT ---
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'ok', 'message' => 'Đã đăng xuất.']);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Lỗi Server: ' . $e->getMessage()]);
}
?>