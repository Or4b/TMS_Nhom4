<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = "Quản lý Khách hàng";

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $customerId = $_GET['id'];
    
    if ($_GET['action'] == 'delete') {
        try {
            // 1. Lấy user_id từ bảng customers trước
            $stmt = $pdo->prepare("SELECT user_id FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch();
            
            if ($customer) {
                $pdo->beginTransaction();
                
                // 2. SỬA LỖI: Xóa booking dựa trên user_id (không phải customer_id)
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id = ?");
                $stmt->execute([$customer['user_id']]);
                
                // 3. Xóa trong bảng customers
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->execute([$customerId]);
                
                // 4. Xóa trong bảng users
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$customer['user_id']]);
                
                $pdo->commit();
                
                $_SESSION['message'] = "Đã xóa khách hàng và tất cả dữ liệu liên quan thành công!";
            } else {
                $_SESSION['message'] = "Không tìm thấy khách hàng!";
            }
        } catch(PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['message'] = "Lỗi khi xóa khách hàng: " . $e->getMessage();
        }
        header("Location: manage_customers.php");
        exit();
    } elseif ($_GET['action'] == 'toggle_status') {
        // ... (Giữ nguyên logic toggle)
        $stmt = $pdo->prepare("SELECT user_id FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$customer['user_id']]);
        
        $_SESSION['message'] = "Đã thay đổi trạng thái khách hàng!";
        header("Location: manage_customers.php");
        exit();
    }
}

