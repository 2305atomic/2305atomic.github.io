<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$order_number = $input['order_number'] ?? '';

if (empty($order_number)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order number is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT id, status, user_id, total_amount 
        FROM orders 
        WHERE order_number = ? AND user_id = ?
    ");
    $stmt->execute([$order_number, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    // Check if order can be cancelled
    if (!in_array($order['status'], ['pending', 'confirmed'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Order cannot be cancelled at this stage']);
        exit;
    }
    
    // Update order status to cancelled
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE id = ?
    ");
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
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity + ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => 'Unable to cancel order'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'An unexpected error occurred'
    ]);
}
?>
