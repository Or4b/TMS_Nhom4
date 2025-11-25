<?php
include 'config.php';

$pageTitle = "Quản lý Nhân viên";

// --- XỬ LÝ FORM (THÊM MỚI HOẶC CẬP NHẬT) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staffId = $_POST['staff_id'] ?? ''; // Lấy ID nếu đang sửa
    
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $salary = $_POST['salary'];
    
    // Username và Password chỉ lấy khi thêm mới
    $username = $_POST['username'] ?? ''; 
    $passwordRaw = $_POST['password'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($staffId) {
            // === TRƯỜNG HỢP 1: CẬP NHẬT (UPDATE) ===
            // 1. Lấy user_id từ bảng staff
            $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $currentStaff = $stmt->fetch();

            if ($currentStaff) {
                // 2. Cập nhật bảng USERS (Tên, Email, SĐT)
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $currentStaff['user_id']]);

                // 3. Cập nhật bảng STAFF (Lương)
                $stmt = $pdo->prepare("UPDATE staff SET salary = ? WHERE id = ?");
                $stmt->execute([$salary, $staffId]);

                $_SESSION['message'] = "Cập nhật thông tin nhân viên thành công!";
            }
        } else {
            // === TRƯỜNG HỢP 2: THÊM MỚI (INSERT) ===
            $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
            
            // 1. Insert vào USERS
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, phone, role) VALUES (?, ?, ?, ?, ?, 'staff')");
            $stmt->execute([$username, $passwordHash, $email, $full_name, $phone]);
            $userId = $pdo->lastInsertId();
            
            // 2. Insert vào STAFF
            $stmt = $pdo->prepare("INSERT INTO staff (user_id, salary, hire_date) VALUES (?, ?, CURDATE())");
            $stmt->execute([$userId, $salary]);

            $_SESSION['message'] = "Thêm nhân viên mới thành công!";
        }
        
        $pdo->commit();
        header("Location: manage_staff.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Lỗi xử lý: " . $e->getMessage();
    }
}

// Handle actions (Delete / Toggle Status)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $staffId = $_GET['id'];
    
    if ($_GET['action'] == 'delete') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch();
            
            if ($staff) {
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                $stmt->execute([$staffId]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$staff['user_id']]);
            }
            $pdo->commit();
            $_SESSION['message'] = "Đã xóa nhân viên thành công!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Lỗi khi xóa: " . $e->getMessage();
        }
        header("Location: manage_staff.php");
        exit();
    } elseif ($_GET['action'] == 'toggle_status') {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch();
            if ($staff) {
                $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = ?");
                $stmt->execute([$staff['user_id']]);
                $_SESSION['message'] = "Đã thay đổi trạng thái tài khoản!";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Lỗi: " . $e->getMessage();
        }
        header("Location: manage_staff.php");
        exit();
    }
}

