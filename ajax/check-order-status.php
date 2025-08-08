<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order number required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current order status
    $stmt = $pdo->prepare("
        SELECT status, payment_status, updated_at 
        FROM orders 
        WHERE order_number = ?
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    echo json_encode([
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'updated_at' => $order['updated_at']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
