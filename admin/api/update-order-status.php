<?php
require_once '../config.php';
checkAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$order_id = (int)$input['order_id'];
$new_status = $input['status'];
$payment_status = $input['payment_status'] ?? null;

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Get current order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Update order status
    $update_fields = ['status = ?'];
    $update_params = [$new_status];
    
    // Auto-update payment status based on order status
    if ($new_status === 'delivered' && $order['payment_status'] === 'pending') {
        $update_fields[] = 'payment_status = ?';
        $update_params[] = 'paid';
    } elseif ($new_status === 'cancelled') {
        $update_fields[] = 'payment_status = ?';
        $update_params[] = 'refunded';
    } elseif ($payment_status && in_array($payment_status, ['pending', 'paid', 'failed', 'refunded'])) {
        $update_fields[] = 'payment_status = ?';
        $update_params[] = $payment_status;
    }
    
    // Add timestamps for specific statuses
    if ($new_status === 'shipped' && $order['status'] !== 'shipped') {
        $update_fields[] = 'shipped_at = CURRENT_TIMESTAMP';
    } elseif ($new_status === 'delivered' && $order['status'] !== 'delivered') {
        $update_fields[] = 'delivered_at = CURRENT_TIMESTAMP';
    }
    
    $update_params[] = $order_id;
    
    $sql = "UPDATE orders SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_params);
    
    // Log the status change
    $stmt = $pdo->prepare("
        INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes, created_at) 
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        $order_id,
        $order['status'],
        $new_status,
        $_SESSION['admin_id'],
        "Status changed by admin: " . $_SESSION['admin_name']
    ]);
    
    // If order is cancelled, restore product stock
    if ($new_status === 'cancelled' && $order['status'] !== 'cancelled') {
        $stmt = $pdo->prepare("
            SELECT oi.product_id, oi.quantity 
            FROM order_items oi 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();
        
        foreach ($order_items as $item) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
    }
    
    $pdo->commit();
    
    // Get updated order for response
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $updated_order = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'order' => [
            'id' => $updated_order['id'],
            'order_number' => $updated_order['order_number'],
            'status' => $updated_order['status'],
            'payment_status' => $updated_order['payment_status'],
            'updated_at' => $updated_order['updated_at']
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating order status: ' . $e->getMessage()
    ]);
}
?>
