<?php
include 'includes/header.php';
if (session_status() == PHP_SESSION_NONE) session_start();

try {
    $pdo = new PDO("mysql:host=localhost;dbname=webdatxe", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8mb4");
} catch (PDOException $e) {
    die("Lỗi kết nối: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$name = $user['full_name'] ?: $user['username'];
$words = explode(' ', trim($name));
$initials = (count($words) >= 2)
    ? mb_strtoupper(mb_substr(end($words), 0, 1)) . mb_strtoupper(mb_substr($words[0], 0, 1))
    : mb_strtoupper(mb_substr($name, 0, 2));

// Lấy tab hiện tại từ URL
$current_tab = $_GET['tab'] ?? 'info';

// Cập nhật thông tin
if ($_POST && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $address   = trim($_POST['address']);
    $gender    = $_POST['gender'] ?? '';

    if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $error = "Số điện thoại phải có 10-11 số!";
    } else {
        $pdo->prepare("UPDATE users SET full_name=?, phone=?, address=?, gender=? WHERE user_id=?")
            ->execute([$full_name, $phone, $address, $gender, $user_id]);
        $success = "Cập nhật thông tin thành công!";
        $user['full_name'] = $full_name; $user['phone'] = $phone;
        $user['address'] = $address; $user['gender'] = $gender;
        $name = $full_name;
        $current_tab = 'info';
    }
}

// Đổi mật khẩu
if ($_POST && isset($_POST['change_password'])) {
    $cur = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($cur, $user['password'])) {
        $pass_error = "Mật khẩu hiện tại không đúng!";
    } elseif ($new !== $confirm) {
        $pass_error = "Xác nhận mật khẩu không khớp!";
    } elseif (strlen($new) < 8) {
        $pass_error = "Mật khẩu mới phải ít nhất 8 ký tự!";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([$hash, $user_id]);
        $pass_success = "Đổi mật khẩu thành công!";
    }
    $current_tab = 'password';
}
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f5f7fa;
}

.profile-page {
    background: #f5f7fa;
    min-height: calc(100vh - 80px);
    padding: 40px 20px;
}

.profile-container {
    max-width: 1400px;
    margin: 0 auto;
}

.profile-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 30px;
}

.profile-left-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.profile-header {
    padding: 40px 30px 30px;
    text-align: center;
}

.profile-avatar {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: bold;
    margin: 0 auto 20px;
}

