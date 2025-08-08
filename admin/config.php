<?php
// Admin Configuration
require_once '../config/config.php';

// Admin session management
function checkAdminLogin() {
    if (!isset($_SESSION['admin_id'])) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function getAdminUser() {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }

    try {
        $stmt = getDBConnection()->prepare("SELECT * FROM admin_users WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// Admin permissions
function hasPermission($permission) {
    $admin = getAdminUser();
    if (!$admin) return false;

    // Super admin has all permissions
    if ($admin['role'] === 'super_admin') return true;

    // Define role permissions
    $permissions = [
        'admin' => ['view_dashboard', 'manage_products', 'manage_categories', 'manage_orders', 'view_users', 'manage_reviews'],
        'manager' => ['view_dashboard', 'manage_products', 'manage_orders', 'view_users', 'manage_users']
    ];

    return in_array($permission, $permissions[$admin['role']] ?? []);
}

// Get website setting
function getSetting($key, $default = '') {
    try {
        $stmt = getDBConnection()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Update website setting
function updateSetting($key, $value) {
    try {
        $stmt = getDBConnection()->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

// Admin constants
define('ADMIN_URL', SITE_URL . '/admin');
define('ADMIN_TITLE', 'TeWuNeed Admin');
?>
