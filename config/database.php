<?php
// Database Configuration - FORCED TO db_tewuneed2
define("DB_HOST", "localhost");
define("DB_NAME", "db_tewuneed2");
define("DB_USER", "root");
define("DB_PASS", "");

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to get database connection
function getDBConnection() {
    global $pdo;
    return $pdo;
}
?>