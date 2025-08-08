<?php
// Admin Setup Script - Run this once to create admin user
require_once '../config/config.php';

echo "<h2>Admin User Setup</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px;'>";

try {
    $pdo = getDBConnection();
    
    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if (!$stmt->fetch()) {
        echo "<p style='color: red;'>❌ admin_users table does not exist. Please run the database setup first.</p>";
        echo "<p><a href='../setup_database.php'>Run Database Setup</a></p>";
        exit;
    }
    
    echo "<p>✅ admin_users table exists</p>";
    
    // Check existing admin users
    $stmt = $pdo->query("SELECT * FROM admin_users");
    $existing_admins = $stmt->fetchAll();
    
    echo "<h3>Existing Admin Users:</h3>";
    if (empty($existing_admins)) {
        echo "<p>No admin users found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        foreach ($existing_admins as $admin) {
            echo "<tr>";
            echo "<td>" . $admin['id'] . "</td>";
            echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Create/Update admin user
    $username = 'admin';
    $email = 'admin@tewuneed.com';
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<h3>Creating/Updating Admin User:</h3>";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $existing_admin = $stmt->fetch();
    
    if ($existing_admin) {
        // Update existing admin
        $stmt = $pdo->prepare("
            UPDATE admin_users 
            SET password = ?, first_name = 'Admin', last_name = 'User', role = 'super_admin', status = 'active'
            WHERE id = ?
        ");
        $result = $stmt->execute([$hashed_password, $existing_admin['id']]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Admin user updated successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to update admin user.</p>";
        }
    } else {
        // Create new admin
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, email, password, first_name, last_name, role, status) 
            VALUES (?, ?, ?, 'Admin', 'User', 'super_admin', 'active')
        ");
        $result = $stmt->execute([$username, $email, $hashed_password]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create admin user.</p>";
        }
    }
    
    // Verify the admin user
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<h3>Admin User Details:</h3>";
        echo "<ul>";
        echo "<li><strong>Username:</strong> " . htmlspecialchars($admin['username']) . "</li>";
        echo "<li><strong>Email:</strong> " . htmlspecialchars($admin['email']) . "</li>";
        echo "<li><strong>Role:</strong> " . htmlspecialchars($admin['role']) . "</li>";
        echo "<li><strong>Status:</strong> " . htmlspecialchars($admin['status']) . "</li>";
        echo "</ul>";
        
        // Test password verification
        if (password_verify($password, $admin['password'])) {
            echo "<p style='color: green;'>✅ Password verification successful!</p>";
        } else {
            echo "<p style='color: red;'>❌ Password verification failed!</p>";
        }
        
        echo "<h3>Login Credentials:</h3>";
        echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
        echo "<strong>Username:</strong> admin<br>";
        echo "<strong>Password:</strong> admin123<br>";
        echo "<strong>Login URL:</strong> <a href='login.php'>Admin Login</a>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>❌ Admin user not found after creation!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Database connection settings in config/database.php</li>";
    echo "<li>MySQL service is running</li>";
    echo "<li>Database 'db_tewuneed2' exists</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}

table {
    background: white;
    margin: 10px 0;
}

th, td {
    padding: 8px 12px;
    text-align: left;
}

th {
    background-color: #0d6efd;
    color: white;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

a {
    color: #0d6efd;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>
