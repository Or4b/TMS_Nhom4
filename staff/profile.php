<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    header("Location: ../login.php"); 
    exit();
}

$user_id = $_SESSION['user_id'] ?? null; // Nếu không có session user_id → null
$staff_name = $_SESSION['staff_name'] ?? 'Nhân viên'; // Nếu không có session staff_name → "Nhân viên"

$profile_data = null;
$message = '';

// 1. LOGIC LẤY THÔNG TIN HIỂN THỊ
try {
    $sql = "SELECT full_name, email, phone FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $profile_data = $stmt->fetch();
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Lỗi truy vấn thông tin: ' . $e->getMessage() . '</div>';
}

// 2. LOGIC XỬ LÝ FORM CẬP NHẬT THÔNG TIN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    if (empty($full_name) || empty($email) || empty($phone)) {
        $message = '<div class="alert alert-warning">Vui lòng điền đầy đủ thông tin.</div>';
    } else {
        try {
            $sql_update = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$full_name, $email, $phone, $user_id]);
            
            $message = '<div class="alert alert-success">Cập nhật thông tin thành công!</div>';
            $_SESSION['staff_name'] = $full_name; 
            
            header("Location: profile.php"); // Redirect để làm mới trang
            exit();

        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Lỗi cập nhật: ' . $e->getMessage() . '</div>';
        }
    }
}

// 3. LOGIC XỬ LÝ FORM ĐỔI MẬT KHẨU
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $user_pass_hash = $pdo->prepare("SELECT password FROM users WHERE id = ?")->execute([$user_id])->fetchColumn();

    if ($new_password !== $confirm_password) {
        $message = '<div class="alert alert-warning">Mật khẩu mới và xác nhận mật khẩu không khớp.</div>';
    } elseif (!password_verify($current_password, $user_pass_hash)) {
        $message = '<div class="alert alert-danger">Mật khẩu hiện tại không đúng.</div>';
    } else {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        try {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_password_hash, $user_id]);
            $message = '<div class="alert alert-success">Đổi mật khẩu thành công!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Lỗi đổi mật khẩu: ' . $e->getMessage() . '</div>';
        }
    }
}

include 'includes/header_staff.php'; 
?>

<h5 class="fw-bold mb-4 text-dark"><i class="fas fa-user-circle me-2"></i> Hồ sơ Nhân viên</h5>

<?php echo $message; // Hiển thị thông báo (nếu có) ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center">
                <i class="fas fa-user-tie fa-3x mb-2"></i>
                <h5 class="mb-0"><?php echo htmlspecialchars($profile_data['full_name'] ?? $staff_name); ?></h5>
            </div>
            <div class="card-body">
                <p><strong>Mã User ID:</strong> <?php echo htmlspecialchars($user_id ?? 'N/A'); ?></p>

                <p><strong>Email:</strong> <?php echo htmlspecialchars($profile_data['email'] ?? 'N/A'); ?></p>
                <p><strong>Số Điện Thoại:</strong> <?php echo htmlspecialchars($profile_data['phone'] ?? 'N/A'); ?></p>
                <p><strong>Ngày Vào Làm:</strong> N/A</p> 
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">Chỉnh sửa thông tin cá nhân</div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Họ và Tên:</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile_data['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Số Điện Thoại:</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($profile_data['phone'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary float-end">Lưu thay đổi</button>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">Đổi mật khẩu</div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="change_password" value="1">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mật khẩu hiện tại:</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Mật khẩu mới:</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới:</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-danger float-end">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
include 'includes/footer_staff.php'; 
?>