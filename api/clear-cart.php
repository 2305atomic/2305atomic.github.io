<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Clear cart based on user type
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $result = $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
        $result = $stmt->execute([session_id()]);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
