<?php
// Add to Wishlist AJAX Handler
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please login to add items to wishlist');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['product_id'])) {
        throw new Exception('Invalid input data');
    }
    
    $product_id = (int)$input['product_id'];
    $user_id = $_SESSION['user_id'];
    
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    // Check if product exists
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, status FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    if ($product['status'] !== 'active') {
        throw new Exception('Product is not available');
    }
    
    // Check if item already exists in wishlist
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing_item = $stmt->fetch();
    
    if ($existing_item) {
        // Remove from wishlist if already exists
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
        $stmt->execute([$existing_item['id']]);
        
        $message = 'Product removed from wishlist';
        $action = 'removed';
    } else {
        // Add to wishlist
        $stmt = $pdo->prepare("
            INSERT INTO wishlist (user_id, product_id, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $product_id]);
        
        $message = 'Product added to wishlist';
        $action = 'added';
    }
    
    // Get updated wishlist count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = $stmt->fetch()['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'action' => $action,
        'wishlist_count' => $wishlist_count,
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
