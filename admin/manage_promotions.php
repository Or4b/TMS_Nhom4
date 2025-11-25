<?php
include 'config.php';

$pageTitle = "Quản lý Khuyến mãi";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $promotion_code = $_POST['promotion_code'];
    $promotion_name = $_POST['promotion_name'];
    $description = $_POST['description'];
    $discount_type = $_POST['discount_type'];
    $discount_value = $_POST['discount_value'];
    $min_order_value = $_POST['min_order_value'];
    $max_discount = $_POST['max_discount'] ?: NULL; // Set to NULL if empty
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $usage_limit = $_POST['usage_limit'] ?: NULL; // Set to NULL if empty
    
    try {
        $stmt = $pdo->prepare("INSERT INTO promotions (promotion_code, promotion_name, description, discount_type, discount_value, min_order_value, max_discount, start_date, end_date, usage_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$promotion_code, $promotion_name, $description, $discount_type, $discount_value, $min_order_value, $max_discount, $start_date, $end_date, $usage_limit]);
        
        $_SESSION['message'] = "Thêm khuyến mãi thành công!";
        header("Location: manage_promotions.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Lỗi khi thêm khuyến mãi: " . $e->getMessage();
    }
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $promotionId = $_GET['id'];
    
    if ($_GET['action'] == 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
            $stmt->execute([$promotionId]);
            $_SESSION['message'] = "Đã xóa khuyến mãi thành công!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Lỗi khi xóa khuyến mãi: " . $e->getMessage();
        }
        header("Location: manage_promotions.php");
        exit();
    } elseif ($_GET['action'] == 'toggle_status') {
        try {
            $stmt = $pdo->prepare("UPDATE promotions SET status = IF(status='active', 'inactive', 'active') WHERE id = ?");
            $stmt->execute([$promotionId]);
            $_SESSION['message'] = "Đã thay đổi trạng thái khuyến mãi!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Lỗi khi thay đổi trạng thái: " . $e->getMessage();
        }
        header("Location: manage_promotions.php");
        exit();
    }
}

