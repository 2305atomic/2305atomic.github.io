<?php
require_once 'config/config.php';

echo "<h2>Check Orders Table Structure</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f5f5f5;'>";

try {
    $pdo = getDBConnection();
    
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Orders table exists</p>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE orders");
        $columns = $stmt->fetchAll();
        
        echo "<h3>Current Orders Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #e9ecef;'><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $existing_columns = [];
        foreach ($columns as $column) {
            $existing_columns[] = $column['Field'];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for required columns
        $required_columns = [
            'id', 'order_number', 'user_id', 'status', 'total_amount', 
            'shipping_amount', 'tax_amount', 'discount_amount', 
            'payment_method', 'payment_status', 'shipping_address', 
            'notes', 'coupon_id', 'customer_info', 'created_at'
        ];
        
        echo "<h3>Column Check:</h3>";
        $missing_columns = [];
        foreach ($required_columns as $col) {
            if (in_array($col, $existing_columns)) {
                echo "<p style='color: green;'>✅ $col - exists</p>";
            } else {
                echo "<p style='color: red;'>❌ $col - missing</p>";
                $missing_columns[] = $col;
            }
        }
        
        // Fix missing columns
        if (!empty($missing_columns)) {
            echo "<h3>Fixing Missing Columns:</h3>";
            
            $column_definitions = [
                'shipping_amount' => 'DECIMAL(10,2) DEFAULT 0',
                'tax_amount' => 'DECIMAL(10,2) DEFAULT 0',
                'discount_amount' => 'DECIMAL(10,2) DEFAULT 0',
                'coupon_id' => 'INT NULL',
                'customer_info' => 'JSON NULL'
            ];
            
            foreach ($missing_columns as $col) {
                if (isset($column_definitions[$col])) {
                    try {
                        $sql = "ALTER TABLE orders ADD COLUMN $col " . $column_definitions[$col];
                        $pdo->exec($sql);
                        echo "<p style='color: green;'>✅ Added column: $col</p>";
                    } catch (PDOException $e) {
                        echo "<p style='color: red;'>❌ Error adding $col: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ Orders table does not exist</p>";
        
        // Create orders table
        echo "<h3>Creating Orders Table:</h3>";
        $create_sql = "
            CREATE TABLE orders (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                user_id INT,
                status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
                total_amount DECIMAL(10,2) NOT NULL,
                shipping_amount DECIMAL(10,2) DEFAULT 0,
                tax_amount DECIMAL(10,2) DEFAULT 0,
                discount_amount DECIMAL(10,2) DEFAULT 0,
                payment_method VARCHAR(50),
                payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
                shipping_address TEXT,
                billing_address TEXT,
                notes TEXT,
                coupon_id INT NULL,
                customer_info JSON NULL,
                shipped_at TIMESTAMP NULL,
                delivered_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_status (status),
                INDEX idx_order_number (order_number)
            )
        ";
        
        try {
            $pdo->exec($create_sql);
            echo "<p style='color: green;'>✅ Orders table created successfully</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error creating orders table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check order_items table
    echo "<h3>Order Items Table:</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Order items table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Order items table missing</p>";
        
        // Create order_items table
        $create_items_sql = "
            CREATE TABLE order_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                INDEX idx_order (order_id),
                INDEX idx_product (product_id)
            )
        ";
        
        try {
            $pdo->exec($create_items_sql);
            echo "<p style='color: green;'>✅ Order items table created successfully</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error creating order items table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Test a simple insert
    echo "<h3>Test Database Operations:</h3>";
    try {
        $test_order_number = 'TEST-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, user_id, status, total_amount, shipping_amount, 
                              tax_amount, discount_amount, payment_method, payment_status, 
                              shipping_address, notes, customer_info, created_at) 
            VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $test_order_number,
            null, // user_id
            150000, // total amount
            15000,  // shipping
            13500,  // tax
            0,      // discount
            'bank_transfer',
            'Test Address',
            'Test notes',
            json_encode(['test' => 'data'])
        ]);
        
        if ($result) {
            $order_id = $pdo->lastInsertId();
            echo "<p style='color: green;'>✅ Test order insert successful. Order ID: $order_id</p>";
            
            // Clean up test order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            echo "<p style='color: blue;'>ℹ Test order cleaned up</p>";
        } else {
            echo "<p style='color: red;'>❌ Test order insert failed</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Database test error: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<p style='margin-top: 30px;'><a href='order-review.php?debug=1' style='color: #0d6efd;'>→ Test Order Review</a></p>";
echo "</div>";
?>
