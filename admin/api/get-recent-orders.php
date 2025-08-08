<?php
require_once '../config.php';
checkAdminLogin();

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get recent orders with customer information
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email,
               COUNT(oi.id) as item_count
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // Format orders for JSON response
    $formatted_orders = [];
    foreach ($orders as $order) {
        $customer_name = '';
        if ($order['first_name'] && $order['last_name']) {
            $customer_name = $order['first_name'] . ' ' . $order['last_name'];
        } else {
            // Try to get customer info from order data
            $customer_info = json_decode($order['customer_info'] ?? '{}', true);
            if ($customer_info && isset($customer_info['first_name'])) {
                $customer_name = $customer_info['first_name'] . ' ' . $customer_info['last_name'];
            } else {
                $customer_name = 'Guest Customer';
            }
        }
        
        $formatted_orders[] = [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $customer_name,
            'customer_email' => $order['email'] ?: ($customer_info['email'] ?? ''),
            'total_amount' => $order['total_amount'],
            'formatted_amount' => formatPrice($order['total_amount']),
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'item_count' => $order['item_count'],
            'created_at' => $order['created_at'],
            'formatted_date' => formatDate($order['created_at']),
            'time_ago' => timeAgo($order['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $formatted_orders,
        'count' => count($formatted_orders)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading orders: ' . $e->getMessage()
    ]);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return formatDate($datetime);
}
?>