// Get all promotions
$stmt = $pdo->query("SELECT * FROM promotions ORDER BY created_at DESC");
$promotions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #34495e;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            padding: 0.75rem 1.5rem;
        }

        .sidebar-menu li.active {
            background: #34495e;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .promotions-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-block;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .discount-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-block;
        }

        .discount-percentage {
            background: #e8f4fd;
            color: #0c5460;
        }

        .discount-fixed {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>🎁 Quản Lý Khuyến Mãi</h1>
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

        <button class="btn btn-success" onclick="openAddPromotionModal()" style="margin-bottom: 1rem;">+ Thêm khuyến mãi</button>

        <div class="promotions-table">
            <table>
                <thead>
                    <tr>
                        <th>Mã khuyến mãi</th>
                        <th>Tên khuyến mãi</th>
                        <th>Loại giảm giá</th>
                        <th>Giá trị</th>
                        <th>Đơn tối thiểu</th>
                        <th>Thời hạn</th>
                        <th>Đã sử dụng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($promotions)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <p>Chưa có khuyến mãi nào. Hãy thêm khuyến mãi mới!</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($promotions as $promotion): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($promotion['promotion_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($promotion['promotion_name']); ?></td>
                            <td>
                                <span class="discount-badge discount-<?php echo $promotion['discount_type']; ?>">
                                    <?php echo $promotion['discount_type'] == 'percentage' ? 'Phần trăm' : 'Cố định'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($promotion['discount_type'] == 'percentage'): ?>
                                    <?php echo $promotion['discount_value']; ?>%
                                <?php else: ?>
                                    <?php echo number_format($promotion['discount_value'], 0, ',', '.'); ?>₫
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($promotion['min_order_value'], 0, ',', '.'); ?>₫</td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($promotion['start_date'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($promotion['end_date'])); ?>
                            </td>
                            <td><?php echo $promotion['used_count']; ?>/<?php echo $promotion['usage_limit'] ?? '∞'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $promotion['status']; ?>">
                                    <?php echo $promotion['status'] == 'active' ? 'Đang hoạt động' : 'Ngừng hoạt động'; ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <?php if($promotion['status'] == 'active'): ?>
                                    <a href="manage_promotions.php?action=toggle_status&id=<?php echo $promotion['id']; ?>" 
                                       class="btn btn-warning">Tạm dừng</a>
                                <?php else: ?>
                                    <a href="manage_promotions.php?action=toggle_status&id=<?php echo $promotion['id']; ?>" 
                                       class="btn btn-success">Kích hoạt</a>
                                <?php endif; ?>
                                <a href="manage_promotions.php?action=delete&id=<?php echo $promotion['id']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa khuyến mãi này?')">Xóa</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Promotion Modal -->
    <div class="modal" id="addPromotionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🎁 Thêm Khuyến Mãi Mới</h2>
                <button class="close-btn" onclick="closeModal('addPromotionModal')">&times;</button>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Mã khuyến mãi *</label>
                    <input type="text" name="promotion_code" required placeholder="VD: WELCOME2024" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Tên khuyến mãi *</label>
                    <input type="text" name="promotion_name" required placeholder="VD: Khuyến mãi chào năm mới 2024" maxlength="255">
                </div>
                <div class="form-group">
                    <label>Mô tả</label>
                    <textarea name="description" rows="2" placeholder="Mô tả về khuyến mãi..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Loại giảm giá *</label>
                        <select name="discount_type" required onchange="toggleDiscountFields()">
                            <option value="percentage">Phần trăm</option>
                            <option value="fixed">Cố định</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Giá trị giảm *</label>
                        <input type="number" name="discount_value" required min="0" step="0.01" placeholder="VD: 10 hoặc 50000">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Đơn hàng tối thiểu</label>
                        <input type="number" name="min_order_value" min="0" step="0.01" value="0" placeholder="VD: 200000">
                    </div>
                    <div class="form-group">
                        <label>Giảm tối đa</label>
                        <input type="number" name="max_discount" min="0" step="0.01" placeholder="Chỉ áp dụng cho giảm phần trăm">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Ngày bắt đầu *</label>
                        <input type="date" name="start_date" required id="start_date">
                    </div>
                    <div class="form-group">
                        <label>Ngày kết thúc *</label>
                        <input type="date" name="end_date" required id="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Giới hạn sử dụng</label>
                    <input type="number" name="usage_limit" min="1" placeholder="Để trống nếu không giới hạn">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="closeModal('addPromotionModal')">Hủy</button>
                    <button type="submit" class="btn btn-success">Thêm Khuyến mãi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddPromotionModal() {
            document.getElementById('addPromotionModal').style.display = 'flex';
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').min = today;
            document.getElementById('end_date').min = today;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function toggleDiscountFields() {
            const discountType = document.querySelector('select[name="discount_type"]').value;
            const maxDiscountField = document.querySelector('input[name="max_discount"]');
            
            if (discountType === 'percentage') {
                maxDiscountField.placeholder = "VD: 50000 (Tối đa 50,000₫ giảm)";
                maxDiscountField.disabled = false;
            } else {
                maxDiscountField.placeholder = "Không áp dụng cho giảm giá cố định";
                maxDiscountField.value = '';
                maxDiscountField.disabled = true;
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('addPromotionModal');
            if (event.target === modal) {
                closeModal('addPromotionModal');
            }
        }

        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            startDateInput.value = today;
            
            // Set end date to 30 days from today
            const endDate = new Date();
            endDate.setDate(endDate.getDate() + 30);
            endDateInput.value = endDate.toISOString().split('T')[0];

            // Initialize discount fields
            toggleDiscountFields();
        });

        // Validate end date
        document.getElementById('end_date').addEventListener('change', function() {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(this.value);
            
            if (endDate < startDate) {
                alert('Ngày kết thúc phải sau ngày bắt đầu!');
                this.value = document.getElementById('start_date').value;
            }
        });
    </script>
</body>
</html>