.profile-name {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.profile-email {
    font-size: 14px;
    color: #6b7280;
}

.profile-stats {
    border-top: 1px solid #e5e7eb;
    padding: 24px 30px;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    font-size: 15px;
}

.stat-row:last-child {
    margin-bottom: 0;
}

.stat-label {
    color: #6b7280;
}

.stat-value {
    font-weight: 600;
    color: #111827;
}

.stat-value.orange {
    color: #f97316;
}

.password-change-section {
    border-top: 1px solid #e5e7eb;
    padding: 30px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
}

.profile-right-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.tab-nav {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    padding: 0 32px;
}

.tab-link {
    padding: 20px 24px;
    font-size: 16px;
    font-weight: 600;
    color: #6b7280;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -1px;
    transition: all 0.2s;
}

.tab-link:hover {
    color: #3b82f6;
}

.tab-link.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.tab-content {
    padding: 40px;
}

.form-title {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 32px;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-input, .form-select {
    width: 100%;
    padding: 12px 16px;
    font-size: 15px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    outline: none;
    transition: all 0.2s;
    font-family: inherit;
}

.form-input:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input::placeholder {
    color: #9ca3af;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    font-size: 16px;
    font-weight: 600;
    padding: 14px 32px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
}

.alert-success {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.password-requirements {
    background: #f9fafb;
    padding: 12px 14px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.requirement-item {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
    font-size: 12px;
    color: #4b5563;
}

.requirement-item:last-child {
    margin-bottom: 0;
}

.check-icon {
    color: #10b981;
    font-weight: bold;
    margin-right: 8px;
    font-size: 14px;
}

/* Password form trong left card */
.password-form-group {
    margin-bottom: 20px;
}

.password-form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.password-form-input {
    width: 100%;
    padding: 10px 12px;
    font-size: 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    outline: none;
    transition: all 0.2s;
    font-family: inherit;
}

.password-form-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.password-alert {
    font-size: 13px;
    padding: 10px 12px;
    margin-bottom: 16px;
}

.btn-submit-password {
    width: 100%;
    padding: 12px;
    font-size: 15px;
}

@media (max-width: 1024px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="profile-page">
    <div class="profile-container">
        
        <?php if ($current_tab === 'info'): ?>
        <!-- TAB THÔNG TIN CÁ NHÂN -->
        <div class="profile-grid">
            <!-- Cột trái -->
            <div class="profile-left-card">
                <div class="profile-header">
                    <div class="profile-avatar"><?= $initials ?></div>
                    <h2 class="profile-name"><?= htmlspecialchars($name) ?></h2>
                    <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
                </div>

                <div class="profile-stats">
                    <div class="stat-row">
                        <span class="stat-label">Tổng chuyến đi:</span>
                        <span class="stat-value">12</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Vé đang chờ:</span>
                        <span class="stat-value orange">2</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Thành viên từ:</span>
                        <span class="stat-value">15/08/2025</span>
                    </div>
                </div>
            </div>

            <!-- Cột phải -->
            <div class="profile-right-card">
                <div class="tab-nav">
                    <a href="?tab=info" class="tab-link active">Thông tin cá nhân</a>
                    <a href="?tab=password" class="tab-link">Đổi mật khẩu</a>
                </div>

                <div class="tab-content">
                    <h3 class="form-title">Thông tin cá nhân</h3>

                    <?php if(isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <?php if(isset($error)): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Họ và tên</label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Số điện thoại</label>
                                <input type="text" name="phone" class="form-input"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Địa chỉ</label>
                            <input type="text" name="address" class="form-input"
                                placeholder="123 Nguyễn Huệ, Quận 1, TP.HCM"
                                value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                        </div>

                        <div class="form-group" style="max-width: 400px;">
                            <label class="form-label">Giới tính</label>
                            <select name="gender" class="form-select">
                                <option value="">-- Chọn giới tính --</option>
                                <option value="Nam" <?= ($user['gender']??'')==='Nam'?'selected':'' ?>>Nam</option>
                                <option value="Nữ" <?= ($user['gender']??'')==='Nữ'?'selected':'' ?>>Nữ</option>
                                <option value="Khác" <?= ($user['gender']??'')==='Khác'?'selected':'' ?>>Khác</option>
                            </select>
                        </div>

                        <div style="margin-top: 32px;">
                            <button type="submit" class="btn-primary">Cập nhật thông tin</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- TAB ĐỔI MẬT KHẨU -->
        <div class="profile-grid">
            <!-- Cột trái -->
            <div class="profile-left-card">
                <div class="profile-header">
                    <div class="profile-avatar"><?= $initials ?></div>
                    <h2 class="profile-name"><?= htmlspecialchars($name) ?></h2>
                    <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
                </div>

                <div class="profile-stats">
                    <div class="stat-row">
                        <span class="stat-label">Tổng chuyến đi:</span>
                        <span class="stat-value">12</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Vé đang chờ:</span>
                        <span class="stat-value orange">2</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Thành viên từ:</span>
                        <span class="stat-value">15/08/2025</span>
                    </div>
                </div>

                <!-- Form Đổi Mật Khẩu -->
                <div class="password-change-section">
                    <h3 class="section-title">Đổi Mật Khẩu</h3>

                    <?php if(isset($pass_error)): ?>
                    <div class="alert alert-error password-alert"><?= $pass_error ?></div>
                    <?php endif; ?>
                    
                    <?php if(isset($pass_success)): ?>
                    <div class="alert alert-success password-alert"><?= $pass_success ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="change_password" value="1">

                        <div class="password-form-group">
                            <label class="password-form-label">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="password-form-input"
                                placeholder="Nhập mật khẩu hiện tại" required>
                        </div>

                        <div class="password-form-group">
                            <label class="password-form-label">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="password-form-input"
                                placeholder="Nhập mật khẩu mới" required>
                        </div>

                        <div class="password-requirements">
                            <div class="requirement-item">
                                <span class="check-icon">✓</span>
                                Ít nhất 8 ký tự
                            </div>
                            <div class="requirement-item">
                                <span class="check-icon">✓</span>
                                Chữ hoa và chữ thường
                            </div>
                            <div class="requirement-item">
                                <span class="check-icon">✓</span>
                                Ít nhất 1 số
                            </div>
                            <div class="requirement-item">
                                <span class="check-icon">✓</span>
                                Ít nhất 1 ký tự đặc biệt
                            </div>
                        </div>

                        <div class="password-form-group">
                            <label class="password-form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="password-form-input"
                                placeholder="Nhập lại mật khẩu mới" required>
                        </div>

                        <button type="submit" class="btn-primary btn-submit-password">Đổi mật khẩu</button>
                    </form>
                </div>
            </div>

            <!-- Cột phải -->
            <div class="profile-right-card">
                <div class="tab-nav">
                    <a href="?tab=info" class="tab-link">Thông tin cá nhân</a>
                    <a href="?tab=password" class="tab-link active">Đổi mật khẩu</a>
                </div>
                
                <div class="tab-content">
                    <a href="?tab=info" class="btn-primary">Cập nhật thông tin</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>