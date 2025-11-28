<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'BusTicket - Đặt vé xe buýt trực tuyến'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50">

<header style="background: #3d4a5c;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Logo + Menu -->
            <div class="flex items-center space-x-8">
                <a href="index.php" class="flex items-center">
                    <div class="text-2xl font-bold text-blue-400" style="letter-spacing: 1px;">VéXe</div>
                </a>

                <nav class="hidden lg:flex space-x-8 text-white font-medium">
                    <a href="index.php" class="hover:text-blue-300 transition">Trang chủ</a>
                    <a href="#" class="hover:text-blue-300 transition">Vé của tôi</a>
                    <a href="#" class="hover:text-blue-300 transition">Khuyến mãi</a>
                    <a href="#" class="hover:text-blue-300 transition">Hỗ trợ</a>
                </nav>
            </div>

            <!-- PHẦN USER -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="relative group">
                    <!-- Click vào đây mở dropdown -->
                    <button class="flex items-center space-x-3 text-white hover:text-blue-300 transition font-medium">
                        <!-- Avatar tròn màu xanh dương -->
                        <div class="w-10 h-10 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold text-sm shadow">
                            <?php
                            $name = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
                            $parts = explode(' ', trim($name));
                            $initial = '';
                            if (count($parts) >= 2) {
                                $initial = mb_strtoupper(mb_substr(end($parts), 0, 1)) . mb_strtoupper(mb_substr($parts[0], 0, 1));
                            } else {
                                $initial = mb_strtoupper(mb_substr($name, 0, 2));
                            }
                            echo $initial ?: 'NA';
                            ?>
                        </div>
                        <span class="text-sm"><?php echo htmlspecialchars($name); ?></span>
                        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Dropdown menu -->
                    <div class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-xl border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="py-2">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-50 transition">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Hồ sơ cá nhân
                            </a>
                            <a href="change-password.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-50 transition">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Đổi mật khẩu
                            </a>
                            <hr class="my-2">
                            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-white font-medium hover:text-blue-300">Đăng nhập</a>
                    <a href="register.php" class="bg-purple-600 text-white px-6 py-2.5 rounded-lg hover:bg-purple-700 transition font-medium">Đăng ký</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</header>

<main class="min-h-screen">