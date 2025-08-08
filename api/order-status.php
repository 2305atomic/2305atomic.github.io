<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';

// Get order number from request
$order_number = $_GET['order_number'] ?? '';

if (empty($order_number)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order number is required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get current order status
    $stmt = $pdo->prepare("
        SELECT id, order_number, status, payment_status, 
               created_at, updated_at, shipped_at, delivered_at,
               total_amount, shipping_amount, tax_amount
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
    
    // Get order items count
    $stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $item_count = $stmt->fetch()['item_count'];
    
    // Calculate estimated delivery date based on status
    $estimated_delivery = null;
    switch ($order['status']) {
        case 'pending':
        case 'confirmed':
            $estimated_delivery = date('Y-m-d', strtotime('+5 days'));
            break;
        case 'processing':
            $estimated_delivery = date('Y-m-d', strtotime('+3 days'));
            break;
        case 'shipped':
            $estimated_delivery = date('Y-m-d', strtotime('+2 days'));
            break;
        case 'delivered':
            $estimated_delivery = $order['delivered_at'] ? date('Y-m-d', strtotime($order['delivered_at'])) : date('Y-m-d');
            break;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'item_count' => $item_count,
        'total_amount' => $order['total_amount'],
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at'],
        'estimated_delivery' => $estimated_delivery,
        'timeline' => [
            'pending' => [
                'status' => 'completed',
                'date' => $order['created_at'],
                'title' => 'Order Placed',
                'description' => 'Your order has been received and is being reviewed'
            ],
            'confirmed' => [
                'status' => in_array($order['status'], ['confirmed', 'processing', 'shipped', 'delivered']) ? 'completed' : 'pending',
                'date' => in_array($order['status'], ['confirmed', 'processing', 'shipped', 'delivered']) ? $order['updated_at'] : null,
                'title' => 'Order Confirmed',
                'description' => 'Payment verified and order confirmed'
            ],
            'processing' => [
                'status' => in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : 'pending',
                'date' => in_array($order['status'], ['processing', 'shipped', 'delivered']) ? $order['updated_at'] : null,
                'title' => 'Processing',
                'description' => 'Your order is being prepared for shipment'
            ],
            'shipped' => [
                'status' => in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : 'pending',
                'date' => $order['shipped_at'],
                'title' => 'Shipped',
                'description' => 'Your order is on its way to you'
            ],
            'delivered' => [
                'status' => $order['status'] === 'delivered' ? 'completed' : 'pending',
                'date' => $order['delivered_at'],
                'title' => 'Delivered',
                'description' => 'Your order has been delivered successfully'
            ]
        ]
    ];
    
    // Add tracking information if shipped
    if ($order['status'] === 'shipped' || $order['status'] === 'delivered') {
        $response['tracking'] = [
            'carrier' => 'JNE Express', // Default carrier
            'tracking_number' => 'JNE' . str_pad($order['id'], 8, '0', STR_PAD_LEFT),
            'tracking_url' => 'https://www.jne.co.id/id/tracking/trace'
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => 'Unable to fetch order status'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'An unexpected error occurred'
    ]);
}
?>
