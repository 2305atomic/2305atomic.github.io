<?php
// Add to Cart AJAX Handler
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['product_id']) || !isset($input['quantity'])) {
        throw new Exception('Invalid input data');
    }
    
    $product_id = (int)$input['product_id'];
    $quantity = (int)$input['quantity'];
    
    if ($product_id <= 0 || $quantity <= 0) {
        throw new Exception('Invalid product ID or quantity');
    }
    
    // Check if product exists and is available
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, price, sale_price, stock_quantity, status FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    if ($product['status'] !== 'active') {
        throw new Exception('Product is not available');
    }
    
    if ($product['stock_quantity'] < $quantity) {
        throw new Exception('Insufficient stock available');
    }
    
    // Get user ID or session ID
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    // Check if item already exists in cart
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
        $stmt->execute([$session_id, $product_id]);
    }
    
    $existing_item = $stmt->fetch();
    
    if ($existing_item) {
        // Update existing cart item
        $new_quantity = $existing_item['quantity'] + $quantity;
        
        if ($new_quantity > $product['stock_quantity']) {
            throw new Exception('Cannot add more items. Stock limit reached.');
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_quantity, $existing_item['id']]);
    } else {
        // Add new cart item
        $stmt = $pdo->prepare("
            INSERT INTO cart (user_id, session_id, product_id, quantity, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $session_id, $product_id, $quantity]);
    }
    
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
        'message' => 'Product added to cart successfully',
        'cart_count' => $cart_count,
        'product_name' => $product['name']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
