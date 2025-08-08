<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$coupon_code = sanitize($input['coupon_code'] ?? '');
$subtotal = (float)($input['subtotal'] ?? 0);

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
    exit;
}

if ($subtotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order amount']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Validate coupon
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE code = ? AND status = 'active' 
        AND (expires_at IS NULL OR expires_at > NOW())
        AND (max_uses = 0 OR used_count < max_uses)
    ");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
        exit;
    }
    
    // Check minimum amount
    if ($subtotal < $coupon['min_amount']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Minimum order amount of ' . formatPrice($coupon['min_amount']) . ' required for this coupon'
        ]);
        exit;
    }
    
    // Calculate discount
    $discount_amount = 0;
    if ($coupon['type'] === 'percentage') {
        $discount_amount = $subtotal * ($coupon['value'] / 100);
        // Cap percentage discount at subtotal
        $discount_amount = min($discount_amount, $subtotal);
    } else {
        $discount_amount = $coupon['value'];
        // Cap fixed discount at subtotal
        $discount_amount = min($discount_amount, $subtotal);
    }
    
    // Calculate new total (including shipping and tax)
    $shipping_fee = $subtotal >= 100000 ? 0 : 15000;
    $tax_amount = $subtotal * 0.10;
    $new_total = $subtotal + $shipping_fee + $tax_amount - $discount_amount;
    
    echo json_encode([
        'success' => true,
        'message' => 'Coupon applied successfully',
        'coupon_code' => $coupon_code,
        'discount_amount' => $discount_amount,
        'discount_formatted' => formatPrice($discount_amount),
        'new_total' => $new_total,
        'new_total_formatted' => formatPrice($new_total),
        'coupon_type' => $coupon['type'],
        'coupon_value' => $coupon['value']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error validating coupon']);
}
?>
