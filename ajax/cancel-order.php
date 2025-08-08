<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_number = sanitize($input['order_number'] ?? '');

if (empty($order_number)) {
    echo json_encode(['success' => false, 'message' => 'Order number is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Check if user owns this order (if logged in)
    if (isset($_SESSION['user_id']) && $order['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Check if order can be cancelled
    if (!in_array($order['status'], ['pending', 'processing'])) {
        echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$order['id']]);
    
    // Restore product stock
    $stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity 
        FROM order_items oi 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll();
    
    foreach ($order_items as $item) {
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Restore coupon usage if applicable
    if ($order['coupon_id']) {
        $stmt = $pdo->prepare("UPDATE coupons SET used_count = used_count - 1 WHERE id = ?");
        $stmt->execute([$order['coupon_id']]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error cancelling order']);
}
?>
