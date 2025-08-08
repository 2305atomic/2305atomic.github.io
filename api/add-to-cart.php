<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        exit;
    }
    
    // Check if product exists and is active
    $stmt = getDBConnection()->prepare("
        SELECT id, name, price, stock_quantity, status 
        FROM products 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unavailable']);
        exit;
    }
    
    // Check stock availability
    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock available']);
        exit;
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    try {
        if (isset($_SESSION['user_id'])) {
            // Logged-in user
            $user_id = $_SESSION['user_id'];
            $session_id = null;
            
            // Check if item already exists in cart
            $stmt = $pdo->prepare("
                SELECT id, quantity 
                FROM cart 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$user_id, $product_id]);
            $existing_item = $stmt->fetch();
            
            if ($existing_item) {
                // Update existing item
                $new_quantity = $existing_item['quantity'] + $quantity;
                
                // Check stock again for new total quantity
                if ($product['stock_quantity'] < $new_quantity) {
                    throw new Exception('Insufficient stock for requested quantity');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE cart 
                    SET quantity = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$new_quantity, $existing_item['id']]);
            } else {
                // Add new item
                $stmt = $pdo->prepare("
                    INSERT INTO cart (user_id, product_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $product_id, $quantity]);
            }
        } else {
            // Guest user
            $session_id = session_id();
            
            // Check if item already exists in cart
            $stmt = $pdo->prepare("
                SELECT id, quantity 
                FROM cart 
                WHERE session_id = ? AND product_id = ? AND user_id IS NULL
            ");
            $stmt->execute([$session_id, $product_id]);
            $existing_item = $stmt->fetch();
            
            if ($existing_item) {
                // Update existing item
                $new_quantity = $existing_item['quantity'] + $quantity;
                
                // Check stock again for new total quantity
                if ($product['stock_quantity'] < $new_quantity) {
                    throw new Exception('Insufficient stock for requested quantity');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE cart 
                    SET quantity = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$new_quantity, $existing_item['id']]);
            } else {
                // Add new item
                $stmt = $pdo->prepare("
                    INSERT INTO cart (session_id, product_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$session_id, $product_id, $quantity]);
            }
        }
        
        $pdo->commit();
        
        // Get updated cart count
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("
                SELECT SUM(quantity) as total_count 
                FROM cart 
                WHERE user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("
                SELECT SUM(quantity) as total_count 
                FROM cart 
                WHERE session_id = ? AND user_id IS NULL
            ");
            $stmt->execute([session_id()]);
        }
        
        $result = $stmt->fetch();
        $cart_count = (int)($result['total_count'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'cart_count' => $cart_count,
            'product_name' => $product['name']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
