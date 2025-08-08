<?php
require_once '../config.php';
checkAdminLogin();

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Get total orders count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Get pending orders count
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetch()['pending'];
    
    // Get today's orders count
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_orders = $stmt->fetch()['today'];
    
    // Get today's revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) as revenue FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
    $today_revenue = $stmt->fetch()['revenue'] ?: 0;
    
    // Get status distribution
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $status_distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get recent activity (last 24 hours)
    $stmt = $pdo->query("
        SELECT COUNT(*) as recent 
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent_activity = $stmt->fetch()['recent'];
    
    echo json_encode([
        'success' => true,
        'total_orders' => $total_orders,
        'pending_orders' => $pending_orders,
        'today_orders' => $today_orders,
        'today_revenue' => $today_revenue,
        'formatted_revenue' => formatPrice($today_revenue),
        'status_distribution' => $status_distribution,
        'recent_activity' => $recent_activity,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error getting orders stats: ' . $e->getMessage()
    ]);
}
?>