// Get all staff
$stmt = $pdo->query("SELECT s.*, u.username, u.email, u.full_name, u.phone, u.status 
                     FROM staff s 
                     JOIN users u ON s.user_id = u.id 
                     ORDER BY s.id DESC");
$staffMembers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        /* GIỮ NGUYÊN CSS CŨ */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8f9fa; display: flex; }
        .sidebar { width: 250px; background: #2c3e50; color: white; height: 100vh; position: fixed; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #34495e; text-align: center; }
        .sidebar-menu { list-style: none; padding: 1rem 0; }
        .sidebar-menu li { padding: 0.75rem 1.5rem; }
        .sidebar-menu li.active { background: #34495e; border-left: 4px solid #3498db; }
        .sidebar-menu a { color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .main-content { margin-left: 250px; padding: 2rem; width: calc(100% - 250px); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .user-menu { display: flex; align-items: center; gap: 1rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; font-size: 0.9rem; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
        .staff-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .staff-avatar { width: 40px; height: 40px; border-radius: 50%; background: #3498db; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .status-badge { padding: 0.3rem 0.7rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .form-row { display: flex; gap: 1rem; }
        .form-row .form-group { flex: 1; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Quản Lý Nhân Viên</h1>
            <div class="user-menu">
                <span>Xin chào, <?php echo $_SESSION['full_name'] ?? 'Quản trị viên'; ?></span>
                <a href="../logout.php" class="btn btn-danger">🚪 Đăng xuất</a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <button class="btn btn-success" onclick="openAddStaffModal()" style="margin-bottom: 1rem;">+ Thêm nhân viên</button>

        <div class="staff-table">
            <table>
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Lương</th>
                        <th>Ngày vào làm</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffMembers)): ?>
                        <tr><td colspan="7" style="text-align: center;">Không có nhân viên nào</td></tr>
                    <?php else: ?>
                        <?php foreach($staffMembers as $staff): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="staff-avatar">
                                        <?php
                                        $nameParts = explode(' ', $staff['full_name']);
                                        $initials = '';
                                        foreach($nameParts as $part) $initials .= strtoupper(substr($part, 0, 1));
                                        echo substr($initials, 0, 2);
                                        ?>
                                    </div>
                                    <div>
                                        <div><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></div>
                                        <div style="font-size: 0.8rem; color: #666;">@<?php echo htmlspecialchars($staff['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                            <td><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($staff['salary'], 0, ',', '.'); ?>₫</td>
                            <td><?php echo date('d/m/Y', strtotime($staff['hire_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $staff['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $staff['status'] == 'active' ? 'Đang hoạt động' : 'Đã khóa'; ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button onclick='openEditStaffModal(<?php echo json_encode($staff); ?>)' class="btn btn-primary btn-sm">Sửa</button>

                                <?php if ($staff['status'] == 'active'): ?>
                                    <a href="manage_staff.php?action=toggle_status&id=<?php echo $staff['id']; ?>" 
                                       class="btn btn-warning btn-sm"
                                       onclick="return confirm('Khóa tài khoản này?')">Khóa</a>
                                <?php else: ?>
                                    <a href="manage_staff.php?action=toggle_status&id=<?php echo $staff['id']; ?>" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Mở khóa tài khoản này?')">Mở</a>
                                <?php endif; ?>
                                <a href="manage_staff.php?action=delete&id=<?php echo $staff['id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Xóa nhân viên này?')">Xóa</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="staffModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Thêm Nhân Viên Mới</h2>
                <button class="close-btn" onclick="closeModal('staffModal')">&times;</button>
            </div>
            <form method="POST" id="staffForm">
                <input type="hidden" name="staff_id" id="staff_id">

                <div class="form-row">
                    <div class="form-group">
                        <label>Họ và tên *</label>
                        <input type="text" name="full_name" id="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <input type="text" name="phone" id="phone">
                    </div>
                    <div class="form-group">
                        <label>Lương cơ bản *</label>
                        <input type="number" name="salary" id="salary" step="0.01" required>
                    </div>
                </div>

                <div id="accountFields">
                    <hr style="margin: 1rem 0; border: 0; border-top: 1px dashed #ddd;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tên đăng nhập *</label>
                            <input type="text" name="username" id="username">
                        </div>
                        <div class="form-group">
                            <label>Mật khẩu *</label>
                            <input type="password" name="password" id="password" value="123456">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal('staffModal')">Hủy</button>
                    <button type="submit" class="btn btn-success" id="submitBtn">Thêm Nhân viên</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mở Modal THÊM MỚI
        function openAddStaffModal() {
            document.getElementById('staffForm').reset();
            document.getElementById('staff_id').value = ''; // ID rỗng -> Thêm mới
            
            document.getElementById('modalTitle').innerText = 'Thêm Nhân Viên Mới';
            document.getElementById('submitBtn').innerText = 'Thêm Nhân Viên';
            
            // Hiển thị các trường Username/Password
            document.getElementById('accountFields').style.display = 'block';
            document.getElementById('username').required = true;
            document.getElementById('password').required = true;

            document.getElementById('staffModal').style.display = 'flex';
        }

        // Mở Modal SỬA
        function openEditStaffModal(data) {
            document.getElementById('staff_id').value = data.id; // Có ID -> Cập nhật
            
            // Điền dữ liệu cũ
            document.getElementById('full_name').value = data.full_name;
            document.getElementById('email').value = data.email;
            document.getElementById('phone').value = data.phone;
            document.getElementById('salary').value = data.salary;

            document.getElementById('modalTitle').innerText = 'Cập Nhật Thông Tin';
            document.getElementById('submitBtn').innerText = 'Lưu Thay Đổi';

            // Ẩn các trường Username/Password (Không sửa ở đây để an toàn)
            document.getElementById('accountFields').style.display = 'none';
            document.getElementById('username').required = false;
            document.getElementById('password').required = false;

            document.getElementById('staffModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('staffModal');
            if (event.target === modal) {
                closeModal('staffModal');
            }
        }
    </script>
</body>
</html>