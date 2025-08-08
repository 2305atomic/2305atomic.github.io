<?php
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    $count = 0;
    
    if (isset($_SESSION['user_id'])) {
        // Get count for logged-in user
        $stmt = getDBConnection()->prepare("
            SELECT SUM(quantity) as total_count 
            FROM cart 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $count = (int)($result['total_count'] ?? 0);
    } else {
        // Get count for guest user using session
        $session_id = session_id();
        if ($session_id) {
            $stmt = getDBConnection()->prepare("
                SELECT SUM(quantity) as total_count 
                FROM cart 
                WHERE session_id = ? AND user_id IS NULL
            ");
            $stmt->execute([$session_id]);
            $result = $stmt->fetch();
            $count = (int)($result['total_count'] ?? 0);
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get cart count',
        'count' => 0
    ]);
}
?>
