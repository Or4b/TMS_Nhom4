<?php
// FILE: check_promo.php
session_start();
require_once 'includes/config.php';
header('Content-Type: application/json');

// Lấy dữ liệu từ request
$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';
$total_amount = $data['total_amount'] ?? 0;

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã khuyến mãi']);
    exit;
}

try {
    // Truy vấn mã khuyến mãi từ DB
    // Kiểm tra: mã đúng, đang active, còn hạn sử dụng
    $stmt = $pdo->prepare("SELECT * FROM promotions 
                           WHERE promotion_code = ? 
                           AND status = 'active' 
                           AND start_date <= CURDATE() 
                           AND end_date >= CURDATE()");
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    // 1. Kiểm tra mã tồn tại
    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi không tồn tại hoặc đã hết hạn']);
        exit;
    }

    // 2. Kiểm tra giới hạn sử dụng (usage_limit)
    if ($promo['usage_limit'] !== null && $promo['used_count'] >= $promo['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Mã khuyến mãi đã hết lượt sử dụng']);
        exit;
    }

    // 3. Kiểm tra giá trị đơn hàng tối thiểu (min_order_value)
    if ($total_amount < $promo['min_order_value']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Đơn hàng chưa đủ điều kiện. Tối thiểu: ' . number_format($promo['min_order_value']) . ' VNĐ'
        ]);
        exit;
    }

    // 4. Tính toán số tiền giảm
    $discount_amount = 0;
    if ($promo['discount_type'] === 'fixed') {
        $discount_amount = $promo['discount_value'];
    } elseif ($promo['discount_type'] === 'percentage') {
        $discount_amount = ($total_amount * $promo['discount_value']) / 100;
        
        // Kiểm tra giảm giá tối đa (max_discount) nếu có
        if ($promo['max_discount'] !== null && $discount_amount > $promo['max_discount']) {
            $discount_amount = $promo['max_discount'];
        }
    }

    // Đảm bảo không giảm quá số tiền gốc
    if ($discount_amount > $total_amount) {
        $discount_amount = $total_amount;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Áp dụng mã thành công: ' . $promo['promotion_name'],
        'discount_amount' => $discount_amount,
        'promotion_id' => $promo['id']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>