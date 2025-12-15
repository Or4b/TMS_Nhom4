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

    // --- 1. VALIDATE HỌ TÊN ---
    if (empty($fullname)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập họ tên.']); exit;
    }

    // --- 2. VALIDATE USERNAME (Rỗng -> Định dạng) ---
    if (empty($username)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tên đăng nhập.']); exit;
    }
    // Sau khi chắc chắn có nhập mới check ký tự đặc biệt
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode(['status' => 'error', 'message' => 'Username không được chứa ký tự đặc biệt.']); exit;
    }

    // --- 3. VALIDATE EMAIL (Rỗng -> Định dạng) ---
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập email.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Email không hợp lệ.']); exit;
    }

    // --- 4. VALIDATE SỐ ĐIỆN THOẠI (Rỗng -> Định dạng) ---
    if (empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập số điện thoại.']); exit;
    }
    if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Số điện thoại không hợp lệ (cần 9-11 số).']); exit;
    }

    // --- 5. VALIDATE MẬT KHẨU (Rỗng -> Độ dài -> Khớp lệnh) ---
    if (empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập mật khẩu.']); exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password phải ít nhất có 6 kí tự.']); exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']); exit;
    }

    // --- 6. KIỂM TRA TRÙNG LẶP DATABASE ---
    // Kiểm tra Username tồn tại
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Username đã tồn tại.']); exit;
    }

    // Kiểm tra Email tồn tại
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email đã được sử dụng.']); exit;
    }

    // --- 7. XỬ LÝ LƯU VÀO DB ---
    try {
        $pdo->beginTransaction();

        $plain_pass = $password; // Demo: Không hash

        $sqlUser = "INSERT INTO users (username, password, email, full_name, phone, role, status) 
                    VALUES (?, ?, ?, ?, ?, 'customer', 'active')";
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

// --- SR-1.2: ĐĂNG NHẬP (Đã nâng cấp thông báo chi tiết) ---
    // --- SR-1.2: ĐĂNG NHẬP ---
if ($action === 'login') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. Kiểm tra rỗng (Tách riêng thông báo)
    if (empty($login)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tên tài khoản.']); exit;
    }

    if (empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập mật khẩu.']); exit;
    }

    // 2. Validate định dạng Username (Tránh check nhầm Email)
    // Logic: Nếu chuỗi KHÔNG chứa ký tự '@' -> Coi là Username -> Check ký tự đặc biệt
    if (strpos($login, '@') === false) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
            echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập không chứa ký tự đặc biệt.']); exit;
        }
    }

    // 3. Tìm user trong Database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    // 4. Kiểm tra sự tồn tại của tài khoản
    if (!$user) {
        // Đây là thông báo "Tên đăng nhập không hợp lệ" như bạn yêu cầu
        echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập không hợp lệ (Tài khoản không tồn tại).']);
        exit;
    }

    // 5. Kiểm tra mật khẩu
    // Lưu ý: Vẫn đang dùng so sánh chuỗi thường (plain text) theo code Đăng ký trước đó
    if ($user['password'] !== $password) { 
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu không chính xác.']); 
        exit;
    }
    
    // Kiểm tra trạng thái tài khoản (Optional - nên có)
    if ($user['status'] !== 'active') {
        echo json_encode(['status' => 'error', 'message' => 'Tài khoản của bạn đang bị khóa.']); 
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