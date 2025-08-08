<?php
// Clear Cart AJAX Handler
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

try {
    $pdo = getDBConnection();
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    // Clear cart items for current user/session
    if ($user_id) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ?");
        $stmt->execute([$session_id]);
    }
    
    $deleted_count = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Cart cleared successfully ($deleted_count items removed)",
        'cart_count' => 0
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
