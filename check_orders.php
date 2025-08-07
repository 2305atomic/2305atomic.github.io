<?php
require_once 'config/config.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT order_number, status, payment_status, total_amount, created_at FROM orders ORDER BY created_at DESC LIMIT 5');
    $orders = $stmt->fetchAll();
    
    if (empty($orders)) {
        echo 'No orders found in database' . PHP_EOL;
    } else {
        echo 'Recent orders:' . PHP_EOL;
        foreach ($orders as $order) {
            echo '- ' . $order['order_number'] . ' | ' . $order['status'] . ' | ' . $order['payment_status'] . ' | ' . $order['total_amount'] . ' | ' . $order['created_at'] . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
