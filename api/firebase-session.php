<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['uid']) || !isset($input['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$uid = $input['uid'];
$email = $input['email'];
$displayName = $input['displayName'] ?? '';

try {
    $pdo = getDBConnection();
    
    // Check if user exists in our database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Create new user record
        $names = explode(' ', $displayName, 2);
        $firstName = $names[0] ?? '';
        $lastName = $names[1] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO users (email, first_name, last_name, firebase_uid, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$email, $firstName, $lastName, $uid]);
        $userId = $pdo->lastInsertId();
        
        // Get the newly created user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    } else {
        // Update Firebase UID if not set
        if (empty($user['firebase_uid'])) {
            $stmt = $pdo->prepare("UPDATE users SET firebase_uid = ?, last_login = NOW() WHERE id = ?");
            $stmt->execute([$uid, $user['id']]);
        } else {
            // Just update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
    }
    
    // Create PHP session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_first_name'] = $user['first_name'];
    $_SESSION['user_last_name'] = $user['last_name'];
    $_SESSION['firebase_uid'] = $uid;
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['first_name'] . ' ' . $user['last_name']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Firebase session error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
