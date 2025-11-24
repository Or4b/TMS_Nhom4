<?php
session_start();
require 'includes/config.php'; //require config
require __DIR__ . '/vendor/autoload.php'; //require autoload

//có outoload thì mình có thể use các class của thư viện PHPMailer
use PHPMailer\PHPMailer\PHPMailer; // use PHPMailer class
use PHPMailer\PHPMailer\Exception; // use Exception class

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);  // Lấy email từ form và loại bỏ khoảng trắng thừa

    if (empty($email)) {
        $error = 'Vui lòng nhập địa chỉ email';
    } else {
        //kiểm tra xem email có tồn tại không bằng cách truy vấn vào database   
        $query = "SELECT user_id, username FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        //end truy vấn

        if ($result->num_rows == 1) { // Nếu có một người dùng với email này
            $user = $result->fetch_assoc(); 
            $user_id = $user['user_id'];    // Lấy thông tin người dùng (id)
            
            $token = bin2hex(random_bytes(32)); // Tạo token ngẫu nhiên
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));  // Đặt thời gian hết hạn cho token (1 giờ sau)
            
            // Lưu token vào database để xác thực sau này
            $query = "INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $user_id, $token, $expiry);
            $stmt->execute();
            //end lưu 


            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/webdatxe/change-pass.php?token=" . $token; // Tạo liên kết đặt lại mật khẩu
            $to = $email; // Địa chỉ email người dùng

            // Soạn thảo email
            $subject = "Đặt lại mật khẩu - Đặt vé xe & tàu"; // Tiêu đề email

            // Nội dung email
            $message = "Xin chào " . $user['username'] . ",\n\n";
            $message .= "Vui lòng nhấp vào liên kết sau để đặt lại mật khẩu của bạn:\n";
            $message .= $reset_link . "\n\n";
            $message .= "Liên kết này sẽ hết hạn sau 1 giờ.\n\n";
            $message .= "Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.\n\n";
            $message .= "Trân trọng,\nĐội ngũ Đặt vé xe & tàu";
            $headers = "From: no-reply@datxevatauhoa.com";
            $headers .= "Content-Type: text/plain; charset=UTF-8";
                //end nội dung email

            $mail = new PHPMailer(true); // Tạo một đối tượng PHPMailer mới
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; 
                $mail->SMTPAuth = true;
                $mail->Username = 'vudo.contact.vn@gmail.com'; 
                $mail->Password = 'lotizzxmsmpekrxl'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('vudo.contact.vn@gmail.com', 'Dat Xe & Tau');
                $mail->addAddress($email, $user['username']);
                
                $mail->isHTML(false);
                $mail->CharSet = 'UTF-8';
                
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send(); // Gửi email
                // Nếu gửi thành công, hiển thị thông báo thành công
                $success = 'Chúng tôi đã gửi email hướng dẫn đặt lại mật khẩu. Vui lòng kiểm tra hộp thư của bạn.';
            } catch (Exception $e) {
                $error = 'Không thể gửi email. Lỗi: ' . $mail->ErrorInfo;
            }
        } else {
            $success = 'Nếu email này tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu.';
        }
        $stmt->close(); // Đóng kết nối truy vấn
    }
}
$conn->close(); // Đóng kết nối database
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - Đặt vé xe & tàu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-md w-full max-w-md overflow-hidden">
            <!-- Header form -->
            <div class="bg-blue-600 py-4 px-6">
                <h2 class="text-xl font-bold text-white">Quên mật khẩu</h2>
            </div>
            
            <!-- Nội dung form -->
            <div class="p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <p><?php echo htmlspecialchars($success); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <p class="mb-4 text-gray-600">Nhập địa chỉ email của bạn và chúng tôi sẽ gửi cho bạn liên kết để đặt lại mật khẩu.</p>
                
                <form action="forgot-password.php" method="POST" class="space-y-4">
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                        <input type="email" id="email" name="email" 
                               class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required autofocus>
                    </div>
                    
                    <div class="flex items-center justify-between mb-4">
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                            Gửi liên kết đặt lại mật khẩu
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="text-blue-600 hover:text-blue-800 text-sm inline-block">
                        ← Quay lại đăng nhập
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
