<?php
// Update Cart Quantity AJAX Handler
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['cart_id']) || !isset($input['quantity'])) {
        throw new Exception('Invalid input data');
    }
    
    $cart_id = (int)$input['cart_id'];
    $quantity = (int)$input['quantity'];
    
    if ($cart_id <= 0 || $quantity <= 0) {
        throw new Exception('Invalid cart ID or quantity');
    }
    
    $pdo = getDBConnection();
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    // Verify cart item belongs to current user/session
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT c.*, p.stock_quantity, p.name 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cart_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, p.stock_quantity, p.name 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.session_id = ?
        ");
        $stmt->execute([$cart_id, $session_id]);
    }
    
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        throw new Exception('Cart item not found');
    }
    
    // Check stock availability
    if ($quantity > $cart_item['stock_quantity']) {
        throw new Exception('Requested quantity exceeds available stock (' . $cart_item['stock_quantity'] . ' available)');
    }
    
    // Update cart item quantity
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$quantity, $cart_id]);
    
    // Get updated cart count
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ?");
        $stmt->execute([$session_id]);
    }
    
    $cart_count = $stmt->fetch()['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'cart_count' => $cart_count,
        'product_name' => $cart_item['name']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