// Get all customers (Giữ nguyên)
$stmt = $pdo->query("SELECT c.*, u.username, u.email, u.full_name, u.phone, u.status as user_status 
                     FROM customers c 
                     JOIN users u ON c.user_id = u.id 
                     ORDER BY c.id DESC");
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        /* ... CSS giữ nguyên ... */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8f9fa; display: flex; }
        .sidebar { width: 250px; background: #2c3e50; color: white; height: 100vh; position: fixed; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #34495e; }
        .sidebar-menu { list-style: none; padding: 1rem 0; }
        .sidebar-menu li { padding: 0.75rem 1.5rem; }
        .sidebar-menu li.active { background: #34495e; border-left: 4px solid #3498db; }
        .sidebar-menu a { color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .main-content { margin-left: 250px; padding: 2rem; width: calc(100% - 250px); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .search-filters { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 300px; }
        .search-box input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; font-size: 0.9rem; }
        .btn-primary { background: #3498db; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .customers-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 2rem; border-radius: 8px; width: 80%; max-width: 900px; max-height: 80vh; overflow-y: auto; }
        .close { float: right; font-size: 1.5rem; font-weight: bold; cursor: pointer; }
        .history-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #3498db; }
        .stat-label { color: #666; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Quản Lý Tài Khoản Khách Hàng</h1>
            <div class="user-menu">
                <span>Xin chào, <?php echo $_SESSION['full_name'] ?? 'Quản trị viên'; ?></span>
                <a href="../logout.php" style="color: #e74c3c;">🚪 Đăng xuất</a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <div class="search-filters">
            <div class="search-box">
                <input type="text" placeholder="Tìm kiếm theo tên, email hoặc số điện thoại...">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button class="btn btn-primary">Tìm kiếm</button>
            </div>
        </div>

        <div class="customers-table">
            <table>
                <thead>
                    <tr>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Ngày đăng ký</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($customers as $customer): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($customer['full_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $customer['user_status']; ?>">
                                <?php echo $customer['user_status'] == 'active' ? 'Đang hoạt động' : 'Ngừng hoạt động'; ?>
                            </span>
                        </td>
                        <td class="action-buttons">
                            <button class="btn btn-info" onclick="showCustomerHistory(<?php echo $customer['id']; ?>)">
                                Lịch sử
                            </button>
                            <?php if($customer['user_status'] == 'active'): ?>
                                <a href="manage_customers.php?action=toggle_status&id=<?php echo $customer['id']; ?>" 
                                   class="btn btn-danger">Khóa</a>
                            <?php else: ?>
                                <a href="manage_customers.php?action=toggle_status&id=<?php echo $customer['id']; ?>" 
                                   class="btn btn-success">Mở khóa</a>
                            <?php endif; ?>
                            <a href="manage_customers.php?action=delete&id=<?php echo $customer['id']; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Bạn có chắc chắn muốn xóa khách hàng này? TẤT CẢ lịch sử đặt vé của khách hàng cũng sẽ bị xóa. Hành động này không thể hoàn tác!')">Xóa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Lịch sử Khách hàng</h2>
            <div id="customerInfo"></div>
            <div id="historyStats" class="history-stats"></div>
            <div id="bookingHistory"></div>
        </div>
    </div>

    <script>
        function searchCustomers() {
            const searchTerm = document.querySelector('.search-box input').value.toLowerCase();
            const rows = document.querySelectorAll('.customers-table tbody tr');
            
            rows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const email = row.cells[1].textContent.toLowerCase();
                const phone = row.cells[2].textContent.toLowerCase();
                
                if (name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function showCustomerHistory(customerId) {
            fetch(`get_customer_history.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCustomerHistory(data);
                    } else {
                        alert('Lỗi khi tải lịch sử khách hàng');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Lỗi khi tải lịch sử khách hàng');
                });
        }

        function displayCustomerHistory(data) {
            const modal = document.getElementById('historyModal');
            const customerInfo = document.getElementById('customerInfo');
            const historyStats = document.getElementById('historyStats');
            const bookingHistory = document.getElementById('bookingHistory');

            // Customer info
            customerInfo.innerHTML = `
                <h3>${data.customer.full_name}</h3>
                <p>Email: ${data.customer.email} | SĐT: ${data.customer.phone}</p>
                <p>Ngày đăng ký: ${data.customer.created_at}</p>
            `;

            // Statistics
            historyStats.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${data.stats.total_bookings || 0}</div>
                    <div class="stat-label">Tổng số vé</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${new Intl.NumberFormat('vi-VN').format(data.stats.total_spent || 0)}₫</div>
                    <div class="stat-label">Tổng chi tiêu</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${data.stats.completed_bookings || 0}</div>
                    <div class="stat-label">Vé đã hoàn thành</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${data.stats.cancelled_bookings || 0}</div>
                    <div class="stat-label">Vé đã hủy</div>
                </div>
            `;

            // Booking history
            if (data.bookings.length > 0) {
                let bookingsHtml = `
                    <h3>Lịch sử đặt vé</h3>
                    <table style="width: 100%; margin-top: 1rem;">
                        <thead>
                            <tr>
                                <th>Mã vé</th>
                                <th>Tuyến đường</th>
                                <th>Ngày đặt</th>
                                <th>Số ghế</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thanh toán</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.bookings.forEach(booking => {
                    bookingsHtml += `
                        <tr>
                            <td>#${booking.id}</td>
                            <td>${booking.route}</td>
                            <td>${booking.booking_date}</td>
                            <td>${booking.number_of_seats}</td>
                            <td>${new Intl.NumberFormat('vi-VN').format(booking.total_amount)}₫</td>
                            <td>
                                <span class="status-badge status-${booking.status === 'completed' ? 'active' : 'inactive'}">
                                    ${getStatusText(booking.status)}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-${booking.payment_status === 'paid' ? 'active' : 'inactive'}">
                                    ${getPaymentStatusText(booking.payment_status)}
                                </span>
                            </td>
                        </tr>
                    `;
                });
                
                bookingsHtml += '</tbody></table>';
                bookingHistory.innerHTML = bookingsHtml;
            } else {
                bookingHistory.innerHTML = '<p>Khách hàng chưa có lịch sử đặt vé.</p>';
            }

            modal.style.display = 'block';
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'Chờ xác nhận',
                'confirmed': 'Đã xác nhận',
                'completed': 'Hoàn thành',
                'cancelled': 'Đã hủy'
            };
            return statusMap[status] || status;
        }

        function getPaymentStatusText(paymentStatus) {
            const paymentMap = {
                'pending': 'Chờ thanh toán',
                'paid': 'Đã thanh toán',
                'failed': 'Thất bại'
            };
            return paymentMap[paymentStatus] || paymentStatus;
        }

        function closeModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('historyModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.querySelector('.search-box input').addEventListener('keyup', searchCustomers);
        document.querySelector('.btn-primary').addEventListener('click', searchCustomers);
    </script>
</body>
</html>