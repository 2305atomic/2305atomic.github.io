<?php
// Get Cart Count AJAX Handler
session_start();
header('Content-Type: application/json');

require_once '../config/config.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    $pdo = getDBConnection();
    
    // Get cart count
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
        'count' => (int)$cart_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => $e->getMessage()
    ]);
}
?>
