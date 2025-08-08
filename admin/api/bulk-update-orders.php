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

if (!isset($input['order_ids']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$order_ids = $input['order_ids'];
$new_status = $input['status'];
$payment_status = $input['payment_status'] ?? null;

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Validate order IDs
if (!is_array($order_ids) || empty($order_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No orders selected']);
    exit;
}

// Sanitize order IDs
$order_ids = array_map('intval', $order_ids);
$order_ids = array_filter($order_ids, function($id) { return $id > 0; });

if (empty($order_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order IDs']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $updated_count = 0;
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    // Get current orders
    $stmt = $pdo->prepare("SELECT id, status, payment_status FROM orders WHERE id IN ($placeholders)");
    $stmt->execute($order_ids);
    $orders = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($order_ids as $order_id) {
        if (!isset($orders[$order_id])) {
            continue; // Skip non-existent orders
        }
        
        $current_order = $orders[$order_id];
        
        // Prepare update fields
        $update_fields = ['status = ?'];
        $update_params = [$new_status];
        
        // Auto-update payment status based on order status
        if ($new_status === 'delivered' && $current_order['payment_status'] === 'pending') {
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
        if ($new_status === 'shipped' && $current_order['status'] !== 'shipped') {
            $update_fields[] = 'shipped_at = CURRENT_TIMESTAMP';
        } elseif ($new_status === 'delivered' && $current_order['status'] !== 'delivered') {
            $update_fields[] = 'delivered_at = CURRENT_TIMESTAMP';
        }
        
        $update_params[] = $order_id;
        
        // Update order
        $sql = "UPDATE orders SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($update_params);
        
        if ($stmt->rowCount() > 0) {
            $updated_count++;
            
            // Log the status change
            $stmt = $pdo->prepare("
                INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $order_id,
                $current_order['status'],
                $new_status,
                $_SESSION['admin_id'],
                "Bulk status update by admin: " . $_SESSION['admin_name']
            ]);
            
            // If order is cancelled, restore product stock
            if ($new_status === 'cancelled' && $current_order['status'] !== 'cancelled') {
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
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated $updated_count orders",
        'updated_count' => $updated_count,
        'total_selected' => count($order_ids)
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating orders: ' . $e->getMessage()
    ]);
}
?>
