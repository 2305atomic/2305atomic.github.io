<?php
// Setup Order Status History Table
require_once 'config.php';
checkAdminLogin();

echo "<h2>Setup Order Status History</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f5f5f5;'>";

try {
    $pdo = getDBConnection();
    
    // Create order_status_history table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_status_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            old_status VARCHAR(50),
            new_status VARCHAR(50) NOT NULL,
            changed_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_order (order_id),
            INDEX idx_changed_by (changed_by),
            INDEX idx_created_at (created_at)
        )
    ");
    echo "<p style='color: green;'>✅ order_status_history table created successfully</p>";
    
    // Add missing columns to orders table if they don't exist
    $columns_to_add = [
        'shipped_at' => 'TIMESTAMP NULL',
        'delivered_at' => 'TIMESTAMP NULL',
        'customer_info' => 'JSON NULL',
        'coupon_id' => 'INT NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $column $definition");
            echo "<p style='color: green;'>✅ Added column '$column' to orders table</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<p style='color: blue;'>ℹ Column '$column' already exists in orders table</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding column '$column': " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Check existing orders and add initial history entries
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_status_history");
    $history_count = $stmt->fetch()['count'];
    
    if ($history_count == 0) {
        echo "<p>Adding initial history entries for existing orders...</p>";
        
        $stmt = $pdo->query("SELECT id, status, created_at FROM orders");
        $orders = $stmt->fetchAll();
        
        foreach ($orders as $order) {
            $stmt = $pdo->prepare("
                INSERT INTO order_status_history (order_id, old_status, new_status, notes, created_at) 
                VALUES (?, NULL, ?, 'Initial order creation', ?)
            ");
            $stmt->execute([$order['id'], $order['status'], $order['created_at']]);
        }
        
        echo "<p style='color: green;'>✅ Added initial history entries for " . count($orders) . " orders</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Order status history already contains $history_count entries</p>";
    }
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>Setup Complete!</h3>";
    echo "<p>✅ Order status history tracking is now enabled</p>";
    echo "<p>✅ Real-time order updates are ready</p>";
    echo "<p>✅ Admin can now track all order status changes</p>";
    echo "<p><a href='index.php' style='color: #0d6efd;'>← Back to Dashboard</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background-color: #f8f9fa;
}

a {
    color: #0d6efd;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
