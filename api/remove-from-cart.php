<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    
    if ($cart_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
        exit;
    }
    
    $pdo = getDBConnection();
    
    // Verify ownership and delete
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$cart_id, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ? AND user_id IS NULL");
        $result = $stmt->execute([$cart_id, session_id()]);
    }
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or already removed']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
