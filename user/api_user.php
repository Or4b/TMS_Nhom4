<?php
// api_user.php
// Main API for user module (register, login, forgot password, reset, logout, get_user)
// Uses SQLite for simplicity and portability.
// Naming: PHP variables use $camelCase as requested.

// --- Basic settings
header('Content-Type: application/json; charset=utf-8');
session_start();
$dbFile = __DIR__ . '/data/users.db';

// Ensure data dir exists
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Create or open DB
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if not exist
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT NOT NULL,
    username TEXT,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    role TEXT DEFAULT 'customer',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reset_token TEXT,
    reset_expire INTEGER
);
");

// Helper: json response
function jsonResponse($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// Read action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// SERVER-SIDE VALIDATION FUNCTIONS
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
function isValidPhone($phone) {
    return preg_match('/^[0-9]{7,15}$/', $phone);
}
function passwordMeetsPolicy($password) {
    // Basic policy: >=8 chars
    return strlen($password) >= 8;
}

// ROUTES
if ($action === 'register') {
    // Expect POST JSON or form
    $fullname = trim($_POST['fullname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate
    if ($fullname === '' || $email === '' || $password === '' || $confirmPassword === '') {
        jsonResponse('error','Vui lòng điền đầy đủ thông tin.');
    }
    if (!isValidEmail($email)) {
        jsonResponse('error','Email không hợp lệ.');
    }
    if (!isValidPhone($phone)) {
        jsonResponse('error','Số điện thoại không hợp lệ (7-15 chữ số).');
    }
    if ($password !== $confirmPassword) {
        jsonResponse('error','Mật khẩu xác nhận không khớp.');
    }
    if (!passwordMeetsPolicy($password)) {
        jsonResponse('error','Mật khẩu phải có ít nhất 8 ký tự.');
    }

    // Check existing email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        jsonResponse('error','Email đã được đăng ký.');
    }

    // Create user
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password_hash) VALUES (:fullname, :email, :phone, :password_hash)");
    $stmt->execute([
        ':fullname' => $fullname,
        ':email' => $email,
        ':phone' => $phone,
        ':password_hash' => $passwordHash
    ]);

    jsonResponse('ok','Đăng ký thành công. Vui lòng đăng nhập.');
}

if ($action === 'login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'customer'; // from UI role selector
    if ($login === '' || $password === '') {
        jsonResponse('error','Vui lòng nhập tên đăng nhập/email và mật khẩu.');
    }

    // Find by email or username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = :login OR username = :login) LIMIT 1");
    $stmt->execute([':login' => $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        jsonResponse('error','Tài khoản không tồn tại.');
    }
    if (!password_verify($password, $user['password_hash'])) {
        jsonResponse('error','Mật khẩu sai.');
    }

    // Optionally check role (SCR requires role-aware redirect)
    if ($role === 'customer' && $user['role'] !== 'customer' && $user['role'] !== 'admin' && $user['role'] !== 'staff') {
        // allow for simplicity - but you might enforce stricter rules
    }

    // Save session
    $_SESSION['userId'] = $user['id'];
    $_SESSION['userFullname'] = $user['fullname'];
    $_SESSION['userEmail'] = $user['email'];
    $_SESSION['userRole'] = $user['role'];

    jsonResponse('ok','Đăng nhập thành công.','/user_home.php');
}

if ($action === 'logout') {
    session_destroy();
    jsonResponse('ok','Đã đăng xuất.','/login.php');
}

if ($action === 'request_reset') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!isValidEmail($email)) jsonResponse('error','Email không hợp lệ.');

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        // Do not reveal existence for security; still respond success
        jsonResponse('ok','Nếu email tồn tại trong hệ thống, chúng tôi đã gửi link đặt lại mật khẩu.');
    }

    // Generate token & expiry (24h)
    $token = bin2hex(random_bytes(16));
    $expire = time() + 24 * 3600;
    $stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expire = :expire WHERE id = :id");
    $stmt->execute([':token' => $token, ':expire' => $expire, ':id' => $user['id']]);

    // In real system: send email. Here we return the reset link for demo.
    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=$token";

    // NOTE: For production, send $resetLink to user's email. For demo we return it.
    jsonResponse('ok','Link đặt lại mật khẩu đã được gửi (demo trả link).', ['reset_link' => $resetLink]);
}

if ($action === 'reset_password') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($token === '' || $password === '' || $confirm === '') {
        jsonResponse('error','Thiếu dữ liệu.');
    }
    if ($password !== $confirm) {
        jsonResponse('error','Mật khẩu xác nhận không khớp.');
    }
    if (!passwordMeetsPolicy($password)) {
        jsonResponse('error','Mật khẩu phải có ít nhất 8 ký tự.');
    }

    $stmt = $pdo->prepare("SELECT id, reset_expire FROM users WHERE reset_token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) jsonResponse('error','Liên kết không hợp lệ hoặc đã hết hạn.');
    if ($user['reset_expire'] < time()) jsonResponse('error','Liên kết đã hết hạn.');

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, reset_token = NULL, reset_expire = NULL WHERE id = :id");
    $stmt->execute([':hash' => $passwordHash, ':id' => $user['id']]);
    jsonResponse('ok','Đổi mật khẩu thành công.');
}

if ($action === 'get_user') {
    if (!isset($_SESSION['userId'])) {
        jsonResponse('error','Chưa đăng nhập.');
    }
    $stmt = $pdo->prepare("SELECT id, fullname, email, phone, role, created_at FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['userId']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    jsonResponse('ok','User info', $user);
}

// Default
jsonResponse('error','Unknown action.');
