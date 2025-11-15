<?php
// login.php - File đơn giản để test login
session_start();

// Kiểm tra nếu đã đăng nhập thì chuyển hướng
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION['role'] == 'staff') {
        header("Location: staff/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        // Kết nối database - sử dụng cùng config với admin
        require_once 'admin/config.php';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Kiểm tra trạng thái tài khoản
            if ($user['status'] != 'active') {
                $error = "Tài khoản của bạn đã bị khóa! Vui lòng liên hệ quản trị viên.";
            } else {
                // Kiểm tra mật khẩu - ưu tiên password_verify trước
                if (password_verify($password, $user['password'])) {
                    // Đăng nhập thành công
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Chuyển hướng theo role
                    if ($user['role'] == 'admin') {
                        header("Location: admin/dashboard.php");
                    } elseif ($user['role'] == 'staff') {
                        header("Location: staff/dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                } 
                // Fallback: kiểm tra mật khẩu plain text (cho các tài khoản mẫu)
                elseif ($user['password'] === $password) {
                    // Đăng nhập thành công
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Chuyển hướng theo role
                    if ($user['role'] == 'admin') {
                        header("Location: admin/dashboard.php");
                    } elseif ($user['role'] == 'staff') {
                        header("Location: staff/dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                } else {
                    $error = "Sai mật khẩu!";
                }
            }
        } else {
            $error = "Tài khoản không tồn tại!";
        }
    } catch (PDOException $e) {
        $error = "Lỗi kết nối database: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Lỗi hệ thống: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            text-align: center;
            padding: 2rem;
        }
        .test-accounts {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="card-header">
                        <h4 class="mb-0">🚌 Đăng nhập Hệ thống</h4>
                        <p class="mb-0 mt-2">Quản lý Vé Xe Khách</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="loginForm">
                            <div class="mb-3">
                                <label class="form-label">👤 Tên đăng nhập</label>
                                <input type="text" class="form-control" name="username" placeholder="Nhập tên đăng nhập" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">🔒 Mật khẩu</label>
                                <input type="password" class="form-control" name="password" placeholder="Nhập mật khẩu" required>
                            </div>
                            <button type="submit" class="btn btn-login text-white w-100 mb-3">Đăng nhập</button>
                        </form>
                        
                        <div class="test-accounts">
                            <h6 class="text-center mb-3">💡 Tài khoản thử nghiệm:</h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="d-block fw-bold text-primary">Admin</small>
                                    <small class="d-block">admin</small>
                                    <small class="d-block">password</small>
                                </div>
                                <div class="col-4">
                                    <small class="d-block fw-bold text-success">Staff</small>
                                    <small class="d-block">staff1</small>
                                    <small class="d-block">password</small>
                                </div>
                                <div class="col-4">
                                    <small class="d-block fw-bold text-info">Customer</small>
                                    <small class="d-block">customer1</small>
                                    <small class="d-block">password</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill for testing
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const testUser = urlParams.get('test');
            
            if (testUser) {
                const users = {
                    'admin': { username: 'admin', password: 'password' },
                    'staff': { username: 'staff1', password: 'password' },
                    'customer': { username: 'customer1', password: 'password' }
                };
                
                if (users[testUser]) {
                    document.querySelector('input[name="username"]').value = users[testUser].username;
                    document.querySelector('input[name="password"]').value = users[testUser].password;
                }
            }
        });
    </script>
</body>
</html>