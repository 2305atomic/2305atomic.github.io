<?php
// Database Debug - Check Everything
echo "<h1>🔍 Complete Database Debug</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; background: white; border-radius: 10px;'>";

// Test basic PHP and MySQL
echo "<h2>🔧 System Check</h2>";
echo "<p>✅ PHP Version: " . phpversion() . "</p>";
echo "<p>✅ PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "</p>";
echo "<p>✅ PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "</p>";

// Test database connection
echo "<h2>🔌 Database Connection Test</h2>";

try {
    // Try to connect to MySQL server first
    $pdo_test = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '');
    $pdo_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ MySQL server connection: SUCCESS</p>";
    
    // Check if database exists
    $stmt = $pdo_test->query("SHOW DATABASES LIKE 'db_tewuneed2'");
    $db_exists = $stmt->fetch();
    
    if ($db_exists) {
        echo "<p style='color: green;'>✅ Database 'db_tewuneed2' exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Database 'db_tewuneed2' does not exist</p>";
        echo "<p style='color: orange;'>🔧 Creating database...</p>";
        $pdo_test->exec("CREATE DATABASE IF NOT EXISTS db_tewuneed2");
        echo "<p style='color: green;'>✅ Database created successfully</p>";
    }
    
    // Now try to connect to our specific database
    require_once 'config/config.php';
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✅ Database connection to 'db_tewuneed2': SUCCESS</p>";
    
    // Check tables
    echo "<h2>📋 Table Structure Check</h2>";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Tables found:</strong> " . implode(', ', $tables) . "</p>";
    
    $required_tables = ['products', 'categories', 'users', 'cart'];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        if (in_array($table, $tables)) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>❌ Table '$table' missing</p>";
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3 style='color: #856404;'>⚠️ Missing Tables Detected</h3>";
        echo "<p style='color: #856404;'>Creating missing tables...</p>";
        
        // Create missing tables
        if (in_array('categories', $missing_tables)) {
            $pdo->exec("
                CREATE TABLE categories (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    slug VARCHAR(100) UNIQUE NOT NULL,
                    description TEXT,
                    image VARCHAR(255),
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            echo "<p style='color: green;'>✅ Categories table created</p>";
        }
        
        if (in_array('products', $missing_tables)) {
            $pdo->exec("
                CREATE TABLE products (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) UNIQUE NOT NULL,
                    description TEXT,
                    short_description VARCHAR(500),
                    price DECIMAL(10,2) NOT NULL,
                    sale_price DECIMAL(10,2) NULL,
                    sku VARCHAR(100) UNIQUE,
                    stock_quantity INT DEFAULT 0,
                    category_id INT,
                    image VARCHAR(255),
                    gallery TEXT,
                    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
                    featured BOOLEAN DEFAULT FALSE,
                    weight DECIMAL(8,2) DEFAULT 0,
                    dimensions VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
                )
            ");
            echo "<p style='color: green;'>✅ Products table created</p>";
        }
        
        if (in_array('cart', $missing_tables)) {
            $pdo->exec("
                CREATE TABLE cart (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NULL,
                    session_id VARCHAR(255) NULL,
                    product_id INT NOT NULL,
                    quantity INT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                )
            ");
            echo "<p style='color: green;'>✅ Cart table created</p>";
        }
        
        echo "</div>";
    }
    
    // Check if we have data
    echo "<h2>📊 Data Check</h2>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $product_count = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $category_count = $stmt->fetch()['total'];
    
    echo "<p><strong>Products:</strong> $product_count</p>";
    echo "<p><strong>Categories:</strong> $category_count</p>";
    
    if ($product_count == 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3 style='color: #856404;'>⚠️ No Products Found</h3>";
        echo "<p style='color: #856404;'>Database structure is correct but no products exist.</p>";
        echo "<a href='fix_column_error.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; display: inline-block;'>Import Sample Products</a>";
        echo "</div>";
    } else {
        // Test the exact query from products.php
        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT 5
        ");
        $test_products = $stmt->fetchAll();
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3 style='color: #155724;'>✅ Products Query Test: SUCCESS</h3>";
        echo "<p style='color: #155724;'>Found " . count($test_products) . " products with the exact query used by products.php</p>";
        echo "</div>";
        
        if (!empty($test_products)) {
            echo "<h3>📦 Sample Products:</h3>";
            echo "<ul>";
            foreach ($test_products as $product) {
                echo "<li><strong>" . htmlspecialchars($product['name']) . "</strong> - " . formatPrice($product['price']) . " (Category: " . htmlspecialchars($product['category_name'] ?? 'None') . ")</li>";
            }
            echo "</ul>";
        }
    }
    
    echo "<h2>🚀 Quick Actions</h2>";
    echo "<div style='display: flex; gap: 10px; flex-wrap: wrap;'>";
    echo "<a href='products.php' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🛍️ Test Products Page</a>";
    echo "<a href='fix_column_error.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>📊 Import Products</a>";
    echo "<a href='index.php' style='background: #6c757d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🏠 Home</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3 style='color: #721c24;'>❌ Database Error</h3>";
    echo "<p style='color: #721c24;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    
    echo "<h3>🔧 Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Make sure XAMPP is running</li>";
    echo "<li>Start Apache and MySQL services</li>";
    echo "<li>Check if MySQL is running on port 3306</li>";
    echo "<li>Try accessing phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
    echo "</ol>";
}

echo "</div>";
?>

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin: 0;
    padding: 20px;
    min-height: 100vh;
}

h1, h2, h3 {
    color: #333;
}
</style>
