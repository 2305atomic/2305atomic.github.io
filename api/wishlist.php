<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to manage wishlist']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    if ($method === 'POST') {
        // Add to wishlist
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = (int)($input['product_id'] ?? 0);
        
        if (!$product_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Product ID is required']);
            exit;
        }
        
        // Check if product exists
        $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        // Check if already in wishlist
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Product already in wishlist']);
            exit;
        }
        
        // Add to wishlist
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $product_id]);
        
        // Get wishlist count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wishlist_count = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product added to wishlist!',
            'wishlist_count' => $wishlist_count
        ]);
        
    } elseif ($method === 'DELETE') {
        // Remove from wishlist
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = (int)($input['product_id'] ?? 0);
        
        if (!$product_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Product ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        if ($stmt->rowCount() > 0) {
            // Get wishlist count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wishlist_count = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Product removed from wishlist!',
                'wishlist_count' => $wishlist_count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found in wishlist']);
        }
        
    } elseif ($method === 'GET') {
        // Get wishlist items
        $sql = "
            SELECT w.*, p.name, p.price, p.sale_price, p.image, p.slug, p.stock_quantity,
                   c.name as category_name
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE w.user_id = ? AND p.status = 'active'
            ORDER BY w.created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $items = $stmt->fetchAll();
        
        // Format items for JSON response
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = [
                'id' => $item['id'],
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'sale_price' => $item['sale_price'],
                'formatted_price' => formatPrice($item['sale_price'] ?? $item['price']),
                'image' => $item['image'] ? SITE_URL . '/uploads/' . $item['image'] : null,
                'slug' => $item['slug'],
                'stock_quantity' => $item['stock_quantity'],
                'category_name' => $item['category_name'],
                'created_at' => $item['created_at'],
                'time_ago' => timeAgo($item['created_at'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'items' => $formatted_items,
            'count' => count($formatted_items)
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
