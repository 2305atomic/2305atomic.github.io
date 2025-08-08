<?php
require_once 'config.php';
checkAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_error'] = 'Invalid request method.';
    redirect(ADMIN_URL . '/orders.php');
}

$order_id = (int)($_POST['order_id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$order_id || !$email || !$subject || !$message) {
    $_SESSION['admin_error'] = 'All fields are required.';
    redirect(ADMIN_URL . '/order-detail.php?id=' . $order_id);
}

try {
    $pdo = getDBConnection();
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['admin_error'] = 'Order not found.';
        redirect(ADMIN_URL . '/orders.php');
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_error'] = 'Invalid email address.';
        redirect(ADMIN_URL . '/order-detail.php?id=' . $order_id);
    }
    
    // Prepare email content
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .order-info { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>TeWuNeed</h1>
                <p>Order Update Notification</p>
            </div>
            <div class='content'>
                " . nl2br(htmlspecialchars($message)) . "
                
                <div class='order-info'>
                    <h3>Order Information</h3>
                    <p><strong>Order Number:</strong> " . htmlspecialchars($order['order_number']) . "</p>
                    <p><strong>Order Date:</strong> " . formatDate($order['created_at']) . "</p>
                    <p><strong>Total Amount:</strong> " . formatPrice($order['total_amount']) . "</p>
                    <p><strong>Current Status:</strong> " . ucfirst($order['status']) . "</p>
                    <p><strong>Payment Status:</strong> " . ucfirst($order['payment_status']) . "</p>
                </div>
                
                <p>If you have any questions about your order, please don't hesitate to contact us.</p>
            </div>
            <div class='footer'>
                <p>This email was sent from TeWuNeed Admin Panel</p>
                <p>© " . date('Y') . " TeWuNeed. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: TeWuNeed Admin <admin@tewuneed.com>',
        'Reply-To: admin@tewuneed.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Send email
    $mail_sent = mail($email, $subject, $email_body, implode("\r\n", $headers));
    
    if ($mail_sent) {
        // Log the email in order history
        $stmt = $pdo->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $order_id,
            $order['status'],
            $order['status'],
            $_SESSION['admin_id'],
            "Email sent to customer: " . $subject
        ]);
        
        $_SESSION['admin_success'] = 'Email sent successfully to ' . $email;
    } else {
        $_SESSION['admin_error'] = 'Failed to send email. Please check your mail configuration.';
    }
    
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Error sending email: ' . $e->getMessage();
}

redirect(ADMIN_URL . '/order-detail.php?id=' . $order_id);
?>
