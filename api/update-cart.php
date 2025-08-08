<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    if ($cart_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Get cart item and verify ownership
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT c.*, p.stock_quantity, p.name 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, p.stock_quantity, p.name 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.session_id = ? AND c.user_id IS NULL
        ");
        $stmt->execute([$cart_id, session_id()]);
    }
    
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }
    
    // Check stock availability
    if ($quantity > $cart_item['stock_quantity']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Only ' . $cart_item['stock_quantity'] . ' items available in stock'
        ]);
        exit;
    }
    
    // Update cart item
    $stmt = $pdo->prepare("
        UPDATE cart 
        SET quantity = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$quantity, $cart_id])) {
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated successfully',
            'quantity' => $quantity
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
