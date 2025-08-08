<?php
// Real-time notifications endpoint using Server-Sent Events (SSE)
require_once '../config.php';
checkAdminLogin();

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Prevent timeout
set_time_limit(0);
ignore_user_abort(false);

// Function to send SSE data
function sendSSE($id, $event, $data) {
    echo "id: $id\n";
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Get last check timestamp from session or use current time
$lastCheck = $_SESSION['admin_last_check'] ?? time();

try {
    $pdo = getDBConnection();
    
    while (true) {
        // Check for new orders
        $stmt = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email,
                   COUNT(oi.id) as item_count,
                   SUM(oi.total) as order_total
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.created_at > FROM_UNIXTIME(?)
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$lastCheck]);
        $newOrders = $stmt->fetchAll();
        
        // Send new order notifications
        foreach ($newOrders as $order) {
            $customerName = $order['first_name'] && $order['last_name'] 
                ? $order['first_name'] . ' ' . $order['last_name']
                : 'Guest Customer';
                
            // Parse customer info if guest order
            if (!$customerName || $customerName === ' ') {
                $customerInfo = json_decode($order['customer_info'], true);
                if ($customerInfo) {
                    $customerName = $customerInfo['first_name'] . ' ' . $customerInfo['last_name'];
                }
            }
            
            sendSSE(
                'order_' . $order['id'],
                'new_order',
                [
                    'id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'customer_name' => $customerName,
                    'customer_email' => $order['email'] ?: ($customerInfo['email'] ?? 'N/A'),
                    'total_amount' => $order['total_amount'],
                    'item_count' => $order['item_count'],
                    'payment_method' => $order['payment_method'],
                    'status' => $order['status'],
                    'payment_status' => $order['payment_status'],
                    'created_at' => $order['created_at'],
                    'formatted_amount' => formatPrice($order['total_amount']),
                    'formatted_date' => formatDate($order['created_at'])
                ]
            );
        }
        
        // Check for order status updates
        $stmt = $pdo->prepare("
            SELECT o.*, u.first_name, u.last_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE o.updated_at > FROM_UNIXTIME(?) AND o.created_at <= FROM_UNIXTIME(?)
            ORDER BY o.updated_at DESC
        ");
        $stmt->execute([$lastCheck, $lastCheck]);
        $updatedOrders = $stmt->fetchAll();
        
        // Send order update notifications
        foreach ($updatedOrders as $order) {
            $customerName = $order['first_name'] && $order['last_name'] 
                ? $order['first_name'] . ' ' . $order['last_name']
                : 'Guest Customer';
                
            sendSSE(
                'update_' . $order['id'],
                'order_updated',
                [
                    'id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'customer_name' => $customerName,
                    'status' => $order['status'],
                    'payment_status' => $order['payment_status'],
                    'updated_at' => $order['updated_at']
                ]
            );
        }
        
        // Get current statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
        $todayOrders = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
        $todayRevenue = $stmt->fetch()['total'] ?: 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
        $pendingOrders = $stmt->fetch()['total'];
        
        // Send statistics update
        sendSSE(
            'stats_' . time(),
            'stats_update',
            [
                'today_orders' => $todayOrders,
                'today_revenue' => $todayRevenue,
                'pending_orders' => $pendingOrders,
                'formatted_revenue' => formatPrice($todayRevenue)
            ]
        );
        
        // Update last check timestamp
        $lastCheck = time();
        $_SESSION['admin_last_check'] = $lastCheck;
        
        // Send heartbeat
        sendSSE('heartbeat_' . time(), 'heartbeat', ['timestamp' => time()]);
        
        // Wait 5 seconds before next check
        sleep(5);
        
        // Check if connection is still alive
        if (connection_aborted()) {
            break;
        }
    }
    
} catch (Exception $e) {
    sendSSE('error_' . time(), 'error', ['message' => $e->getMessage()]);
}
?>
